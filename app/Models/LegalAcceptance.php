<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_REGISTRO_ACEPTACION_TCU = 'REGISTRO_ACEPTACION_TCU';

    public const ACTION_PANEL_ACEPTACION_MARCO_LEGAL = 'PANEL_ACEPTACION_MARCO_LEGAL';

    public const ACTION_COOKIES_ACEPTACION = 'COOKIES_ACEPTACION';

    public const ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE = 'ACEPTACION_ROL_GESTOR_RESPONSABLE';

    public const ACTION_ACEPTACION_ROL_GESTOR = 'ACEPTACION_ROL_GESTOR';

    public const ACTION_ACEPTACION_ROL_VENDEDOR = 'ACEPTACION_ROL_VENDEDOR';

    public const ACTION_COBRO_PREMIO_CONFIRMADO = 'COBRO_PREMIO_CONFIRMADO';

    public const ACTION_DONACION_PREMIO_CONFIRMADA = 'DONACION_PREMIO_CONFIRMADA';

    public const ACTION_LIQUIDACION_DEFINITIVA_CONFIRMADA = 'LIQUIDACION_DEFINITIVA_CONFIRMADA';

    public const ACTION_SOLICITUD_BAJA_CUENTA = 'SOLICITUD_BAJA_CUENTA';

    public const ACTION_VENTA_DIGITAL_TERMINOS = 'VENTA_DIGITAL_TERMINOS';

    public const ACTION_CONTRATO_SAAS_ADMINISTRACION = 'CONTRATO_SAAS_ADMINISTRACION';

    public const ACTION_CONTRATO_MARCO_ENTIDAD = 'CONTRATO_MARCO_ENTIDAD';

    public const ACTION_ACEPTACION_RECIBO_PARTICIPACIONES = 'ACEPTACION_RECIBO_PARTICIPACIONES';

    public const RESULT_ACEPTADO = 'ACEPTADO';

    public const RESULT_RECHAZADO = 'RECHAZADO';

    public const CHANNEL_WEB = 'WEB';

    public const CHANNEL_WEB_ENTIDAD = 'WEB_ENTIDAD';

    public const CHANNEL_APP_IOS = 'APP_IOS';

    public const CHANNEL_APP_ANDROID = 'APP_ANDROID';

    protected $fillable = [
        'user_id',
        'action',
        'result',
        'version',
        'text_hash',
        'entity_id',
        'lottery_id',
        'administration_id',
        'channel',
        'ip_address',
        'user_agent',
        'context',
        'accepted_at',
    ];

    protected $casts = [
        'context' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rechazos de invitación a gestor visibles en la ficha de entidad.
     *
     * @return \Illuminate\Support\Collection<int, array{at: \Illuminate\Support\Carbon|null, email: string, name: string, role_label: string, is_primary: bool}>
     */
    public static function managerInvitationRejectionsForEntity(int $entityId, int $limit = 10): \Illuminate\Support\Collection
    {
        $rows = static::query()
            ->where('entity_id', $entityId)
            ->where('result', self::RESULT_RECHAZADO)
            ->whereIn('action', [
                self::ACTION_ACEPTACION_ROL_GESTOR,
                self::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE,
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(function (self $row) {
            $ctx = is_array($row->context) ? $row->context : [];
            $email = trim((string) ($ctx['manager_email'] ?? ''));
            $name = trim((string) ($ctx['manager_name'] ?? ''));
            $managerId = (int) ($ctx['manager_id'] ?? 0);

            if ($email === '' && $row->user_id) {
                $email = trim((string) (User::query()->where('id', $row->user_id)->value('email') ?? ''));
            }

            // Casos antiguos: el usuario se borró y el context no tenía email → recuperar del log de invitación.
            if ($email === '' && $managerId > 0 && class_exists(EmailCommunicationLog::class)) {
                $email = trim((string) (EmailCommunicationLog::query()
                    ->where('message_type', 'entity_manager_invitation')
                    ->where(function ($q) use ($managerId) {
                        $q->where('mail_payload->manager_id', $managerId)
                            ->orWhere('mail_payload->manager_id', (string) $managerId);
                    })
                    ->orderByDesc('id')
                    ->value('recipient_email') ?? ''));
            }

            $isPrimary = (bool) ($ctx['is_primary'] ?? false)
                || (bool) ($ctx['pending_primary'] ?? false)
                || ($ctx['role_type'] ?? '') === 'gestor_responsable'
                || $row->action === self::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE;

            return [
                'at' => $row->accepted_at ?? $row->created_at,
                'email' => $email !== '' ? $email : '—',
                'name' => $name !== '' ? $name : '',
                'role_label' => $isPrimary ? 'Gestor responsable' : 'Gestor',
                'is_primary' => $isPrimary,
            ];
        })->values();
    }
}
