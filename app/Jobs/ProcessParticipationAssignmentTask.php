<?php

namespace App\Jobs;

use App\Models\BackgroundTask;
use App\Models\Seller;
use App\Services\ParticipationAssignmentReceiptService;
use App\Services\BackgroundTaskService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessParticipationAssignmentTask implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly string $taskUuid)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(BackgroundTaskService $backgroundTaskService): void
    {
        $task = BackgroundTask::query()->where('uuid', $this->taskUuid)->first();
        if (! $task) {
            return;
        }

        $payload = (array) ($task->payload ?? []);
        $sellerId = (int) ($payload['seller_id'] ?? 0);
        $participations = $payload['participations'] ?? [];

        if ($sellerId <= 0 || ! is_array($participations) || empty($participations)) {
            throw new \RuntimeException('Payload inválido para asignación.');
        }

        $seller = Seller::with('user')->findOrFail($sellerId);
        if ((int) $seller->status !== Seller::STATUS_ACTIVE) {
            throw new \RuntimeException('El vendedor no está activo.');
        }

        $total = count($participations);
        $backgroundTaskService->markRunning($task, $total);

        $createdBy = $task->requested_by_user_id
            ? \App\Models\User::find((int) $task->requested_by_user_id)
            : null;

        $batch = app(ParticipationAssignmentReceiptService::class)->processAssignmentBatch(
            $seller,
            $participations,
            $createdBy
        );

        $backgroundTaskService->updateProgress($task, $total, $total);

        $messageParts = [];
        if (($batch['proposal_count'] ?? 0) > 0) {
            $messageParts[] = 'Propuesta de recibo enviada por email ('.$batch['proposal_count'].' participación(es) física(s)).';
        }
        if (($batch['assigned_count'] ?? 0) > 0) {
            $messageParts[] = 'Asignadas '.$batch['assigned_count'].' participación(es) digitales.';
        }

        $backgroundTaskService->complete($task, [
            'seller_id' => $seller->id,
            'requested' => $total,
            'assigned' => (int) ($batch['assigned_count'] ?? 0),
            'proposal_count' => (int) ($batch['proposal_count'] ?? 0),
            'proposal_id' => $batch['proposal']?->id,
            'omitted' => max(0, $total - (int) ($batch['assigned_count'] ?? 0) - (int) ($batch['proposal_count'] ?? 0)),
            'message' => $messageParts !== [] ? implode(' ', $messageParts) : 'Asignación procesada en segundo plano.',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $task = BackgroundTask::query()->where('uuid', $this->taskUuid)->first();
        if (! $task) {
            return;
        }
        app(BackgroundTaskService::class)->fail($task, $exception->getMessage());
    }
}
