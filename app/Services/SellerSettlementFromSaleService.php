<?php

namespace App\Services;

use App\Models\Participation;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementPayment;

class SellerSettlementFromSaleService
{
    public function recordIfNeeded($seller, $participations, $set, float $saleAmount, ?string $paymentMethod, int $userId): void
    {
        if (! in_array($paymentMethod, ['efectivo', 'bizum', 'transferencia'], true)) {
            return;
        }

        $lotteryId = $set->reserve->lottery_id ?? null;
        if (! $lotteryId) {
            return;
        }

        $paymentMethod = in_array($paymentMethod, ['efectivo', 'bizum', 'transferencia'], true)
            ? $paymentMethod
            : 'otro';
        $pricePerParticipation = $set->pricePerParticipation();
        $now = now();

        $allParticipations = Participation::where('seller_id', $seller->id)
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->whereIn('status', ['asignada', 'vendida', 'pagada'])
            ->with('set')
            ->get();

        $totalParticipations = $allParticipations->count();
        $totalAmount = $allParticipations->sum(fn ($p) => (float) ($p->set?->pricePerParticipation() ?? 0));

        $previousPaid = SellerSettlement::where('seller_id', $seller->id)
            ->where('lottery_id', $lotteryId)
            ->sum('paid_amount');

        $totalPaidWithNew = $previousPaid + $saleAmount;
        $pendingAmount = $totalAmount - $totalPaidWithNew;
        $calculatedParticipations = $pricePerParticipation > 0
            ? round($saleAmount / $pricePerParticipation, 2)
            : 0;

        $settlement = SellerSettlement::create([
            'seller_id' => $seller->id,
            'lottery_id' => $lotteryId,
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'paid_amount' => $saleAmount,
            'pending_amount' => $pendingAmount,
            'total_participations' => $totalParticipations,
            'calculated_participations' => $calculatedParticipations,
            'settlement_date' => $now->format('Y-m-d'),
            'settlement_time' => $now->format('H:i:s'),
            'notes' => 'Venta registrada desde app',
        ]);

        SellerSettlementPayment::create([
            'seller_settlement_id' => $settlement->id,
            'amount' => $saleAmount,
            'payment_method' => $paymentMethod,
            'notes' => 'Venta - '.ucfirst($paymentMethod),
            'payment_date' => $now,
        ]);
    }
}
