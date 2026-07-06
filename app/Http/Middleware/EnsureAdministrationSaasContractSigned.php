<?php

namespace App\Http\Middleware;

use App\Services\AdministrationContractService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrationSaasContractSigned
{
    public function __construct(
        private AdministrationContractService $contractService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($request->routeIs([
            'administration-contract.pending',
            'administration-contract.resend',
            'logout',
            'administration-contract.sign',
            'administration-contract.sign.submit',
        ])) {
            return $next($request);
        }

        if (! $this->contractService->userMustSignBeforeAccess($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Debe firmar el contrato SaaS de la administración antes de continuar.');
        }

        return redirect()->route('administration-contract.pending');
    }
}
