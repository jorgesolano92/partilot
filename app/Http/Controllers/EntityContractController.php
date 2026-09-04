<?php

namespace App\Http\Controllers;

use App\Models\Entity;
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

    public function sign(string $token)
    {
        $entity = $this->contractService->findPendingByToken($token);

        if (! $entity) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue firmado.',
            ]);
        }

        $viewData = $this->contractService->buildViewData($entity);

        return view('contracts.entity-authorized-signer-accept', [
            'token' => $token,
            'entity' => $entity,
            'viewData' => $viewData,
        ]);
    }

    public function storeSign(Request $request, string $token)
    {
        $entity = $this->contractService->findPendingByToken($token);

        if (! $entity) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Enlace no válido',
                'message' => 'El enlace de firma no es válido o el contrato ya fue firmado.',
            ]);
        }

        $rules = [
            'signer_name' => 'required|string|max:255',
            'signer_nif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'accept_contract' => 'accepted',
        ];
        $messages = [
            'accept_contract.accepted' => 'Debe aceptar el contrato marco para continuar.',
        ];

        if ($entity->isNaturalOrganizer()) {
            $rules['accept_organizer_declaration'] = 'accepted';
            $messages['accept_organizer_declaration.accepted'] = 'Debe confirmar la declaración de Organizador para continuar.';
        }

        $data = $request->validate($rules, $messages);

        try {
            $this->contractService->signContractByAuthorizedSigner(
                $entity,
                $data['signer_name'],
                $data['signer_nif'],
                $request
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['signer_name' => $e->getMessage()])->withInput();
        }

        return view('contracts.administration-result', [
            'success' => true,
            'title' => 'Contrato firmado',
            'message' => 'El contrato marco ha sido firmado correctamente. Se ha notificado al gestor responsable para que acepte su cargo. Hemos enviado una copia en PDF al correo de la entidad.',
        ]);
    }

    /**
     * Compatibilidad: el enlace antiguo del gestor ya no firma el contrato.
     * Si el contrato está pendiente, informa; si está firmado, redirige a aceptar el rol.
     */
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

        return view('contracts.administration-result', [
            'success' => false,
            'title' => 'Contrato pendiente de firma',
            'message' => 'El contrato marco debe firmarlo primero el representante autorizado de la entidad. Cuando esté firmado, podrá aceptar el cargo de gestor responsable desde el correo de invitación.',
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

        if ($manager->entity->contract_status === Entity::CONTRACT_PENDING) {
            return view('contracts.administration-result', [
                'success' => false,
                'title' => 'Contrato pendiente de firma',
                'message' => 'El representante autorizado aún no ha firmado el contrato marco. No es posible aceptar el cargo todavía.',
            ]);
        }

        $request->validate([
            'role_terms' => 'accepted',
        ], [
            'role_terms.accepted' => 'Debe aceptar las responsabilidades del cargo para continuar.',
        ]);

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
            'message' => '¡Cargo aceptado! Ya puede iniciar sesión en PARTILOT.',
            'type' => 'accept',
            'manager' => $manager,
        ]);
    }
}
