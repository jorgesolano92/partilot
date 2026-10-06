<?php

namespace App\Http\Controllers;

use App\Services\PanelLegalAcceptanceService;
use App\Support\PanelAuthContext;
use Illuminate\Http\Request;

class PanelLegalAcceptanceController extends Controller
{
    public function __construct(
        private PanelLegalAcceptanceService $panelLegalAcceptance
    ) {}

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return PanelAuthContext::redirectHome($user);
        }

        if (! $this->panelLegalAcceptance->userMustAcceptBeforeAccess($user)) {
            PanelAuthContext::clearIntended($request);

            return PanelAuthContext::redirectHome($user);
        }

        $request->validate([
            'accept_legal' => 'accepted',
        ], [
            'accept_legal.accepted' => 'Debe aceptar las condiciones legales para continuar.',
        ]);

        $this->panelLegalAcceptance->recordAcceptance($user, $request);
        PanelAuthContext::clearIntended($request);

        return PanelAuthContext::redirectHome($user, 'success', 'Condiciones legales aceptadas correctamente.');
    }
}
