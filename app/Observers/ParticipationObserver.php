<?php

namespace App\Observers;

use App\Models\Participation;
use App\Models\ParticipationActivityLog;
use App\Services\FirebaseServiceModern;
use App\Services\ParticipationOwnerService;
use Illuminate\Support\Facades\Log;

class ParticipationObserver
{
    public function __construct(
        protected FirebaseServiceModern $firebaseServiceModern
    ) {
    }
    /**
     * Handle the Participation "created" event.
     */
    public function created(Participation $participation): void
    {
        // Registrar la creación de la participación
        ParticipationActivityLog::log($participation->id, 'created', [
            'entity_id' => $participation->entity_id,
            'description' => "Participación #{$participation->participation_number} creada",
            'metadata' => [
                'participation_code' => $participation->participation_code,
                'book_number' => $participation->book_number,
                'set_id' => $participation->set_id,
                'design_format_id' => $participation->design_format_id,
            ],
        ]);
    }

    /**
     * Handle the Participation "updated" event.
     */
    public function updated(Participation $participation): void
    {
        $changes = $participation->getChanges();
        $original = $participation->getOriginal();

        // Ignorar actualizaciones de timestamps
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $statusChanged = array_key_exists('status', $changes);
        $sellerChanged = array_key_exists('seller_id', $changes);
        $oldStatus = $original['status'] ?? null;
        $newStatus = $changes['status'] ?? null;
        $oldSellerId = $original['seller_id'] ?? null;
        $newSellerId = $changes['seller_id'] ?? null;

        // PRIORIDAD 1: Detectar cuando se QUITA el seller_id (independientemente del estado)
        // Esto debe evaluarse PRIMERO antes que cualquier otro caso
        if ($sellerChanged && $newSellerId === null && $oldSellerId !== null) {
            ParticipationActivityLog::log($participation->id, 'returned_by_seller', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $oldSellerId,
                'old_seller_id' => $oldSellerId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus ?? $oldStatus, // Usar el estado actual si no cambió
                'description' => "Participación devuelta por el vendedor ID: {$oldSellerId}",
                'metadata' => $changes,
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'returned_by_seller');

            return; // Evitar registros duplicados
        }

        // Reserva de venta digital: el vendedor de la app queda reflejado en la participación
        if ($statusChanged && $newStatus === 'reserva_venta_digital') {
            $sellerId = $newSellerId ?? $participation->seller_id;
            ParticipationActivityLog::log($participation->id, 'status_changed', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $sellerId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => $sellerId
                    ? "Venta digital reservada por el vendedor ID: {$sellerId}"
                    : "Estado cambiado de '{$oldStatus}' a '{$newStatus}'",
                'metadata' => $changes,
            ]);

            return;
        }

        // Caso 1: Asignación a vendedor (status cambia a 'asignada' y se asigna vendedor)
        if ($statusChanged && $newStatus === 'asignada' && $sellerChanged && $newSellerId !== null && $oldSellerId === null) {
            ParticipationActivityLog::log($participation->id, 'assigned', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $newSellerId,
                'new_seller_id' => $newSellerId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Participación asignada al vendedor ID: {$newSellerId}",
                'metadata' => $changes,
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'assigned');
            
            return; // Evitar registros duplicados
        }

        // Vinculación a cartera (buyer_name pasa de vacío a id de usuario)
        if (array_key_exists('buyer_name', $changes)
            && empty($original['buyer_name'])
            && ! empty($changes['buyer_name'])
            && ! ($statusChanged && $newStatus === 'vendida')) {
            $ownerLabel = ParticipationOwnerService::ownerDisplayName($participation);
            ParticipationActivityLog::log($participation->id, 'modified', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'user_id' => ParticipationOwnerService::resolveOwnerUser($participation)?->id ?? auth()->id(),
                'description' => $ownerLabel
                    ? "Participación vinculada a cartera de {$ownerLabel}"
                    : 'Participación vinculada a cartera de usuario',
                'metadata' => array_merge(
                    ParticipationOwnerService::ownerMetadata($participation),
                    ['changes' => $changes]
                ),
            ]);

            return;
        }

        // Caso 2: Participación vendida
        if ($statusChanged && $newStatus === 'vendida') {
            $ownerLabel = ParticipationOwnerService::ownerDisplayName($participation);
            ParticipationActivityLog::log($participation->id, 'sold', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => $ownerLabel
                    ? "Participación vendida — titular: {$ownerLabel}"
                    : 'Participación vendida',
                'metadata' => array_merge($changes, [
                    'sale_amount' => $participation->sale_amount ?? null,
                ], ParticipationOwnerService::ownerMetadata($participation)),
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'sold');
            
            return; // Evitar registros duplicados
        }

        // Caso 4: Devolución a la administración (status cambia a 'devuelta' sin eliminar vendedor)
        if ($statusChanged && $newStatus === 'devuelta' && !($sellerChanged && $newSellerId === null)) {
            ParticipationActivityLog::log($participation->id, 'returned_to_administration', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Participación devuelta a la administración",
                'metadata' => array_merge($changes, [
                    'return_reason' => $participation->return_reason ?? null,
                ]),
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'returned_to_administration');
            
            return; // Evitar registros duplicados
        }

        // Caso 5: Participación anulada
        if ($statusChanged && $newStatus === 'anulada') {
            ParticipationActivityLog::log($participation->id, 'cancelled', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Participación anulada: " . ($participation->cancellation_reason ?? 'Sin motivo especificado'),
                'metadata' => array_merge($changes, [
                    'cancellation_reason' => $participation->cancellation_reason ?? null,
                    'cancelled_by' => $participation->cancelled_by ?? null,
                    'cancellation_date' => $participation->cancellation_date ?? null,
                ]),
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'cancelled');

            return; // Evitar registros duplicados
        }

        // Caso 6: Reasignación de vendedor (cambio de un vendedor a otro)
        if ($sellerChanged && $newSellerId !== null && $oldSellerId !== null && $newSellerId !== $oldSellerId) {
            ParticipationActivityLog::log($participation->id, 'assigned', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $newSellerId,
                'old_seller_id' => $oldSellerId,
                'new_seller_id' => $newSellerId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Participación reasignada del vendedor ID: {$oldSellerId} al vendedor ID: {$newSellerId}",
                'metadata' => $changes,
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'reassigned');
            
            return; // Evitar registros duplicados
        }

        // Caso 7: Asignación simple (sin cambio de estado 'asignada')
        if ($sellerChanged && $newSellerId !== null && $oldSellerId === null && (!$statusChanged || $newStatus !== 'asignada')) {
            ParticipationActivityLog::log($participation->id, 'assigned', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $newSellerId,
                'new_seller_id' => $newSellerId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Participación asignada al vendedor ID: {$newSellerId}",
                'metadata' => $changes,
            ]);
            
            // Enviar notificación
            $this->sendNotification($participation, 'assigned');
            
            return; // Evitar registros duplicados
        }

        // Caso 8: Cambio de estado genérico (no cubierto por casos anteriores)
        if ($statusChanged) {
            ParticipationActivityLog::log($participation->id, 'status_changed', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'description' => "Estado cambiado de '{$oldStatus}' a '{$newStatus}'",
                'metadata' => $changes,
            ]);
            return; // Evitar registros duplicados
        }

        // Caso 9: Modificaciones de datos importantes (comprador, importe, etc.)
        $significantFields = ['buyer_name', 'buyer_phone', 'buyer_email', 'buyer_nif', 'sale_amount'];
        $hasSignificantChanges = false;

        foreach ($significantFields as $field) {
            if (isset($changes[$field])) {
                $hasSignificantChanges = true;
                break;
            }
        }

        if ($hasSignificantChanges) {
            ParticipationActivityLog::log($participation->id, 'modified', [
                'entity_id' => $participation->entity_id,
                'seller_id' => $participation->seller_id,
                'description' => "Información de la participación modificada",
                'metadata' => [
                    'changes' => $changes,
                    'original' => array_intersect_key($original, $changes),
                ],
            ]);
        }
    }

    /**
     * Handle the Participation "deleted" event.
     */
    public function deleted(Participation $participation): void
    {
        // Registrar eliminación
        ParticipationActivityLog::log($participation->id, 'cancelled', [
            'entity_id' => $participation->entity_id,
            'seller_id' => $participation->seller_id,
            'description' => "Participación eliminada del sistema",
            'metadata' => [
                'participation_code' => $participation->participation_code,
                'status' => $participation->status,
            ],
        ]);
    }

    /**
     * Handle the Participation "restored" event.
     */
    public function restored(Participation $participation): void
    {
        // Registrar restauración
        ParticipationActivityLog::log($participation->id, 'modified', [
            'entity_id' => $participation->entity_id,
            'seller_id' => $participation->seller_id,
            'description' => "Participación restaurada",
            'metadata' => [
                'participation_code' => $participation->participation_code,
                'status' => $participation->status,
            ],
        ]);
    }

    /**
     * Handle the Participation "force deleted" event.
     */
    public function forceDeleted(Participation $participation): void
    {
        // No registrar nada ya que la participación y sus logs serán eliminados permanentemente
    }

    /**
     * Enviar notificación a los usuarios correctos según el evento
     */
    private function sendNotification($participation, $event, $data = [])
    {
        try {
            $tokensToNotify = $this->getRelevantUserTokens($participation, $event);
            
            if (empty($tokensToNotify)) {
                Log::info("No hay usuarios para notificar sobre el evento '{$event}'");
                return;
            }

            Log::info("📤 Enviando notificación: {$event}", [
                'participation_id' => $participation->id,
                'participation_code' => $participation->participation_code,
                'usuarios_a_notificar' => count($tokensToNotify)
            ]);

            // Preparar título y mensaje según el evento
            $notification = $this->prepareNotificationContent($participation, $event, $data);
            
            // Enviar a cada usuario
            foreach ($tokensToNotify as $userInfo) {
                try {
                    $this->firebaseServiceModern->sendToDevice(
                        $userInfo['token'],
                        $notification['title'],
                        $notification['body'],
                        array_merge($notification['data'], [
                            'user_id' => (string) $userInfo['user_id'],
                            'user_role' => (string) $userInfo['role'],
                        ])
                    );
                    
                    Log::info("✅ Notificación enviada a {$userInfo['name']} ({$userInfo['role']})");
                } catch (\Exception $e) {
                    Log::error("❌ Error enviando a {$userInfo['name']}: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error general en sendNotification: " . $e->getMessage());
        }
    }

    /**
     * Obtener tokens de usuarios relevantes según el evento
     */
    private function getRelevantUserTokens($participation, $event)
    {
        $tokens = [];
        
        // Cargar relaciones necesarias
        $participation->load(['seller.user.fcmTokens', 'entity.manager.user.fcmTokens']);

        $pushTokensForUser = function ($user, string $role) use (&$tokens): void {
            if (! $user || $user->fcmTokens->isEmpty() || $user->shouldExcludeFromOperationalPushRecipients()) {
                return;
            }
            foreach ($user->fcmTokens as $row) {
                $tokens[] = [
                    'user_id' => $user->id,
                    'token' => $row->token,
                    'name' => $user->name,
                    'role' => $role,
                ];
            }
        };

        // Manager de la entidad
        if ($participation->entity && $participation->entity->manager && $participation->entity->manager->user) {
            $pushTokensForUser($participation->entity->manager->user, 'manager');
        }

        switch ($event) {
            case 'assigned':
            case 'reassigned':
                if ($participation->seller && $participation->seller->user) {
                    $pushTokensForUser($participation->seller->user, 'seller');
                }
                break;

            case 'sold':
                break;

            case 'returned_by_seller':
                if ($participation->seller && $participation->seller->user) {
                    $pushTokensForUser($participation->seller->user, 'seller');
                }
                break;
        }

        // Un mismo dispositivo no debe recibir duplicados
        $uniqueTokens = [];
        $seenTokenValues = [];
        foreach ($tokens as $tokenInfo) {
            if (! in_array($tokenInfo['token'], $seenTokenValues, true)) {
                $uniqueTokens[] = $tokenInfo;
                $seenTokenValues[] = $tokenInfo['token'];
            }
        }

        return $uniqueTokens;
    }

    /**
     * Preparar contenido de la notificación según el evento
     */
    private function prepareNotificationContent($participation, $event, $data = [])
    {
        $participationCode = $participation->participation_code;
        $sellerName = $data['seller_name'] ?? 'desconocido';
        
        $notifications = [
            'assigned' => [
                'title' => '📋 Participación Asignada',
                'body' => "Se te ha asignado la participación #{$participationCode}",
            ],
            'reassigned' => [
                'title' => '🔄 Participación Reasignada',
                'body' => "La participación #{$participationCode} ha sido reasignada",
            ],
            'sold' => [
                'title' => '✅ Participación Vendida',
                'body' => "La participación #{$participationCode} ha sido vendida",
            ],
            'returned_by_seller' => [
                'title' => '↩️ Participación Devuelta',
                'body' => "La participación #{$participationCode} ha sido devuelta por el vendedor",
            ],
            'returned_to_administration' => [
                'title' => '↩️ Participación Devuelta',
                'body' => "La participación #{$participationCode} ha sido devuelta a la administración",
            ],
            'cancelled' => [
                'title' => '❌ Participación Anulada',
                'body' => "La participación #{$participationCode} ha sido anulada",
            ],
        ];

        $content = $notifications[$event] ?? [
            'title' => '📢 Actualización de Participación',
            'body' => "La participación #{$participationCode} ha sido actualizada",
        ];

        $content['data'] = [
            'type' => 'participation_update',
            'event' => $event,
            'participation_id' => (string)$participation->id,
            'participation_code' => $participationCode,
            'entity_id' => (string)$participation->entity_id,
            'timestamp' => now()->toIso8601String(),
        ];

        return $content;
    }
}
