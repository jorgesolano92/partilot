<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Administration extends Model
{
    use HasFactory;

    public const CONTRACT_PENDING = 'pending';

    public const CONTRACT_SIGNED = 'signed';

    /** Estados de ciclo de vida (INC-006): Pendiente=null, Activo=1, Inactivo=0, Bloqueado=3 */
    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_BLOCKED = 3;

    protected $fillable = [
        "web",
        "name",
        "receiving",
        "admin_number",
        "society",
        "nif_cif",
        "province",
        "city",
        "postal_code",
        "address",
        "email",
        "phone",
        "account",
        "status",
        "image",
        "prepago_integration_name",
        "prepago_api_url",
        "prepago_auth_method",
        "prepago_api_prefix",
        "prepago_api_key",
        "prepago_use_partilot_default",
        "prepago_integration_enabled",
        "stripe_customer_id",
        "billing_payment_mode",
        "billing_remittance_frequency",
        "billing_sepa_mandate_id",
        "billing_sepa_mandate_signed_at",
        "contract_status",
        "contract_reference",
        "contract_version",
        "contract_token",
        "contract_sent_at",
        "contract_signed_at",
        "contract_signed_by_user_id",
        "contract_signer_name",
        "contract_signer_nif",
        "contract_pdf_path",
    ];

    protected $casts = [
        // status: no castear a integer — (int) null === 0 y convertiría Pendiente en Inactivo (INC-006).
        'prepago_api_key' => 'encrypted',
        'prepago_use_partilot_default' => 'boolean',
        'prepago_integration_enabled' => 'boolean',
        'billing_sepa_mandate_signed_at' => 'date',
        'contract_sent_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

    /**
     * Normaliza status conservando null = Pendiente.
     */
    public function getStatusAttribute($value)
    {
        if ($value === null || $value === '' || (int) $value === -1) {
            return null;
        }

        return (int) $value;
    }

    public function setStatusAttribute($value): void
    {
        if ($value === null || $value === '' || (int) $value === -1) {
            $this->attributes['status'] = null;

            return;
        }

        $this->attributes['status'] = (int) $value;
    }

    protected $hidden = [
        'prepago_api_key',
    ];

    /**
     * Relación con Entity
     */
    public function entities()
    {
        return $this->hasMany(Entity::class);
    }

    public function billingCharges()
    {
        return $this->hasMany(BillingCharge::class);
    }

    public function billingDirectDebitOrders()
    {
        return $this->hasMany(BillingDirectDebitOrder::class);
    }

    public function debtorIban(): string
    {
        $digits = preg_replace('/\D/', '', (string) ($this->account ?? ''));

        return strlen($digits) === 22 ? 'ES'.$digits : '';
    }

    public function sepaMandateId(): string
    {
        $configured = trim((string) ($this->billing_sepa_mandate_id ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'PARTILOT-ADM-'.$this->id;
    }

    public function manager()
    {
        return $this->hasOne(Manager::class,'administration_id','id')->where('is_primary', true);
    }

    /**
     * Nombre para la cuenta de usuario del panel: solo nombre comercial.
     * Si falta, se usa sociedad; si ambos faltan, "Administración".
     */
    public static function panelDisplayNameFromParts(?string $commercialName, ?string $society): string
    {
        $name = trim((string) $commercialName);
        if ($name !== '') {
            return $name;
        }
        $soc = trim((string) $society);

        return $soc !== '' ? $soc : 'Administración';
    }

    /**
     * Usuario de acceso al panel (fijo): receptor (5 dígitos) + 3 últimos del nº administración (Administración de Lotería).
     * Punto de venta mixto (sin número de administración): solo el receptor.
     */
    public static function panelLoginUsernameFromParts(?string $receiving, ?string $adminNumber): string
    {
        $recvDigits = preg_replace('/\D/', '', (string) $receiving);
        $recvDigits = substr(str_pad($recvDigits, 5, '0', STR_PAD_LEFT), -5);

        $adm = trim((string) $adminNumber);
        if ($adm === '') {
            return $recvDigits;
        }

        $numDigits = preg_replace('/\D/', '', $adm);
        $last3 = substr(str_pad($numDigits, 3, '0', STR_PAD_LEFT), -3);

        return $recvDigits.$last3;
    }

    /**
     * True si el usuario de panel sigue en formato "solo receptor" y ahora hay Nº Administración:
     * conviene regenerar a receptor + 3 últimos dígitos.
     */
    public static function panelLoginUsernameNeedsAdminNumberUpgrade(
        ?string $currentUsername,
        ?string $receiving,
        ?string $adminNumber
    ): bool {
        if (trim((string) $adminNumber) === '') {
            return false;
        }

        $current = trim((string) $currentUsername);
        if ($current === '') {
            return true;
        }

        $receptorOnly = self::panelLoginUsernameFromParts($receiving, null);
        if ($current === $receptorOnly) {
            return true;
        }

        // Colisión histórica: 12345-1, 12345-2, …
        return (bool) preg_match('/^'.preg_quote($receptorOnly, '/').'-\d+$/', $current);
    }

    /**
     * Si el login quedó solo con el receptor (sin nº administración al crear) y ahora
     * hay Nº Administración, actualiza `panel_login_username` del usuario panel.
     */
    public function syncPanelLoginUsernameAfterAdminNumber(?User $panelUser): ?string
    {
        if (! $panelUser) {
            return null;
        }

        $receiving = (string) ($this->receiving ?? '');
        $adminNumber = (string) ($this->admin_number ?? '');
        if (! self::panelLoginUsernameNeedsAdminNumberUpgrade(
            $panelUser->panel_login_username,
            $receiving,
            $adminNumber
        )) {
            return null;
        }

        $base = self::panelLoginUsernameFromParts($receiving, $adminNumber);
        $username = self::ensureUniquePanelLoginUsername($base, (int) $panelUser->id);
        $panelUser->forceFill(['panel_login_username' => $username])->save();

        return $username;
    }

    /**
     * Garantizar unicidad de `panel_login_username` en users.
     */
    public static function ensureUniquePanelLoginUsername(string $base, ?int $exceptUserId = null): string
    {
        $candidate = $base;
        $n = 0;

        while (true) {
            $q = User::query()->where('panel_login_username', $candidate);
            if ($exceptUserId !== null) {
                $q->where('id', '!=', $exceptUserId);
            }
            if (! $q->exists()) {
                return $candidate;
            }
            $n++;
            $candidate = $base.'-'.$n;
        }
    }

    /**
     * Relación con los escrutinios de lotería de esta administración
     */
    public function lotteryScrutinies()
    {
        return $this->hasMany(AdministrationLotteryScrutiny::class);
    }

    /**
     * Obtener el estado como texto
     */
    public function getStatusTextAttribute()
    {
        if ($this->status === null || $this->status === -1) {
            return 'Pendiente';
        }

        return match ((int) $this->status) {
            self::STATUS_ACTIVE => 'Activo',
            self::STATUS_BLOCKED => 'Bloqueado',
            default => 'Inactivo',
        };
    }

    /**
     * Obtener el estado como clase CSS
     */
    public function getStatusClassAttribute()
    {
        if ($this->status === null || $this->status === -1) {
            return 'secondary';
        }

        return match ((int) $this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_BLOCKED => 'warning',
            default => 'danger',
        };
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === null || (int) $this->status === -1;
    }

    public function isBlocked(): bool
    {
        return (int) $this->status === self::STATUS_BLOCKED;
    }

    public function isInactive(): bool
    {
        return (int) $this->status === self::STATUS_INACTIVE;
    }

    /**
     * URL pública del logotipo en public/images/ (o null si no hay archivo).
     */
    public function logoPublicUrl(): ?string
    {
        $image = trim((string) ($this->image ?? ''));
        if ($image === '') {
            return null;
        }

        if (! is_file(public_path('images/'.$image))) {
            return null;
        }

        return asset('images/'.$image);
    }

    /**
     * Scope para filtrar administraciones accesibles por usuario.
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $administrationIds = $user->accessibleAdministrationIds();

        if (empty($administrationIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $administrationIds);
    }

    public function hasSignedSaasContract(): bool
    {
        return $this->contract_status === self::CONTRACT_SIGNED;
    }

    public function getContractStatusTextAttribute(): string
    {
        return match ($this->contract_status) {
            self::CONTRACT_SIGNED => 'Firmado',
            self::CONTRACT_PENDING => 'Pendiente de firma',
            default => 'Pendiente de firma',
        };
    }

    public function getContractStatusClassAttribute(): string
    {
        return $this->hasSignedSaasContract() ? 'success' : 'warning';
    }
}
