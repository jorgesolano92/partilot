<?php

namespace App\Services;

use App\Models\Participation;
use App\Models\Set;
use App\Support\ParticipationTicketReference;
use Illuminate\Support\Facades\DB;

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
            ->with(['reserve.lottery', 'reserve.entity'])
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

        if ($participation->status !== 'vendida' && $participation->status !== 'pagada') {
            return [
                'success' => false,
                'error' => 'Esta participación no está asignada.',
                'ticket' => null,
            ];
        }

        $reserve = $set->reserve;
        $lottery = $reserve->lottery;
        $reservedNumbers = $reserve->reservation_numbers ?? [];
        $winningNumbers = count($reservedNumbers) === 1 ? $reservedNumbers : $reservedNumbers;

        $scrutinyResults = collect();
        $totalPrizeAmount = 0.0;
        $allWinningCategories = [];

        if (! empty($winningNumbers)) {
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

        $prizeInfo = $scrutinyResults->count() > 0
            ? [
                'has_won' => true,
                'prize_category' => 'Premio del Escrutinio',
                'prize_amount' => $totalPrizeAmount,
                'matching_numbers' => $winningNumbers,
                'winning_categories' => $allWinningCategories,
                'scrutiny_results_count' => $scrutinyResults->count(),
            ]
            : [
                'has_won' => false,
                'prize_category' => null,
                'prize_amount' => 0,
                'matching_numbers' => $winningNumbers,
                'winning_categories' => [],
            ];

        return [
            'success' => true,
            'error' => null,
            'ticket' => [
                'data' => [
                    'participation_code' => $participation->display_participation_code,
                    'participation_number' => $ref,
                    'numbers' => $reservedNumbers,
                    'winning_numbers' => $winningNumbers,
                ],
                'set' => [
                    'id' => $set->id,
                    'played_amount' => $set->played_amount,
                ],
                'reserve' => [
                    'entity' => [
                        'name' => $reserve->entity->name ?? null,
                    ],
                    'reservation_numbers' => $reservedNumbers,
                ],
                'lottery' => [
                    'name' => $lottery->name ?? null,
                    'draw_date' => $lottery->draw_date ?? null,
                    'ticket_price' => $lottery->ticket_price ?? 6,
                ],
                'prize_info' => $prizeInfo,
            ],
        ];
    }
}
