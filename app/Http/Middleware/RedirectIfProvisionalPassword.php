<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a sustituir la contraseña provisional antes de usar el panel.
 */
class RedirectIfProvisionalPassword
{
    /**
     * Rutas que el resto de middlewares del grupo usan como destino (contratos, aceptación legal):
     * deben seguir accesibles para no provocar bucles de redirección.
     */
    private const ALLOWED_ROUTES = [
        'provisional-password.*',
        'entity-manager.legacy-password.*',
        'logout',
        'administration-contract.*',
        'entity-contract.*',
        'entity-managers.*',
        'panel-legal.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->mustChangeProvisionalPassword()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Debe cambiar la contraseña provisional antes de continuar.');
        }

        return redirect()->route('provisional-password.show');
    }
}
