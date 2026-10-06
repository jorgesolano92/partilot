<?php

namespace App\Http\Controllers;

use App\Models\EmailCommunicationLog;
use App\Services\CommunicationEmailService;

class CommunicationEmailController extends Controller
{
    public function __construct(
        private readonly CommunicationEmailService $communicationEmailService,
    ) {
    }

    public function index()
    {
        $logs = EmailCommunicationLog::query()
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        if (auth()->check() && !auth()->user()?->isSuperAdmin()) {
            $accessibleEntityIds = auth()->user()->accessibleEntityIds();

            $logs = $logs->filter(function (EmailCommunicationLog $log) use ($accessibleEntityIds) {
                // Las entidades no deben ver comunicaciones enviadas por superadmin.
                // (Ej. "gestor responsable confirmado", que llega como gestor-entidad.)
                if (auth()->user()?->isEntity() && !auth()->user()?->isAdministration() && (string) $log->sender_type === 'superadmin') {
                    return false;
                }

                return $this->userCanAccessLog($log, $accessibleEntityIds);
            })->values();
        }

        return view('communications.index', compact('logs'));
    }

    public function preview(int $id)
    {
        $log = EmailCommunicationLog::query()->findOrFail($id);
        $this->ensureUserCanAccessLog($log);

        try {
            $preview = $this->communicationEmailService->previewLog(
                $log,
                revealSecrets: (bool) auth()->user()?->isSuperAdmin()
            );

            return response()->json([
                'success' => true,
                'subject' => $preview['subject'],
                'html' => $preview['html'],
                'recipient_email' => $log->recipient_email,
                'message_type' => $log->displayMessageType(),
                'date' => $log->displayEffectiveDate()?->format('d/m/Y H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo reconstruir el contenido del email: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function resend(int $id)
    {
        $log = EmailCommunicationLog::query()->findOrFail($id);
        $this->ensureUserCanAccessLog($log);

        $this->communicationEmailService->resendLog($log);

        return redirect()->route('communications.index')
            ->with('success', 'Email reenviado correctamente (si estaba soportado para reenviar).');
    }

    public function destroy(int $id)
    {
        $log = EmailCommunicationLog::query()->findOrFail($id);
        $this->ensureUserCanAccessLog($log);
        $log->delete(); // “delete normal” (borrado real)

        return redirect()->route('communications.index')
            ->with('success', 'Registro de comunicación eliminado.');
    }

    private function ensureUserCanAccessLog(EmailCommunicationLog $log): void
    {
        $user = auth()->user();
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        if (! $this->userCanAccessLog($log, $user->accessibleEntityIds())) {
            abort(403, 'No tienes permiso para acceder a este registro.');
        }
    }

    /**
     * @param  array<int>  $accessibleEntityIds
     */
    private function userCanAccessLog(EmailCommunicationLog $log, array $accessibleEntityIds): bool
    {
        $contextEntityId = (int) (($log->context['entity_id'] ?? 0) ?: 0);

        return $contextEntityId > 0 && in_array($contextEntityId, $accessibleEntityIds, true);
    }
}

