<?php

namespace App\Policies;

use App\Models\PrintOrder;
use App\Models\User;

class PrintOrderPolicy
{
    public function view(User $user, PrintOrder $printOrder): bool
    {
        return $this->canAccess($user, $printOrder);
    }

    public function update(User $user, PrintOrder $printOrder): bool
    {
        return $this->canAccess($user, $printOrder);
    }

    public function design(User $user, PrintOrder $printOrder): bool
    {
        return $this->canAccess($user, $printOrder);
    }

    public function exportDesignPdf(User $user, PrintOrder $printOrder): bool
    {
        return $this->canAccess($user, $printOrder);
    }

    private function canAccess(User $user, PrintOrder $printOrder): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isPrintShop()) {
            $panelShopId = (int) ($user->panel_account_id ?? 0);
            if ($panelShopId <= 0) {
                return false;
            }

            return (int) $printOrder->print_configuration_id === $panelShopId;
        }

        if ($printOrder->entity_id && $user->canAccessEntity((int) $printOrder->entity_id)) {
            return true;
        }

        return false;
    }
}
