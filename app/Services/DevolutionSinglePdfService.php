<?php

namespace App\Services;

use App\Models\Devolution;
use Carbon\Carbon;

class DevolutionSinglePdfService
{
    public function __construct(
        private readonly ContractDocumentService $documents
    ) {}

    /**
     * @return array{binary: string, filename: string, devolution: Devolution}
     */
    public function build(int $devolutionId): array
    {
        $devolution = Devolution::with([
            'entity',
            'lottery',
            'reserve',
            'seller',
            'user',
            'details.participation.set',
        ])->find($devolutionId);

        if (! $devolution) {
            throw new \InvalidArgumentException('Devolución no encontrada.');
        }

        $details = $devolution->details;
        $byAction = $details->groupBy(fn ($d) => (string) $d->action);

        $rows = [];
        foreach ($details as $detail) {
            $p = $detail->participation;
            $set = $p?->set;
            $action = (string) $detail->action;
            $rows[] = [
                'number' => $p->participation_number ?? '—',
                'code' => $p?->display_participation_code ?: ('#'.($p->id ?? '')),
                'set' => $set?->set_name ?: ($set ? 'Set #'.$set->id : '—'),
                'action' => $this->actionLabel($action),
                'status' => $p->status_text ?? ($p->status ?? '—'),
            ];
        }

        $reserveId = $devolution->resolvedReserveId();
        $reservationNumbers = '';
        if ($devolution->reserve) {
            $reservationNumbers = is_array($devolution->reserve->reservation_numbers)
                ? implode(', ', $devolution->reserve->reservation_numbers)
                : (string) ($devolution->reserve->reservation_numbers ?? $devolution->reserve->id);
        }

        $binary = $this->documents->renderPdfBinary('devolutions.single_pdf', [
            'devolution' => $devolution,
            'code' => '#DEV'.str_pad((string) $devolution->id, 4, '0', STR_PAD_LEFT),
            'typeLabel' => $devolution->typeLabel(),
            'isAnnulment' => $devolution->isAnnulment(),
            'dateLabel' => Carbon::parse($devolution->devolution_date)->format('d/m/Y'),
            'generatedAt' => Carbon::now()->format('d/m/Y H:i'),
            'reserveId' => $reserveId,
            'reservationNumbers' => $reservationNumbers,
            'rows' => $rows,
            'counts' => [
                'devolver' => ($byAction->get('devolver')?->count() ?? 0)
                    + ($byAction->get('devolver_vendedor')?->count() ?? 0),
                'vender' => $byAction->get('vender')?->count() ?? 0,
                'anular' => $byAction->get('anular')?->count() ?? 0,
                'total' => $details->count(),
            ],
            'liquidation' => $devolution->isAnnulment()
                ? null
                : (float) ($devolution->total_liquidation ?? 0),
        ]);

        $filename = 'devolucion-'.$devolution->id.'-'.Carbon::now()->format('Ymd').'.pdf';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'devolution' => $devolution,
        ];
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
