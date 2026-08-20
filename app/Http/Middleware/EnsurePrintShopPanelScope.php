<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrintShopPanelScope
{
    /**
     * Usuarios con perfil imprenta solo pueden operar en su módulo dedicado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isPrintShop()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === 'dashboard') {
            return redirect()->route('print-shop.index');
        }

        $allowedPrefixes = [
            'print-shop',
            'cuenta',
            'design/pdf',
            // Assets del editor al diseñar desde una orden de imprenta
            'design-editor',
        ];

        foreach ($allowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        if ($request->routeIs('logout', 'panel-legal.submit') || $path === 'logout') {
            return $next($request);
        }

        if ($path === 'panel/aceptacion-legal') {
            return $next($request);
        }

        abort(403, 'Tu perfil de imprenta solo puede acceder al módulo de órdenes de impresión.');
    }
}
