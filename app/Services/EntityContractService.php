<?php

namespace App\Services;

use App\Mail\EntityContractSignedMail;
use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
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

        return $entity->fresh(['administration']);
    }

    public function signContractForPrimaryManager(
        Entity $entity,
        Manager $manager,
        User $user,
        string $signerName,
        string $signerNif,
        Request $request
    ): Entity {
        if ($entity->contract_status === Entity::CONTRACT_SIGNED) {
            throw new \InvalidArgumentException('El contrato marco de la entidad ya está firmado.');
        }

        if (! $manager->is_primary && ! $manager->pending_primary) {
            throw new \InvalidArgumentException('Solo el gestor responsable puede firmar el contrato marco.');
        }

        if ((int) $manager->user_id !== (int) $user->id) {
            throw new \InvalidArgumentException('El usuario no coincide con el gestor responsable designado.');
        }

        $entity->loadMissing('administration');
        $signedAt = now();
        $signature = [
            'signer_name' => $signerName,
            'signer_nif' => $signerNif,
            'signed_at' => $signedAt,
            'signer_ip' => $request->ip(),
        ];

        $pdfBinary = $this->documents->renderPdfBinary(
            'contracts.entity_framework_pdf',
            $this->buildViewData($entity, $manager, $signature)
        );

        $pdfPath = $this->documents->storeBinary(
            'contracts/entities/'.$entity->id.'/marco-'.$signedAt->format('YmdHis').'.pdf',
            $pdfBinary
        );

        $entity->update([
            'contract_status' => Entity::CONTRACT_SIGNED,
            'contract_signed_at' => $signedAt,
            'contract_token' => null,
            'contract_signed_by_user_id' => $user->id,
            'contract_signer_name' => $signerName,
            'contract_signer_nif' => $signerNif,
            'contract_pdf_path' => $pdfPath,
        ]);

        $entity = $entity->fresh(['administration']);
        $textHash = $this->textHash($entity, $manager);

        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_CONTRATO_MARCO_ENTIDAD,
            request: $request,
            user: $user,
            version: self::VERSION,
            textHash: $textHash,
            entityId: (int) $entity->id,
            administrationId: $entity->administration_id ? (int) $entity->administration_id : null,
            context: [
                'contract_reference' => $entity->contract_reference,
                'signer_name' => $signerName,
                'signer_nif' => $signerNif,
                'signer_ip' => $request->ip(),
                'manager_id' => $manager->id,
                'via' => 'gestor_responsable',
            ],
        );

        $recipientEmail = trim((string) ($manager->user?->email ?: $entity->email));
        if ($recipientEmail !== '') {
            try {
                Mail::to($recipientEmail)->send(
                    new EntityContractSignedMail($entity->fresh(['administration']), $pdfPath)
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando copia contrato marco entidad: '.$e->getMessage());
            }
        }

        return $entity;
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
        if ($signerName === '' && $manager?->user) {
            $signerName = trim((string) ($manager->user->full_name ?? $manager->user->name ?? ''));
        }

        $signerNif = trim((string) ($signature['signer_nif'] ?? ''));
        if ($signerNif === '' && $manager?->user) {
            $signerNif = trim((string) ($manager->user->nif_cif ?? ''));
        }

        $signerEmail = trim((string) ($manager?->user?->email ?? ''));
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
        return $this->pendingEntityIdsForUser($user) !== [];
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
