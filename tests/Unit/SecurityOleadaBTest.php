<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\PanelPassword;
use App\Support\PasswordRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityOleadaBTest extends TestCase
{
    #[Test]
    public function panel_password_generates_non_legacy_value(): void
    {
        $password = PanelPassword::generate();

        $this->assertGreaterThanOrEqual(16, strlen($password));
        $this->assertNotSame(User::ENTITY_MANAGER_LEGACY_DEFAULT_PASSWORD, $password);
    }

    #[Test]
    public function user_email_is_normalized_on_save(): void
    {
        $user = new User;
        $user->name = 'Test';
        $user->email = '  Test.User@Example.COM  ';
        $user->password = PanelPassword::generate();
        $user->role = User::ROLE_CLIENT;
        $user->status = true;

        $user->save();

        $this->assertSame('test.user@example.com', $user->fresh()->email);

        $user->delete();
    }

    #[Test]
    public function registration_password_rules_require_at_least_eight_chars(): void
    {
        $validator = validator(
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => PasswordRules::registration()]
        );

        $this->assertTrue($validator->fails());
    }
}
