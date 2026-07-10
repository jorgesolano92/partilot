<?php

namespace App\Models;

use App\Services\LotteryDrawDateGuardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lottery extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'draw_date',
        'draw_time',
        'deadline_date',
        'deadline_time',
        'digitalization_closed_at',
        'ticket_price',
        'total_tickets',
        'sold_tickets',
        'prize_description',
        'prize_value',
        'image',
        'status',
        'lottery_type_id',
        // 'lottery_type_code', // J, X, S, N, B, V
        'is_special' // Para sorteos especiales como 15€ Especial
    ];

    protected $casts = [
        'draw_date' => 'date',
        'deadline_date' => 'date',
        'deadline_time' => 'datetime:H:i:s',
        'digitalization_closed_at' => 'datetime',
        'draw_time' => 'datetime:H:i:s',
        'ticket_price' => 'decimal:2',
        'prize_value' => 'decimal:2',
        'is_special' => 'boolean',
    ];

    // Relación con Tipo de Lotería
    public function lotteryType()
    {
        return $this->belongsTo(LotteryType::class, 'lottery_type_id');
    }

    // Relación con Sets (vía reservas)
    public function sets()
    {
        return $this->hasManyThrough(
            Set::class,
            Reserve::class,
            'lottery_id',
            'reserve_id',
            'id',
            'id'
        );
    }

    // Relación con Reservas
    public function reserves()
    {
        return $this->hasMany(Reserve::class);
    }

    /**
     * Sorteos cuyo draw_date no ha pasado (o sin fecha), si LOTTERY_ENFORCE_DRAW_DATE_RULES está activo.
     */
    public function scopeOpenForOperations(Builder $query): Builder
    {
        return app(LotteryDrawDateGuardService::class)->applyOpenForOperationsScope($query);
    }

    // Relación con Resultados
    public function result()
    {
        return $this->hasOne(LotteryResult::class);
    }

    // Relación con los escrutinios de administraciones
    public function administrationScrutinies()
    {
        return $this->hasMany(AdministrationLotteryScrutiny::class);
    }

    /**
     * Etiqueta legible para historial, SMS y app (name puede venir vacío en BD).
     */
    public function displayLabel(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $this->loadMissing('lotteryType');
        $type = $this->lotteryType;
        $segments = [];

        if ($type) {
            $typeName = trim((string) ($type->name ?? ''));
            if ($typeName !== '') {
                $segments[] = $typeName;
            } elseif (! empty($type->identificador)) {
                $segments[] = trim((string) $type->identificador);
            }
        }

        $price = $this->ticket_price ?? $type?->ticket_price;
        if ($price !== null && (float) $price > 0) {
            $segments[] = intval((float) $price).'€';
        }

        if ($segments !== []) {
            return implode(' · ', $segments);
        }

        if ($this->draw_date) {
            return 'Sorteo '.$this->draw_date->format('d/m/Y');
        }

        return 'Sorteo #'.$this->id;
    }

    /**
     * Hora de cierre de venta formateada (HH:mm).
     */
    public function deadlineTimeLabel(): string
    {
        if (! $this->deadline_time) {
            return '23:59';
        }

        return \Carbon\Carbon::parse($this->deadline_time)->format('H:i');
    }

    /**
     * Verificar si una administración ha escrutado este sorteo
     */
    public function isScrutinizedByAdministration($administrationId)
    {
        return $this->administrationScrutinies()
            ->where('administration_id', $administrationId)
            ->where('is_scrutinized', true)
            ->exists();
    }

    /**
     * Obtener el escrutinio de una administración específica
     */
    public function getAdministrationScrutiny($administrationId)
    {
        return $this->administrationScrutinies()
            ->where('administration_id', $administrationId)
            ->first();
    }

    /**
     * Obtener el identificador único del tipo de sorteo
     * Combina precio + código + especial para identificar exactamente el tipo
     */
    public function getLotteryTypeIdentifier()
    {
        // Convertir ticket_price a entero para evitar decimales
        $ticketPrice = intval($this->ticket_price);
        $identifier = $ticketPrice . '_' . $this->lotteryType->identificador;
        
        // Manejar casos especiales
        if ($this->is_special && $this->lotteryType->identificador == 'S' && $ticketPrice == 15) {
            $identifier .= '_ESPECIAL';
        }
        
        return $identifier;
    }

    /**
     * Obtener la configuración del tipo de sorteo
     */
    public function getLotteryTypeConfig()
    {
        $identifier = $this->getLotteryTypeIdentifier();
        $lotteryTypes = config('lotteryTypes');
        
        return $lotteryTypes[$identifier] ?? null;
    }

    /**
     * Obtener las categorías de premios aplicables para este sorteo
     */
    public function getApplicablePrizeCategories()
    {
        $identifier = $this->getLotteryTypeIdentifier();
        $categories = config('lotteryCategories');
        
        $applicableCategories = [];
        
        foreach ($categories as $category) {
            $prizeAmount = $category['importe_por_tipo'][$identifier] ?? 0;
            $prizeCount = is_array($category['cantidad_premios']) 
                ? ($category['cantidad_premios'][$identifier] ?? 0)
                : $category['cantidad_premios'];
            
            if ($prizeAmount > 0 && $prizeCount > 0) {
                $applicableCategories[] = array_merge($category, [
                    'importe_aplicable' => $prizeAmount,
                    'cantidad_aplicable' => $prizeCount
                ]);
            }
        }
        
        return $applicableCategories;
    }

    /**
     * Verificar si este sorteo tiene un tipo específico de premio
     */
    public function hasPrizeCategory($categoryKey)
    {
        $identifier = $this->getLotteryTypeIdentifier();
        $categories = config('lotteryCategories');
        
        $category = collect($categories)->firstWhere('key_categoria', $categoryKey);
        
        if (!$category) {
            return false;
        }
        
        $prizeAmount = $category['importe_por_tipo'][$identifier] ?? 0;
        $prizeCount = is_array($category['cantidad_premios']) 
            ? ($category['cantidad_premios'][$identifier] ?? 0)
            : $category['cantidad_premios'];
        
        return $prizeAmount > 0 && $prizeCount > 0;
    }
}
