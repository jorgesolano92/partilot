<?php

namespace App\Services;

use App\Models\LegalAcceptance;
use App\Models\User;
use Illuminate\Http\Request;

class PanelLegalAcceptanceService
{
    public function __construct(
        private readonly LegalAcceptanceService $legalAcceptance,
    ) {}

    public function userMustAcceptBeforeAccess(User $user): bool
    {
        if (! $user->isPanelAccount() || $user->isSuperAdmin()) {
            return false;
        }

        return ! $this->userHasAccepted($user);
    }

    public function userHasAccepted(User $user): bool
    {
        if (! $user->isPanelAccount()) {
            return true;
        }

        return LegalAcceptance::query()
            ->where('user_id', $user->id)
            ->whereIn('action', $this->acceptedActions())
            ->where('result', LegalAcceptance::RESULT_ACEPTADO)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(User $user): array
    {
        $documents = $this->legalAcceptance->listPublicDocuments();
        $documentLinks = collect($documents)
            ->filter(fn (array $doc) => in_array($doc['slug'] ?? '', [
                'terminos-y-condiciones',
                'politica-de-privacidad',
                'marco-legal',
                'aviso-legal',
            ], true))
            ->values()
            ->all();

        $meta = $this->legalAcceptance->registrationDocumentMeta();

        return [
            'user' => $user,
            'checkbox_label' => (string) config('legal.panel_checkbox_label', config('legal.registration_checkbox_label')),
            'intro' => (string) config('legal.panel_intro_sentence', ''),
            'documents' => $documentLinks,
            'version' => $meta['version'],
            'text_hash' => $meta['text_hash'],
        ];
    }

    public function recordAcceptance(User $user, Request $request): void
    {
        $meta = $this->legalAcceptance->registrationDocumentMeta();
        $administrationId = $user->isAdministrationPanelAccount()
            ? (int) $user->panel_account_id
            : null;
        $entityId = $user->isEntityPanelAccount()
            ? (int) $user->panel_account_id
            : null;

        $this->legalAcceptance->recordFromRequest(
            action: LegalAcceptance::ACTION_PANEL_ACEPTACION_MARCO_LEGAL,
            request: $request,
            user: $user,
            version: $meta['version'],
            textHash: $meta['text_hash'],
            entityId: $entityId ?: null,
            administrationId: $administrationId,
            context: [
                'panel_account_type' => $user->panel_account_type,
                'panel_account_id' => $user->panel_account_id,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function acceptedActions(): array
    {
        return [
            LegalAcceptance::ACTION_PANEL_ACEPTACION_MARCO_LEGAL,
            LegalAcceptance::ACTION_REGISTRO_ACEPTACION_TCU,
        ];
    }
}
