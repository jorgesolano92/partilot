<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\AdministrationLotteryScrutiny;
use App\Models\Participation;
use App\Models\User;
use App\Services\AppInboxNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyWalletStorageAfterScrutinyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scrutinyId
    ) {}

    public function handle(): void
    {
        $scrutiny = AdministrationLotteryScrutiny::query()
            ->with('lottery')
            ->find($this->scrutinyId);

        if (! $scrutiny || ! $scrutiny->lottery_id) {
            return;
        }

        $apiController = app(ApiController::class);
        $inbox = app(AppInboxNotificationService::class);
        $lottery = $scrutiny->lottery;
        $lotteryName = $lottery?->name ?? 'sorteo';

        $byUser = [];

        $participations = Participation::query()
            ->where('wallet_mode', Participation::WALLET_MODE_STORAGE)
            ->whereNotNull('buyer_name')
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $scrutiny->lottery_id))
            ->with(['set.entity', 'set.reserve'])
            ->get();

        foreach ($participations as $participation) {
            $buyer = trim((string) $participation->buyer_name);
            if ($buyer === '' || ! ctype_digit($buyer)) {
                continue;
            }

            $ref = $this->referenceFromParticipation($participation);
            if ($ref === '') {
                continue;
            }

            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $userId = (int) $buyer;
            $byUser[$userId] = ($byUser[$userId] ?? 0) + 1;
        }

        foreach ($byUser as $userId => $count) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $title = 'Resultados en tu almacén';
            $message = "Ya puedes consultar los resultados de {$count} participación(es) guardadas en almacén del sorteo {$lotteryName}.";

            try {
                $inbox->notifyUser(
                    recipientUserId: $userId,
                    entityId: null,
                    administrationId: (int) $scrutiny->administration_id,
                    senderId: null,
                    kind: 'almacen_resultados',
                    title: $title,
                    message: $message,
                    meta: [
                        'scrutiny_id' => $scrutiny->id,
                        'lottery_id' => $scrutiny->lottery_id,
                        'storage_count' => $count,
                    ],
                    sendPush: true
                );
            } catch (\Throwable $e) {
                Log::warning('NotifyWalletStorageAfterScrutinyJob inbox: '.$e->getMessage());
            }

            if ($user->email) {
                try {
                    Mail::raw($message, function ($mail) use ($user, $title) {
                        $mail->to($user->email)->subject($title);
                    });
                } catch (\Throwable $e) {
                    Log::warning('NotifyWalletStorageAfterScrutinyJob email: '.$e->getMessage());
                }
            }
        }
    }

    protected function referenceFromParticipation(Participation $participation): string
    {
        $participation->loadMissing('set');
        $set = $participation->set;
        if (! $set || ! is_array($set->tickets)) {
            return '';
        }

        foreach ($set->tickets as $ticket) {
            if (isset($ticket['n']) && (int) $ticket['n'] === (int) $participation->participation_number) {
                return (string) ($ticket['r'] ?? '');
            }
        }

        return '';
    }
}
