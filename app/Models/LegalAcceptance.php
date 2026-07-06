<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_REGISTRO_ACEPTACION_TCU = 'REGISTRO_ACEPTACION_TCU';

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
}
