<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Entity;
use App\Models\Set;
use App\Models\Participation;

class DesignFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'lottery_id',
        'set_id',
        'design_name',
        'format',
        'page',
        'rows',
        'cols',
        'orientation',
        'margin_up',
        'margin_right',
        'margin_left',
        'margin_top',
        'identation',
        'cut_lines',
        'matrix_box',
        'page_rigth',
        'page_bottom',
        'guide_color',
        'guide_weight',
        'participation_number',
        'participation_from',
        'participation_to',
        'participation_page',
        'guides',
        'generate',
        'documents',
        'blocks',
        'participation_html',
        'vertical_space',
        'horizontal_space',
        'margin_custom',
        'cover_html',
        'back_html',
        'back_skipped',
        'backgrounds',
        'margins',
        'output',
        'snapshot_path',
        'designer_type',
        'approval_status',
        'submitted_for_approval_at',
        'approval_decided_at',
        'approved_by_user_id',
        'approval_rejection_reason',
        'participation_export_locked_at',
    ];

    protected $casts = [
        'blocks' => 'array',
        'guides' => 'boolean',
        'backgrounds' => 'array',
        'output' => 'array',
        'margins' => 'array',
        'back_skipped' => 'boolean',
        'submitted_for_approval_at' => 'datetime',
        'approval_decided_at' => 'datetime',
        'participation_export_locked_at' => 'datetime',
    ];

    /**
     * Márgenes / sangres por defecto al crear un diseño nuevo.
     *
     * @return array<string, mixed>
     */
    public static function defaultLayoutAttributes(): array
    {
        return [
            'margins' => [
                'up' => 1,
                'right' => 1,
                'left' => 1,
                'top' => 1,
            ],
            'margin_up' => 1,
            'margin_right' => 1,
            'margin_left' => 1,
            'margin_top' => 1,
            'identation' => 0,
            'cut_lines' => 2.5,
            'matrix_box' => 40,
            'margin_custom' => 1,
            'horizontal_space' => 0,
            'vertical_space' => 0,
            'page_rigth' => 0,
            'page_bottom' => 0,
        ];
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function lottery()
    {
        return $this->belongsTo(Lottery::class);
    }

    public function set()
    {
        return $this->belongsTo(Set::class);
    }

    public function hasBackDesign(): bool
    {
        if ($this->back_skipped) {
            return false;
        }

        return ! empty(trim(strip_tags((string) ($this->back_html ?? ''))));
    }

    public function participations()
    {
        return $this->hasMany(Participation::class);
    }

    /**
     * Boot del modelo para eventos automáticos
     */
    protected static function boot()
    {
        parent::boot();

        // Cuando se crea un DesignFormat, generar participaciones automáticamente
        static::created(function ($designFormat) {
            $designFormat->generateParticipations();
        });

        // Cuando se actualiza un DesignFormat, actualizar participaciones si es necesario
        static::updated(function ($designFormat) {
            if ($designFormat->wasChanged(['output', 'participation_from', 'participation_to'])) {
                return $designFormat->updateParticipations();
            }
        });

        // Cuando se elimina un DesignFormat, eliminar participaciones asociadas
        static::deleted(function ($designFormat) {
            $designFormat->deleteParticipations();
        });
    }

    /**
     * Generar participaciones automáticamente
     */
    public function generateParticipations()
    {
        // Primero eliminar participaciones existentes para evitar duplicados
        $this->deleteParticipations();
        
        try {
            \Log::info('Iniciando generación de participaciones para DesignFormat ID: ' . $this->id);
            
            // Validar relaciones antes de proceder
            $validationErrors = $this->validateRelationships();
            if (!empty($validationErrors)) {
                \Log::error('Errores de validación: ' . implode(', ', $validationErrors));
                return false;
            }
            
            if (!$this->set) {
                \Log::warning('No se encontró el set asociado al DesignFormat ID: ' . $this->id);
                return false;
            }

            $totalParticipations = $this->set->total_participations ?? 0;
            \Log::info('Total de participaciones a generar: ' . $totalParticipations);
            
            if ($totalParticipations <= 0) {
                \Log::warning('El total de participaciones es 0 o menor para Set ID: ' . $this->set_id);
                return false;
            }

            // Obtener participaciones por taco desde el output
            $output = is_string($this->output) ? json_decode($this->output, true) : $this->output;
            // Sets digitales: un solo taco (book_number = 1 para todas)
            $isDigitalOnly = $this->set->digital_participations > 0 && (int) ($this->set->physical_participations ?? 0) === 0;
            $participationsPerBook = $isDigitalOnly ? $totalParticipations : (int) ($output['participations_per_book'] ?? 50);
            if ($participationsPerBook <= 0) {
                $participationsPerBook = 50;
            }

            \Log::info('Participaciones por taco: ' . $participationsPerBook);

            // Obtener número de set
            $setNumber = $this->getSetNumber();
            \Log::info('Número de set calculado: ' . $setNumber);

            // Verificar que los IDs necesarios existan
            if (!$this->entity_id || !$this->set_id || !$this->id) {
                \Log::error('Faltan IDs requeridos - entity_id: ' . $this->entity_id . ', set_id: ' . $this->set_id . ', design_format_id: ' . $this->id);
                return false;
            }

            $participationsToCreate = [];
            $totalCreated = 0;

            // Obtener el rango de números de participación global para este set
            $participationRange = $this->set->getParticipationNumberRange();
            $globalStartNumber = $participationRange['start'];
            
            $isDigitalOnly = $this->set->digital_participations > 0 && (int) ($this->set->physical_participations ?? 0) === 0;
            for ($participationNumber = 1; $participationNumber <= $totalParticipations; $participationNumber++) {
                // Calcular a qué taco pertenece esta participación
                $bookNumber = ceil($participationNumber / $participationsPerBook);
                
                // Calcular el número global de participación
                $globalParticipationNumber = $globalStartNumber + $participationNumber - 1;
                
                // Código de participación: digital usa "1D/00001" en BD (único) y se muestra como "1/00001"
                if ($isDigitalOnly) {
                    $participationCode = '1D/' . sprintf('%05d', $participationNumber);
                } else {
                    $participationCode = sprintf('%d/%05d', $setNumber, $globalParticipationNumber);
                }

                $participationData = [
                    'entity_id' => $this->entity_id,
                    'set_id' => $this->set_id,
                    'design_format_id' => $this->id,
                    'participation_number' => $globalParticipationNumber, // Usar número global
                    'participation_code' => $participationCode,
                    'book_number' => $bookNumber,
                    'status' => 'disponible',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $participationsToCreate[] = $participationData;

                // Insertar en lotes de 100 para mejor rendimiento (más pequeño para debugging)
                if (count($participationsToCreate) >= 100) {
                    try {
                        $this->clearParticipationNumberConflictsBeforeInsert($participationsToCreate);

                        $result = Participation::insert($participationsToCreate);
                        $insertedCount = count($participationsToCreate);
                        $totalCreated += $insertedCount;
                        \Log::info('Insertado lote de ' . $insertedCount . ' participaciones. Total creadas: ' . $totalCreated);
                        
                        // Crear logs para las participaciones insertadas
                        $this->createActivityLogsForBatch($participationsToCreate);
                        
                        $participationsToCreate = [];
                    } catch (\Exception $e) {
                        \Log::error('Error al insertar lote de participaciones: ' . $e->getMessage());
                        \Log::error('Datos del lote: ' . json_encode($participationsToCreate));
                        throw $e;
                    }
                }
            }

            // Insertar las participaciones restantes
            if (!empty($participationsToCreate)) {
                try {
                    $this->clearParticipationNumberConflictsBeforeInsert($participationsToCreate);

                    $result = Participation::insert($participationsToCreate);
                    $insertedCount = count($participationsToCreate);
                    $totalCreated += $insertedCount;
                    \Log::info('Insertado lote final de ' . $insertedCount . ' participaciones. Total creadas: ' . $totalCreated);
                    
                    // Crear logs para las participaciones insertadas
                    $this->createActivityLogsForBatch($participationsToCreate);
                } catch (\Exception $e) {
                    \Log::error('Error al insertar lote final de participaciones: ' . $e->getMessage());
                    \Log::error('Datos del lote final: ' . json_encode($participationsToCreate));
                    throw $e;
                }
            }

            \Log::info('Generación de participaciones completada. Total creadas: ' . $totalCreated);
            
            // Verificar que las participaciones se crearon correctamente
            $actualCount = Participation::where('design_format_id', $this->id)->count();
            \Log::info('Verificación: participaciones en BD para DesignFormat ID ' . $this->id . ': ' . $actualCount);
            
            if ($actualCount !== $totalCreated) {
                \Log::warning('Discrepancia: se esperaban ' . $totalCreated . ' participaciones pero se encontraron ' . $actualCount . ' en la BD');
            }
            
            return $totalCreated;

        } catch (\Exception $e) {
            \Log::error('Error en generateParticipations: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Crear logs de actividad para un lote de participaciones insertadas
     */
    private function createActivityLogsForBatch($participationsData)
    {
        try {
            $logsToCreate = [];
            $now = now();
            
            foreach ($participationsData as $participationData) {
                // Obtener el ID de la participación recién creada por su código
                $participation = Participation::where('participation_code', $participationData['participation_code'])
                    ->where('design_format_id', $this->id)
                    ->first();
                
                if ($participation) {
                    $logsToCreate[] = [
                        'participation_id' => $participation->id,
                        'activity_type' => 'created',
                        'user_id' => auth()->id(),
                        'seller_id' => null,
                        'entity_id' => $this->entity_id,
                        'old_status' => null,
                        'new_status' => 'disponible',
                        'old_seller_id' => null,
                        'new_seller_id' => null,
                        'description' => "Participación #{$participationData['participation_number']} creada",
                        'metadata' => json_encode([
                            'participation_code' => $participationData['participation_code'],
                            'book_number' => $participationData['book_number'],
                            'set_id' => $this->set_id,
                            'design_format_id' => $this->id,
                        ]),
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                // Insertar logs en lotes de 100
                if (count($logsToCreate) >= 100) {
                    DB::table('participation_activity_logs')->insert($logsToCreate);
                    \Log::info('Insertados ' . count($logsToCreate) . ' logs de actividad');
                    $logsToCreate = [];
                }
            }
            
            // Insertar logs restantes
            if (!empty($logsToCreate)) {
                DB::table('participation_activity_logs')->insert($logsToCreate);
                \Log::info('Insertados ' . count($logsToCreate) . ' logs de actividad (lote final)');
            }
            
        } catch (\Exception $e) {
            \Log::error('Error al crear logs de actividad: ' . $e->getMessage());
            // No lanzar excepción para no interrumpir la creación de participaciones
        }
    }

    /**
     * Actualizar participaciones existentes
     */
    public function updateParticipations()
    {
        // Eliminar participaciones existentes
        $this->deleteParticipations();
        
        // Generar nuevas participaciones
        return $this->generateParticipations();
    }

    /**
     * Evita violar participations_set_id_participation_number_unique antes de insertar.
     *
     * @param  array<int, array<string, mixed>>  $participationsToCreate
     */
    private function clearParticipationNumberConflictsBeforeInsert(array $participationsToCreate): void
    {
        $setId = (int) $this->set_id;
        $numbers = array_values(array_unique(array_map('intval', array_column($participationsToCreate, 'participation_number'))));
        if ($numbers === []) {
            return;
        }

        $foreignQuery = Participation::query()
            ->where('set_id', $setId)
            ->whereIn('participation_number', $numbers)
            ->where(function ($q) {
                $q->where('design_format_id', '!=', $this->id)
                    ->orWhereNull('design_format_id');
            });

        $hasBlocked = (clone $foreignQuery)
            ->where(function ($q) {
                $q->whereNotNull('seller_id')
                    ->orWhereIn('status', ['vendida', 'reservada', 'pagada', 'perdida']);
            })
            ->exists();

        if ($hasBlocked) {
            throw new \RuntimeException(
                'No se pueden generar participaciones: el set ya tiene participaciones comprometidas en otro diseño.'
            );
        }

        $deleted = (clone $foreignQuery)->delete();
        if ($deleted > 0) {
            \Log::info("Eliminadas {$deleted} participaciones huérfanas/de otro diseño antes de insertar para DesignFormat ID {$this->id}");
        }
    }

    /**
     * Eliminar participaciones asociadas
     */
    public function deleteParticipations()
    {
        try {
            // Eliminar participaciones del design format actual
            $deleted = Participation::where('design_format_id', $this->id)->delete();
            \Log::info('Eliminadas ' . $deleted . ' participaciones existentes para DesignFormat ID: ' . $this->id);
            
            return $deleted;
        } catch (\Exception $e) {
            \Log::error('Error al eliminar participaciones: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Método de prueba para crear una sola participación
     */
    public function testCreateSingleParticipation()
    {
        try {
            $setNumber = $this->getSetNumber();
            $participationCode = sprintf('%d/%05d', $setNumber, 1);
            
            $participationData = [
                'entity_id' => $this->entity_id,
                'set_id' => $this->set_id,
                'design_format_id' => $this->id,
                'participation_number' => 1,
                'participation_code' => $participationCode,
                'book_number' => 1,
                'status' => 'disponible',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            \Log::info('Intentando crear participación de prueba: ' . json_encode($participationData));
            
            $result = Participation::create($participationData);
            \Log::info('Participación de prueba creada exitosamente con ID: ' . $result->id);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('Error al crear participación de prueba: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar que las relaciones existan antes de crear participaciones
     */
    public function validateRelationships()
    {
        $errors = [];
        
        // Verificar que la entidad existe
        if (!$this->entity_id) {
            $errors[] = 'entity_id no está definido';
        } elseif (!Entity::find($this->entity_id)) {
            $errors[] = 'La entidad con ID ' . $this->entity_id . ' no existe';
        }
        
        // Verificar que el set existe
        if (!$this->set_id) {
            $errors[] = 'set_id no está definido';
        } elseif (!Set::find($this->set_id)) {
            $errors[] = 'El set con ID ' . $this->set_id . ' no existe';
        }
        
        // Verificar que el design format existe
        if (!$this->id) {
            $errors[] = 'design_format_id no está definido';
        }
        
        return $errors;
    }

    /**
     * Obtener el número de set desde el modelo Set
     */
    private function getSetNumber()
    {
        return $this->set ? $this->set->set_number : 1;
    }

    /**
     * Tarea 1 tacos: genera taco_qrs para venta por QR de taco completo.
     * taco_ref incluye entity_id, set_id, set_number, book_number y firma HMAC para verificación.
     * Formato: TACO-{entity_id}-{set_id}-{set_number}-B{book_number}-{signature}
     * Devuelve el array output con la clave 'taco_qrs' añadida/actualizada.
     */
    public static function mergeTacoQrsIntoOutput(?int $setId, array $output): array
    {
        if (!$setId || !isset($output['participations_per_book'])) {
            return $output;
        }
        $set = Set::find($setId);
        if (!$set) {
            return $output;
        }
        $total = (int) ($set->total_participations ?? 0);
        if ($total <= 0 && !empty($set->tickets)) {
            $tickets = is_array($set->tickets) ? $set->tickets : [];
            $total = count($tickets);
        }
        if ($total <= 0) {
            return $output;
        }
        $perBook = (int) ($output['participations_per_book'] ?? 50);
        if ($perBook <= 0) {
            $perBook = 50;
        }
        $numBooks = (int) ceil($total / $perBook);
        $entityId = (int) $set->entity_id;
        $setNumber = (int) ($set->set_number ?? 0);
        $tacoQrs = [];
        for ($b = 1; $b <= $numBooks; $b++) {
            $tacoQrs[] = [
                'book_number' => $b,
                'taco_ref' => self::buildTacoRef($entityId, $setId, $setNumber, $b),
            ];
        }
        $output['taco_qrs'] = $tacoQrs;
        return $output;
    }

    /**
     * Construye un taco_ref con firma: TACO-{entity_id}-{set_id}-{set_number}-B{book_number}-{signature}
     * La firma permite verificar en la API que el ref no ha sido alterado.
     */
    public static function buildTacoRef(int $entityId, int $setId, int $setNumber, int $bookNumber): string
    {
        $payload = implode('|', [$entityId, $setId, $setNumber, $bookNumber]);
        $signature = substr(
            hash_hmac('sha256', $payload, config('app.key')),
            0,
            8
        );
        return sprintf('TACO-%d-%d-%d-B%d-%s', $entityId, $setId, $setNumber, $bookNumber, $signature);
    }

    /**
     * Parsea y valida un taco_ref. Devuelve ['entity_id', 'set_id', 'set_number', 'book_number'] o null si no es válido.
     */
    public static function parseTacoRef(string $tacoRef): ?array
    {
        if (!preg_match('/^TACO-(\d+)-(\d+)-(\d+)-B(\d+)-([a-f0-9]{8})$/', $tacoRef, $m)) {
            return null;
        }
        $entityId = (int) $m[1];
        $setId = (int) $m[2];
        $setNumber = (int) $m[3];
        $bookNumber = (int) $m[4];
        $receivedSig = $m[5];
        $expectedSig = substr(
            hash_hmac('sha256', implode('|', [$entityId, $setId, $setNumber, $bookNumber]), config('app.key')),
            0,
            8
        );
        if (!hash_equals($expectedSig, $receivedSig)) {
            return null;
        }
        return [
            'entity_id' => $entityId,
            'set_id' => $setId,
            'set_number' => $setNumber,
            'book_number' => $bookNumber,
        ];
    }

    /** Normaliza participaciones por talonario (1–1000, como en el editor de formato). */
    public static function normalizeCoverParticipationsPerBook(mixed $raw): int
    {
        $value = (int) $raw;

        return max(1, min(1000, $value > 0 ? $value : 50));
    }

    /**
     * Recalcula book_number sin borrar/recrear participaciones (saveQuietly en output).
     */
    public function reassignBookNumbers(int $perBook): int
    {
        $perBook = max(1, $perBook);
        $set = $this->set;
        if (! $set) {
            return 0;
        }

        $range = $set->getParticipationNumberRange();
        $globalStart = (int) ($range['start'] ?? 1);

        $participations = Participation::query()
            ->where('design_format_id', $this->id)
            ->where('status', '!=', 'anulada')
            ->orderBy('participation_number')
            ->get(['id', 'participation_number', 'book_number']);

        if ($participations->isEmpty()) {
            return 0;
        }

        $updated = 0;
        foreach ($participations as $participation) {
            $localIndex = (int) $participation->participation_number - $globalStart + 1;
            $bookNumber = (int) ceil($localIndex / $perBook);
            if ((int) $participation->book_number !== $bookNumber) {
                Participation::whereKey($participation->id)->update(['book_number' => $bookNumber]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Persiste participations_per_book, regenera taco_qrs y actualiza book_number en bloque.
     */
    public function syncCoverTacoConfig(int $perBook): void
    {
        $perBook = self::normalizeCoverParticipationsPerBook($perBook);
        $output = is_array($this->output) ? $this->output : [];
        $output['participations_per_book'] = $perBook;

        if ($this->set_id) {
            $output = self::mergeTacoQrsIntoOutput((int) $this->set_id, $output);
        }

        $this->output = $output;
        // saveQuietly: no dispara updateParticipations al cambiar output.
        $this->saveQuietly();
        $this->reassignBookNumbers($perBook);
    }
}
