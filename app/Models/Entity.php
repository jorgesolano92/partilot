<?php

namespace App\Models;

use App\Support\HtmlText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'administration_id',
        'image',
        'name',
        'province',
        'city',
        'postal_code',
        'address',
        'nif_cif',
        'phone',
        'email',
        'comments',
        'status',
        'billing_iban',
        'entity_pays_management_fee',
        'entity_pays_print_fee',
        'stripe_customer_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'entity_pays_management_fee' => 'boolean',
        'entity_pays_print_fee' => 'boolean',
    ];

    protected function name(): Attribute
    {
        return $this->htmlDecodedTextAttribute();
    }

    protected function city(): Attribute
    {
        return $this->htmlDecodedTextAttribute();
    }

    protected function address(): Attribute
    {
        return $this->htmlDecodedTextAttribute();
    }

    protected function comments(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => HtmlText::decode($value),
            set: fn (?string $value) => HtmlText::sanitizePlainText($value),
        );
    }

    protected function province(): Attribute
    {
        return $this->htmlDecodedTextAttribute();
    }

    private function htmlDecodedTextAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => HtmlText::decode($value),
            set: fn (?string $value) => HtmlText::decode($value),
        );
    }

    /**
     * Relación con Administration
     */
    public function administration()
    {
        return $this->belongsTo(Administration::class);
    }

    /**
     * Relación con Manager (singular - devuelve el gestor principal)
     */
    public function manager()
    {
        return $this->hasOne(Manager::class,'entity_id','id')->where('is_primary', true);
    }

    /**
     * Relación con Managers (plural)
     */
    public function managers()
    {
        return $this->hasMany(Manager::class,'entity_id','id');
    }

    /**
     * Relación con Reservas
     */
    public function reserves()
    {
        return $this->hasMany(Reserve::class);
    }

    public function lotteryPrizeSettings()
    {
        return $this->hasMany(EntityLotteryPrizeSetting::class);
    }

    /**
     * Relación con los resultados de escrutinio
     */
    public function scrutinyResults()
    {
        return $this->hasMany(ScrutinyEntityResult::class);
    }

    /**
     * Relación con Sellers (Many to Many)
     */
    public function sellers()
    {
        return $this->belongsToMany(Seller::class, 'entity_seller')
            ->withTimestamps();
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
     * Scope para filtrar entidades accesibles por usuario.
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $entityIds = $user->accessibleEntityIds();

        // Filtrado por permiso según módulo para usuarios entidad.
        if ($user->isEntity() && !$user->isAdministration()) {
            if (request()->routeIs('sellers.*')) {
                $entityIds = $user->accessibleEntityIdsByPermission('sellers');
            } elseif (request()->routeIs('design.*')) {
                $entityIds = $user->accessibleEntityIdsByPermission('design');
            } elseif (request()->routeIs('configuration.*') || request()->routeIs('sepa-payments.*')) {
                $entityIds = $user->accessibleEntityIdsByPermission('payments');
            }
        }

        if (empty($entityIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $entityIds);
    }

    /** Etiqueta del pagador de la cuota de gestión PARTILOT según Switch 1. */
    public function managementFeePayerLabel(): string
    {
        return $this->entity_pays_management_fee ? 'Entidad' : 'Administración';
    }

    /** Etiqueta del pagador de diseño e impresión según Switch 2. */
    public function printFeePayerLabel(): string
    {
        return $this->entity_pays_print_fee ? 'Entidad' : 'Administración';
    }
}
