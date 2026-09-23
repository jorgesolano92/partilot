<?php

namespace App\Http\Middleware;

use App\Models\Administration;
use App\Models\Entity;
use App\Services\AdministrationSessionInvalidationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsa sesiones cuando la administración/entidad deja de estar activa (INC-006).
 */
class EnsureAssociatedPanelAccountActive
{
    public function __construct(
        private AdministrationSessionInvalidationService $sessionInvalidation
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->routeIs(['logout', 'login'])) {
            return $next($request);
        }

        if ($this->sessionInvalidation->shouldForceLogout((int) $user->id)) {
            return $this->logoutToLogin($request, 'Tu sesión se ha cerrado porque el estado de la cuenta asociada ha cambiado.');
        }

        if ($user->isPanelAccount() && $user->panel_account_type === 'administration') {
            $administration = Administration::query()->find($user->panel_account_id);
            if (! $administration) {
                return $this->logoutToLogin(
                    $request,
                    'Las credenciales proporcionadas no coinciden con nuestros registros.'
                );
            }

            // INC-006: Pendiente puede completar primer acceso; Bloqueado/Inactivo no.
            if ($administration->isPending() || $administration->isActive()) {
                return $next($request);
            }

            return $this->logoutToLogin(
                $request,
                $this->loginDeniedMessageForAdministration($administration)
            );
        }

        if ($user->isPanelAccount() && $user->panel_account_type === 'entity') {
            $entity = Entity::query()->find($user->panel_account_id);
            if (! $entity || (int) $entity->status !== 1) {
                return $this->logoutToLogin(
                    $request,
                    'Las credenciales proporcionadas no coinciden con nuestros registros.'
                );
            }
        }

        if ($user->isAdministration() && ! $user->isPanelAccount()) {
            $hasActive = $user->managers()
                ->where('status', 1)
                ->whereHas('administration', fn ($q) => $q->where('status', Administration::STATUS_ACTIVE))
                ->exists();
            if (! $hasActive) {
                $blocked = $user->managers()
                    ->whereHas('administration', fn ($q) => $q->where('status', Administration::STATUS_BLOCKED))
                    ->exists();

                return $this->logoutToLogin(
                    $request,
                    $blocked
                        ? 'Tu cuenta de Partilot ha sido bloqueada. Para obtener más información, ponte en contacto con Partilot'
                        : 'Las credenciales proporcionadas no coinciden con nuestros registros.'
                );
            }
        }

        return $next($request);
    }

    private function loginDeniedMessageForAdministration(?Administration $administration): string
    {
        if ($administration && $administration->isBlocked()) {
            return 'Tu cuenta de Partilot ha sido bloqueada. Para obtener más información, ponte en contacto con Partilot';
        }

        // Pendiente / Inactivo / inexistente: respuesta genérica (INC-006).
        return 'Las credenciales proporcionadas no coinciden con nuestros registros.';
    }

    private function logoutToLogin(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
