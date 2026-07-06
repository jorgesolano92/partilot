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
        'status' => 'integer',
        'prepago_api_key' => 'encrypted',
        'prepago_use_partilot_default' => 'boolean',
        'prepago_integration_enabled' => 'boolean',
        'billing_sepa_mandate_signed_at' => 'date',
        'contract_sent_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

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
        } elseif ($this->status == 1) {
            return 'Activo';
        } else {
            return 'Inactivo';
        }
    }

    /**
     * Obtener el estado como clase CSS
     */
    public function getStatusClassAttribute()
    {
        if ($this->status === null || $this->status === -1) {
            return 'secondary';
        } elseif ($this->status == 1) {
            return 'success';
        } else {
            return 'danger';
        }
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
