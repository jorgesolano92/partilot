<?php

namespace App\Services;

use App\Mail\AdministrationContractMail;
use App\Mail\AdministrationContractSignedMail;
use App\Models\Administration;
use App\Models\LegalAcceptance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdministrationContractService
{
    public const VERSION = 'saas_v1';

    public const VERSION_LABEL = '1.0';

    public function __construct(
        private readonly ContractDocumentService $documents,
        private readonly LegalAcceptanceService $legalAcceptance,
    ) {}

    public function generateReference(Administration $administration): string
    {
        return sprintf('ADM-%s-%05d', now()->format('Y'), (int) $administration->id);
    }

    public function initializeForNewAdministration(Administration $administration): Administration
    {
        $administration->update([
            'contract_status' => Administration::CONTRACT_PENDING,
            'contract_reference' => $this->generateReference($administration),
            'contract_version' => self::VERSION,
        ]);

        try {
            $this->sendContractInvitation($administration->fresh(['manager.user']));
        } catch (\Throwable $e) {
            \Log::warning('No se pudo enviar contrato SaaS al crear administración '.$administration->id.': '.$e->getMessage());
        }

        return $administration->fresh();
    }

    public function sendContractInvitation(Administration $administration, ?int $userId = null): Administration
    {
        if ($administration->contract_status === Administration::CONTRACT_SIGNED) {
            throw new \InvalidArgumentException('El contrato ya está firmado.');
        }

        $recipientEmail = trim((string) $administration->email);
        if ($recipientEmail === '') {
            throw new \InvalidArgumentException('La administración no tiene email de contacto configurado.');
        }

        if (! $administration->contract_reference) {
            $administration->contract_reference = $this->generateReference($administration);
        }

        $token = Str::random(64);
        $administration->update([
            'contract_status' => Administration::CONTRACT_PENDING,
            'contract_token' => $token,
            'contract_sent_at' => now(),
            'contract_version' => self::VERSION,
        ]);

        try {
            Mail::to($recipientEmail)->send(
                new AdministrationContractMail($administration->fresh(['manager.user']), $token)
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando contrato SaaS administración: '.$e->getMessage());
            throw new \RuntimeException('No se pudo enviar el email del contrato.');
        }

        return $administration->fresh();
    }

    public function signContractByToken(
        string $token,
        string $signerName,
        string $signerNif,
        ?int $userId = null,
        ?string $ip = null,
        ?Request $request = null
    ): Administration {
        $administration = Administration::query()
            ->where('contract_token', $token)
            ->with(['manager.user'])
            ->first();

        if (! $administration || $administration->contract_status !== Administration::CONTRACT_PENDING) {
            throw new \InvalidArgumentException('El enlace de firma no es válido o el contrato ya fue gestionado.');
        }

        $signedAt = now();
        $signature = [
            'signer_name' => $signerName,
            'signer_nif' => $signerNif,
            'signed_at' => $signedAt,
        ];

        $pdfBinary = $this->documents->renderPdfBinary(
            'contracts.administration_saas_pdf',
            $this->buildViewData($administration, $signature)
        );

        $pdfPath = $this->documents->storeBinary(
            'contracts/administrations/'.$administration->id.'/saas-'.$signedAt->format('YmdHis').'.pdf',
            $pdfBinary
        );

        $administration->update([
            'contract_status' => Administration::CONTRACT_SIGNED,
            'contract_signed_at' => $signedAt,
            'contract_token' => null,
            'contract_signed_by_user_id' => $userId,
            'contract_signer_name' => $signerName,
            'contract_signer_nif' => $signerNif,
            'contract_pdf_path' => $pdfPath,
        ]);

        $administration = $administration->fresh(['manager.user']);
        $textHash = $this->textHash($administration);

        $signerUser = $userId ? User::query()->find($userId) : null;
        $this->legalAcceptance->record(
            action: LegalAcceptance::ACTION_CONTRATO_SAAS_ADMINISTRACION,
            user: $signerUser,
            version: self::VERSION,
            textHash: $textHash,
            administrationId: $administration->id,
            channel: LegalAcceptance::CHANNEL_WEB,
            request: $request,
            context: [
                'contract_reference' => $administration->contract_reference,
                'signer_name' => $signerName,
                'signer_nif' => $signerNif,
                'ip' => $ip,
                'via' => 'token',
            ],
        );

        try {
            Mail::to($administration->email)->send(
                new AdministrationContractSignedMail($administration, $pdfPath)
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando copia contrato SaaS firmado: '.$e->getMessage());
        }

        return $administration;
    }

    /**
     * @param  array{signer_name?: string, signer_nif?: string, signed_at?: \Illuminate\Support\Carbon}  $signature
     * @return array<string, mixed>
     */
    public function buildViewData(Administration $administration, array $signature = []): array
    {
        $managerUser = $administration->manager?->user;
        $commercialName = trim((string) ($administration->name ?: $administration->society));
        $representativeName = trim((string) ($signature['signer_name'] ?? ''));
        if ($representativeName === '' && $managerUser) {
            $representativeName = trim($managerUser->name.' '.($managerUser->last_name ?? ''));
        }

        $representativeNif = trim((string) ($signature['signer_nif'] ?? ''));
        if ($representativeNif === '' && $managerUser?->nif_cif) {
            $representativeNif = (string) $managerUser->nif_cif;
        }

        $signedAt = $signature['signed_at'] ?? null;
        $account = (string) ($administration->account ?? '');
        if ($account !== '' && ! str_starts_with($account, 'ES')) {
            $account = 'ES'.$account;
        }

        return [
            'administration' => $administration,
            'contractReference' => $administration->contract_reference ?: $this->generateReference($administration),
            'contractVersion' => self::VERSION_LABEL,
            'commercialName' => $commercialName,
            'society' => trim((string) $administration->society),
            'selaeCode' => trim((string) ($administration->admin_number ?? '')) ?: '—',
            'receivingCode' => trim((string) ($administration->receiving ?? '')) ?: '—',
            'nifCif' => trim((string) ($administration->nif_cif ?? '')) ?: '—',
            'address' => trim((string) ($administration->address ?? '')) ?: '—',
            'city' => trim((string) ($administration->city ?? '')) ?: '—',
            'province' => trim((string) ($administration->province ?? '')) ?: '—',
            'postalCode' => trim((string) ($administration->postal_code ?? '')) ?: '—',
            'email' => trim((string) ($administration->email ?? '')) ?: '—',
            'phone' => trim((string) ($administration->phone ?? '')) ?: '—',
            'iban' => $account !== '' ? $account : '—',
            'representativeName' => $representativeName !== '' ? $representativeName : '—',
            'representativeNif' => $representativeNif !== '' ? $representativeNif : '—',
            'activationDate' => ($signedAt ?? now())->format('d/m/Y'),
            'signedAt' => $signedAt,
            'signerName' => $signature['signer_name'] ?? null,
            'signerNif' => $signature['signer_nif'] ?? null,
            'forPdf' => true,
        ];
    }

    public function textHash(Administration $administration): string
    {
        $html = $this->documents->renderHtml(
            'contracts.administration_saas_content',
            $this->buildViewData($administration)
        );

        return hash('sha256', $html);
    }

    public function previewPdfBinary(Administration $administration): string
    {
        return $this->documents->renderPdfBinary(
            'contracts.administration_saas_pdf',
            $this->buildViewData($administration->loadMissing(['manager.user']))
        );
    }

    /** @return int[] */
    public function pendingAdministrationIdsForUser(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [];
        }

        $ids = [];

        if ($user->isAdministrationPanelAccount() && $user->panel_account_id) {
            $ids[] = (int) $user->panel_account_id;
        }

        if ($user->isAdministration() && ! $user->isPanelAccount()) {
            $ids = array_merge($ids, $user->accessibleAdministrationIds());
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Administration::query()
            ->whereIn('id', $ids)
            ->where('contract_status', Administration::CONTRACT_PENDING)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function userMustSignBeforeAccess(User $user): bool
    {
        return $this->pendingAdministrationIdsForUser($user) !== [];
    }

    public function firstPendingAdministrationForUser(User $user): ?Administration
    {
        $ids = $this->pendingAdministrationIdsForUser($user);
        if ($ids === []) {
            return null;
        }

        return Administration::query()->find($ids[0]);
    }

    public function userCanAccessAdministrationContract(User $user, Administration $administration): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array((int) $administration->id, $this->pendingAdministrationIdsForUser($user), true)
            || in_array((int) $administration->id, $user->accessibleAdministrationIds(), true);
    }
}
