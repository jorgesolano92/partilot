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
        ];
    }

    /**
     * Aceptaciones pendientes que bloquean el uso (L3–L5; extensible).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingAcceptancesForUser(User $user): array
    {
        // Fase 2: consultar managers/sellers pendientes y devolver pantallas bloqueantes.
        return [];
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
