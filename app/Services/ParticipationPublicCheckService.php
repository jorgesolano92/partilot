<?php

namespace App\Services;

use App\Models\DesignFormat;
use App\Models\Participation;
use App\Models\Set;
use App\Support\ParticipationTicketReference;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Resolución pública de participación + premio (QR / web partilot.es).
 */
class ParticipationPublicCheckService
{
    /**
     * @return array{success: bool, error: ?string, ticket: ?array}
     */
    public function check(?string $rawRef, ?string $sig = null): array
    {
        if ($rawRef === null || trim($rawRef) === '') {
            return [
                'success' => false,
                'error' => null,
                'ticket' => null,
            ];
        }

        $ref = ParticipationTicketReference::normalize($rawRef);
        if ($authError = ParticipationTicketReference::authenticationError($ref, $sig)) {
            return [
                'success' => false,
                'error' => $authError,
                'ticket' => null,
            ];
        }

        $set = Set::query()
            ->whereNotNull('tickets')
            ->with(['reserve.lottery', 'reserve.entity', 'designFormats'])
            ->get()
            ->first(function (Set $set) use ($ref) {
                if (! is_array($set->tickets)) {
                    return false;
                }
                foreach ($set->tickets as $ticket) {
                    if (isset($ticket['r']) && $ticket['r'] == $ref) {
                        return true;
                    }
                }

                return false;
            });

        if (! $set) {
            return [
                'success' => false,
                'error' => 'No se encontró ninguna participación con esa referencia.',
                'ticket' => null,
            ];
        }

        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] == $ref) {
                $participationNumber = $ticket['n'];
                break;
            }
        }

        $participation = Participation::query()
            ->where('set_id', $set->id)
            ->where('participation_number', $participationNumber)
            ->first();

        if (! $participation) {
            return [
                'success' => false,
                'error' => 'No se encontró la participación correspondiente a esa referencia.',
                'ticket' => null,
            ];
        }

        $status = (string) ($participation->status ?? '');
        // La comprobación por QR debe mostrar preview y datos aunque aún no esté asignada/vendida.
        // Solo se bloquean estados inválidos (anulada / perdida).
        $blockedStatuses = ['anulada', 'perdida'];
        if (in_array($status, $blockedStatuses, true)) {
            return [
                'success' => false,
                'error' => 'Esta participación no está disponible para comprobación.',
                'ticket' => null,
            ];
        }

        $reserve = $set->reserve;
        $lottery = $reserve?->lottery;
        $reservedNumbers = $reserve->reservation_numbers ?? [];
        $winningNumbers = count($reservedNumbers) === 1 ? $reservedNumbers : $reservedNumbers;

        $drawStatus = $this->resolveDrawStatus($lottery);
        $scrutinyResults = collect();
        $totalPrizeAmount = 0.0;
        $allWinningCategories = [];

        // Solo consultar escrutinio si el sorteo ya se ha celebrado (o al menos su fecha ha llegado).
        if ($drawStatus !== 'pending_celebration' && ! empty($winningNumbers)) {
            $scrutinyResults = DB::table('scrutiny_detailed_results')
                ->join('administration_lottery_scrutinies', 'scrutiny_detailed_results.scrutiny_id', '=', 'administration_lottery_scrutinies.id')
                ->whereIn('scrutiny_detailed_results.winning_number', $winningNumbers)
                ->where('scrutiny_detailed_results.set_id', $set->id)
                ->where('administration_lottery_scrutinies.is_scrutinized', true)
                ->select('scrutiny_detailed_results.*')
                ->get();

            foreach ($scrutinyResults as $result) {
                $totalPrizeAmount += (float) $result->premio_por_participacion;
                $categories = json_decode($result->winning_categories, true);
                if (! is_array($categories)) {
                    continue;
                }
                foreach ($categories as $category) {
                    if (is_array($category) && isset($category['categoria'], $category['premio_decimo'])) {
                        $allWinningCategories[] = $category;
                    } elseif (is_string($category) && trim($category) !== '') {
                        $allWinningCategories[] = [
                            'categoria' => $category,
                            'premio_decimo' => $result->premio_por_decimo ?? 0,
                        ];
                    }
                }
            }
        }

        if ($drawStatus === 'pending_celebration') {
            $prizeInfo = null;
            $drawStatus = 'pending_celebration';
        } elseif ($scrutinyResults->isEmpty() && $drawStatus === 'pending_results') {
            $prizeInfo = null;
        } elseif ($scrutinyResults->count() > 0) {
            $drawStatus = 'completed';
            $prizeInfo = [
                'has_won' => true,
                'prize_category' => 'Premio del Escrutinio',
                'prize_amount' => $totalPrizeAmount,
                'matching_numbers' => $winningNumbers,
                'winning_categories' => $allWinningCategories,
                'scrutiny_results_count' => $scrutinyResults->count(),
            ];
        } else {
            $drawStatus = 'completed';
            $prizeInfo = [
                'has_won' => false,
                'prize_category' => null,
                'prize_amount' => 0,
                'matching_numbers' => $winningNumbers,
                'winning_categories' => [],
            ];
        }

        $played = (float) ($set->played_amount ?? 0);
        $donation = (float) ($set->donation_amount ?? 0);
        $total = $played + $donation;
        if ($total <= 0 && isset($set->total_participation_amount)) {
            $total = (float) $set->total_participation_amount;
        }

        $previewImageUrl = url('/comprobar-participaciones/imagen?ref='.urlencode($ref));
        if ($sig) {
            $previewImageUrl .= '&sig='.urlencode($sig);
        }

        return [
            'success' => true,
            'error' => null,
            'ticket' => [
                'data' => [
                    'participation_code' => $participation->display_participation_code,
                    'participation_number' => $ref,
                    'numbers' => $reservedNumbers,
                    'winning_numbers' => $winningNumbers,
                    'status' => $status,
                ],
                'set' => [
                    'id' => $set->id,
                    'played_amount' => $played,
                    'donation_amount' => $donation,
                    'total_amount' => $total,
                    'amount_label' => $this->formatAmountLabel($played, $donation, $total),
                ],
                'reserve' => [
                    'entity' => [
                        'name' => $reserve->entity->name ?? null,
                    ],
                    'reservation_numbers' => $reservedNumbers,
                ],
                'lottery' => [
                    'name' => $lottery?->displayLabel() ?? ($lottery->name ?? null),
                    'draw_number' => trim((string) ($lottery->name ?? '')) !== ''
                        ? trim((string) $lottery->name)
                        : ($lottery?->displayLabel() ?? null),
                    // Fecha civil (Y-m-d) para evitar desfase UTC → día anterior en el cliente.
                    'draw_date' => $lottery?->draw_date
                        ? Carbon::parse($lottery->draw_date)->timezone(config('app.timezone', 'Europe/Madrid'))->format('Y-m-d')
                        : null,
                    'ticket_price' => $lottery->ticket_price ?? 6,
                ],
                'draw_status' => $drawStatus,
                'preview_image_url' => $previewImageUrl,
                'prize_info' => $prizeInfo,
            ],
        ];
    }

    /**
     * @param  object|null  $lottery
     * @return 'pending_celebration'|'pending_results'|'completed'
     */
    protected function resolveDrawStatus($lottery): string
    {
        if (! $lottery || empty($lottery->draw_date)) {
            return 'pending_celebration';
        }

        try {
            $drawDay = Carbon::parse($lottery->draw_date)
                ->timezone(config('app.timezone', 'Europe/Madrid'))
                ->startOfDay();
        } catch (\Throwable) {
            return 'pending_celebration';
        }

        if (now()->lt($drawDay)) {
            return 'pending_celebration';
        }

        $lotteryId = $lottery->id ?? null;
        if ($lotteryId) {
            $hasScrutiny = DB::table('administration_lottery_scrutinies')
                ->where('lottery_id', $lotteryId)
                ->where('is_scrutinized', true)
                ->exists();
            if ($hasScrutiny) {
                return 'completed';
            }
        }

        // Día del sorteo o posterior sin escrutinio publicado.
        return 'pending_results';
    }

    protected function formatAmountLabel(float $played, float $donation, float $total): string
    {
        $fmt = static function (float $n): string {
            if (abs($n - round($n)) < 0.001) {
                return (string) (int) round($n);
            }

            return number_format($n, 2, ',', '.');
        };

        if ($total > 0) {
            return $fmt($total).'€';
        }

        return $fmt($played).'€';
    }

    /**
     * Diseño asociado al set (preferir aprobado / con snapshot).
     */
    public function resolveDesignForSet(Set $set): ?DesignFormat
    {
        $query = DesignFormat::query()->where('set_id', $set->id);

        $approved = (clone $query)
            ->where('approval_status', 'approved')
            ->orderByDesc('id')
            ->first();
        if ($approved) {
            return $approved;
        }

        $withSnapshot = (clone $query)
            ->whereNotNull('snapshot_path')
            ->where('snapshot_path', '!=', '')
            ->orderByDesc('id')
            ->first();
        if ($withSnapshot) {
            return $withSnapshot;
        }

        return $query->orderByDesc('id')->first();
    }

    public function resolveSnapshotAbsolutePath(?DesignFormat $design): ?string
    {
        if (! $design || empty($design->snapshot_path)) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', (string) $design->snapshot_path), '/');
        $candidates = [
            storage_path('app/public/'.$relative),
            storage_path('app/'.$relative),
            public_path('storage/'.$relative),
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->path($relative);
        }

        return null;
    }
}
