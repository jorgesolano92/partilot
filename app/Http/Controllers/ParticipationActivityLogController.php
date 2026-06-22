<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParticipationActivityLog;
use App\Models\Participation;
use App\Models\Seller;
use App\Models\Entity;
use App\Services\ParticipationOwnerService;

class ParticipationActivityLogController extends Controller
{
    /**
     * Obtener el historial de actividades de una participación
     */
    public function getParticipationHistory($participationId)
    {
        try {
            $participation = Participation::with(['seller', 'pendingDigitalSales.seller'])
                ->forUser(auth()->user())
                ->findOrFail($participationId);
            
            $activitiesQuery = ParticipationActivityLog::with(['user', 'seller', 'entity', 'oldSeller', 'newSeller'])
                ->forParticipation($participationId)
                ->orderBy('created_at', 'desc');

            $activities = $this->applyAccessFilter($activitiesQuery)
                ->get()
                ->map(function ($activity) use ($participation) {
                    return [
                        'id' => $activity->id,
                        'activity_type' => $activity->activity_type,
                        'activity_type_text' => $activity->activity_type_text,
                        'activity_badge' => $activity->activity_badge,
                        'description' => $activity->description,
                        'user' => $this->resolveActivityUserName($activity),
                        'seller' => $this->resolveActivitySellerName($activity, $participation),
                        'entity' => $activity->entity ? $activity->entity->name : null,
                        'old_status' => $activity->old_status,
                        'new_status' => $activity->new_status,
                        'old_seller' => $activity->oldSeller ? $activity->oldSeller->full_name : null,
                        'new_seller' => $activity->newSeller ? $activity->newSeller->full_name : null,
                        'metadata' => $this->enrichActivityMetadata($activity->metadata, $participation),
                        'owner_user_name' => ParticipationOwnerService::ownerDisplayName($participation),
                        'created_at' => $activity->created_at->format('d/m/Y H:i:s'),
                        'ip_address' => $activity->ip_address,
                    ];
                });

            return response()->json([
                'success' => true,
                'participation' => [
                    'code' => $participation->participation_code,
                    'number' => $participation->participation_number,
                    'status' => $participation->status,
                ],
                'activities' => $activities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el historial de actividades de un vendedor
     */
    public function getSellerHistory(Request $request, $sellerId)
    {
        try {
            $seller = Seller::forUser(auth()->user())->findOrFail($sellerId);
            
            $query = ParticipationActivityLog::with(['participation', 'user', 'entity'])
                ->where(function($q) use ($sellerId) {
                    $q->where('seller_id', $sellerId)
                      ->orWhere('old_seller_id', $sellerId)
                      ->orWhere('new_seller_id', $sellerId);
                });

            $query = $this->applyAccessFilter($query)
                ->orderBy('created_at', 'desc');

            // Filtros opcionales
            if ($request->has('activity_type')) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $activities = $query->paginate(50);

            return response()->json([
                'success' => true,
                'seller' => [
                    'id' => $seller->id,
                    'name' => $seller->name,
                ],
                'activities' => $activities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial del vendedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el historial de actividades de una entidad
     */
    public function getEntityHistory(Request $request, $entityId)
    {
        try {
            $entity = Entity::forUser(auth()->user())->findOrFail($entityId);
            
            $query = ParticipationActivityLog::with(['participation', 'user', 'seller'])
                ->byEntity($entityId);

            $query = $this->applyAccessFilter($query)
                ->orderBy('created_at', 'desc');

            // Filtros opcionales
            if ($request->has('activity_type')) {
                $query->where('activity_type', $request->activity_type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $activities = $query->paginate(50);

            return response()->json([
                'success' => true,
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                ],
                'activities' => $activities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial de la entidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de actividad
     */
    public function getActivityStats(Request $request)
    {
        try {
            $query = $this->applyAccessFilter(ParticipationActivityLog::query());

            // Filtros opcionales
            if ($request->has('seller_id')) {
                if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                    return response()->json([
                        'success' => true,
                        'stats' => [
                            'total' => 0,
                            'by_type' => collect(),
                            'recent_7_days' => 0,
                            'recent_30_days' => 0,
                        ],
                    ]);
                }
                $query->where(function($q) use ($request) {
                    $q->where('seller_id', $request->seller_id)
                      ->orWhere('old_seller_id', $request->seller_id)
                      ->orWhere('new_seller_id', $request->seller_id);
                });
            }

            if ($request->has('entity_id')) {
                if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
                    return response()->json([
                        'success' => true,
                        'stats' => [
                            'total' => 0,
                            'by_type' => collect(),
                            'recent_7_days' => 0,
                            'recent_30_days' => 0,
                        ],
                    ]);
                }
                $query->where('entity_id', $request->entity_id);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $baseQuery = clone $query;
            $byTypeQuery = clone $query;

            $stats = [
                'total' => $baseQuery->count(),
                'by_type' => $byTypeQuery->selectRaw('activity_type, count(*) as count')
                    ->groupBy('activity_type')
                    ->get()
                    ->pluck('count', 'activity_type'),
                'recent_7_days' => $this->applyAccessFilter(ParticipationActivityLog::recent(7))->count(),
                'recent_30_days' => $this->applyAccessFilter(ParticipationActivityLog::recent(30))->count(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar vista del historial de una participación
     */
    public function show($participationId)
    {
        $participation = Participation::with(['activityLogs.user', 'activityLogs.seller', 'entity', 'set'])
            ->forUser(auth()->user())
            ->findOrFail($participationId);

        return view('participations.activity_log', compact('participation'));
    }

    /**
     * Obtener actividades recientes (últimos 7 días)
     */
    public function getRecentActivities(Request $request)
    {
        try {
            $days = $request->get('days', 7);
            $limit = $request->get('limit', 50);

            $activities = $this->applyAccessFilter(
                ParticipationActivityLog::with(['participation', 'user', 'seller', 'entity'])
                    ->recent($days)
            )
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'activity_type' => $activity->activity_type,
                        'activity_type_text' => $activity->activity_type_text,
                        'activity_badge' => $activity->activity_badge,
                        'description' => $activity->description,
                        'participation_code' => $activity->participation ? $activity->participation->participation_code : null,
                        'user' => $this->resolveActivityUserName($activity),
                        'seller' => $activity->seller ? $activity->seller->full_name : null,
                        'entity' => $activity->entity ? $activity->entity->name : null,
                        'created_at' => $activity->created_at->format('d/m/Y H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'activities' => $activities,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener actividades recientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplicar filtros de acceso según el rol del usuario autenticado.
     */
    private function applyAccessFilter($query)
    {
        $user = auth()->user();

        if (!$user || $user->isSuperAdmin()) {
            return $query;
        }

        $entityIds = $user->accessibleEntityIds();
        $sellerIds = $user->accessibleSellerIds();

        return $query->where(function ($q) use ($entityIds, $sellerIds) {
            $hasCondition = false;

            if (!empty($entityIds)) {
                $q->whereIn('entity_id', $entityIds);
                $hasCondition = true;
            }

            if (!empty($sellerIds)) {
                $sellerClosure = function ($sellerQuery) use ($sellerIds) {
                    $sellerQuery->whereIn('seller_id', $sellerIds)
                        ->orWhereIn('old_seller_id', $sellerIds)
                        ->orWhereIn('new_seller_id', $sellerIds);
                };

                if ($hasCondition) {
                    $q->orWhere($sellerClosure);
                } else {
                    $q->where($sellerClosure);
                    $hasCondition = true;
                }
            }

            if (!$hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Resuelve owner_user_* en metadata y traduce buyer_name numérico a nombre legible.
     */
    private function enrichActivityMetadata(?array $metadata, Participation $participation): array
    {
        $metadata = $metadata ?? [];

        if (empty($metadata['owner_user_name'])) {
            $metadata = array_merge($metadata, ParticipationOwnerService::ownerMetadata($participation));
        }

        if (! empty($metadata['buyer_name']) && ctype_digit((string) $metadata['buyer_name'])) {
            $owner = ParticipationOwnerService::resolveOwnerUser($participation);
            if ($owner) {
                $metadata['buyer_name'] = ParticipationOwnerService::ownerDisplayName($participation);
            }
        }

        if (! empty($metadata['changes']['buyer_name']) && ctype_digit((string) $metadata['changes']['buyer_name'])) {
            $owner = ParticipationOwnerService::resolveOwnerUser($participation);
            if ($owner) {
                $metadata['changes']['buyer_name'] = ParticipationOwnerService::ownerDisplayName($participation);
            }
        }

        return $metadata;
    }

    private function resolveActivityUserName(ParticipationActivityLog $activity): string
    {
        if (! $activity->user) {
            return 'Sistema';
        }

        return trim($activity->user->full_name) !== ''
            ? $activity->user->full_name
            : $activity->user->name;
    }

    private function resolveActivitySellerName(
        ParticipationActivityLog $activity,
        Participation $participation
    ): ?string {
        foreach ([$activity->seller, $activity->newSeller, $activity->oldSeller] as $seller) {
            if ($seller) {
                return $seller->full_name;
            }
        }

        $metadata = $activity->metadata ?? [];
        foreach (['seller_id', 'new_seller_id', 'old_seller_id'] as $key) {
            $sellerId = $metadata[$key] ?? ($metadata['changes'][$key] ?? null);
            if ($sellerId) {
                $seller = Seller::find($sellerId);
                if ($seller) {
                    return $seller->full_name;
                }
            }
        }

        $statuses = array_filter([$activity->new_status, $activity->old_status]);
        $sellerRelevant = ! empty(array_intersect(
            $statuses,
            ['reserva_venta_digital', 'vendida', 'pagada', 'asignada']
        ));

        if ($sellerRelevant) {
            if ($participation->relationLoaded('seller') && $participation->seller) {
                return $participation->seller->full_name;
            }

            $pendingSale = $participation->relationLoaded('pendingDigitalSales')
                ? $participation->pendingDigitalSales->sortByDesc('id')->first()
                : $participation->pendingDigitalSales()->with('seller')->orderByDesc('pending_digital_sales.id')->first();

            if ($pendingSale?->seller) {
                return $pendingSale->seller->full_name;
            }
        }

        return null;
    }
}
