<?php

namespace App\Http\Controllers;

use App\Models\Administration;
use App\Models\User;
use App\Services\AdministrationContractService;
use App\Support\ActiveEntityContext;
use App\Support\PanelAuthContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class AdministrationContractController extends Controller
{
    public function __construct(
        private AdministrationContractService $contractService
    ) {}

    public function show(Request $request, string $token)
    {
        $administration = $this->findPendingByToken($token);
        if (! $administration) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue gestionado.',
            ]);
        }

        $panelUser = $this->panelUserForAdministration($administration);
        // R3-INC-001: si hay sesión de otro rol, forzar cierre antes de firmar.
        PanelAuthContext::forceLogoutIfNotUser($panelUser, $request);

        $administration->load(['manager.user']);

        return view('contracts.administration-sign', [
            'administration' => $administration,
            'token' => $token,
            'viewData' => $this->contractService->buildViewData($administration),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $administration = $this->findPendingByToken($token);
        if (! $administration) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue gestionado.',
            ]);
        }

        $panelUser = $this->panelUserForAdministration($administration);
        PanelAuthContext::forceLogoutIfNotUser($panelUser, $request);

        $data = $request->validate([
            'signer_name' => 'required|string|max:255',
            'signer_nif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'accept_terms' => 'accepted',
        ], [
            'accept_terms.accepted' => 'Debes aceptar el contrato para continuar.',
        ]);

        try {
            $administration = $this->contractService->signContractByToken(
                $token,
                $data['signer_name'],
                $data['signer_nif'],
                auth()->id(),
                $request->ip(),
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'No se pudo firmar',
                'message' => $e->getMessage(),
            ]);
        }

        $goToPanelUrl = URL::temporarySignedRoute(
            'administration-contract.go-to-panel',
            now()->addHours(2),
            ['administration' => $administration->id]
        );

        return view('contracts.administration-result', [
            'success' => true,
            'title' => 'Contrato firmado',
            'message' => 'El contrato SaaS ha quedado registrado correctamente. Hemos enviado una copia en PDF al correo de la administración. Ya puede acceder al panel de PARTILOT.',
            'goToPanelUrl' => $goToPanelUrl,
        ]);
    }

    /**
     * R3-INC-001: «Ir al panel» tras firma — home del rol de la administración, sin heredar otra sesión.
     */
    public function goToPanel(Request $request, Administration $administration)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        if ($administration->contract_status !== Administration::CONTRACT_SIGNED) {
            return redirect()->route('login')
                ->withErrors(['email' => 'El contrato aún no está firmado. Use el enlace de firma recibido por correo.']);
        }

        $panelUser = $this->panelUserForAdministration($administration);
        if (! $panelUser) {
            PanelAuthContext::forceLogoutIfNotUser(null, $request);

            return redirect()->route('login')
                ->with('success', 'Contrato firmado. Inicie sesión con el usuario de panel de la administración.');
        }

        PanelAuthContext::switchToUser($panelUser, $request);
        ActiveEntityContext::bootstrapSession($request, $panelUser);

        return PanelAuthContext::redirectHome($panelUser);
    }

    public function pending(Request $request)
    {
        $user = $request->user();
        $administration = $this->contractService->firstPendingAdministrationForUser($user);

        if (! $administration) {
            return PanelAuthContext::redirectHome($user);
        }

        return view('contracts.administration-pending', [
            'administration' => $administration,
        ]);
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        $administration = $this->contractService->firstPendingAdministrationForUser($user);

        if (! $administration) {
            return PanelAuthContext::redirectHome($user);
        }

        if (! $this->contractService->userCanAccessAdministrationContract($user, $administration)) {
            abort(403);
        }

        try {
            $this->contractService->sendContractInvitation($administration, (int) $user->id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Se ha reenviado el correo con el enlace de firma a '.$administration->email.'.');
    }

    public function preview(Administration $administration)
    {
        Administration::forUser(auth()->user())->findOrFail($administration->id);

        $binary = $this->contractService->previewPdfBinary($administration);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-saas-preview-'.$administration->id.'.pdf"',
        ]);
    }

    public function download(Administration $administration)
    {
        Administration::forUser(auth()->user())->findOrFail($administration->id);

        if (! $administration->contract_pdf_path || ! Storage::disk('local')->exists($administration->contract_pdf_path)) {
            abort(404, 'No hay contrato firmado disponible.');
        }

        return Storage::disk('local')->download(
            $administration->contract_pdf_path,
            'contrato-saas-'.($administration->contract_reference ?: $administration->id).'.pdf'
        );
    }

    protected function findPendingByToken(string $token): ?Administration
    {
        return Administration::query()
            ->where('contract_token', $token)
            ->where('contract_status', Administration::CONTRACT_PENDING)
            ->first();
    }

    protected function panelUserForAdministration(Administration $administration): ?User
    {
        return User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->first();
    }
}
