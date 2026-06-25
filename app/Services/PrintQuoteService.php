<?php

namespace App\Services;

use App\Models\DesignExternalInvitation;
use App\Models\PrintConfiguration;
use App\Models\Set;

class PrintQuoteService
{
    public function isDigitalOnlySet(Set $set): bool
    {
        return ($set->digital_participations ?? 0) > 0
            && (int) ($set->physical_participations ?? 0) === 0;
    }

    /**
     * Presupuesto de envío a imprenta (diseño ya elaborado en PARTILOT por defecto).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculateForSet(Set $set, PrintConfiguration $cfg, array $input, bool $chargeDesignFee = false): array
    {
        if ($this->isDigitalOnlySet($set)) {
            return $this->calculateForDigitalSet($set, $cfg, $input, $chargeDesignFee);
        }

        $totalParticipations = (int) ($set->total_participations ?? 0);
        $perBook = max(1, (int) ($input['participations_per_book'] ?? 50));
        $books = (int) ceil($totalParticipations / $perBook);
        $backMode = ($input['back_mode'] ?? 'bw') === 'color' ? 'color' : 'bw';

        $priceDesign = (float) ($cfg->price_design ?? 0);
        $priceParticipation = (float) ($cfg->price_participation ?? 0);
        $priceBack = $backMode === 'color'
            ? (float) ($cfg->price_back_color ?? 0)
            : (float) ($cfg->price_back_bw ?? 0);
        $pricePerBook = $this->pricePerBook($cfg, $perBook);

        $designCost = $chargeDesignFee ? $priceDesign : 0.0;
        $participationCost = $totalParticipations * $priceParticipation;
        $backCost = $totalParticipations * $priceBack;
        $booksCost = $books * $pricePerBook;
        $total = $designCost + $participationCost + $backCost + $booksCost;

        return [
            'print_configuration_id' => (int) $cfg->id,
            'print_configuration_name' => $cfg->displayName(),
            'total_participations' => $totalParticipations,
            'print_size' => $input['print_size'] ?? 'custom',
            'participations_per_book' => $perBook,
            'books' => $books,
            'back_mode' => $backMode,
            'design_fee_waived' => ! $chargeDesignFee,
            'charge_design_fee' => $chargeDesignFee,
            'unit_prices' => [
                'design' => $priceDesign,
                'participation' => $priceParticipation,
                'back' => $priceBack,
                'book' => $pricePerBook,
            ],
            'subtotal' => [
                'design' => $designCost,
                'participation' => $participationCost,
                'back' => $backCost,
                'book' => $booksCost,
            ],
            'total' => round($total, 2),
        ];
    }

    /**
     * Presupuesto flujo externo PARTILOT (incluye tarifa de diseño).
     *
     * @return array<string, mixed>
     */
    public function calculateForExternalInvitation(Set $set, PrintConfiguration $cfg, DesignExternalInvitation $invitation): array
    {
        $input = [
            'participations_per_book' => (int) ($invitation->participations_per_book ?? 50),
            'back_mode' => $invitation->back_mode === 'color' ? 'color' : 'bw',
            'print_size' => $invitation->print_size ?? 'custom',
        ];

        return $this->calculateForSet($set, $cfg, $input, chargeDesignFee: true);
    }

    /**
     * Sets digitales: solo tarifa de diseño (no hay impresión física).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculateForDigitalSet(Set $set, PrintConfiguration $cfg, array $input, bool $chargeDesignFee): array
    {
        $priceDesign = (float) ($cfg->price_design ?? 0);
        $designCost = $chargeDesignFee ? $priceDesign : 0.0;

        return [
            'print_configuration_id' => (int) $cfg->id,
            'print_configuration_name' => $cfg->displayName(),
            'total_participations' => (int) ($set->total_participations ?? 0),
            'print_size' => $input['print_size'] ?? 'custom',
            'participations_per_book' => max(1, (int) ($input['participations_per_book'] ?? 50)),
            'books' => 0,
            'back_mode' => ($input['back_mode'] ?? 'bw') === 'color' ? 'color' : 'bw',
            'is_digital_set' => true,
            'design_fee_waived' => ! $chargeDesignFee,
            'charge_design_fee' => $chargeDesignFee,
            'unit_prices' => [
                'design' => $priceDesign,
                'participation' => 0.0,
                'back' => 0.0,
                'book' => 0.0,
            ],
            'subtotal' => [
                'design' => $designCost,
                'participation' => 0.0,
                'back' => 0.0,
                'book' => 0.0,
            ],
            'total' => round($designCost, 2),
        ];
    }

    private function pricePerBook(PrintConfiguration $cfg, int $perBook): float
    {
        if ($perBook <= 25) {
            return (float) ($cfg->price_taco_25 ?? 0);
        }
        if ($perBook >= 100) {
            return (float) ($cfg->price_taco_100 ?? 0);
        }

        return (float) ($cfg->price_taco_50 ?? 0);
    }
}
