<?php

namespace App\Http\Controllers;

use App\Mail\EntityResponsibleManagerConfirmedMail;
use App\Models\Manager;
use App\Models\PendingEntityManagerInvitation;
use App\Models\User;
use App\Rules\SpanishDocument;
use App\Rules\ValidCalendarDate;
use App\Services\CommunicationEmailService;
use App\Services\RoleLegalAcceptanceService;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntityManagerPendingInvitationController extends Controller
{
    public function showRegister(string $token)
    {
        $pending = PendingEntityManagerInvitation::findByToken($token);
        if (! $pending) {
            return view('auth.entity-manager-register-expired');
        }

        $email = PendingEntityManagerInvitation::normalizeEmail((string) $pending->email);
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return view('auth.entity-manager-register-exists', [
                'pending' => $pending->loadMissing('entity.administration'),
                'email' => $email,
            ]);
        }

        $pending->loadMissing('entity.administration');

        return view('auth.entity-manager-register', [
            'pending' => $pending,
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function storeRegister(Request $request, string $token, RoleLegalAcceptanceService $roleService)
    {
        $pending = PendingEntityManagerInvitation::findByToken($token);
        if (! $pending) {
            return view('auth.entity-manager-register-expired');
        }

        $email = PendingEntityManagerInvitation::normalizeEmail((string) $pending->email);
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return view('auth.entity-manager-register-exists', [
                'pending' => $pending->loadMissing('entity.administration'),
                'email' => $email,
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['required', 'string', 'max:20', new SpanishDocument],
            'phone' => 'nullable|string|max:20',
            'birthday' => ValidCalendarDate::birthday(),
            'password' => PasswordRules::registration(),
            'role_terms' => 'accepted',
        ], array_merge(PasswordRules::messages(), [
            'role_terms.accepted' => 'Debe aceptar las responsabilidades del rol para continuar.',
            'nif_cif.required' => 'Indique su NIF/CIF.',
        ]));

        $pending->loadMissing('entity.administration');
        $entity = $pending->entity;

        $manager = DB::transaction(function () use ($request, $pending, $email, $entity) {
            $snapshot = $pending->only([
                'entity_id',
                'is_primary',
                'permission_sellers',
                'permission_design',
                'permission_statistics',
                'permission_payments',
            ]);

            $pending->delete();

            $user = User::create([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'last_name2' => $request->input('last_name2'),
                'nif_cif' => $request->input('nif_cif'),
                'phone' => $request->input('phone') ?: null,
                'birthday' => $request->input('birthday') ?: null,
                'email' => $email,
                'password' => $request->input('password'),
                'must_change_password' => false,
                'role' => User::ROLE_ENTITY,
                'status' => true,
            ]);

            if ($snapshot['is_primary']) {
                Manager::query()->where('entity_id', $snapshot['entity_id'])->update(['is_primary' => false]);
            }

            return Manager::create([
                'user_id' => $user->id,
                'entity_id' => $snapshot['entity_id'],
                'is_primary' => (bool) $snapshot['is_primary'],
                'pending_primary' => false,
                'permission_sellers' => (bool) $snapshot['permission_sellers'],
                'permission_design' => (bool) $snapshot['permission_design'],
                'permission_statistics' => (bool) $snapshot['permission_statistics'],
                'permission_payments' => (bool) $snapshot['permission_payments'],
                'confirmation_token' => Str::random(64),
                'confirmation_sent_at' => now(),
                'requires_password_setup' => false,
                'user_created_for_invitation' => false,
                'status' => null,
            ]);
        });

        $result = $roleService->finalizeManagerActivation($manager, $request, $manager->user);
        if (! $result['success']) {
            return view('entities.manager-confirmation-error', [
                'message' => $result['message'],
            ]);
        }

        $manager->refresh()->load('entity');

        if ($manager->is_primary && $manager->entity) {
            $this->notifyEntityResponsibleManagerConfirmed($manager->entity, $manager);
        }

        return view('entities.manager-confirmation-success', [
            'message' => '¡Cuenta creada e invitación aceptada! Ya puede iniciar sesión en el panel con su email y la contraseña elegida.',
            'type' => 'accept',
            'manager' => $manager,
        ]);
    }

    public function reject(string $token)
    {
        $pending = PendingEntityManagerInvitation::findByToken($token);
        if (! $pending) {
            return view('entities.manager-confirmation-error', [
                'message' => 'El enlace de invitación no es válido o ya ha sido utilizado.',
            ]);
        }

        $pending->delete();

        return view('entities.manager-confirmation-success', [
            'message' => 'Invitación rechazada. No se creará ninguna cuenta ni vínculo como gestor de esta entidad.',
            'type' => 'reject',
            'manager' => null,
        ]);
    }

    private function notifyEntityResponsibleManagerConfirmed(\App\Models\Entity $entity, Manager $newPrimary): void
    {
        try {
            $newPrimary->loadMissing('user');
            $entityContactUser = User::where('panel_account_type', 'entity')
                ->where('panel_account_id', $entity->id)
                ->first();

            if ($entityContactUser && ! empty($entityContactUser->email) && $newPrimary->user) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $entityContactUser->email,
                    recipientRole: 'entidad',
                    recipientUser: $entityContactUser,
                    messageType: 'entity_responsible_manager_confirmed',
                    templateKey: null,
                    mailClass: EntityResponsibleManagerConfirmedMail::class,
                    mailPayload: ['entity_id' => $entity->id, 'responsible_manager_user_id' => $newPrimary->user->id],
                    context: ['entity_id' => $entity->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando confirmación de gestor responsable: '.$e->getMessage());
        }
    }
}
