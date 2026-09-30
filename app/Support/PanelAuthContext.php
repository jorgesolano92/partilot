<?php

namespace App\Support;

use App\Models\User;
use App\Services\AdministrationSessionInvalidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Destino de panel y cambio de sesión seguros (R3-INC-001).
 * Evita heredar sesión/intended de otro rol (p. ej. superadmin) durante activación.
 */
class PanelAuthContext
{
    public static function homeUrlFor(?User $user): string
    {
        if (! $user) {
            return route('login');
        }

        if ($user->isPrintShop()) {
            return route('print-shop.index');
        }

        return route('dashboard');
    }

    public static function redirectHome(?User $user, ?string $flashKey = null, ?string $flashMessage = null): RedirectResponse
    {
        $redirect = redirect()->to(self::homeUrlFor($user));

        if ($flashKey && $flashMessage !== null) {
            $redirect->with($flashKey, $flashMessage);
        }

        return $redirect;
    }

    public static function clearIntended(Request $request): void
    {
        $request->session()->forget('url.intended');
    }

    /**
     * Si hay sesión de otro usuario, cierra sesión de forma explícita antes de continuar.
     */
    public static function forceLogoutIfNotUser(?User $expected, Request $request): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if ($expected && (int) Auth::id() === (int) $expected->id) {
            return false;
        }

        self::logoutFully($request);

        return true;
    }

    public static function logoutFully(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Sustituye la sesión actual por la del usuario objetivo (activación / Ir al panel).
     */
    public static function switchToUser(User $user, Request $request): void
    {
        if (Auth::check() && (int) Auth::id() === (int) $user->id) {
            self::clearIntended($request);

            return;
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user);
        $request->session()->regenerate();
        self::clearIntended($request);

        app(AdministrationSessionInvalidationService::class)
            ->markSessionValidated((int) $user->id);
    }
}
