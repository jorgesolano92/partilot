<?php

namespace App\Services;

use App\Models\Lottery;
use App\Models\Participation;

class LotteryDigitalizationService
{
    public const IRREVERSIBLE_NOTICE = 'El proceso de digitalización no se puede deshacer. Si la participación tiene premio, el cobro será online.';

    public const STORAGE_NOTICE = 'Guardar en almacén no digitaliza la participación. Solo podrás consultar el resultado; el cobro presencial requiere acudir a la entidad con el papel.';

    public const STORAGE_WALLET_MESSAGE = 'Esta participación solo se podrá cobrar de manera presencial, ya que no está digitalizada. Lo que ves aquí es una extensión informativa de tu participación.';

    /** Sorteo completado o cancelado. */
    private const CLOSED_STATUSES = [3, 4];

    public function isDigitalizationClosed(Lottery $lottery): bool
    {
        if (in_array((int) $lottery->status, self::CLOSED_STATUSES, true)) {
            return true;
        }

        if ($lottery->digitalization_closed_at && now()->gte($lottery->digitalization_closed_at)) {
            return true;
        }

        if ($lottery->deadline_date && now()->startOfDay()->gt($lottery->deadline_date)) {
            return true;
        }

        return false;
    }

    public function digitalizationClosedMessage(Lottery $lottery): string
    {
        return 'El plazo de digitalización para el sorteo «'.($lottery->name ?? '—').'» ha finalizado.';
    }

    public function assertCanRegisterInWallet(Lottery $lottery): void
    {
        if ($this->isDigitalizationClosed($lottery)) {
            throw new \InvalidArgumentException($this->digitalizationClosedMessage($lottery));
        }
    }

    public function isPhysicalParticipation(Participation $participation): bool
    {
        $code = (string) ($participation->participation_code ?? '');
        if (str_starts_with($code, '1D/')) {
            return false;
        }

        $participation->loadMissing('set');
        $set = $participation->set;
        if (! $set) {
            return false;
        }

        $physical = (int) ($set->physical_participations ?? 0);
        $digital = (int) ($set->digital_participations ?? 0);

        return $physical > 0 || ($physical <= 0 && $digital <= 0);
    }

    /**
     * @return array{can_digitalize: bool, can_store_in_warehouse: bool, digitalization_closed: bool, notice: ?string}
     */
    public function walletRegistrationOptions(Participation $participation, ?Lottery $lottery): array
    {
        if (! $lottery) {
            return [
                'can_digitalize' => false,
                'can_store_in_warehouse' => false,
                'digitalization_closed' => false,
                'notice' => null,
            ];
        }

        $closed = $this->isDigitalizationClosed($lottery);
        $physical = $this->isPhysicalParticipation($participation);

        return [
            'can_digitalize' => $physical && ! $closed,
            'can_store_in_warehouse' => $physical && ! $closed,
            'digitalization_closed' => $closed,
            'notice' => $closed ? $this->digitalizationClosedMessage($lottery) : null,
        ];
    }
}
