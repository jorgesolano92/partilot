<?php

namespace App\Http\Controllers;

use App\Models\LotteryDeadlineAdminDecision;
use App\Services\LotteryDeadlineClosureService;
use App\Services\LotteryDeadlineReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LotteryDeadlineAdminDecisionController extends Controller
{
    public function assumeDebt(
        Request $request,
        LotteryDeadlineReminderService $reminderService,
        LotteryDeadlineClosureService $closureService
    ): JsonResponse {
        return $this->storeDecision(
            $request,
            $reminderService,
            $closureService,
            LotteryDeadlineAdminDecision::DECISION_ASSUME_DEBT
        );
    }

    public function annul(
        Request $request,
        LotteryDeadlineReminderService $reminderService,
        LotteryDeadlineClosureService $closureService
    ): JsonResponse {
        return $this->storeDecision(
            $request,
            $reminderService,
            $closureService,
            LotteryDeadlineAdminDecision::DECISION_ANNUL
        );
    }

    private function storeDecision(
        Request $request,
        LotteryDeadlineReminderService $reminderService,
        LotteryDeadlineClosureService $closureService,
        string $decision
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isAdministration() || $user->isSuperAdmin()) {
            return response()->json(['ok' => false, 'message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'lottery_id' => ['required', 'integer', 'exists:lotteries,id'],
            'confirm' => ['accepted'],
        ]);

        $entityId = (int) $validated['entity_id'];
        $lotteryId = (int) $validated['lottery_id'];

        if (! $user->canAccessEntity($entityId)) {
            return response()->json(['ok' => false, 'message' => 'Sin acceso a esta entidad.'], 403);
        }

        if (! $reminderService->isAdminDecisionRequired($entityId, $lotteryId)) {
            return response()->json(['ok' => false, 'message' => 'No hay decisión pendiente para este sorteo hoy.'], 422);
        }

        if (LotteryDeadlineAdminDecision::hasDecision($entityId, $lotteryId)) {
            return response()->json(['ok' => false, 'message' => 'Ya existe una decisión registrada.'], 422);
        }

        try {
            $closureResult = DB::transaction(function () use ($entityId, $lotteryId, $decision, $user, $closureService) {
                LotteryDeadlineAdminDecision::create([
                    'entity_id' => $entityId,
                    'lottery_id' => $lotteryId,
                    'decision' => $decision,
                    'user_id' => $user->id,
                ]);

                return $closureService->applyAdminDecision($entityId, $lotteryId, $decision, true);
            });
        } catch (\Throwable $e) {
            Log::error('Error aplicando decisión de fecha límite: '.$e->getMessage(), [
                'entity_id' => $entityId,
                'lottery_id' => $lotteryId,
                'decision' => $decision,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo aplicar la decisión: '.$e->getMessage(),
            ], 500);
        }

        if (($closureResult['stat_key'] ?? null) === 'failed') {
            return response()->json([
                'ok' => false,
                'message' => $closureResult['message'] ?? 'Error al procesar el cierre.',
            ], 500);
        }

        if ($decision === LotteryDeadlineAdminDecision::DECISION_ASSUME_DEBT) {
            $message = 'Decisión aplicada: se asume la deuda. Las participaciones no devueltas se liquidan como vendidas.';
        } else {
            $message = 'Decisión aplicada: participaciones pendientes anuladas por impago. Al escanear mostrarán que no son válidas.';
        }

        if (! empty($closureResult['message'])) {
            $message .= ' '.$closureResult['message'];
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'closure' => $closureResult,
        ]);
    }
}
