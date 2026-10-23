<?php

namespace App\Services;

use App\Models\Devolution;
use App\Models\Reserve;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DevolutionReservePdfService
{
    public function __construct(
        private readonly ContractDocumentService $documents
    ) {}

    /**
     * @return array{binary: string, filename: string, reserve: Reserve}
     */
    public function build(int $reserveId): array
    {
        $reserve = Reserve::with(['entity', 'lottery', 'sets'])->find($reserveId);
        if (! $reserve) {
            throw new \InvalidArgumentException('Reserva no encontrada.');
        }

        $devolutions = Devolution::query()
            ->with([
                'seller',
                'user',
                'details.participation.set',
            ])
            ->where(function ($q) use ($reserveId) {
                $q->where('reserve_id', $reserveId)
                    ->orWhereHas('details.participation.set', fn ($sq) => $sq->where('reserve_id', $reserveId));
            })
            ->orderBy('devolution_date')
            ->orderBy('devolution_time')
            ->orderBy('id')
            ->get()
            ->unique('id')
            ->values();

        $movements = $devolutions->map(function (Devolution $dev) {
            $details = $dev->details;
            $byAction = $details->groupBy(fn ($d) => (string) $d->action);

            return [
                'id' => $dev->id,
                'code' => '#DEV'.str_pad((string) $dev->id, 4, '0', STR_PAD_LEFT),
                'type' => $dev->typeLabel(),
                'date' => Carbon::parse($dev->devolution_date)->format('d/m/Y'),
                'seller' => $dev->seller
                    ? ($dev->seller->full_name ?: $dev->seller->name)
                    : null,
                'total' => (int) $dev->total_participations,
                'devolver' => ($byAction->get('devolver')?->count() ?? 0)
                    + ($byAction->get('devolver_vendedor')?->count() ?? 0),
                'vender' => $byAction->get('vender')?->count() ?? 0,
                'anular' => $byAction->get('anular')?->count() ?? 0,
                'liquidation' => $dev->isAnnulment()
                    ? null
                    : (float) ($dev->total_liquidation ?? 0),
            ];
        })->all();

        $setSections = $this->buildSetSections($devolutions, $reserve);

        $reservationNumbers = is_array($reserve->reservation_numbers)
            ? implode(', ', $reserve->reservation_numbers)
            : (string) ($reserve->reservation_numbers ?? $reserve->id);

        $binary = $this->documents->renderPdfBinary('devolutions.reserve_pdf', [
            'reserve' => $reserve,
            'reservationNumbers' => $reservationNumbers,
            'generatedAt' => Carbon::now()->format('d/m/Y H:i'),
            'movements' => $movements,
            'setSections' => $setSections,
            'totals' => [
                'movements' => count($movements),
                'devolver' => collect($movements)->sum('devolver'),
                'vender' => collect($movements)->sum('vender'),
                'anular' => collect($movements)->sum('anular'),
            ],
        ]);

        $filename = 'devoluciones-reserva-'.$reserve->id.'-'.Carbon::now()->format('Ymd').'.pdf';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'reserve' => $reserve,
        ];
    }

    /**
     * @param  Collection<int, Devolution>  $devolutions
     * @return list<array<string, mixed>>
     */
    private function buildSetSections(Collection $devolutions, Reserve $reserve): array
    {
        /** @var array<int, array<string, mixed>> $bySet */
        $bySet = [];

        foreach ($devolutions as $dev) {
            $dateLabel = Carbon::parse($dev->devolution_date)->format('d/m/Y');
            $devCode = '#DEV'.str_pad((string) $dev->id, 4, '0', STR_PAD_LEFT);

            foreach ($dev->details as $detail) {
                $p = $detail->participation;
                $set = $p?->set;
                if (! $set) {
                    continue;
                }
                // Solo sets de esta reserva (fallback whereHas puede traer detalles de otro set en teoría)
                if ((int) ($set->reserve_id ?? 0) !== (int) $reserve->id) {
                    continue;
                }

                $setId = (int) $set->id;
                if (! isset($bySet[$setId])) {
                    $price = (float) ($set->played_amount ?? 0) + (float) ($set->donation_amount ?? 0);
                    if ($price <= 0) {
                        $price = (float) ($set->total_participation_amount ?? 0);
                    }
                    $bySet[$setId] = [
                        'set_id' => $setId,
                        'set_name' => $set->set_name ?: ('Set #'.$setId),
                        'price' => $price,
                        'counts' => [
                            'devolver' => 0,
                            'devolver_vendedor' => 0,
                            'vender' => 0,
                            'anular' => 0,
                        ],
                        'rows' => [],
                    ];
                }

                $action = (string) $detail->action;
                if (! isset($bySet[$setId]['counts'][$action])) {
                    $bySet[$setId]['counts'][$action] = 0;
                }
                $bySet[$setId]['counts'][$action]++;

                $bySet[$setId]['rows'][] = [
                    'number' => $p->participation_number ?? '—',
                    'code' => $p->display_participation_code ?: ('#'.$p->id),
                    'action' => $this->actionLabel($action),
                    'status' => $p->status_text ?? ($p->status ?? '—'),
                    'date' => $dateLabel,
                    'devolution' => $devCode,
                ];
            }
        }

        // Orden: sets de la reserva por nombre, luego huérfanos por id
        uasort($bySet, fn ($a, $b) => strcasecmp((string) $a['set_name'], (string) $b['set_name']));

        return array_values(array_map(function (array $section) {
            $counts = $section['counts'];
            $returned = (int) ($counts['devolver'] ?? 0) + (int) ($counts['devolver_vendedor'] ?? 0);
            $sold = (int) ($counts['vender'] ?? 0);
            $annulled = (int) ($counts['anular'] ?? 0);
            $price = (float) $section['price'];

            $section['summary'] = [
                'devolver' => $returned,
                'vender' => $sold,
                'anular' => $annulled,
                'devolver_amount' => round($returned * $price, 2),
                'vender_amount' => round($sold * $price, 2),
                'anular_amount' => round($annulled * $price, 2),
            ];

            return $section;
        }, $bySet));
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'devolver', 'devolver_vendedor' => 'Devuelta',
            'vender' => 'Vendida / liquidada',
            'anular' => 'Anulada',
            default => $action,
        };
    }
}
