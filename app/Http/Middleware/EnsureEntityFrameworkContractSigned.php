<?php

namespace App\Http\Middleware;

use App\Services\EntityContractService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEntityFrameworkContractSigned
{
    public function __construct(
        private EntityContractService $contractService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->routeIs([
            'entity-contract.pending',
            'entity-contract.accept-primary',
            'entity-contract.accept-primary.store',
            'entity-contract.sign',
            'entity-contract.sign.store',
            'entity-managers.confirm-accept',
            'entity-managers.confirm-respond',
            'entity-managers.confirm-reject',
            'entity-managers.pending.register',
            'entity-managers.pending.register.store',
            'entity-managers.pending.reject',
            'logout',
            'panel-legal.submit',
            'administration-contract.pending',
            'administration-contract.resend',
            'administration-contract.sign',
            'administration-contract.sign.submit',
        ])) {
            return $next($request);
        }

        if (! $this->contractService->userMustSignBeforeAccess($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Debe firmarse el contrato marco de la entidad antes de continuar.');
        }

        return redirect()->route('entity-contract.pending');
    }
}
