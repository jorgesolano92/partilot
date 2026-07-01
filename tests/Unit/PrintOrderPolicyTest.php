<?php

namespace Tests\Unit;

use App\Models\PrintOrder;
use App\Models\User;
use App\Policies\PrintOrderPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrintOrderPolicyTest extends TestCase
{
    #[Test]
    public function print_shop_user_cannot_view_other_shop_order(): void
    {
        $policy = new PrintOrderPolicy;

        $user = new User([
            'role' => User::ROLE_PRINT_SHOP,
            'panel_account_type' => 'print_shop',
            'panel_account_id' => 10,
        ]);

        $order = new PrintOrder([
            'print_configuration_id' => 20,
            'entity_id' => 1,
        ]);

        $this->assertFalse($policy->view($user, $order));
        $this->assertFalse($policy->exportDesignPdf($user, $order));
    }

    #[Test]
    public function print_shop_user_can_view_own_shop_order(): void
    {
        $policy = new PrintOrderPolicy;

        $user = new User([
            'role' => User::ROLE_PRINT_SHOP,
            'panel_account_type' => 'print_shop',
            'panel_account_id' => 20,
        ]);

        $order = new PrintOrder([
            'print_configuration_id' => 20,
            'entity_id' => 1,
        ]);

        $this->assertTrue($policy->view($user, $order));
    }
}
