<?php

namespace App\Services;

use App\Mail\EntityContractSignedMail;
use App\Mail\EntityContractSignRequestMail;
use App\Mail\EntityManagerInvitationMail;
use App\Mail\EntityManagerPreregisterInviteMail;
use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
use App\Models\PendingEntityManagerInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EntityContractService
{
    public const VERSION = 'marco_v1';

    public const VERSION_LABEL = '1.0';

    private const AUTO_PENDING_LABEL = '[Generado automáticamente por el sistema]';

    public function __construct(
        private readonly ContractDocumentService $documents,
        private readonly LegalAcceptanceService $legalAcceptance,
    ) {}

    public function generateReference(Entity $entity): string
    {
        return sprintf('ENT-%s-%05d', now()->format('Y'), (int) $entity->id);
    }

    public function initializeForNewEntity(Entity $entity): Entity
    {
        $entity->update([
            'contract_status' => Entity::CONTRACT_PENDING,
            'contract_reference' => $this->generateReference($entity),
            'contract_version' => self::VERSION,
            'status' => 0,
        ]);

        $entity = $entity->fresh(['administration']);

        try {
            $this->sendSigningInvitation($entity);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo enviar contrato marco al crear entidad '.$entity->id.': '.$e->getMessage());
        }

        return $entity->fresh(['administration']);
    }

    public function sendSigningInvitation(Entity $entity): Entity
    {
        if ($entity->contract_status === Entity::CONTRACT_SIGNED) {
            throw new \InvalidArgumentException('El contrato marco de la entidad ya está firmado.');
        }

        $recipientEmail = trim((string) ($entity->email ?? ''));
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La entidad no tiene un email de contacto válido para enviar el contrato.');
        }

        if (! $entity->contract_reference) {
            $entity->contract_reference = $this->generateReference($entity);
        }

        $token = Str::random(64);
        $entity->update([
            'contract_status' => Entity::CONTRACT_PENDING,
            'contract_token' => $token,
            'contract_sent_at' => now(),
            'contract_version' => self::VERSION,
            'contract_reference' => $entity->contract_reference,
        ]);

        $entity = $entity->fresh(['administration']);

        try {
            Mail::to($recipientEmail)->send(new EntityContractSignRequestMail($entity, $token));
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando solicitud de firma contrato entidad: '.$e->getMessage());
            throw new \RuntimeException('No se pudo enviar el email del contrato marco.');
        }

        return $entity;
    }

    public function findPendingByToken(string $token): ?Entity
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return Entity::query()
            ->where('contract_token', $token)
            ->where('contract_status', Entity::CONTRACT_PENDING)
            ->with(['administration'])
            ->first();
    }

    public function signContractByAuthorizedSigner(
        Entity $entity,
        string $signerName,
        string $signerNif,
        Request $request
    ): Entity {
        if ($entity->contract_status === Entity::CONTRACT_SIGNED) {
            throw new \InvalidArgumentException('El contrato marco de la entidad ya está firmado.');
        }

        $expectedName = $entity->signerFullName();
        $expectedNif = strtoupper(trim((string) ($entity->signer_nif ?? '')));
        $providedName = trim($signerName);
        $providedNif = strtoupper(trim($signerNif));

        if ($expectedName !== '' && strcasecmp($expectedName, $providedName) !== 0) {
            throw new \InvalidArgumentException('El nombre del firmante no coincide con el registrado para esta entidad.');
        }

        if ($expectedNif !== '' && $expectedNif !== $providedNif) {
            throw new \InvalidArgumentException('El DNI/NIE del firmante no coincide con el registrado para esta entidad.');
        }

        $entity->loadMissing('administration');
        $signedAt = now();
        $signature = [
            'signer_name' => $providedName !== '' ? $providedName : $expectedName,
            'signer_nif' => $providedNif !== '' ? $providedNif : $expectedNif,
            'signed_at' => $signedAt,
            'signer_ip' => $request->ip(),
        ];

        $pdfBinary = $this->documents->renderPdfBinary(
            'contracts.entity_framework_pdf',
            $this->buildViewData($entity, null, $signature)
        );

        $pdfPath = $this->documents->storeBinary(
            'contracts/entities/'.$entity->id.'/marco-'.$signedAt->format('YmdHis').'.pdf',
            $pdfBinary
        );

        $entity->update([
            'contract_status' => Entity::CONTRACT_SIGNED,
            'contract_signed_at' => $signedAt,
            'contract_token' => null,
            'contract_signed_by_user_id' => null,
            'contract_signer_name' => $signature['signer_name'],
            'contract_signer_nif' => $signature['signer_nif'],
            'contract_pdf_path' => $pdfPath,
        ]);

        $entity = $entity->fresh(['administration']);
        $textHash = $this->textHash($entity);

        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_CONTRATO_MARCO_ENTIDAD,
            request: $request,
            user: null,
            version: self::VERSION,
            textHash: $textHash,
            entityId: (int) $entity->id,
            administrationId: $entity->administration_id ? (int) $entity->administration_id : null,
            context: [
                'contract_reference' => $entity->contract_reference,
                'signer_name' => $signature['signer_name'],
                'signer_nif' => $signature['signer_nif'],
                'signer_ip' => $request->ip(),
                'via' => 'firmante_autorizado',
            ],
        );

        $recipientEmail = trim((string) ($entity->email ?? ''));
        if ($recipientEmail !== '') {
            try {
                Mail::to($recipientEmail)->send(
                    new EntityContractSignedMail($entity->fresh(['administration']), $pdfPath)
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando copia contrato marco entidad: '.$e->getMessage());
            }
        }

        $this->notifyPrimaryManagerInvitations($entity);

        return $entity;
    }

    /**
     * Envía (o reenvía) la invitación de rol al gestor responsable / pending tras la firma.
     */
    public function notifyPrimaryManagerInvitations(Entity $entity): void
    {
        $managers = Manager::query()
            ->where('entity_id', $entity->id)
            ->where(function ($q) {
                $q->where('is_primary', true)->orWhere('pending_primary', true);
            })
            ->whereNotNull('confirmation_token')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('pending_primary', true);
            })
            ->with('user')
            ->get();

        foreach ($managers as $manager) {
            $user = $manager->user;
            if (! $user || trim((string) $user->email) === '') {
                continue;
            }

            if ($user->isPanelAccount()) {
                continue;
            }

            try {
                if (! $manager->confirmation_token) {
                    $manager->update([
                        'confirmation_token' => Str::random(64),
                        'confirmation_sent_at' => now(),
                    ]);
                } else {
                    $manager->update(['confirmation_sent_at' => now()]);
                }

                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $user->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $user,
                    messageType: 'entity_manager_invitation',
                    templateKey: null,
                    mailClass: EntityManagerInvitationMail::class,
                    mailPayload: [
                        'entity_id' => $entity->id,
                        'user_id' => $user->id,
                        'manager_id' => $manager->id,
                    ],
                    context: ['entity_id' => $entity->id],
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando invitación gestor tras firma entidad '.$entity->id.': '.$e->getMessage());
            }
        }

        $pendingInvites = PendingEntityManagerInvitation::query()
            ->where('entity_id', $entity->id)
            ->where('is_primary', true)
            ->get();

        foreach ($pendingInvites as $pending) {
            try {
                if (! $pending->confirmation_token) {
                    $pending->update([
                        'confirmation_token' => PendingEntityManagerInvitation::issueToken(),
                        'confirmation_sent_at' => now(),
                    ]);
                } else {
                    $pending->update(['confirmation_sent_at' => now()]);
                }

                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $pending->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: null,
                    messageType: 'entity_manager_preregister_invitation',
                    templateKey: null,
                    mailClass: EntityManagerPreregisterInviteMail::class,
                    mailPayload: [
                        'entity_id' => $entity->id,
                        'pending_invitation_id' => $pending->id,
                    ],
                    context: ['entity_id' => $entity->id],
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando pre-registro gestor tras firma entidad '.$entity->id.': '.$e->getMessage());
            }
        }
    }

    /**
     * @deprecated La firma la realiza el firmante autorizado vía token de entidad.
     */
    public function signContractForPrimaryManager(
        Entity $entity,
        Manager $manager,
        User $user,
        string $signerName,
        string $signerNif,
        Request $request
    ): Entity {
        return $this->signContractByAuthorizedSigner($entity, $signerName, $signerNif, $request);
    }

    /**
     * @param  array{signer_name?: string, signer_nif?: string, signed_at?: \Illuminate\Support\Carbon, signer_ip?: string|null}  $signature
     * @return array<string, mixed>
     */
    public function buildViewData(Entity $entity, ?Manager $manager = null, array $signature = []): array
    {
        $entity->loadMissing('administration');
        $administration = $entity->administration;
        $manager?->loadMissing('user');

        $signerName = trim((string) ($signature['signer_name'] ?? ''));
        if ($signerName === '') {
            $signerName = $entity->signerFullName();
        }
        if ($signerName === '' && $manager?->user) {
            $signerName = trim((string) ($manager->user->full_name ?? $manager->user->name ?? ''));
        }

        $signerNif = trim((string) ($signature['signer_nif'] ?? ''));
        if ($signerNif === '') {
            $signerNif = trim((string) ($entity->signer_nif ?? ''));
        }
        if ($signerNif === '' && $manager?->user) {
            $signerNif = trim((string) ($manager->user->nif_cif ?? ''));
        }

        $signerEmail = trim((string) ($entity->email ?? $manager?->user?->email ?? ''));
        $signedAt = $signature['signed_at'] ?? null;
        $isSigned = $signerName !== '' && $signedAt !== null;
        $pending = self::AUTO_PENDING_LABEL;

        $entityAddress = trim((string) ($entity->address ?? ''));
        $entityCity = trim((string) ($entity->city ?? ''));
        $entityProvince = trim((string) ($entity->province ?? ''));
        $entityPostalCode = trim((string) ($entity->postal_code ?? ''));
        $entityFullAddress = collect([
            $entityAddress,
            trim($entityPostalCode.' '.$entityCity),
            $entityProvince,
        ])->filter()->implode(', ') ?: '—';

        return [
            'entity' => $entity,
            'administration' => $administration,
            'contractReference' => $entity->contract_reference ?: $this->generateReference($entity),
            'contractVersion' => self::VERSION_LABEL,
            'entityName' => trim((string) ($entity->name ?? '')) ?: '—',
            'entityNif' => trim((string) ($entity->nif_cif ?? '')) ?: '—',
            'entityAddress' => $entityAddress !== '' ? $entityAddress : '—',
            'entityCity' => $entityCity !== '' ? $entityCity : '—',
            'entityProvince' => $entityProvince !== '' ? $entityProvince : '—',
            'entityPostalCode' => $entityPostalCode !== '' ? $entityPostalCode : '—',
            'entityFullAddress' => $entityFullAddress,
            'entityEmail' => trim((string) ($entity->email ?? '')) ?: '—',
            'entityPhone' => trim((string) ($entity->phone ?? '')) ?: '—',
            'administrationName' => trim((string) ($administration?->name ?? $administration?->society ?? '')) ?: '—',
            'administrationLinked' => $this->formatAdministrationLinked($administration),
            'signerName' => $signerName !== '' ? $signerName : '—',
            'signerNif' => $signerNif !== '' ? $signerNif : '—',
            'signerEmail' => $signerEmail !== '' ? $signerEmail : '—',
            'isSigned' => $isSigned,
            'acceptanceDate' => $isSigned ? $signedAt->format('d/m/Y H:i') : $pending,
            'acceptanceTimestamp' => $isSigned ? $signedAt->format('d/m/Y H:i:s') : $pending,
            'acceptanceIp' => $isSigned
                ? (trim((string) ($signature['signer_ip'] ?? '')) ?: '—')
                : $pending,
            'partilotSignerName' => 'Administrador Único',
            'partilotSignerRole' => 'Administrador Único',
            'entityWebStatus' => 'No activado. Activable previa solicitud a PARTILOT.',
            'managementFeePayer' => ($entity->entity_pays_management_fee ? 'Entidad' : 'Administración').' — configurable por set',
            'prizePaymentStatus' => 'No contratado. Requiere Acuerdo Específico de Mandato de Pago cuando el sorteo tenga premios.',
            'activationDate' => ($signedAt ?? now())->format('d/m/Y'),
            'signedAt' => $signedAt,
            'forPdf' => true,
        ];
    }

    protected function formatAdministrationLinked(?\App\Models\Administration $administration): string
    {
        if (! $administration) {
            return '—';
        }

        $name = trim((string) ($administration->name ?: $administration->society));
        $selae = trim((string) ($administration->admin_number ?? ''));
        $receiving = trim((string) ($administration->receiving ?? ''));

        $parts = array_filter([$name, $selae !== '' ? 'Cód. SELAE '.$selae : null]);
        $label = implode(' — ', $parts);

        if ($receiving !== '') {
            $label .= ($label !== '' ? ' · ' : '').'Receptor '.$receiving;
        }

        return $label !== '' ? $label : '—';
    }

    public function textHash(Entity $entity, ?Manager $manager = null): string
    {
        $html = $this->documents->renderHtml(
            'contracts.entity_framework_content',
            $this->buildViewData($entity, $manager)
        );

        return hash('sha256', $html);
    }

    public function previewPdfBinary(Entity $entity, ?Manager $manager = null): string
    {
        return $this->documents->renderPdfBinary(
            'contracts.entity_framework_pdf',
            $this->buildViewData($entity->loadMissing(['administration']), $manager)
        );
    }

    /** @return int[] */
    public function pendingEntityIdsForUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [];
        }

        $ids = [];

        if ($user->isEntityPanelAccount() && $user->panel_account_id) {
            $ids[] = (int) $user->panel_account_id;
        }

        $managerEntityIds = Manager::query()
            ->where('user_id', $user->id)
            ->whereNotNull('entity_id')
            ->pluck('entity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = array_values(array_unique(array_merge($ids, $managerEntityIds)));

        if ($ids === []) {
            return [];
        }

        return Entity::query()
            ->whereIn('id', $ids)
            ->where('contract_status', Entity::CONTRACT_PENDING)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function userMustSignBeforeAccess(User $user): bool
    {
        // El gestor ya no firma el contrato; el firmante autorizado lo hace por token público.
        // Solo la cuenta panel espera mientras el contrato esté pendiente.
        return $user->isEntityPanelAccount() && $this->userIsWaitingForPrimaryManager($user);
    }

    public function firstPendingEntityForUser(User $user): ?Entity
    {
        $ids = $this->pendingEntityIdsForUser($user);
        if ($ids === []) {
            return null;
        }

        return Entity::query()
            ->with(['administration', 'manager.user'])
            ->find($ids[0]);
    }

    public function primaryPendingManagerForUser(User $user, ?Entity $entity = null): ?Manager
    {
        $query = Manager::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('is_primary', true)->orWhere('pending_primary', true);
            })
            ->whereNotNull('confirmation_token')
            ->whereHas('entity', fn ($q) => $q->where('contract_status', Entity::CONTRACT_PENDING))
            ->with(['entity.administration', 'user']);

        if ($entity) {
            $query->where('entity_id', $entity->id);
        }

        return $query->orderByDesc('pending_primary')->orderBy('id')->first();
    }

    public function userIsWaitingForPrimaryManager(User $user): bool
    {
        if (! $user->isEntityPanelAccount()) {
            return false;
        }

        $entity = Entity::query()->find($user->panel_account_id);

        return $entity && $entity->contract_status === Entity::CONTRACT_PENDING;
    }

    public function issueContractToken(Entity $entity): string
    {
        $token = Str::random(64);
        $entity->update([
            'contract_token' => $token,
            'contract_sent_at' => now(),
        ]);

        return $token;
    }
}
