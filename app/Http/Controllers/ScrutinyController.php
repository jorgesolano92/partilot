<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lottery;
use App\Models\LotteryResult;
use App\Models\LotteryType;
use App\Services\Scrutiny\LotteryPrizeCalculator;

class ScrutinyController extends Controller
{
    /**
     * Mostrar la vista principal de escrutinio
     */
    public function index()
    {
        $lotteries = Lottery::with(['lotteryType', 'result'])
            ->whereHas('result')
            ->orderBy('draw_date', 'desc')
            ->get();

        $lotteryTypes = LotteryType::orderBy('name')->get();

        return view('scrutiny.index', compact('lotteries', 'lotteryTypes'));
    }

    /**
     * Generar escrutinio completo para un sorteo específico
     */
    public function generateScrutiny(Request $request)
    {
        // Aumentar límite de tiempo de ejecución
        set_time_limit(300); // 5 minutos
        
        $request->validate([
            'lottery_id' => 'required|integer|exists:lotteries,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:1000',
            'start_range' => 'nullable|integer|min:0|max:99999',
            'end_range' => 'nullable|integer|min:0|max:99999',
            'sort_order' => 'nullable|in:asc,desc',
            'premio_al_decimo' => 'nullable|boolean'
        ]);

        $lottery = Lottery::with(['lotteryType', 'result'])->findOrFail($request->lottery_id);
        
        if (!$lottery->result) {
            return response()->json([
                'success' => false,
                'message' => 'No hay resultados disponibles para este sorteo'
            ], 404);
        }

        // Parámetros
        $startRange = $request->get('start_range', 0);
        $endRange = $request->get('end_range', 99999);
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Calcular resultados (sin caché)
        if ($startRange > 0 || $endRange < 99999) {
            $scrutinyResults = $this->calculateNumbersInRange($lottery, $startRange, $endRange);
        } else {
            $scrutinyResults = $this->calculateAllNumbers($lottery);
        }
        
        // Calcular total de premios
        $totalPrizes = 0;
        foreach ($scrutinyResults as $result) {
            $totalPrizes += count($result['prizes']);
        }
        
        // Ordenamiento
        if ($sortOrder === 'asc') {
            usort($scrutinyResults, function($a, $b) {
                return $a['total_prize'] <=> $b['total_prize'];
            });
        } else {
            usort($scrutinyResults, function($a, $b) {
                return $b['total_prize'] <=> $a['total_prize'];
            });
        }
        
        // NO guardar en caché temporalmente
        
        // Paginación
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 100);
        $total = count($scrutinyResults);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = array_slice($scrutinyResults, $offset, $perPage);

        // Si se pide premio al décimo (1/10 del premio a la serie), convertir importes
        if ($request->boolean('premio_al_decimo')) {
            foreach ($paginatedResults as &$result) {
                $result['total_prize'] = round($result['total_prize'] / 10, 2);
                foreach ($result['prizes'] as &$prize) {
                    $prize['amount'] = round($prize['amount'] / 10, 2);
                }
            }
            unset($result, $prize);
        }
        
        \Log::info("=== TOTAL PREMIOS CALCULADO: {$totalPrizes} ===");
        
        return response()->json([
            'success' => true,
            'lottery' => $lottery,
            'total_numbers_with_prizes' => $total,
            'total_prizes' => $totalPrizes,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'search_range' => [
                'start' => $startRange,
                'end' => $endRange,
                'is_full_range' => $startRange == 0 && $endRange == 99999
            ],
            'results' => $paginatedResults
        ]);
    }


    /**
     * Exportar resultados del escrutinio a CSV
     */
    public function exportScrutiny(Request $request)
    {
        $request->validate([
            'lottery_id' => 'required|integer|exists:lotteries,id'
        ]);

        $lottery = Lottery::with(['lotteryType', 'result'])->findOrFail($request->lottery_id);
        
        if (!$lottery->result) {
            return response()->json([
                'success' => false,
                'message' => 'No hay resultados disponibles para este sorteo'
            ], 404);
        }

        $scrutinyResults = $this->calculateAllNumbers($lottery);
        
        // Generar CSV
        $filename = 'escrutinio_' . $lottery->name . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($scrutinyResults) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fwrite($file, "\xEF\xBB\xBF");
            
            // Encabezados
            fputcsv($file, ['Número', 'Premio Total (€)', 'Categorías', 'Detalle Premios']);
            
            // Datos
            foreach ($scrutinyResults as $result) {
                $categories = implode(', ', array_column($result['prizes'], 'category'));
                $prizeDetails = '';
                foreach ($result['prizes'] as $prize) {
                    $prizeDetails .= $prize['category'] . ': ' . number_format($prize['amount'], 2) . '€; ';
                }
                $prizeDetails = rtrim($prizeDetails, '; ');
                
                fputcsv($file, [
                    $result['number'],
                    number_format($result['total_prize'], 2),
                    $categories,
                    $prizeDetails
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }


    /**
     * Calcular premios para un rango específico de números
     */
    private function calculateNumbersInRange(Lottery $lottery, $startRange, $endRange)
    {
        $calculator = app(LotteryPrizeCalculator::class);
        $lotteryResult = $lottery->result;
        $typeIdentifier = $lottery->getLotteryTypeIdentifier();

        $winningNumbers = [];
        for ($i = $startRange; $i <= $endRange; $i++) {
            $prizeInfo = $this->calculateNumberPrizes($calculator, $i, $lotteryResult, $typeIdentifier);
            if ($prizeInfo['total_prize'] > 0) {
                $winningNumbers[] = $prizeInfo;
            }
        }

        usort($winningNumbers, function ($a, $b) {
            return $b['total_prize'] <=> $a['total_prize'];
        });

        return $winningNumbers;
    }

    /**
     * Calcular premios para todos los números del 00000 al 99999 (solo los que pueden tener premio)
     */
    private function calculateAllNumbers(Lottery $lottery)
    {
        $calculator = app(LotteryPrizeCalculator::class);
        $lotteryResult = $lottery->result;
        $typeIdentifier = $lottery->getLotteryTypeIdentifier();

        $candidates = $calculator->candidateNumbers($lotteryResult);
        \Log::info("=== CALCULANDO PREMIOS PARA " . count($candidates) . " NÚMEROS POTENCIALES ===");

        $results = [];
        foreach ($candidates as $numberStr) {
            $prizeInfo = $this->calculateNumberPrizes($calculator, $numberStr, $lotteryResult, $typeIdentifier);
            if ($prizeInfo['total_prize'] > 0) {
                $results[] = $prizeInfo;
            }
        }

        \Log::info("=== TOTAL NÚMEROS CON PREMIOS: " . count($results) . " ===");

        usort($results, function ($a, $b) {
            return $b['total_prize'] <=> $a['total_prize'];
        });

        return $results;
    }

    /**
     * Premios de un número en € al billete, en el formato que consumen la vista y el CSV
     */
    private function calculateNumberPrizes(LotteryPrizeCalculator $calculator, $number, $lotteryResult, $typeIdentifier)
    {
        $calculation = $calculator->calculate($number, $lotteryResult, $typeIdentifier);

        return [
            'number' => $calculation['number'],
            'total_prize' => $calculation['total_prize'],
            'prizes' => array_map(fn ($prize) => [
                'category' => $prize['name'],
                'amount' => $prize['amount'],
                'type' => $prize['type'],
            ], $calculation['prizes']),
        ];
    }
}
