<?php

namespace App\Http\Controllers;

use App\Models\Lottery;
use App\Models\LotteryResult;
use App\Models\Administration;
use App\Models\Entity;
use App\Models\Reserve;
use App\Models\AdministrationLotteryScrutiny;
use App\Services\EntityLotteryPrizePaymentService;
use App\Services\Scrutiny\LotteryPrizeCalculator;
use App\Models\ScrutinyEntityResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Participation;
use App\Models\Set;

class LotteryScrutinyController extends Controller
{
    /**
     * Mostrar el formulario de escrutinio para una administración específica
     * Incluye tanto el escrutinio normal como el escrutinio por categoría
     */
    public function show($lotteryId)
    {
        $lottery = Lottery::with(['lotteryType', 'result'])->findOrFail($lotteryId);
        
        // Verificar que hay una administración seleccionada
        $administrationId = session('selected_administration.id');
        if (!$administrationId) {
            return redirect()->route('lottery.administrations')
                ->with('error', 'Debe seleccionar una administración primero');
        }

        $administration = Administration::forUser(auth()->user())->findOrFail($administrationId);

        // Verificar que el sorteo tiene resultados
        if (!$lottery->result) {
            return redirect()->route('lottery.results')
                ->with('error', 'Este sorteo aún no tiene resultados publicados');
        }

        // Verificar si ya existe un escrutinio para esta administración
        $existingScrutiny = AdministrationLotteryScrutiny::where('administration_id', $administrationId)
            ->where('lottery_id', $lotteryId)
            ->first();

        if ($existingScrutiny && $existingScrutiny->is_scrutinized) {
            return redirect()->route('lottery.show-administration-scrutiny', [$lotteryId, $administrationId])
                ->with('info', 'Este sorteo ya ha sido escrutado para esta administración');
        }

        // Obtener entidades de la administración que tienen reservas para este sorteo
        $entitiesWithReserves = Entity::forUser(auth()->user())
            ->where('administration_id', $administrationId)
            ->whereHas('reserves', function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId)
                      ->where('status', 1); // Solo reservas confirmadas
            })
            ->with(['reserves' => function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId)
                      ->where('status', 1);
            }])
            ->get();

        if ($entitiesWithReserves->isEmpty()) {
            return redirect()->route('lottery.results')
                ->with('warning', 'No hay entidades con reservas confirmadas para este sorteo en la administración seleccionada');
        }

        // Escrutinio por categoría primero (centenas, anterior, etc.) — fuente de verdad de premios
        $reservedNumbers = $this->getReservedNumbersForAdministration($administrationId, $lotteryId);
        $scrutinyResults = $this->calculateCategoryScrutiny($lottery, $reservedNumbers);
        $scrutinyResultsByEntity = $this->organizeResultsByEntity($scrutinyResults, $entitiesWithReserves, $lotteryId);

        // Datos por entidad alineados con el escrutinio por categoría (incluye digitales vendidas)
        $scrutinyData = $this->prepareScrutinyData($lottery, $entitiesWithReserves, $scrutinyResults);
        $scrutinyData = $this->enrichScrutinyDataWithCategoryResults($scrutinyData, $scrutinyResultsByEntity);

        $prizePaymentService = app(EntityLotteryPrizePaymentService::class);
        $entitiesPendingDevolution = $prizePaymentService->entitiesPendingAdminDevolutionClosure($administrationId, $lotteryId);
        $scrutinyBlocked = $entitiesPendingDevolution->isNotEmpty();
        $scrutinyBlockedMessage = $prizePaymentService->administrationScrutinyBlockedMessage($administrationId, $lotteryId);

        return view('lottery.scrutiny', compact(
            'lottery',
            'administration',
            'entitiesWithReserves',
            'scrutinyData',
            'scrutinyResults',
            'scrutinyResultsByEntity',
            'entitiesPendingDevolution',
            'scrutinyBlocked',
            'scrutinyBlockedMessage'
        ));
    }

    /**
     * Procesar y guardar el escrutinio
     */
    public function process(Request $request, $lotteryId)
    {
        $lottery = Lottery::with('result')->findOrFail($lotteryId);
        $administrationId = session('selected_administration.id');

        if (!$administrationId) {
            return redirect()->route('lottery.administrations')
                ->with('error', 'Debe seleccionar una administración primero');
        }

        if (!$lottery->result) {
            return redirect()->route('lottery.results')
                ->with('error', 'Este sorteo aún no tiene resultados');
        }

        $prizePaymentService = app(EntityLotteryPrizePaymentService::class);
        $blockedMessage = $prizePaymentService->administrationScrutinyBlockedMessage($administrationId, $lotteryId);
        if ($blockedMessage !== null) {
            return redirect()->route('lottery.scrutiny', $lotteryId)
                ->with('error', $blockedMessage);
        }

        try {
            DB::beginTransaction();

            $this->runScrutiny($lottery, (int) $administrationId, [
                'scrutiny_date' => now(),
                'is_scrutinized' => true,
                'scrutinized_by' => Auth::id(),
                'comments' => $request->input('comments'),
            ]);

            DB::commit();

            return redirect()->route('lottery.show-administration-scrutiny', [$lotteryId, $administrationId])
                ->with('success', 'Escrutinio procesado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al procesar escrutinio: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()
                ->with('error', 'Error al procesar el escrutinio: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Calcula el escrutinio de una administración y regenera sus resultados detallados.
     * Debe llamarse dentro de una transacción.
     */
    public function runScrutiny(Lottery $lottery, int $administrationId, array $attributes = []): AdministrationLotteryScrutiny
    {
        $lotteryId = $lottery->id;

        $entitiesWithReserves = Entity::forUser(auth()->user())
            ->where('administration_id', $administrationId)
            ->whereHas('reserves', function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id)
                      ->where('status', 1);
            })
            ->with(['reserves' => function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id)
                      ->where('status', 1);
            }])
            ->get();

        $reservedNumbers = $this->getReservedNumbersForAdministration($administrationId, $lotteryId);
        $scrutinyResults = $this->calculateCategoryScrutiny($lottery, $reservedNumbers);
        $scrutinyResultsByEntity = $this->organizeResultsByEntity($scrutinyResults, $entitiesWithReserves, $lotteryId);

        // Calcular totales correctos desde los resultados por categoría
        $totalWinning = 0;
        $totalPrizeAmount = 0;

        foreach ($scrutinyResultsByEntity as $entityId => $entityResults) {
            foreach ($entityResults as $categoryResult) {
                $decimosInfo = $categoryResult['decimos_info'] ?? [];
                $totalWinning += $decimosInfo['total_participations'] ?? 0;

                $premioPorDecimo = $categoryResult['total_prize'];
                $ticketPrice = $decimosInfo['ticket_price'] ?? 0;
                foreach ($decimosInfo['sets_info'] ?? [] as $setInfo) {
                    $importeJugado = $setInfo['importe_jugado'] ?? 0;
                    $participacionesVendidas = (int) ($setInfo['participations_vendidas'] ?? 0);
                    if ($ticketPrice > 0 && $importeJugado > 0 && $participacionesVendidas > 0) {
                        $premioPorParticipacion = $premioPorDecimo * ($importeJugado / $ticketPrice);
                        $totalPrizeAmount += $premioPorParticipacion * $participacionesVendidas;
                    }
                }
            }
        }

        // Obtener total de participaciones asignadas de TODAS las entidades
        $totalAsignadas = \App\Models\Participation::whereHas('set.reserve', function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId);
            })
            ->whereHas('entity', function ($query) use ($administrationId) {
                $query->where('administration_id', $administrationId);
            })
            ->soldForScrutiny()
            ->count();

        // Total de premios de los escrutinios guardados del resto de administraciones para este sorteo
        $totalOtherScrutinies = DB::table('scrutiny_detailed_results')
            ->join('administration_lottery_scrutinies', 'scrutiny_detailed_results.scrutiny_id', '=', 'administration_lottery_scrutinies.id')
            ->where('administration_lottery_scrutinies.lottery_id', $lotteryId)
            ->where('administration_lottery_scrutinies.administration_id', '!=', $administrationId)
            ->where('administration_lottery_scrutinies.is_saved', true)
            ->sum('scrutiny_detailed_results.premio_total');

        $scrutiny = AdministrationLotteryScrutiny::updateOrCreate([
            'administration_id' => $administrationId,
            'lottery_id' => $lotteryId
        ], array_merge([
            'lottery_result_id' => $lottery->result->id,
            'scrutiny_summary' => [
                'total_entities' => count($entitiesWithReserves),
                'total_winning_participations' => $totalWinning,
                'total_non_winning_participations' => $totalAsignadas - $totalWinning,
                'total_prize_amount' => $totalOtherScrutinies + $totalPrizeAmount
            ]
        ], $attributes));

        DB::table('scrutiny_detailed_results')->where('scrutiny_id', $scrutiny->id)->delete();
        $this->saveDetailedScrutinyResults($scrutiny, $scrutinyResultsByEntity, $lottery);

        return $scrutiny;
    }

    /**
     * Mostrar los resultados del escrutinio de una administración
     */
    public function showResults($lotteryId, $administrationId)
    {
        $lottery = Lottery::with(['lotteryType', 'result'])->findOrFail($lotteryId);
        $administration = Administration::forUser(auth()->user())->findOrFail($administrationId);

        $scrutiny = AdministrationLotteryScrutiny::where('administration_id', $administrationId)
            ->where('lottery_id', $lotteryId)
            ->with(['detailedResults.entity', 'detailedResults.set', 'scrutinizedBy', 'savedBy'])
            ->firstOrFail();

        if (!$scrutiny->is_scrutinized) {
            return redirect()->route('lottery.scrutiny', $lotteryId)
                ->with('info', 'El escrutinio aún no ha sido completado');
        }

        // Obtener el total de premios de TODOS los escrutinios guardados para este sorteo
        $totalAllScrutinies = DB::table('scrutiny_detailed_results')
            ->join('administration_lottery_scrutinies', 'scrutiny_detailed_results.scrutiny_id', '=', 'administration_lottery_scrutinies.id')
            ->where('administration_lottery_scrutinies.lottery_id', $lotteryId)
            ->where('administration_lottery_scrutinies.is_saved', true)
            ->sum('scrutiny_detailed_results.premio_total');

        // Actualizar el resumen con el total de todos los escrutinios
        $currentSummary = $scrutiny->scrutiny_summary;
        $currentSummary['total_prize_amount'] = $totalAllScrutinies;
        $scrutiny->scrutiny_summary = $currentSummary;
        $scrutiny->save();

        return view('lottery.scrutiny_results', compact('lottery', 'administration', 'scrutiny'));
    }

    /**
     * Participaciones vendidas de una entidad (físicas y digitales).
     * Incluye vendidas definitivas y digitales pendientes de vinculación (reserva_venta_digital).
     */
    private function getSoldParticipationsForEntity(Entity $entity, Lottery $lottery)
    {
        return Participation::query()
            ->with(['set.reserve'])
            ->soldForScrutiny()
            ->whereHas('set.reserve', function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id);
            })
            ->where(function ($query) use ($entity) {
                $query->where('entity_id', $entity->id)
                    ->orWhereHas('set', function ($setQuery) use ($entity) {
                        $setQuery->where('entity_id', $entity->id);
                    });
            })
            ->get();
    }

    /**
     * Número de lotería reservado al que pertenece una participación.
     */
    private function getParticipationReservedNumber(Participation $participation): ?string
    {
        $reservedNumbers = $participation->set?->reserve?->reservation_numbers ?? [];
        if (empty($reservedNumbers)) {
            return null;
        }
        if (count($reservedNumbers) === 1) {
            return (string) $reservedNumbers[0];
        }
        $index = (int) $participation->participation_number - 1;

        return isset($reservedNumbers[$index]) ? (string) $reservedNumbers[$index] : null;
    }

    /**
     * Comprueba si la participación pertenece a un número premiado (escrutinio por categoría).
     */
    private function participationMatchesWinningNumbers(Participation $participation, array $categoryWinningNumbers): bool
    {
        $number = $this->getParticipationReservedNumber($participation);
        if ($number === null) {
            return false;
        }
        foreach ($categoryWinningNumbers as $winningNumber) {
            if ($this->compareNumbers($number, $winningNumber)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sincroniza totales de entidad con el escrutinio por categoría (premio real por set vendido).
     */
    private function enrichScrutinyDataWithCategoryResults(array $scrutinyData, array $scrutinyResultsByEntity): array
    {
        foreach ($scrutinyData['entities'] as &$data) {
            $entityId = $data['entity']->id;
            if (! isset($scrutinyResultsByEntity[$entityId])) {
                continue;
            }

            $result = $data['result'];
            $winningParticipations = 0;
            $winningNumbers = [];

            foreach ($scrutinyResultsByEntity[$entityId] as $categoryResult) {
                $decimosInfo = $categoryResult['decimos_info'] ?? [];
                $winningParticipations += (int) ($decimosInfo['total_participations'] ?? 0);
                if (! empty($categoryResult['number'])) {
                    $winningNumbers[] = (string) $categoryResult['number'];
                }
            }

            if ($winningParticipations > 0) {
                $result->winning_participations = $winningParticipations;
                $result->winning_numbers = array_values(array_unique($winningNumbers));
                $result->total_winning = count($result->winning_numbers);
            }
        }
        unset($data);

        return $scrutinyData;
    }

    /**
     * Preparar datos para el escrutinio
     */
    private function prepareScrutinyData($lottery, $entitiesWithReserves, array $scrutinyResults = [])
    {
        $scrutinyData = [];
        $lotteryResult = $lottery->result;
        $categoryWinningNumbers = collect($scrutinyResults)
            ->pluck('number')
            ->filter(fn ($n) => $n !== null && $n !== '')
            ->values()
            ->all();
        
        // Variables para calcular totales globales
        $allWinningNumbers = [];
        $allNonWinningNumbers = [];
        $totalPrizeAmount = 0;
        
        // Obtener TODOS los números únicos de TODAS las reservas de la administración
        $allReservedNumbers = [];
        foreach ($entitiesWithReserves as $entity) {
            $reserves = Reserve::where('entity_id', $entity->id)
                ->where('lottery_id', $lottery->id)
                ->where('status', 1)
                ->get();
                
            foreach ($reserves as $reserve) {
                if ($reserve->reservation_numbers) {
                    $allReservedNumbers = array_merge($allReservedNumbers, $reserve->reservation_numbers);
                }
            }
        }
        $allReservedNumbers = array_unique($allReservedNumbers);

        foreach ($entitiesWithReserves as $entity) {
            $allAssignedParticipations = $this->getSoldParticipationsForEntity($entity, $lottery);

            // Obtener todas las participaciones disponibles de esta entidad para este sorteo
            $allParticipations = Participation::where(function ($query) use ($entity) {
                    $query->where('entity_id', $entity->id)
                        ->orWhereHas('set', function ($setQuery) use ($entity) {
                            $setQuery->where('entity_id', $entity->id);
                        });
                })
                ->whereHas('set.reserve', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id);
                })
                ->whereIn('status', ['vendida', 'disponible', 'asignada', 'devuelta'])
                ->get();

            // Obtener participaciones devueltas de esta entidad para este sorteo
            $returnedParticipations = Participation::where(function ($query) use ($entity) {
                    $query->where('entity_id', $entity->id)
                        ->orWhereHas('set', function ($setQuery) use ($entity) {
                            $setQuery->where('entity_id', $entity->id);
                        });
                })
                ->whereHas('set.reserve', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id);
                })
                ->where('status', 'devuelta')
                ->get();

            // Números reservados con participaciones vendidas
            $assignedNumbers = [];
            foreach ($allAssignedParticipations as $participation) {
                $number = $this->getParticipationReservedNumber($participation);
                if ($number !== null) {
                    $assignedNumbers[] = $number;
                }
            }
            $assignedNumbers = array_values(array_unique($assignedNumbers));

            // Fallback legacy: solo si no hay escrutinio por categoría
            $legacyWinningNumbers = [];
            if (empty($categoryWinningNumbers)) {
                $tempEntityResult = new ScrutinyEntityResult([
                    'entity_id' => $entity->id,
                    'reserved_numbers' => $assignedNumbers,
                    'total_reserved' => $allAssignedParticipations->count(),
                    'total_issued' => $allParticipations->count(),
                    'total_sold' => $allAssignedParticipations->count(),
                    'total_returned' => $returnedParticipations->count(),
                ]);
                $tempEntityResult->calculatePrizes($lotteryResult, $lottery->lotteryType);
                $legacyWinningNumbers = $tempEntityResult->winning_numbers ?? [];
            }

            $winningParticipations = [];
            $winningParticipationsByNumber = [];
            $totalWinningParticipations = 0;

            foreach ($allAssignedParticipations as $participation) {
                $hasWinningNumber = ! empty($categoryWinningNumbers)
                    ? $this->participationMatchesWinningNumbers($participation, $categoryWinningNumbers)
                    : $this->participationMatchesWinningNumbers($participation, $legacyWinningNumbers);

                if (! $hasWinningNumber) {
                    continue;
                }

                $winningParticipations[] = $participation;
                $number = $this->getParticipationReservedNumber($participation);
                if ($number !== null) {
                    $matchesCategory = ! empty($categoryWinningNumbers)
                        ? $this->participationMatchesWinningNumbers($participation, $categoryWinningNumbers)
                        : in_array($number, $legacyWinningNumbers, true);
                    if ($matchesCategory) {
                        $totalWinningParticipations++;
                        $winningParticipationsByNumber[$number] = ($winningParticipationsByNumber[$number] ?? 0) + 1;
                    }
                }
            }

            $winningNumbers = [];
            foreach ($winningParticipations as $participation) {
                $number = $this->getParticipationReservedNumber($participation);
                if ($number !== null) {
                    $winningNumbers[] = $number;
                }
            }
            $winningNumbers = array_values(array_unique($winningNumbers));

            // Calcular participaciones sin premio (sets sin números ganadores)
            $nonWinningParticipations = $allAssignedParticipations->count() - count($winningParticipations);

            // Crear el resultado final con solo las participaciones de sets ganadores
            $entityResult = new ScrutinyEntityResult([
                'entity_id' => $entity->id,
                'reserved_numbers' => $assignedNumbers, // Usar todos los números de participaciones vendidas
                'total_reserved' => count($winningParticipations), // Solo participaciones de sets ganadores
                'total_issued' => $allParticipations->count(), // Total de participaciones emitidas
                'total_sold' => count($winningParticipations), // Solo participaciones de sets ganadores
                //'total_sold' => $allAssignedParticipations->count(), // Solo participaciones de sets ganadores
                'total_returned' => $returnedParticipations->count(),
                'total_non_winning' => $nonWinningParticipations // Participaciones sin premio
            ]);

            $entityResult->winning_participations = $totalWinningParticipations;
            
            // Calcular los premios con las participaciones ganadoras por número
            $entityResult->calculatePrizes($lotteryResult, $lottery->lotteryType, $winningParticipationsByNumber);

            if ($totalWinningParticipations > 0) {
                $entityResult->winning_numbers = $winningNumbers;
                $entityResult->total_winning = count($winningNumbers);
                $entityResult->winning_participations = $totalWinningParticipations;
            }

            // Acumular números ganadores para el total global
            $allWinningNumbers = array_merge($allWinningNumbers, $entityResult->winning_numbers);
            
            // Solo acumular el premio si la entidad tiene números ganadores
            if ($entityResult->total_winning > 0) {
                $totalPrizeAmount += $entityResult->total_prize_amount;
            }

            $scrutinyData[] = [
                'entity' => $entity,
                'result' => $entityResult
            ];
        }

        // Calcular totales de participaciones (no números únicos)
        $totalWinningParticipations = 0;
        $totalNonWinningParticipations = 0;
        
        foreach ($scrutinyData as $data) {
            $result = $data['result'];
            $totalWinningParticipations += $result->total_winning;
            $totalNonWinningParticipations += ($result->total_issued ?? 0) - ($result->total_winning ?? 0);
        }

        return [
            'entities' => $scrutinyData,
            'summary' => [
                'unique_winning_numbers' => $totalWinningParticipations,
                'unique_non_winning_numbers' => $totalNonWinningParticipations,
                'total_prize_amount' => $totalPrizeAmount
            ]
        ];
    }

    /**
     * Procesar resultados de todas las entidades
     */
    private function processEntityResults($scrutiny, $lottery)
    {
        $lotteryResult = $lottery->result;
        
        // Obtener entidades con reservas para este sorteo
        $entitiesWithReserves = Entity::forUser(auth()->user())
            ->where('administration_id', $scrutiny->administration_id)
            ->whereHas('reserves', function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id)
                      ->where('status', 1);
            })
            ->with(['reserves' => function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id)
                      ->where('status', 1);
            }])
            ->get();

        foreach ($entitiesWithReserves as $entity) {
            // Obtener participaciones asignadas de esta entidad para este sorteo
            $allAssignedParticipations = Participation::where('entity_id', $entity->id)
                ->whereHas('set.reserve', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id);
                })
                ->soldForScrutiny()
                ->get();

            // Obtener todas las participaciones disponibles de esta entidad para este sorteo
            $allParticipations = Participation::where('entity_id', $entity->id)
                ->whereHas('set.reserve', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id);
                })
                ->whereIn('status', ['disponible', 'asignada'])
                ->get();

            // Obtener los números de las participaciones asignadas
            $assignedNumbers = [];
            foreach ($allAssignedParticipations as $participation) {
                if ($participation->set && $participation->set->reserve) {
                    $reservedNumbers = $participation->set->reserve->reservation_numbers ?? [];
                    // Si solo hay un número reservado, todas las participaciones del set tienen ese número
                    if (count($reservedNumbers) === 1) {
                        $assignedNumbers[] = $reservedNumbers[0];
                    } else {
                        // Si hay múltiples números, usar el índice correspondiente
                        if (isset($reservedNumbers[$participation->participation_number - 1])) {
                            $assignedNumbers[] = $reservedNumbers[$participation->participation_number - 1];
                        }
                    }
                }
            }

            // Eliminar duplicados
            $assignedNumbers = array_unique($assignedNumbers);

            // Calcular premios para obtener los números ganadores
            $tempEntityResult = new ScrutinyEntityResult([
                'entity_id' => $entity->id,
                'reserved_numbers' => $assignedNumbers,
                'total_reserved' => $allAssignedParticipations->count(),
                'total_issued' => $allParticipations->count(),
                'total_sold' => $allAssignedParticipations->count(),
                'total_returned' => 0
            ]);

            $tempEntityResult->calculatePrizes($lotteryResult, $lottery->lotteryType);
            
            // Filtrar participaciones asignadas: solo aquellas que están en sets con reservas que tienen números ganadores
            $winningParticipations = [];
            $winningParticipationsByNumber = [];
            $totalWinningParticipations = 0;
            
            foreach ($allAssignedParticipations as $participation) {
                if ($participation->set && $participation->set->reserve) {
                    $reservedNumbers = $participation->set->reserve->reservation_numbers ?? [];
                    $hasWinningNumber = false;
                    
                    // Verificar si esta reserva tiene algún número ganador
                    foreach ($reservedNumbers as $number) {
                        if (in_array($number, $tempEntityResult->winning_numbers)) {
                            $hasWinningNumber = true;
                            break;
                        }
                    }
                    
                    // Solo incluir participaciones de sets con reservas que tienen números ganadores
                    if ($hasWinningNumber) {
                        $winningParticipations[] = $participation;
                        
                        // Calcular participaciones ganadoras por número específico
                        $number = null;
                        if (count($reservedNumbers) === 1) {
                            $number = $reservedNumbers[0];
                        } else {
                            if (isset($reservedNumbers[$participation->participation_number - 1])) {
                                $number = $reservedNumbers[$participation->participation_number - 1];
                            }
                        }
                        
                        if ($number && in_array($number, $tempEntityResult->winning_numbers)) {
                            $totalWinningParticipations++;
                            $winningParticipationsByNumber[$number] = ($winningParticipationsByNumber[$number] ?? 0) + 1;
                        }
                    }
                }
            }

            // Obtener los números de las participaciones ganadoras (solo de sets con reservas ganadoras)
            $winningNumbers = [];
            foreach ($winningParticipations as $participation) {
                if ($participation->set && $participation->set->reserve) {
                    $reservedNumbers = $participation->set->reserve->reservation_numbers ?? [];
                    // Si solo hay un número reservado, todas las participaciones del set tienen ese número
                    if (count($reservedNumbers) === 1) {
                        $winningNumbers[] = $reservedNumbers[0];
                    } else {
                        // Si hay múltiples números, usar el índice correspondiente
                        if (isset($reservedNumbers[$participation->participation_number - 1])) {
                            $winningNumbers[] = $reservedNumbers[$participation->participation_number - 1];
                        }
                    }
                }
            }
            $winningNumbers = array_unique($winningNumbers);

            // Calcular participaciones sin premio (sets sin números ganadores)
            $nonWinningParticipations = $allAssignedParticipations->count() - count($winningParticipations);

            // Crear o actualizar resultado de la entidad con solo las participaciones de sets ganadores
            $entityResult = ScrutinyEntityResult::updateOrCreate([
                'administration_lottery_scrutiny_id' => $scrutiny->id,
                'entity_id' => $entity->id
            ], [
                'reserved_numbers' => $winningNumbers,
                'total_reserved' => count($winningParticipations), // Solo participaciones de sets ganadores
                'total_issued' => $allParticipations->count(), // Total de participaciones emitidas
                'total_sold' => count($winningParticipations), // Solo participaciones de sets ganadores
                'total_returned' => 0,
                'total_non_winning' => $nonWinningParticipations // Participaciones sin premio
            ]);

            $entityResult->winning_participations = $totalWinningParticipations;
            
            // Calcular los premios con las participaciones ganadoras por número
            $entityResult->calculatePrizes($lotteryResult, $lottery->lotteryType, $winningParticipationsByNumber);
            $entityResult->save();
        }
    }


    /**
     * Obtener números reservados para una administración y sorteo
     */
    private function getReservedNumbersForAdministration($administrationId, $lotteryId)
    {
        $reservedNumbers = [];
        
        $entities = Entity::forUser(auth()->user())
            ->where('administration_id', $administrationId)
            ->whereHas('reserves', function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId)
                      ->where('status', 1);
            })
            ->with(['reserves' => function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId)
                      ->where('status', 1);
            }])
            ->get();

        foreach ($entities as $entity) {
            foreach ($entity->reserves as $reserve) {
                if ($reserve->reservation_numbers) {
                    $reservedNumbers = array_merge($reservedNumbers, $reserve->reservation_numbers);
                }
            }
        }

        return array_unique($reservedNumbers);
    }

    /**
     * Calcular escrutinio por categoría para números individuales (premios al décimo)
     */
    private function calculateCategoryScrutiny($lottery, $reservedNumbers)
    {
        $calculator = app(LotteryPrizeCalculator::class);
        $lotteryResult = $lottery->result;
        $typeIdentifier = $lottery->getLotteryTypeIdentifier();

        $results = [];
        foreach ($reservedNumbers as $number) {
            $calculation = $calculator->calculate($number, $lotteryResult, $typeIdentifier);

            if ($calculation['total_prize'] > 0) {
                $results[] = [
                    'number' => $number,
                    'number_str' => $calculation['number'],
                    'total_prize' => $calculation['total_prize'] / 10,
                    'categories' => array_map(fn ($prize) => [
                        'categoria' => $prize['name'],
                        'premio_decimo' => $prize['amount'] / 10,
                        'key' => $prize['key'],
                    ], $calculation['prizes']),
                ];
            }
        }

        usort($results, function ($a, $b) {
            return $b['total_prize'] <=> $a['total_prize'];
        });

        return $results;
    }

    /**
     * Comparar números normalizando formatos (con/sin ceros a la izquierda)
     */
    private function compareNumbers($number1, $number2)
    {
        return str_pad($number1, 5, '0', STR_PAD_LEFT) === str_pad($number2, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Organizar resultados del escrutinio por categoría agrupados por entidad
     */
    private function organizeResultsByEntity($scrutinyResults, $entitiesWithReserves, $lotteryId)
    {
        $resultsByEntity = [];
        
        // Crear un mapa de números por entidad
        $numbersByEntity = [];
        foreach ($entitiesWithReserves as $entity) {
            $entityNumbers = [];
            foreach ($entity->reserves as $reserve) {
                if ($reserve->reservation_numbers) {
                    $entityNumbers = array_merge($entityNumbers, $reserve->reservation_numbers);
                }
            }
            $numbersByEntity[$entity->id] = array_unique($entityNumbers);
        }
        
        // Agrupar resultados por entidad y calcular décimos por set individual
        foreach ($entitiesWithReserves as $entity) {
            $entityResults = [];
            $entityNumbers = $numbersByEntity[$entity->id] ?? [];
            $totalEntityPrize = 0;

            foreach ($scrutinyResults as $scrutinyResult) {
                if (! $this->numberBelongsToEntityReserve($scrutinyResult['number'], $entityNumbers)) {
                    continue;
                }

                $decimosInfo = $this->calculateDecimosForNumberBySets($scrutinyResult['number'], $entity, $lotteryId);

                $premioPorDecimo = $scrutinyResult['total_prize'];
                $premioTotalNumero = 0;
                foreach ($decimosInfo['sets_info'] ?? [] as $setInfo) {
                    $importeJugado = $setInfo['importe_jugado'] ?? 0;
                    $ticketPrice = $decimosInfo['ticket_price'] ?? 0;
                    $participacionesVendidas = (int) ($setInfo['participations_vendidas'] ?? 0);
                    if ($ticketPrice > 0 && $importeJugado > 0 && $participacionesVendidas > 0) {
                        $premioPorParticipacion = $premioPorDecimo * ($importeJugado / $ticketPrice);
                        $premioTotalNumero += $premioPorParticipacion * $participacionesVendidas;
                    }
                }
                $totalEntityPrize += $premioTotalNumero;

                // Copia explícita: no reutilizar el array de $scrutinyResults por referencia
                $entityResults[] = array_merge($scrutinyResult, [
                    'decimos_info' => $decimosInfo,
                    'premio_total_numero' => $premioTotalNumero,
                    'premio_total_entidad' => $totalEntityPrize,
                ]);
            }

            if (! empty($entityResults)) {
                foreach ($entityResults as $index => $entityResult) {
                    $entityResults[$index]['premio_total_entidad'] = $totalEntityPrize;
                }
                $resultsByEntity[$entity->id] = $entityResults;
            }
        }

        return $resultsByEntity;
    }

    /**
     * Comprueba si un número premiado pertenece a la reserva de la entidad.
     */
    private function numberBelongsToEntityReserve($number, array $entityNumbers): bool
    {
        foreach ($entityNumbers as $entityNumber) {
            if ($this->compareNumbers((string) $number, (string) $entityNumber)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Índice de un número en una lista de reserva (comparación normalizada).
     */
    private function findNumberIndexInList($number, array $numbers): int|false
    {
        foreach ($numbers as $index => $candidate) {
            if ($this->compareNumbers((string) $number, (string) $candidate)) {
                return (int) $index;
            }
        }

        return false;
    }

    /**
     * Calcular décimos para un número específico por sets individuales
     */
    private function calculateDecimosForNumberBySets($number, $entity, $lotteryId)
    {
        $lottery = Lottery::find($lotteryId);
        $ticketPrice = $lottery->ticket_price ?? 0; // Precio del décimo del sorteo
        
        $totalParticipations = 0;
        $totalDecimos = 0;
        $setsInfo = [];
        
        \Log::info("=== CALCULANDO DÉCIMOS PARA NÚMERO: {$number} ===");
        \Log::info("Entidad: {$entity->name} (ID: {$entity->id})");
        \Log::info("Precio del décimo del sorteo: {$ticketPrice}");
        \Log::info("Total reservas de la entidad: " . count($entity->reserves));
        
        // Recorrer cada reserva de la entidad
        foreach ($entity->reserves as $reserve) {
            \Log::info("--- Procesando reserva ID: {$reserve->id} ---");
            \Log::info("Números en reserva: " . json_encode($reserve->reservation_numbers));
            
            if ($reserve->reservation_numbers) {
                $numberIndex = $this->findNumberIndexInList($number, $reserve->reservation_numbers);
                \Log::info("Índice del número {$number} en reserva: " . ($numberIndex !== false ? $numberIndex : 'NO ENCONTRADO'));
                
                if ($numberIndex !== false) {
                    // Obtener todos los sets de esta reserva
                    $sets = Set::where('reserve_id', $reserve->id)->get();
                    \Log::info("Sets encontrados en reserva: " . count($sets));
                    
                    foreach ($sets as $set) {
                        \Log::info("--- Procesando Set ID: {$set->id} ---");
                        \Log::info("Precio del set: {$set->price_per_participation}");
                        \Log::info("Donativo del set: {$set->donation_amount}");
                        
                        // Obtener todas las participaciones vendidas para este número específico
                        // Si la reserva tiene solo un número, todas las participaciones del set tienen ese número
                        // Si la reserva tiene múltiples números, filtrar por el número específico
                        $participations = Participation::where('set_id', $set->id)
                            ->soldForScrutiny()
                            ->where(function ($query) use ($entity) {
                                $query->where('entity_id', $entity->id)
                                    ->orWhereHas('set', function ($setQuery) use ($entity) {
                                        $setQuery->where('entity_id', $entity->id);
                                    });
                            });
                        
                        // Si la reserva tiene múltiples números, necesitamos filtrar por participación específica
                        if (count($reserve->reservation_numbers) > 1) {
                            // Para reservas con múltiples números, filtrar por el número de participación correspondiente
                            $participations = $participations->where('participation_number', $numberIndex + 1);
                        }
                        // Si la reserva tiene un solo número, todas las participaciones del set tienen ese número
                        
                        $participacionesVendidas = (clone $participations)->count();
                        \Log::info("Participaciones vendidas para número {$number} en set {$set->id}: {$participacionesVendidas}");
                        
                        if ($participacionesVendidas > 0) {
                            $totalParticipations += $participacionesVendidas;
                            
                            // Obtener el precio real de la participación (played_amount del set)
                            $pricePerParticipation = $set->played_amount ?? 0;
                            \Log::info("Precio real del set: {$pricePerParticipation}");
                            $donationAmount = $set->donation_amount ?? 0;
                            $importeJugado = $pricePerParticipation; // No restar el donativo
                            
                            \Log::info("Set {$set->id}: {$participacionesVendidas} participaciones vendidas, Precio Real: {$pricePerParticipation}, Donativo: {$donationAmount}, Importe Jugado: {$importeJugado}");
                            
                            if ($importeJugado > 0 && $ticketPrice > 0) {
                                $participacionesPorDecimo = $ticketPrice / $importeJugado;
                                $decimosDeEsteSet = ceil(($participacionesVendidas / $participacionesPorDecimo) * 100) / 100;
                                $totalDecimos += $decimosDeEsteSet;
                                
                                $setsInfo[] = [
                                    'set_id' => $set->id,
                                    'participations_vendidas' => $participacionesVendidas,
                                    'importe_jugado' => $importeJugado,
                                    'participaciones_por_decimo' => $participacionesPorDecimo,
                                    'decimos' => $decimosDeEsteSet
                                ];
                                
                                \Log::info("Set {$set->id}: {$participacionesVendidas} participaciones, {$participacionesPorDecimo} por décimo, {$decimosDeEsteSet} décimos");
                            } else {
                                \Log::info("Set {$set->id}: No se puede calcular - Importe jugado: {$importeJugado}, Precio décimo: {$ticketPrice}");
                            }
                        } else {
                            \Log::info("Set {$set->id}: No hay participaciones vendidas para este número");
                        }
                    }
                } else {
                    \Log::info("Número {$number} no encontrado en esta reserva");
                }
            } else {
                \Log::info("Reserva sin números");
            }
        }
        
        \Log::info("Total décimos calculados: {$totalDecimos} para número {$number}");
        
        return [
            'total_participations' => $totalParticipations,
            'total_decimos' => $totalDecimos,
            'ticket_price' => $ticketPrice,
            'sets_info' => $setsInfo
        ];
    }

    /**
     * Guardar escrutinio definitivamente
     */
    public function save(Request $request, $lotteryId)
    {
        $administrationId = session('selected_administration.id');

        if (!$administrationId) {
            return redirect()->route('lottery.administrations')
                ->with('error', 'Debe seleccionar una administración primero');
        }

        $scrutiny = AdministrationLotteryScrutiny::where('administration_id', $administrationId)
            ->where('lottery_id', $lotteryId)
            ->first();

        if (!$scrutiny) {
            return redirect()->route('lottery.results')
                ->with('error', 'No se encontró el escrutinio');
        }

        if (!$scrutiny->is_scrutinized) {
            return redirect()->route('lottery.scrutiny', $lotteryId)
                ->with('error', 'Debe completar el escrutinio antes de guardarlo');
        }

        if ($scrutiny->is_saved) {
            return redirect()->route('lottery.show-administration-scrutiny', [$lotteryId, $administrationId])
                ->with('info', 'El escrutinio ya ha sido guardado');
        }

        try {
            $scrutiny->update([
                'is_saved' => true,
                'saved_at' => now(),
                'saved_by' => Auth::id()
            ]);

            app(EntityLotteryPrizePaymentService::class)->syncAfterScrutinySaved($scrutiny);

            return redirect()->route('lottery.show-administration-scrutiny', [$lotteryId, $administrationId])
                ->with('success', 'Escrutinio guardado exitosamente');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al guardar el escrutinio: ' . $e->getMessage());
        }
    }

    /**
     * Guardar resultados detallados del escrutinio
     */
    private function saveDetailedScrutinyResults($scrutiny, $scrutinyResultsByEntity, $lottery)
    {
        foreach ($scrutinyResultsByEntity as $entityId => $entityResults) {
            foreach ($entityResults as $categoryResult) {
                $decimosInfo = $categoryResult['decimos_info'] ?? [];
                $totalDecimos = $decimosInfo['total_decimos'] ?? 0;
                $premioPorDecimo = $categoryResult['total_prize'];
                $premioTotal = $premioPorDecimo * $totalDecimos;
                
                // Iterar por cada set que tenga participaciones
                if (!empty($decimosInfo['sets_info'])) {
                    foreach ($decimosInfo['sets_info'] as $setInfo) {
                        // Calcular premio por participación para este set específico
                        $premioPorParticipacion = 0;
                        $importeJugado = $setInfo['importe_jugado'] ?? 0;
                        $ticketPrice = $decimosInfo['ticket_price'] ?? 0;
                        
                        if ($ticketPrice > 0 && $importeJugado > 0) {
                            $porcentajeParticipacion = $importeJugado / $ticketPrice;
                            $premioPorParticipacion = $premioPorDecimo * $porcentajeParticipacion;
                        }

                        $participacionesVendidas = (int) ($setInfo['participations_vendidas'] ?? 0);
                        $premioTotalSet = round($premioPorParticipacion * $participacionesVendidas, 2);

                        // Guardar un registro por cada set
                        DB::table('scrutiny_detailed_results')->insert([
                            'scrutiny_id' => $scrutiny->id,
                            'entity_id' => $entityId,
                            'winning_number' => $categoryResult['number'],
                            'set_id' => $setInfo['set_id'],
                            'premio_por_decimo' => $premioPorDecimo,
                            'premio_por_participacion' => $premioPorParticipacion,
                            'total_decimos' => ceil((float) ($setInfo['decimos'] ?? 0) * 100) / 100,
                            'total_participations' => $participacionesVendidas,
                            'premio_total' => $premioTotalSet,
                            'winning_categories' => json_encode($categoryResult['categories']),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                } else {
                    // Si no hay sets_info, guardar con los datos totales
                    \Log::info("No sets_info found, using total data");
                    \Log::info("Total Decimos: " . $totalDecimos);
                    \Log::info("Total Participations: " . ($decimosInfo['total_participations'] ?? 0));
                    
                    // Buscar el primer set de la entidad para obtener el set_id
                    $entity = \App\Models\Entity::forUser(auth()->user())
                        ->with('reserves')
                        ->find($entityId);
                    $firstSet = null;
                    if ($entity) {
                        foreach ($entity->reserves as $reserve) {
                            $set = \App\Models\Set::forUser(auth()->user())
                                ->where('reserve_id', $reserve->id)
                                ->first();
                            if ($set) {
                                $firstSet = $set;
                                break;
                            }
                        }
                    }
                    
                    DB::table('scrutiny_detailed_results')->insert([
                        'scrutiny_id' => $scrutiny->id,
                        'entity_id' => $entityId,
                        'winning_number' => $categoryResult['number'],
                        'set_id' => $firstSet ? $firstSet->id : null,
                        'premio_por_decimo' => $premioPorDecimo,
                        'premio_por_participacion' => $premioPorDecimo, // Usar premio por décimo como fallback
                        'total_decimos' => ceil((float) $totalDecimos * 100) / 100,
                        'total_participations' => $decimosInfo['total_participations'] ?? 0,
                        'premio_total' => $premioTotal,
                        'winning_categories' => json_encode($categoryResult['categories']),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }

    /**
     * Eliminar escrutinio (solo si no está finalizado)
     */
    public function delete($lotteryId, $administrationId)
    {
        $scrutiny = AdministrationLotteryScrutiny::where('administration_id', $administrationId)
            ->where('lottery_id', $lotteryId)
            ->first();

        if (!$scrutiny) {
            return redirect()->route('lottery.results')
                ->with('error', 'No se encontró el escrutinio');
        }

        if ($scrutiny->is_scrutinized) {
            return redirect()->route('lottery.show-administration-scrutiny', [$lotteryId, $administrationId])
                ->with('error', 'No se puede eliminar un escrutinio ya finalizado');
        }

        $scrutiny->delete();

        return redirect()->route('lottery.results')
            ->with('success', 'Escrutinio eliminado exitosamente');
    }
}
