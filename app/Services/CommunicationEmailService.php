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
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
            'mail_payload' => $mailPayload,
            'status' => EmailCommunicationLog::STATUS_PENDING,
            'last_attempt_at' => now(),
            'context' => $context,
        ]);

        try {
            $this->sendFromLogPayload($recipientEmail, $mailClass, $mailPayload);

            $log->update([
                'status' => EmailCommunicationLog::STATUS_SENT,
                'sent_at' => now(),
                'last_attempt_at' => now(),
                'error_message' => null,
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
            $this->sendFromLogPayload($log->recipient_email, $log->mail_class, $log->mail_payload ?? []);

            // Si el envío se hizo correctamente, mantenemos status `resent`.
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
     * Reconstruye el Mailable desde `mail_class` + `mail_payload` (IDs simples).
     *
     * Por ahora solo soporta las 3 clases que hoy están enviándose en backend.
     */
    private function sendFromLogPayload(string $recipientEmail, string $mailClass, array $mailPayload): void
    {
        // Si alguna fila viene con datos inesperados, fallará y el log se marcará como cancelled.
        if ($mailClass === \App\Mail\DesignExternalInvitationMail::class) {
            $invitationId = (int) ($mailPayload['invitation_id'] ?? 0);
            $invitation = DesignExternalInvitation::findOrFail($invitationId);

            Mail::to($recipientEmail)->send(new \App\Mail\DesignExternalInvitationMail($invitation));
            return;
        }

        if ($mailClass === \App\Mail\SellerConfirmationMail::class) {
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $seller = Seller::findOrFail($sellerId);

            Mail::to($recipientEmail)->send(new \App\Mail\SellerConfirmationMail($seller));
            return;
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

            // En la mailable, el foreach es sobre $assignments (array indexado)
            $assignments = array_values($assignmentsBySet);

            Mail::to($recipientEmail)->send(new \App\Mail\ParticipationAssignmentMail($seller, $assignments));
            return;
        }

        if ($mailClass === \App\Mail\ReserveSavedToEntityManagerMail::class) {
            $reserveId = (int) ($mailPayload['reserve_id'] ?? 0);
            $reserve = Reserve::with(['entity.administration', 'entity.manager.user', 'lottery.lotteryType'])->findOrFail($reserveId);

            Mail::to($recipientEmail)->send(new \App\Mail\ReserveSavedToEntityManagerMail($reserve));
            return;
        }

        if ($mailClass === \App\Mail\SetCreatedToEntityManagerMail::class) {
            $setId = (int) ($mailPayload['set_id'] ?? 0);
            $set = Set::with(['entity.manager.user', 'reserve.lottery.lotteryType', 'reserve.lottery'])->findOrFail($setId);

            Mail::to($recipientEmail)->send(new \App\Mail\SetCreatedToEntityManagerMail($set));
            return;
        }

        if ($mailClass === \App\Mail\DevolutionReturnedToAdministrationMail::class) {
            $devolutionId = (int) ($mailPayload['devolution_id'] ?? 0);
            $devolution = Devolution::with(['entity.administration', 'entity.manager.user', 'lottery'])->findOrFail($devolutionId);

            Mail::to($recipientEmail)->send(new \App\Mail\DevolutionReturnedToAdministrationMail($devolution));
            return;
        }

        if ($mailClass === \App\Mail\DevolutionReturnedToEntityManagerMail::class) {
            $devolutionId = (int) ($mailPayload['devolution_id'] ?? 0);
            $devolution = Devolution::with(['entity.administration', 'entity.manager.user', 'lottery'])->findOrFail($devolutionId);

            Mail::to($recipientEmail)->send(new \App\Mail\DevolutionReturnedToEntityManagerMail($devolution));
            return;
        }

        if ($mailClass === \App\Mail\AdministrationWelcomeMail::class) {
            $administrationId = (int) ($mailPayload['administration_id'] ?? 0);
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $administration = Administration::findOrFail($administrationId);
            $user = User::findOrFail($userId);
            $plain = \App\Models\PanelAccessToken::issueForUser($user);
            $magicUrl = route('panel.access', ['token' => $plain], absolute: true);
            Mail::to($recipientEmail)->send(new \App\Mail\AdministrationWelcomeMail($administration, $user, $magicUrl));
            return;
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
            Mail::to($recipientEmail)->send(new \App\Mail\EntityManagerInvitationMail($entity, $user, $manager));
            return;
        }

        if ($mailClass === \App\Mail\EntityResponsibleManagerConfirmedMail::class) {
            $entityId = (int) ($mailPayload['entity_id'] ?? 0);
            $userId = (int) ($mailPayload['responsible_manager_user_id'] ?? 0);
            $entity = \App\Models\Entity::findOrFail($entityId);
            $user = User::findOrFail($userId);
            Mail::to($recipientEmail)->send(new \App\Mail\EntityResponsibleManagerConfirmedMail($entity, $user));
            return;
        }

        if ($mailClass === \App\Mail\SellerSettlementStatusMail::class) {
            $sellerId = (int) ($mailPayload['seller_id'] ?? 0);
            $settlementId = (int) ($mailPayload['settlement_id'] ?? 0);
            $isFullySettled = (bool) ($mailPayload['is_fully_settled'] ?? false);
            $seller = Seller::findOrFail($sellerId);
            $settlement = \App\Models\SellerSettlement::findOrFail($settlementId);
            Mail::to($recipientEmail)->send(new \App\Mail\SellerSettlementStatusMail($seller, $settlement, $isFullySettled));
            return;
        }

        if ($mailClass === \App\Mail\UserWelcomeMail::class) {
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $user = User::findOrFail($userId);
            Mail::to($recipientEmail)->send(new \App\Mail\UserWelcomeMail($user));
            return;
        }

        if ($mailClass === \App\Mail\ParticipationGiftRecipientMail::class) {
            $giftId = (int) ($mailPayload['gift_id'] ?? 0);
            $gift = ParticipationGift::with(['fromUser', 'toUser', 'participation'])->findOrFail($giftId);
            Mail::to($recipientEmail)->send(new \App\Mail\ParticipationGiftRecipientMail($gift));
            return;
        }

        if ($mailClass === \App\Mail\ParticipationGiftSenderMail::class) {
            $giftId = (int) ($mailPayload['gift_id'] ?? 0);
            $gift = ParticipationGift::with(['fromUser', 'toUser', 'participation'])->findOrFail($giftId);
            Mail::to($recipientEmail)->send(new \App\Mail\ParticipationGiftSenderMail($gift));
            return;
        }

        if ($mailClass === \App\Mail\DigitalPurchaseConfirmationMail::class) {
            $buyerId = (int) ($mailPayload['buyer_id'] ?? 0);
            $buyer = User::findOrFail($buyerId);
            $items = $mailPayload['items'] ?? [];
            $total = (float) ($mailPayload['total_amount'] ?? 0);
            Mail::to($recipientEmail)->send(new \App\Mail\DigitalPurchaseConfirmationMail($buyer, $items, $total));
            return;
        }

        if ($mailClass === \App\Mail\TransferCollectionConfirmationMail::class) {
            $collectionId = (int) ($mailPayload['collection_id'] ?? 0);
            $collection = ParticipationCollection::with('user')->findOrFail($collectionId);
            Mail::to($recipientEmail)->send(new \App\Mail\TransferCollectionConfirmationMail($collection));
            return;
        }

        if ($mailClass === \App\Mail\TransferCollectionVerificationMail::class) {
            $collectionId = (int) ($mailPayload['collection_id'] ?? 0);
            $collection = ParticipationCollection::with('user')->findOrFail($collectionId);
            Mail::to($recipientEmail)->send(new \App\Mail\TransferCollectionVerificationMail($collection));
            return;
        }

        if ($mailClass === \App\Mail\DonationCodeConfirmationMail::class) {
            $donationId = (int) ($mailPayload['donation_id'] ?? 0);
            $donation = ParticipationDonation::with('user')->findOrFail($donationId);
            Mail::to($recipientEmail)->send(new \App\Mail\DonationCodeConfirmationMail($donation));
            return;
        }

        if ($mailClass === \App\Mail\DigitalSaleRegistrationInviteMail::class) {
            $pendingId = (int) ($mailPayload['pending_digital_sale_id'] ?? 0);
            $pending = \App\Models\PendingDigitalSale::with(['entity', 'lottery'])->findOrFail($pendingId);
            $pending->ensureLinkCode();
            Mail::to($recipientEmail)->send(new \App\Mail\DigitalSaleRegistrationInviteMail($pending));
            return;
        }

        if ($mailClass === \App\Mail\ParticipationWalletLinkedMail::class) {
            $userId = (int) ($mailPayload['user_id'] ?? 0);
            $participationId = (int) ($mailPayload['participation_id'] ?? 0);
            $user = User::findOrFail($userId);
            $participation = \App\Models\Participation::with(['set.entity', 'set.reserve.lottery'])->findOrFail($participationId);
            Mail::to($recipientEmail)->send(new \App\Mail\ParticipationWalletLinkedMail($user, $participation));
            return;
        }

        if ($mailClass === \App\Mail\ManagementFeePaymentRequestMail::class) {
            $designId = (int) ($mailPayload['design_format_id'] ?? 0);
            $design = \App\Models\DesignFormat::findOrFail($designId);
            Mail::to($recipientEmail)->send(new \App\Mail\ManagementFeePaymentRequestMail($design));
            return;
        }

        throw new \RuntimeException("mail_class no soportado para reenviar: {$mailClass}");
    }
}

