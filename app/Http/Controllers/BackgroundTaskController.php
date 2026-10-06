<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDevolutionDeleteTask;
use App\Jobs\ProcessDevolutionTask;
use App\Jobs\ProcessParticipationAssignmentTask;
use App\Jobs\ProcessParticipationCreationTask;
use App\Models\BackgroundTask;
use App\Services\BackgroundTaskService;
use Illuminate\Http\Request;

class BackgroundTaskController extends Controller
{
    public function __construct(private readonly BackgroundTaskService $backgroundTaskService)
    {
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $data = $request->validate([
            'type' => 'required|string|in:' . implode(',', BackgroundTask::supportedTypes()),
            'payload' => 'nullable|array',
            'entity_id' => 'nullable|integer|exists:entities,id',
            'administration_id' => 'nullable|integer|exists:administrations,id',
            'set_id' => 'nullable|integer|exists:sets,id',
            'resource_key' => 'nullable|string|max:120',
        ]);

        $payload = (array) ($data['payload'] ?? []);
        $type = (string) $data['type'];
        if ($type === BackgroundTask::TYPE_PARTICIPATION_CREATION) {
            if (empty($payload['design_format_id'])) {
                return response()->json(['message' => 'Para creación se requiere payload.design_format_id.'], 422);
            }
            if (empty($data['set_id']) && !empty($payload['set_id'])) {
                $data['set_id'] = (int) $payload['set_id'];
            }
        } elseif ($type === BackgroundTask::TYPE_PARTICIPATION_ASSIGNMENT) {
            if (empty($payload['seller_id']) || empty($payload['participations']) || !is_array($payload['participations'])) {
                return response()->json(['message' => 'Para asignación se requiere payload.seller_id y payload.participations[].'], 422);
            }
            if (empty($data['set_id']) && !empty($payload['set_id'])) {
                $data['set_id'] = (int) $payload['set_id'];
            }
        } elseif ($type === BackgroundTask::TYPE_DEVOLUTION) {
            if (empty($payload)) {
                return response()->json(['message' => 'Para devoluciones se requiere payload con los datos de la operación.'], 422);
            }
            if (empty($data['entity_id']) && !empty($payload['entity_id'])) {
                $data['entity_id'] = (int) $payload['entity_id'];
            }
        } elseif ($type === BackgroundTask::TYPE_DEVOLUTION_DELETE) {
            if (empty($payload['devolution_id'])) {
                return response()->json(['message' => 'Para eliminar en segundo plano se requiere payload.devolution_id.'], 422);
            }
            if (empty($data['entity_id']) && !empty($payload['entity_id'])) {
                $data['entity_id'] = (int) $payload['entity_id'];
            }
        }

        if (empty($data['resource_key'])) {
            $resourceKey = null;
            if (!empty($data['set_id'])) {
                $resourceKey = 'set:' . (int) $data['set_id'];
            } elseif (!empty($data['entity_id'])) {
                $resourceKey = 'entity:' . (int) $data['entity_id'];
            }
            if ($resourceKey) {
                $data['resource_key'] = $resourceKey;
            }
        }

        $task = $this->backgroundTaskService->createTask($user, $data);
        $this->dispatchTaskJob($task);

        return response()->json([
            'task_uuid' => $task->uuid,
            'status' => $task->status,
            'poll_url' => route('background-tasks.show', ['uuid' => $task->uuid]),
            'task' => $this->presentTask($task),
        ]);
    }

    public function show(Request $request, string $uuid)
    {
        $task = BackgroundTask::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorizeAccess($request, $task);

        return response()->json($this->presentTask($task));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $limit = min(max((int) $request->integer('limit', 20), 1), 100);
        $query = BackgroundTask::query()->latest('id');
        if (! $user->isSuperAdmin() || $request->boolean('mine', true)) {
            $query->where('requested_by_user_id', $user->id);
        }

        if ($request->boolean('active')) {
            $query->whereIn('status', [
                BackgroundTask::STATUS_PENDING,
                BackgroundTask::STATUS_RUNNING,
            ]);
        }

        $tasks = $query->limit($limit)->get()->map(fn (BackgroundTask $task) => $this->presentTask($task));

        return response()->json([
            'items' => $tasks,
        ]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $task = BackgroundTask::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorizeAccess($request, $task);

        if (! in_array($task->status, [BackgroundTask::STATUS_PENDING, BackgroundTask::STATUS_RUNNING], true)) {
            return response()->json([
                'message' => 'Solo se pueden cancelar tareas pendientes o en ejecución.',
            ], 422);
        }

        $this->backgroundTaskService->cancel($task);

        return response()->json([
            'message' => 'Tarea cancelada.',
            'task' => $this->presentTask($task->fresh()),
        ]);
    }

    private function authorizeAccess(Request $request, BackgroundTask $task): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado.');
        abort_unless($user->isSuperAdmin() || (int) $task->requested_by_user_id === (int) $user->id, 403, 'No autorizado.');
    }

    private function presentTask(BackgroundTask $task): array
    {
        return [
            'uuid' => $task->uuid,
            'type' => $task->type,
            'status' => $task->status,
            'resource_key' => $task->resource_key,
            'entity_id' => $task->entity_id,
            'administration_id' => $task->administration_id,
            'set_id' => $task->set_id,
            'progress_total' => (int) $task->progress_total,
            'progress_done' => (int) $task->progress_done,
            'progress_percent' => (int) $task->progress_percent,
            'result_summary' => $task->result_summary,
            'error_message' => $task->error_message,
            'started_at' => optional($task->started_at)?->toIso8601String(),
            'finished_at' => optional($task->finished_at)?->toIso8601String(),
            'created_at' => optional($task->created_at)?->toIso8601String(),
        ];
    }

    private function dispatchTaskJob(BackgroundTask $task): void
    {
        if ($task->status !== BackgroundTask::STATUS_PENDING) {
            return;
        }

        match ($task->type) {
            BackgroundTask::TYPE_PARTICIPATION_CREATION => ProcessParticipationCreationTask::dispatch($task->uuid),
            BackgroundTask::TYPE_PARTICIPATION_ASSIGNMENT => ProcessParticipationAssignmentTask::dispatch($task->uuid),
            BackgroundTask::TYPE_DEVOLUTION => ProcessDevolutionTask::dispatch($task->uuid),
            BackgroundTask::TYPE_DEVOLUTION_DELETE => ProcessDevolutionDeleteTask::dispatch($task->uuid),
            default => null,
        };
    }
}
