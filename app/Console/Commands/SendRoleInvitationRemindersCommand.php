<?php

namespace App\Console\Commands;

use App\Models\Manager;
use App\Models\Seller;
use App\Services\RoleLegalNotificationService;
use Illuminate\Console\Command;

class SendRoleInvitationRemindersCommand extends Command
{
    protected $signature = 'sipart:send-role-invitation-reminders';

    protected $description = 'Envía recordatorios G1b/G4/G5 tras 48h sin respuesta a invitaciones de rol';

    public function handle(RoleLegalNotificationService $notifications): int
    {
        $threshold = now()->subHours(48);
        $sent = 0;

        Manager::query()
            ->whereNotNull('confirmation_token')
            ->whereNotNull('confirmation_sent_at')
            ->where('confirmation_sent_at', '<=', $threshold)
            ->whereNull('role_invitation_reminder_sent_at')
            ->with(['user', 'entity'])
            ->orderBy('id')
            ->each(function (Manager $manager) use ($notifications, &$sent) {
                $notifications->sendManagerInvitationReminder($manager);
                $sent++;
            });

        Seller::query()
            ->where('status', Seller::STATUS_PENDING)
            ->whereNotNull('confirmation_token')
            ->whereNotNull('confirmation_sent_at')
            ->where('confirmation_sent_at', '<=', $threshold)
            ->whereNull('role_invitation_reminder_sent_at')
            ->with(['user', 'entities'])
            ->orderBy('id')
            ->each(function (Seller $seller) use ($notifications, &$sent) {
                $notifications->sendSellerInvitationReminder($seller);
                $sent++;
            });

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
