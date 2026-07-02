<?php

namespace App\Services;

use App\Models\LegalAcceptance;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LegalAcceptanceService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $action,
        string $result = LegalAcceptance::RESULT_ACEPTADO,
        ?User $user = null,
        ?string $version = null,
        ?string $textHash = null,
        ?int $entityId = null,
        ?int $lotteryId = null,
        ?int $administrationId = null,
        ?string $channel = null,
        ?Request $request = null,
        array $context = []
    ): ?LegalAcceptance {
        if (! Schema::hasTable('legal_acceptances')) {
            return null;
        }

        return LegalAcceptance::create([
            'user_id' => $user?->id,
            'action' => $action,
            'result' => $result,
            'version' => $version,
            'text_hash' => $textHash,
            'entity_id' => $entityId,
            'lottery_id' => $lotteryId,
            'administration_id' => $administrationId,
            'channel' => $channel ?? ($request ? $this->detectChannel($request) : null),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'context' => $context !== [] ? $context : null,
            'accepted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordFromRequest(
        string $action,
        Request $request,
        ?User $user = null,
        string $result = LegalAcceptance::RESULT_ACEPTADO,
        ?string $version = null,
        ?string $textHash = null,
        ?int $entityId = null,
        ?int $lotteryId = null,
        ?int $administrationId = null,
        array $context = []
    ): ?LegalAcceptance {
        return $this->record(
            action: $action,
            result: $result,
            user: $user,
            version: $version,
            textHash: $textHash,
            entityId: $entityId,
            lotteryId: $lotteryId,
            administrationId: $administrationId,
            request: $request,
            context: $context,
        );
    }

    public function mapUserConsentType(string $type): string
    {
        return match ($type) {
            UserConsent::TYPE_REGISTRATION_TERMS => LegalAcceptance::ACTION_REGISTRO_ACEPTACION_TCU,
            UserConsent::TYPE_DIGITAL_SALE_TERMS => LegalAcceptance::ACTION_VENTA_DIGITAL_TERMINOS,
            default => $type,
        };
    }

    public function registrationDocumentMeta(): array
    {
        $doc = config('legal.documents.marco_legal', []);

        return [
            'version' => (string) ($doc['version'] ?? config('legal.terms_version', '10')),
            'text_hash' => (string) ($doc['hash'] ?? config('legal.terms_text_hash', 'marco_legal_v10')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPublicDocuments(): array
    {
        $documents = [];
        foreach (config('legal.documents', []) as $key => $doc) {
            if (! is_array($doc) || empty($doc['route'])) {
                continue;
            }
            $documents[] = [
                'key' => $key,
                'slug' => $doc['slug'] ?? $key,
                'title' => $doc['title'] ?? $key,
                'version' => $doc['version'] ?? null,
                'hash' => $doc['hash'] ?? null,
                'url' => route($doc['route']),
            ];
        }

        return $documents;
    }

    public function findDocumentBySlug(string $slug): ?array
    {
        foreach ($this->listPublicDocuments() as $document) {
            if ($document['slug'] === $slug) {
                return $document;
            }
        }

        return null;
    }

    /**
     * Configuración consumible por la app móvil (L1).
     *
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        $marco = $this->findDocumentBySlug('marco-legal')
            ?? $this->findDocumentBySlug('terminos-y-condiciones');

        return [
            'terms_version' => config('legal.terms_version'),
            'terms_text_hash' => config('legal.terms_text_hash'),
            'registration' => [
                'checkbox_label' => config('legal.registration_checkbox_label'),
                'version' => $this->registrationDocumentMeta()['version'],
                'text_hash' => $this->registrationDocumentMeta()['text_hash'],
                'documents_url' => $marco['url'] ?? url('/terminos-y-condiciones'),
                'privacy_url' => $this->findDocumentBySlug('politica-de-privacidad')['url'] ?? url('/politica-de-privacidad'),
                'terms_url' => $this->findDocumentBySlug('terminos-y-condiciones')['url'] ?? url('/terminos-y-condiciones'),
            ],
            'role_intro_sentence' => config('legal.role_intro_sentence'),
            'documents' => $this->listPublicDocuments(),
            'prize_collection' => $this->prizeCollectionClientConfig(),
            'prize_donation' => $this->prizeDonationClientConfig(),
            'account_deletion' => $this->accountDeletionClientConfig(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prizeCollectionClientConfig(): array
    {
        $cfg = config('legal_prizes.collection', []);

        return [
            'title' => $cfg['title'] ?? 'Confirmar cobro de premio',
            'irreversibility_warning' => $cfg['irreversibility_warning'] ?? '',
            'confirm_label' => $cfg['confirm_label'] ?? 'Confirmar cobro',
            'confirm_again_label' => $cfg['confirm_again_label'] ?? 'Pulsa de nuevo para confirmar',
            'double_confirm_message' => $cfg['double_confirm_message'] ?? '',
            'legal_link_label' => $cfg['legal_link_label'] ?? 'Ver condiciones de cobro',
            'version' => (string) ($cfg['version'] ?? '3'),
            'text_hash' => (string) ($cfg['hash'] ?? 'l6_cobro_premio_v3'),
            'legal_document_slug' => 'terminos-y-condiciones',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function prizeDonationClientConfig(): array
    {
        $cfg = config('legal_prizes.donation', []);

        return [
            'title' => $cfg['title'] ?? 'Confirmar donación de premio',
            'notice_template' => $cfg['notice_template'] ?? '',
            'fiscal_certificate_question' => $cfg['fiscal_certificate_question'] ?? '',
            'rgpd_notice_template' => $cfg['rgpd_notice_template'] ?? '',
            'confirm_label' => $cfg['confirm_label'] ?? 'Confirmar donación',
            'confirm_again_label' => $cfg['confirm_again_label'] ?? 'Pulsa de nuevo para confirmar',
            'version' => (string) ($cfg['version'] ?? '3'),
            'text_hash' => (string) ($cfg['hash'] ?? 'l7_donacion_premio_v3'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountDeletionClientConfig(): array
    {
        $cfg = config('legal.account_deletion', []);
        $days = (string) ($cfg['grace_days'] ?? 30);

        return [
            'title' => $cfg['title'] ?? 'Eliminar cuenta',
            'main_warning' => $cfg['main_warning'] ?? '',
            'prizes_warning' => $cfg['prizes_warning'] ?? '',
            'blocked_message' => $cfg['blocked_message'] ?? '',
            'email_confirm_label' => $cfg['email_confirm_label'] ?? 'Escribe tu email para confirmar',
            'confirm_button' => $cfg['confirm_button'] ?? 'Eliminar mi cuenta',
            'cancel_button' => $cfg['cancel_button'] ?? 'Cancelar',
            'scheduled_notice' => str_replace(':days', $days, $cfg['scheduled_notice'] ?? ''),
            'version' => (string) ($cfg['version'] ?? '3'),
            'text_hash' => (string) ($cfg['hash'] ?? 'l9_baja_cuenta_v3'),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordDefinitiveLiquidationConfirmation(
        User $user,
        Request $request,
        array $context = [],
        ?int $administrationId = null
    ): ?LegalAcceptance {
        $cfg = config('legal_prizes.definitive_liquidation', []);

        return $this->recordFromRequest(
            action: LegalAcceptance::ACTION_LIQUIDACION_DEFINITIVA_CONFIRMADA,
            request: $request,
            user: $user,
            version: (string) ($cfg['version'] ?? '3'),
            textHash: (string) ($cfg['hash'] ?? 'l8_liquidacion_definitiva_v3'),
            entityId: isset($context['entity_id']) ? (int) $context['entity_id'] : null,
            lotteryId: isset($context['lottery_id']) ? (int) $context['lottery_id'] : null,
            administrationId: $administrationId,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordPrizeCollectionConfirmation(User $user, Request $request, array $context = []): ?LegalAcceptance
    {
        $cfg = config('legal_prizes.collection', []);
        $entityId = isset($context['entity_id']) ? (int) $context['entity_id'] : null;
        $administrationId = isset($context['administration_id']) ? (int) $context['administration_id'] : null;

        return $this->recordFromRequest(
            action: LegalAcceptance::ACTION_COBRO_PREMIO_CONFIRMADO,
            request: $request,
            user: $user,
            version: (string) ($cfg['version'] ?? '3'),
            textHash: (string) ($cfg['hash'] ?? 'l6_cobro_premio_v3'),
            entityId: $entityId,
            administrationId: $administrationId,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordPrizeDonationConfirmation(User $user, Request $request, array $context = []): ?LegalAcceptance
    {
        $cfg = config('legal_prizes.donation', []);
        $entityId = isset($context['entity_id']) ? (int) $context['entity_id'] : null;
        $administrationId = isset($context['administration_id']) ? (int) $context['administration_id'] : null;

        return $this->recordFromRequest(
            action: LegalAcceptance::ACTION_DONACION_PREMIO_CONFIRMADA,
            request: $request,
            user: $user,
            version: (string) ($cfg['version'] ?? '3'),
            textHash: (string) ($cfg['hash'] ?? 'l7_donacion_premio_v3'),
            entityId: $entityId,
            administrationId: $administrationId,
            context: $context,
        );
    }

    /**
     * Aceptaciones pendientes que bloquean el uso (L3–L5; extensible).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingAcceptancesForUser(User $user): array
    {
        return app(RoleLegalAcceptanceService::class)->pendingInvitationsForUser($user);
    }

    public function detectChannel(Request $request): string
    {
        $header = strtolower((string) $request->header('X-Partilot-Channel', ''));

        return match ($header) {
            'app_ios' => LegalAcceptance::CHANNEL_APP_IOS,
            'app_android' => LegalAcceptance::CHANNEL_APP_ANDROID,
            'web_entidad' => LegalAcceptance::CHANNEL_WEB_ENTIDAD,
            default => $request->is('api/*') ? LegalAcceptance::CHANNEL_APP_ANDROID : LegalAcceptance::CHANNEL_WEB,
        };
    }
}
