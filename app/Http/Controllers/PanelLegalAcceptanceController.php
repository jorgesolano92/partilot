<?php

namespace App\Http\Controllers;

use App\Services\PanelLegalAcceptanceService;
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
            return redirect()->route('dashboard');
        }

        if (! $this->panelLegalAcceptance->userMustAcceptBeforeAccess($user)) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'accept_legal' => 'accepted',
        ], [
            'accept_legal.accepted' => 'Debe aceptar las condiciones legales para continuar.',
        ]);

        $this->panelLegalAcceptance->recordAcceptance($user, $request);

        return redirect()->back()->with('success', 'Condiciones legales aceptadas correctamente.');
    }
}
