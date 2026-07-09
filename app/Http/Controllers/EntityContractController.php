<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Manager;
use App\Services\EntityContractService;
use App\Services\RoleLegalAcceptanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntityContractController extends Controller
{
    public function __construct(
        private EntityContractService $contractService,
        private RoleLegalAcceptanceService $roleLegalAcceptance,
    ) {}

    public function pending(Request $request)
    {
        $user = $request->user();
        $entity = $this->contractService->firstPendingEntityForUser($user);

        if (! $entity) {
            return redirect()->route('dashboard');
        }

        $manager = $this->contractService->primaryPendingManagerForUser($user, $entity);

        return view('contracts.entity-pending', [
            'entity' => $entity,
            'manager' => $manager,
            'waitingForManager' => $this->contractService->userIsWaitingForPrimaryManager($user),
        ]);
    }

    public function preview(Entity $entity)
    {
        Entity::forUser(auth()->user())->findOrFail($entity->id);

        $binary = $this->contractService->previewPdfBinary($entity);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-marco-entidad-preview-'.$entity->id.'.pdf"',
        ]);
    }

    public function download(Entity $entity)
    {
        Entity::forUser(auth()->user())->findOrFail($entity->id);

        if (! $entity->contract_pdf_path || ! Storage::disk('local')->exists($entity->contract_pdf_path)) {
            abort(404, 'No hay contrato firmado disponible.');
        }

        return Storage::disk('local')->download(
            $entity->contract_pdf_path,
            'contrato-marco-'.($entity->contract_reference ?: $entity->id).'.pdf'
        );
    }

    public function acceptPrimaryManager(Request $request, string $token)
    {
        $manager = $this->roleLegalAcceptance->findManagerByToken($token);

        if (! $manager || ! $manager->user || ! $manager->entity) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        if (! $manager->is_primary && ! $manager->pending_primary) {
            return redirect()->route('entity-managers.confirm-accept', ['token' => $token]);
        }

        $entity = $manager->entity;
        if ($entity->contract_status === Entity::CONTRACT_SIGNED) {
            return redirect()->route('entity-managers.confirm-accept', ['token' => $token]);
        }

        $invitation = $this->roleLegalAcceptance->buildWebManagerPayload($manager);
        $viewData = $this->contractService->buildViewData($entity, $manager);

        return view('contracts.entity-responsible-accept', [
            'token' => $token,
            'manager' => $manager,
            'entity' => $entity,
            'invitation' => $invitation,
            'viewData' => $viewData,
        ]);
    }

    public function storePrimaryManagerAcceptance(Request $request, string $token)
    {
        $manager = $this->roleLegalAcceptance->findManagerByToken($token);

        if (! $manager || ! $manager->user || ! $manager->entity) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        if ($request->input('action') === 'reject') {
            $this->roleLegalAcceptance->respondManagerInvitation($manager, 'reject', $request);

            return view('entities.manager-confirmation-success', [
                'message' => 'Solicitud rechazada. La administración ha sido notificada.',
                'type' => 'reject',
                'manager' => null,
            ]);
        }

        $data = $request->validate([
            'signer_name' => 'required|string|max:255',
            'signer_nif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'accept_contract' => 'accepted',
            'role_terms' => 'accepted',
        ], [
            'accept_contract.accepted' => 'Debe aceptar el contrato marco en nombre de la entidad.',
            'role_terms.accepted' => 'Debe aceptar las responsabilidades del cargo para continuar.',
        ]);

        try {
            $this->contractService->signContractForPrimaryManager(
                $manager->entity,
                $manager,
                $manager->user,
                $data['signer_name'],
                $data['signer_nif'],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'No se pudo completar',
                'message' => $e->getMessage(),
            ]);
        }

        $result = $this->roleLegalAcceptance->finalizeManagerActivation($manager, $request, $manager->user);
        if (! $result['success']) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Error al activar el cargo',
                'message' => $result['message'],
            ]);
        }

        $manager->refresh()->load('entity');

        return view('entities.manager-confirmation-success', [
            'message' => '¡Cargo aceptado y contrato marco firmado! Hemos enviado una copia en PDF a su correo. Ya puede iniciar sesión en PARTILOT.',
            'type' => 'accept',
            'manager' => $manager,
        ]);
    }
}
