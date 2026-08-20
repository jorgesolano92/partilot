<?php

namespace App\Services;

use App\Models\EmailCommunicationLog;
use App\Models\DesignExternalInvitation;
use App\Models\Seller;
use App\Models\Set;
use App\Models\Reserve;
use App\Models\Devolution;
use App\Models\Administration;
use App\Models\ParticipationGift;
use App\Models\ParticipationCollection;
use App\Models\ParticipationDonation;
use App\Models\DesignFormat;
use App\Models\User;
use App\Models\PendingEntityManagerInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class CommunicationEmailService
{
    /**
     * Enviar un email y crear el log con estado (pending -> sent|cancelled).
     *
     * @param class-string $mailClass
     * @param array $mailPayload Payload simple (IDs) que permitirá reenviar
     */
    public function sendAndLog(
        string $recipientEmail,
        ?string $recipientRole,
        ?User $recipientUser,
        string $messageType,
        ?string $templateKey,
        string $mailClass,
        array $mailPayload,
        ?array $context = null,
    ): EmailCommunicationLog {
        $sender = Auth::user();
        $senderType = $sender ? $this->resolveSenderType($sender) : 'superadmin';

        $log = EmailCommunicationLog::create([
            'template_key' => $templateKey,
            'message_type' => $messageType,
            'sender_type' => $senderType,
            'sender_user_id' => $sender?->id,
            'recipient_email' => $recipientEmail,
            'recipient_role' => $recipientRole,
            'recipient_user_id' => $recipientUser?->id,
            'mail_class' => $mailClass,
            'mail_payload' => $this->sanitizeMailPayloadForLog($mailPayload),
            'status' => EmailCommunicationLog::STATUS_PENDING,
            'last_attempt_at' => now(),
            'context' => $context,
        ]);

        try {
            $secrets = $this->sendFromLogPayload($recipientEmail, $mailClass, $mailPayload);

            $log->update([
                'status' => EmailCommunicationLog::STATUS_SENT,
                'sent_at' => now(),
                'last_attempt_at' => now(),
                'error_message' => null,
                'encrypted_secrets' => $this->encryptSecrets($secrets),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => EmailCommunicationLog::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'last_attempt_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Reenviar un log reutilizando la misma fila y cambiando status a `resent`.
     */
    public function resendLog(EmailCommunicationLog $log): EmailCommunicationLog
    {
        $sender = Auth::user();
        if (! $sender) {
            abort(403, 'No autenticado.');
        }

        $log->update([
            'status' => EmailCommunicationLog::STATUS_RE_SENT,
            'resent_at' => now(),
            'last_attempt_at' => now(),
            'error_message' => null,
        ]);

        try {
            $secrets = $this->sendFromLogPayload($log->recipient_email, $log->mail_class, $log->mail_payload ?? []);

            if ($secrets !== []) {
                $log->update(['encrypted_secrets' => $this->encryptSecrets($secrets)]);
            }

            return $log;
        } catch (\Throwable $e) {
            $log->update([
                'status' => EmailCommunicationLog::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'last_attempt_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return $log;
        }
    }

    private function resolveSenderType(User $sender): string
    {
        if ($sender->isSuperAdmin()) {
            return 'superadmin';
        }

        if ($sender->panel_account_type === 'administration') {
            return 'administracion';
        }

        if ($sender->panel_account_type === 'entity') {
            return 'entidad';
        }

        // Si es un "manager" (gestor) sin panel_account_type, inferimos el tipo
        // desde las relaciones en `managers` (administración vs entidad).
        if ($sender->isAdministration()) {
            return 'administracion';
        }

        if ($sender->isEntity()) {
            return 'entidad';
        }

        // Fallback razonable
        return 'superadmin';
    }

    /**
     * Reconstruye el contenido del email a partir del log (para previsualización).
     *
     * @return array{subject: string, html: string}
     */
    public function previewLog(EmailCommunicationLog $log, bool $revealSecrets = false): array
    {
        if (empty($log->mail_class)) {
            throw new \RuntimeException('Este registro no tiene plantilla de email asociada.');
        }

        $storedSecrets = $revealSecrets ? ($log->decryptedSecrets() ?? []) : [];

        $mailable = $this->buildMailableFromLogPayload(
            $log->recipient_email,
            $log->mail_class,
            $log->mail_payload ?? [],
            forPreview: true,
            storedSecrets: $storedSecrets,
        );

        return [
            'subject' => $mailable->envelope()->subject,
            'html' => $mailable->render(),
        ];
    }

    /**
     * Reconstruye el Mailable desde `mail_class` + `mail_payload` (IDs simples).
     */
    private function buildMailableFromLogPayload(
        string $recipientEmail,
        string $mailClass,
        array $mailPayload,
        bool $forPreview = false,
        array $storedSecrets = [],
    ): Mailable {
        // Si alguna fila viene con datos inesperados, fallará y el log se marcará como cancelled.
        if ($mailClass === \App\Mail\DesignExternalInvitationMail::class) {
            $invitationId = (int) ($mailPayload['invitation_id'] ?? 0);
            $invitation = DesignExternalInvitation::findOrFail($invitationId);

            return new \App\Mail\DesignExternalInvitationMail($invitation);
        }

        if ($mailClass === \App\Mail\SellerConfirmationMail::class) {
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $seller = Seller::with('entities')->findOrFail($sellerId);

            return new \App\Mail\SellerConfirmationMail($seller);
        }

        if ($mailClass === \App\Mail\ParticipationAssignmentMail::class) {
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $seller = Seller::findOrFail($sellerId);

            $assignmentsList = $mailPayload['assignments'] ?? [];
            $assignmentsBySet = [];

            foreach ($assignmentsList as $a) {
                $setId = (int) ($a['set_id'] ?? 0);
                $count = (int) ($a['count'] ?? 0);
                if ($setId <= 0 || $count <= 0) {
                    continue;
                }

                $set = Set::with(['reserve.lottery', 'entity'])->findOrFail($setId);
                $assignmentsBySet[$setId] = [
                    'set' => $set,
                    'lottery' => $set->reserve?->lottery,
                    'count' => $count,
                ];
            }

            $assignments = array_values($assignmentsBySet);

            return new \App\Mail\ParticipationAssignmentMail($seller, $assignments);
        }

        if ($mailClass === \App\Mail\ParticipationAssignmentProposalMail::class) {
            $proposalId = (int) ($mailPayload['proposal_id'] ?? 0);
            $proposal = \App\Models\ParticipationAssignmentProposal::with(['seller.entities', 'entity', 'lottery'])->findOrFail($proposalId);

            return new \App\Mail\ParticipationAssignmentProposalMail($proposal);
        }

        if ($mailClass === \App\Mail\ParticipationAssignmentAcceptedEntityMail::class) {
            $proposalId = (int) ($mailPayload['proposal_id'] ?? 0);
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $assignedCount = (int) ($mailPayload['assigned_count'] ?? 0);
            $proposal = \App\Models\ParticipationAssignmentProposal::with(['entity', 'lottery'])->findOrFail($proposalId);
            $seller = Seller::findOrFail($sellerId);

            return new \App\Mail\ParticipationAssignmentAcceptedEntityMail($seller, $proposal, $assignedCount);
        }

        if ($mailClass === \App\Mail\ReserveSavedToEntityManagerMail::class) {
            $reserveId = (int) ($mailPayload['reserve_id'] ?? 0);
            $reserve = Reserve::with(['entity.administration', 'entity.manager.user', 'lottery.lotteryType'])->findOrFail($reserveId);

            return new \App\Mail\ReserveSavedToEntityManagerMail($reserve);
        }

        if ($mailClass === \App\Mail\ReserveDeletedToEntityManagerMail::class) {
            $reserveId = (int) ($mailPayload['reserve_id'] ?? 0);
            $reserve = Reserve::with([
                'entity.administration',
                'entity.manager.user',
                'lottery.lotteryType',
            ])->findOrFail($reserveId);
            $deletionReason = isset($mailPayload['deletion_reason'])
                ? (string) $mailPayload['deletion_reason']
                : null;

            return new \App\Mail\ReserveDeletedToEntityManagerMail($reserve, $deletionReason);
        }

        if ($mailClass === \App\Mail\SetCreatedToEntityManagerMail::class) {
            $setId = (int) ($mailPayload['set_id'] ?? 0);
            $set = Set::with(['entity.manager.user', 'reserve.lottery.lotteryType', 'reserve.lottery'])->findOrFail($setId);

            return new \App\Mail\SetCreatedToEntityManagerMail($set);
        }

        if ($mailClass === \App\Mail\SetDeletedToEntityManagerMail::class) {
            $setId = (int) ($mailPayload['set_id'] ?? 0);
            $set = Set::with([
                'entity.manager.user',
                'reserve.lottery.lotteryType',
                'reserve.lottery',
            ])->findOrFail($setId);
            $deletionReason = isset($mailPayload['deletion_reason'])
                ? (string) $mailPayload['deletion_reason']
                : null;

            return new \App\Mail\SetDeletedToEntityManagerMail($set, $deletionReason);
        }

        if ($mailClass === \App\Mail\DesignApprovalApprovedToEntityManagerMail::class) {
            $designId = (int) ($mailPayload['design_format_id'] ?? 0);
            $design = DesignFormat::with([
                'entity',
                'entity.manager.user',
                'lottery',
                'set',
                'set.reserve.lottery',
            ])->findOrFail($designId);

            return new \App\Mail\DesignApprovalApprovedToEntityManagerMail($design);
        }

        if ($mailClass === \App\Mail\DesignApprovalRejectedToEntityManagerMail::class) {
            $designId = (int) ($mailPayload['design_format_id'] ?? 0);
            $design = DesignFormat::with([
                'entity',
                'entity.manager.user',
                'lottery',
                'set',
                'set.reserve.lottery',
            ])->findOrFail($designId);

            return new \App\Mail\DesignApprovalRejectedToEntityManagerMail($design);
        }

        if ($mailClass === \App\Mail\DevolutionReturnedToAdministrationMail::class) {
            $devolutionId = (int) ($mailPayload['devolution_id'] ?? 0);
            $devolution = Devolution::with(['entity.administration', 'entity.manager.user', 'lottery'])->findOrFail($devolutionId);

            return new \App\Mail\DevolutionReturnedToAdministrationMail($devolution);
        }

        if ($mailClass === \App\Mail\DevolutionReturnedToEntityManagerMail::class) {
            $devolutionId = (int) ($mailPayload['devolution_id'] ?? 0);
            $devolution = Devolution::with(['entity.administration', 'entity.manager.user', 'lottery'])->findOrFail($devolutionId);

            return new \App\Mail\DevolutionReturnedToEntityManagerMail($devolution);
        }

        if ($mailClass === \App\Mail\AdministrationWelcomeMail::class) {
            $administrationId = (int) ($mailPayload['administration_id'] ?? 0);
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $administration = Administration::findOrFail($administrationId);
            $user = User::findOrFail($userId);
            $magicUrl = (string) ($storedSecrets['magic_link_url'] ?? '');
            if ($magicUrl === '') {
                $magicUrl = $forPreview
                    ? route('panel.access', ['token' => '********'], absolute: true)
                    : route('panel.access', ['token' => \App\Models\PanelAccessToken::issueForUser($user)], absolute: true);
            }

            return new \App\Mail\AdministrationWelcomeMail($administration, $user, $magicUrl);
        }

        if ($mailClass === \App\Mail\EntityWelcomeMail::class) {
            $entityId = (int) ($mailPayload['entity_id'] ?? 0);
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $entity = \App\Models\Entity::with('administration')->findOrFail($entityId);
            $user = User::findOrFail($userId);
            $plainPassword = (string) ($storedSecrets['plain_password'] ?? $mailPayload['plain_password'] ?? '');
            if ($plainPassword === '') {
                $plainPassword = $forPreview
                    ? '[contraseña no disponible en el historial]'
                    : app(ProvisionalPasswordService::class)->assignToUser($user);
            }
            $loginUrl = route('login', absolute: true);

            return new \App\Mail\EntityWelcomeMail($entity, $user, $plainPassword, $loginUrl);
        }

        if ($mailClass === \App\Mail\EntityManagerInvitationMail::class) {
            $entityId = (int) ($mailPayload['entity_id'] ?? 0);
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $managerId = (int) ($mailPayload['manager_id'] ?? 0);
            $entity = \App\Models\Entity::findOrFail($entityId);
            $user = User::findOrFail($userId);
            $manager = $managerId > 0
                ? \App\Models\Manager::findOrFail($managerId)
                : \App\Models\Manager::where('entity_id', $entityId)->where('user_id', $userId)->latest('id')->firstOrFail();
            $plainPassword = (string) ($storedSecrets['plain_password'] ?? $mailPayload['plain_password'] ?? '');
            if ($plainPassword === '') {
                if ($forPreview) {
                    $plainPassword = '[contraseña no disponible en el historial]';
                } elseif ($user->must_change_password) {
                    $plainPassword = app(ProvisionalPasswordService::class)->assignToUser($user);
                }
            }

            return new \App\Mail\EntityManagerInvitationMail($entity, $user, $manager, $plainPassword);
        }

        if ($mailClass === \App\Mail\EntityManagerPreregisterInviteMail::class) {
            $pendingId = (int) ($mailPayload['pending_invitation_id'] ?? 0);
            $pending = PendingEntityManagerInvitation::with('entity.administration')->findOrFail($pendingId);
            $entity = $pending->entity ?? \App\Models\Entity::findOrFail((int) ($mailPayload['entity_id'] ?? $pending->entity_id));

            return new \App\Mail\EntityManagerPreregisterInviteMail($entity, $pending);
        }

        if ($mailClass === \App\Mail\EntityResponsibleManagerConfirmedMail::class) {
            $entityId = (int) ($mailPayload['entity_id'] ?? 0);
            $userId = (int) ($mailPayload['responsible_manager_user_id'] ?? 0);
            $entity = \App\Models\Entity::findOrFail($entityId);
            $user = User::findOrFail($userId);

            return new \App\Mail\EntityResponsibleManagerConfirmedMail($entity, $user);
        }

        if ($mailClass === \App\Mail\SellerSettlementStatusMail::class) {
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $settlementId = (int) ($mailPayload['settlement_id'] ?? 0);
            $isFullySettled = (bool) ($mailPayload['is_fully_settled'] ?? false);
            $seller = Seller::findOrFail($sellerId);
            $settlement = \App\Models\SellerSettlement::findOrFail($settlementId);

            return new \App\Mail\SellerSettlementStatusMail($seller, $settlement, $isFullySettled);
        }

        if ($mailClass === \App\Mail\UserWelcomeMail::class) {
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $user = User::findOrFail($userId);

            return new \App\Mail\UserWelcomeMail($user);
        }

        if ($mailClass === \App\Mail\ParticipationGiftRecipientMail::class) {
            $giftId = (int) ($mailPayload['gift_id'] ?? 0);
            $gift = ParticipationGift::with(['fromUser', 'toUser', 'participation'])->findOrFail($giftId);

            return new \App\Mail\ParticipationGiftRecipientMail($gift);
        }

        if ($mailClass === \App\Mail\ParticipationGiftSenderMail::class) {
            $giftId = (int) ($mailPayload['gift_id'] ?? 0);
            $gift = ParticipationGift::with(['fromUser', 'toUser', 'participation'])->findOrFail($giftId);

            return new \App\Mail\ParticipationGiftSenderMail($gift);
        }

        if ($mailClass === \App\Mail\DigitalPurchaseConfirmationMail::class) {
            $buyerId = (int) ($mailPayload['buyer_id'] ?? 0);
            $buyer = User::findOrFail($buyerId);
            $items = $mailPayload['items'] ?? [];
            $total = (float) ($mailPayload['total_amount'] ?? 0);

            return new \App\Mail\DigitalPurchaseConfirmationMail($buyer, $items, $total);
        }

        if ($mailClass === \App\Mail\TransferCollectionConfirmationMail::class) {
            $collectionId = (int) ($mailPayload['collection_id'] ?? 0);
            $collection = ParticipationCollection::with('user')->findOrFail($collectionId);

            return new \App\Mail\TransferCollectionConfirmationMail($collection);
        }

        if ($mailClass === \App\Mail\TransferCollectionVerificationMail::class) {
            $collectionId = (int) ($mailPayload['collection_id'] ?? 0);
            $collection = ParticipationCollection::with('user')->findOrFail($collectionId);

            return new \App\Mail\TransferCollectionVerificationMail($collection);
        }

        if ($mailClass === \App\Mail\DonationCodeConfirmationMail::class) {
            $donationId = (int) ($mailPayload['donation_id'] ?? 0);
            $donation = ParticipationDonation::with('user')->findOrFail($donationId);

            return new \App\Mail\DonationCodeConfirmationMail($donation);
        }

        if ($mailClass === \App\Mail\DigitalSaleRegistrationInviteMail::class) {
            $pendingId = (int) ($mailPayload['pending_digital_sale_id'] ?? 0);
            $pending = \App\Models\PendingDigitalSale::with(['entity', 'lottery'])->findOrFail($pendingId);
            $pending->ensureLinkCode();

            return new \App\Mail\DigitalSaleRegistrationInviteMail($pending);
        }

        if ($mailClass === \App\Mail\ParticipationWalletLinkedMail::class) {
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $participationId = (int) ($mailPayload['participation_id'] ?? 0);
            $user = User::findOrFail($userId);
            $participation = \App\Models\Participation::with(['set.entity', 'set.reserve.lottery'])->findOrFail($participationId);

            return new \App\Mail\ParticipationWalletLinkedMail($user, $participation);
        }

        if ($mailClass === \App\Mail\ManagementFeePaymentRequestMail::class) {
            $designId = (int) ($mailPayload['design_format_id'] ?? 0);
            $design = \App\Models\DesignFormat::findOrFail($designId);

            return new \App\Mail\ManagementFeePaymentRequestMail($design);
        }

        if ($mailClass === \App\Mail\DesignApprovalPendingMail::class) {
            $designId = (int) ($mailPayload['design_format_id'] ?? 0);
            $design = \App\Models\DesignFormat::findOrFail($designId);

            return new \App\Mail\DesignApprovalPendingMail($design);
        }

        if ($mailClass === \App\Mail\PrintShopWelcomeMail::class) {
            $configId = (int) ($mailPayload['print_configuration_id'] ?? 0);
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $config = \App\Models\PrintConfiguration::findOrFail($configId);
            $user = User::findOrFail($userId);
            $plainPassword = (string) ($storedSecrets['plain_password'] ?? $mailPayload['plain_password'] ?? '');
            $shouldRegeneratePassword = array_key_exists('plain_password', $mailPayload) || $storedSecrets !== [];
            if ($plainPassword === '' && ! $forPreview && $shouldRegeneratePassword) {
                $plainPassword = app(ProvisionalPasswordService::class)->assignToUser($user);
            }
            if ($plainPassword === '' && $forPreview && $shouldRegeneratePassword) {
                $plainPassword = '[contraseña no disponible en el historial]';
            }
            $loginUrl = route('login', absolute: true);

            return new \App\Mail\PrintShopWelcomeMail($config, $user, $plainPassword, $loginUrl);
        }

        if ($mailClass === \App\Mail\PrintOrderCreatedToPrintShopMail::class) {
            $orderId = (int) ($mailPayload['print_order_id'] ?? 0);
            $order = \App\Models\PrintOrder::with([
                'entity',
                'set',
                'lottery',
                'printConfiguration',
            ])->findOrFail($orderId);
            $heldForManagementFee = (bool) ($mailPayload['held_for_management_fee'] ?? false);
            $panelUrl = route('print-shop.orders.show', $order, absolute: true);

            return new \App\Mail\PrintOrderCreatedToPrintShopMail($order, $heldForManagementFee, $panelUrl);
        }

        if ($mailClass === \App\Mail\PrintOrderPaymentRequestMail::class) {
            $orderId = (int) ($mailPayload['print_order_id'] ?? 0);
            $order = \App\Models\PrintOrder::with([
                'entity',
                'set',
                'lottery',
                'printConfiguration',
                'design.set',
            ])->findOrFail($orderId);
            $payUrl = route('design.payPrintOrder', $order, absolute: true);

            return new \App\Mail\PrintOrderPaymentRequestMail($order, $payUrl);
        }

        if ($mailClass === \App\Mail\PrintOrderRejectedByPrintShopMail::class) {
            $orderId = (int) ($mailPayload['print_order_id'] ?? 0);
            $order = \App\Models\PrintOrder::with([
                'entity',
                'set',
                'lottery',
                'printConfiguration',
                'design',
            ])->findOrFail($orderId);
            $summaryUrl = $order->design_format_id
                ? route('design.summary', $order->design_format_id, absolute: true)
                : '';

            return new \App\Mail\PrintOrderRejectedByPrintShopMail($order, $summaryUrl);
        }

        throw new \RuntimeException("mail_class no soportado para reenviar: {$mailClass}");
    }

    /**
     * @return array<string, string>
     */
    private function sendFromLogPayload(string $recipientEmail, string $mailClass, array $mailPayload): array
    {
        $mailable = $this->buildMailableFromLogPayload($recipientEmail, $mailClass, $mailPayload, forPreview: false);
        Mail::to($recipientEmail)->send($mailable);

        return $this->extractSecretsFromMailable($mailClass, $mailable);
    }

    /**
     * @return array<string, string>
     */
    private function extractSecretsFromMailable(string $mailClass, Mailable $mailable): array
    {
        $secrets = [];

        if ($mailable instanceof \App\Mail\EntityWelcomeMail && $mailable->plainPassword !== '') {
            $secrets['plain_password'] = $mailable->plainPassword;
        }

        if ($mailable instanceof \App\Mail\EntityManagerInvitationMail && $mailable->provisionalPassword !== '') {
            $secrets['plain_password'] = $mailable->provisionalPassword;
        }

        if ($mailable instanceof \App\Mail\AdministrationWelcomeMail && $mailable->magicLinkUrl !== '') {
            $secrets['magic_link_url'] = $mailable->magicLinkUrl;
        }

        if ($mailable instanceof \App\Mail\PrintShopWelcomeMail && $mailable->plainPassword !== '') {
            $secrets['plain_password'] = $mailable->plainPassword;
        }

        return $secrets;
    }

    /**
     * @param  array<string, string>  $secrets
     */
    private function encryptSecrets(array $secrets): ?string
    {
        if ($secrets === []) {
            return null;
        }

        return Crypt::encryptString(json_encode($secrets, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $mailPayload
     * @return array<string, mixed>
     */
    protected function sanitizeMailPayloadForLog(array $mailPayload): array
    {
        unset($mailPayload['plain_password']);

        return $mailPayload;
    }

    public function normalizeDeletionReason(mixed $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $reason = trim($reason);
        if ($reason === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            $reason = mb_substr($reason, 0, 2000, 'UTF-8');
        } else {
            $reason = substr($reason, 0, 2000);
        }

        return trim($reason) !== '' ? $reason : null;
    }

    public function sendReserveDeletedToEntityManager(Reserve $reserve, ?string $deletionReason = null): void
    {
        $reserve->loadMissing([
            'entity',
            'entity.manager.user',
            'lottery',
            'lottery.lotteryType',
        ]);

        $entityManagerUser = $reserve->entity?->manager?->user;
        $managerEmail = trim((string) ($entityManagerUser?->email ?? ''));
        if ($managerEmail === '') {
            return;
        }

        $reason = $this->normalizeDeletionReason($deletionReason);
        $mailPayload = ['reserve_id' => $reserve->id];
        if ($reason !== null) {
            $mailPayload['deletion_reason'] = $reason;
        }

        $this->sendAndLog(
            recipientEmail: $managerEmail,
            recipientRole: 'gestor_entidad',
            recipientUser: $entityManagerUser,
            messageType: 'reservation_deleted',
            templateKey: null,
            mailClass: \App\Mail\ReserveDeletedToEntityManagerMail::class,
            mailPayload: $mailPayload,
            context: [
                'reserve_id' => $reserve->id,
                'entity_id' => $reserve->entity_id,
                'lottery_id' => $reserve->lottery_id,
            ],
        );
    }

    public function sendSetDeletedToEntityManager(Set $set, ?string $deletionReason = null): void
    {
        $set->loadMissing([
            'entity',
            'entity.manager.user',
            'reserve',
            'reserve.lottery',
            'reserve.lottery.lotteryType',
        ]);

        $entityManagerUser = $set->entity?->manager?->user;
        $managerEmail = trim((string) ($entityManagerUser?->email ?? ''));
        if ($managerEmail === '') {
            return;
        }

        $reason = $this->normalizeDeletionReason($deletionReason);
        $mailPayload = ['set_id' => $set->id];
        if ($reason !== null) {
            $mailPayload['deletion_reason'] = $reason;
        }

        $this->sendAndLog(
            recipientEmail: $managerEmail,
            recipientRole: 'gestor_entidad',
            recipientUser: $entityManagerUser,
            messageType: 'set_deleted',
            templateKey: null,
            mailClass: \App\Mail\SetDeletedToEntityManagerMail::class,
            mailPayload: $mailPayload,
            context: [
                'set_id' => $set->id,
                'entity_id' => $set->entity_id,
                'reserve_id' => $set->reserve_id,
                'lottery_id' => $set->reserve?->lottery_id,
            ],
        );
    }

    public function sendPrintShopWelcome(
        \App\Models\PrintConfiguration $config,
        User $panelUser,
        ?string $plainPassword = null,
    ): void {
        $email = trim((string) ($panelUser->email ?? ''));
        if ($email === '') {
            return;
        }

        $mailPayload = [
            'print_configuration_id' => $config->id,
            'user_id' => $panelUser->id,
        ];
        if ($plainPassword !== null && $plainPassword !== '') {
            $mailPayload['plain_password'] = $plainPassword;
        }

        $this->sendAndLog(
            recipientEmail: $email,
            recipientRole: 'imprenta',
            recipientUser: $panelUser,
            messageType: 'print_shop_welcome',
            templateKey: null,
            mailClass: \App\Mail\PrintShopWelcomeMail::class,
            mailPayload: $mailPayload,
            context: ['print_configuration_id' => $config->id],
        );
    }

    public function sendPrintOrderCreatedToPrintShop(\App\Models\PrintOrder $order): void
    {
        $order->loadMissing(['printConfiguration', 'entity', 'set', 'lottery']);
        $panelUser = app(\App\Services\PrintShopPanelUserService::class)->panelUser($order->printConfiguration);
        if (! $panelUser) {
            return;
        }

        $email = trim((string) ($panelUser->email ?? ''));
        if ($email === '') {
            return;
        }

        $this->sendAndLog(
            recipientEmail: $email,
            recipientRole: 'imprenta',
            recipientUser: $panelUser,
            messageType: 'print_order_created',
            templateKey: null,
            mailClass: \App\Mail\PrintOrderCreatedToPrintShopMail::class,
            mailPayload: [
                'print_order_id' => $order->id,
                'held_for_management_fee' => ! $order->isVisibleToPrintShop(),
            ],
            context: [
                'print_order_id' => $order->id,
                'print_configuration_id' => $order->print_configuration_id,
                'entity_id' => $order->entity_id,
            ],
        );
    }

    public function sendPrintOrderPaymentRequestToPayer(\App\Models\PrintOrder $order): void
    {
        if (! $order->isAwaitingClientPayment()) {
            return;
        }

        foreach ($this->resolvePrintOrderClientRecipients($order) as $recipient) {
            $this->sendAndLog(
                recipientEmail: $recipient['email'],
                recipientRole: $recipient['role'],
                recipientUser: $recipient['user'],
                messageType: 'print_order_payment_request',
                templateKey: null,
                mailClass: \App\Mail\PrintOrderPaymentRequestMail::class,
                mailPayload: ['print_order_id' => $order->id],
                context: [
                    'print_order_id' => $order->id,
                    'entity_id' => $order->entity_id,
                    'design_format_id' => $order->design_format_id,
                ],
            );
        }
    }

    public function sendPrintOrderRejectedToClient(\App\Models\PrintOrder $order): void
    {
        if ((string) $order->status !== \App\Models\PrintOrder::STATUS_REJECTED) {
            return;
        }

        foreach ($this->resolvePrintOrderClientRecipients($order) as $recipient) {
            $this->sendAndLog(
                recipientEmail: $recipient['email'],
                recipientRole: $recipient['role'],
                recipientUser: $recipient['user'],
                messageType: 'print_order_rejected',
                templateKey: null,
                mailClass: \App\Mail\PrintOrderRejectedByPrintShopMail::class,
                mailPayload: ['print_order_id' => $order->id],
                context: [
                    'print_order_id' => $order->id,
                    'entity_id' => $order->entity_id,
                    'design_format_id' => $order->design_format_id,
                ],
            );
        }
    }

    /**
     * Destinatarios del cliente según quién diseña / paga la impresión (entidad o administración).
     *
     * @return list<array{email: string, role: string, user: ?User}>
     */
    private function resolvePrintOrderClientRecipients(\App\Models\PrintOrder $order): array
    {
        $order->loadMissing([
            'entity.administration.manager.user',
            'entity.manager.user',
            'design',
        ]);

        $entity = $order->entity;
        if (! $entity) {
            return [];
        }

        $entityPays = (bool) $entity->entity_pays_print_fee;
        $recipients = [];

        if ($entityPays) {
            $managerEmail = trim((string) ($entity->manager?->user?->email ?? ''));
            if ($managerEmail !== '') {
                $recipients[] = [
                    'email' => $managerEmail,
                    'role' => 'gestor_entidad',
                    'user' => $entity->manager?->user,
                ];
            }

            $panelUser = \App\Models\User::query()
                ->where('panel_account_type', 'entity')
                ->where('panel_account_id', $entity->id)
                ->first();
            $panelEmail = trim((string) ($panelUser?->email ?? ''));
            if ($panelEmail !== '' && ! collect($recipients)->contains(fn ($r) => $r['email'] === $panelEmail)) {
                $recipients[] = [
                    'email' => $panelEmail,
                    'role' => 'entidad',
                    'user' => $panelUser,
                ];
            }
        } else {
            $adminManagerEmail = trim((string) ($entity->administration?->manager?->user?->email ?? ''));
            if ($adminManagerEmail !== '') {
                $recipients[] = [
                    'email' => $adminManagerEmail,
                    'role' => 'gestor_administracion',
                    'user' => $entity->administration?->manager?->user,
                ];
            }
        }

        return $recipients;
    }
}

