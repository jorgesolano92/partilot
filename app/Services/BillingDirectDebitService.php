<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\BillingCharge;
use App\Models\BillingDirectDebitOrder;
use App\Models\PartilotBillingSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingDirectDebitService
{
    public function canGenerateDirectDebit(Administration $administration): bool
    {
        return app(AdministrationBillingService::class)->hasValidBillingIban($administration)
            && PartilotBillingSetting::current()->creditorIban() !== '';
    }

    /**
     * @param  array<int>  $chargeIds
     */
    public function createOrderFromCharges(
        Administration $administration,
        User $user,
        array $chargeIds,
        Carbon $collectionDate,
        ?string $notes = null
    ): BillingDirectDebitOrder {
        if (! $this->canGenerateDirectDebit($administration)) {
            abort(422, 'Faltan datos bancarios de PARTILOT o de la administración para generar el adeudo.');
        }

        $chargeIds = array_values(array_unique(array_map('intval', $chargeIds)));
        if ($chargeIds === []) {
            abort(422, 'Debe seleccionar al menos un cargo pendiente.');
        }

        $settings = PartilotBillingSetting::current();
        $creditorIban = $settings->creditorIban();
        $creditorSchemeId = $settings->creditorSchemeId();
        if ($creditorSchemeId === '') {
            abort(422, 'Configure el identificador acreedor SEPA en Ajustes → Config. factura auto.');
        }

        return DB::transaction(function () use (
            $administration,
            $user,
            $chargeIds,
            $collectionDate,
            $notes,
            $settings,
            $creditorIban,
            $creditorSchemeId
        ) {
            $charges = BillingCharge::query()
                ->whereIn('id', $chargeIds)
                ->where('administration_id', $administration->id)
                ->where('status', BillingCharge::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            if ($charges->count() !== count($chargeIds)) {
                abort(422, 'Alguno de los cargos seleccionados ya no está pendiente de remesa.');
            }

            $controlSum = round((float) $charges->sum('amount'), 2);
            if ($controlSum <= 0) {
                abort(422, 'El importe total del adeudo debe ser mayor que cero.');
            }

            $hasPreviousCollected = BillingDirectDebitOrder::query()
                ->where('administration_id', $administration->id)
                ->where('status', BillingDirectDebitOrder::STATUS_COLLECTED)
                ->exists();

            $mandateSignedAt = $administration->billing_sepa_mandate_signed_at
                ?? $administration->created_at?->toDateString()
                ?? now()->toDateString();

            $order = BillingDirectDebitOrder::create([
                'administration_id' => $administration->id,
                'message_id' => BillingDirectDebitOrder::generateMessageId(),
                'payment_info_id' => BillingDirectDebitOrder::generateMessageId(),
                'creation_date' => now(),
                'collection_date' => $collectionDate->toDateString(),
                'number_of_transactions' => $charges->count(),
                'control_sum' => $controlSum,
                'creditor_name' => $settings->company_name ?: 'PARTILOT',
                'creditor_nif_cif' => $settings->nif_cif,
                'creditor_iban' => $creditorIban,
                'creditor_scheme_id' => $creditorSchemeId,
                'debtor_name' => $administration->name ?: $administration->society,
                'debtor_nif_cif' => $administration->nif_cif,
                'debtor_iban' => $administration->debtorIban(),
                'debtor_mandate_id' => $administration->sepaMandateId(),
                'debtor_mandate_signed_at' => $mandateSignedAt,
                'sequence_type' => $hasPreviousCollected
                    ? BillingDirectDebitOrder::SEQUENCE_RCUR
                    : BillingDirectDebitOrder::SEQUENCE_FRST,
                'status' => BillingDirectDebitOrder::STATUS_DRAFT,
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($charges as $charge) {
                $charge->forceFill([
                    'status' => BillingCharge::STATUS_IN_REMITTANCE,
                    'billing_direct_debit_order_id' => $order->id,
                ])->save();
            }

            return $order->load('charges');
        });
    }

    public function markExported(BillingDirectDebitOrder $order, string $xmlFilename): BillingDirectDebitOrder
    {
        $order->forceFill([
            'xml_filename' => $xmlFilename,
            'status' => BillingDirectDebitOrder::STATUS_EXPORTED,
            'exported_at' => now(),
        ])->save();

        return $order->refresh();
    }

    public function markCollected(BillingDirectDebitOrder $order): BillingDirectDebitOrder
    {
        return DB::transaction(function () use ($order) {
            $order->load('charges');
            $order->forceFill([
                'status' => BillingDirectDebitOrder::STATUS_COLLECTED,
                'collected_at' => now(),
            ])->save();

            foreach ($order->charges as $charge) {
                $charge->forceFill([
                    'status' => BillingCharge::STATUS_COLLECTED,
                    'collected_at' => now(),
                ])->save();
            }

            return $order->refresh();
        });
    }

    public function cancelOrder(BillingDirectDebitOrder $order): BillingDirectDebitOrder
    {
        if ($order->status === BillingDirectDebitOrder::STATUS_COLLECTED) {
            abort(422, 'No se puede anular un adeudo ya cobrado.');
        }

        return DB::transaction(function () use ($order) {
            $order->load('charges');

            foreach ($order->charges as $charge) {
                $charge->forceFill([
                    'status' => BillingCharge::STATUS_PENDING,
                    'billing_direct_debit_order_id' => null,
                ])->save();
            }

            $order->forceFill(['status' => BillingDirectDebitOrder::STATUS_CANCELLED])->save();

            return $order->refresh();
        });
    }
}
