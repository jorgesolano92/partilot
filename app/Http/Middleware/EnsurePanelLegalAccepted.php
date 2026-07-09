<?php

namespace App\Http\Middleware;

use App\Services\PanelLegalAcceptanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelLegalAccepted
{
    public function __construct(
        private PanelLegalAcceptanceService $panelLegalAcceptance
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->routeIs([
            'panel-legal.submit',
            'logout',
            'administration-contract.pending',
            'administration-contract.resend',
            'administration-contract.sign',
            'administration-contract.sign.submit',
            'entity-contract.pending',
            'entity-contract.accept-primary',
            'entity-contract.accept-primary.store',
        ])) {
            return $next($request);
        }

        if ($this->panelLegalAcceptance->userMustAcceptBeforeAccess($user) && $request->expectsJson()) {
            abort(403, 'Debe aceptar las condiciones legales del panel antes de continuar.');
        }

        return $next($request);
    }
}
