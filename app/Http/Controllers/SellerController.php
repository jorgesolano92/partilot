<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\User;
use App\Models\Entity;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\Participation;
use App\Models\Lottery;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementPayment;
use App\Models\ParticipationActivityLog;
use App\Models\PendingDigitalSale;
use App\Models\DesignFormat;
use App\Models\BackgroundTask;
use App\Jobs\ProcessParticipationAssignmentTask;
use App\Services\SellerLiquidationService;
use App\Services\SellerService;
use App\Services\BackgroundTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\EmailCommunicationLog;
use App\Services\CommunicationEmailService;
use App\Mail\SellerSettlementStatusMail;
use App\Support\ParticipationTicketReference;

class SellerController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Display a listing of the resource.
     * Carga conteos de participaciones (asignadas, vendidas, devueltas) y deuda.
     * La deuda = pendiente por liquidar: (participaciones liquidables × precio) − lo ya pagado por sorteo (misma lógica que Liquidación de Vendedor).
     */
    public function index()
    {
        $sellers = Seller::with(['entities' => fn ($q) => $q->select('entities.id', 'entities.name', 'entities.province')])
            ->forUser(auth()->user())
            ->withCount([
                'participations as participaciones_asignadas' => fn ($q) => $q->where('status', 'asignada'),
                'participations as participaciones_vendidas' => fn ($q) => $q->where('status', 'vendida'),
                'participations as participaciones_devueltas' => fn ($q) => $q->where('status', 'devuelta'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $sellerIds = $sellers->pluck('id')->toArray();
        $deudas = $this->getPendingLiquidationBySellers($sellerIds);

        // Participaciones devueltas también desde participation_activity_logs (returned_by_seller):
        // cuando la devolución deja la participación como "disponible", no queda status=devuelta pero sí queda el log
        $devueltasDesdeLog = [];
        if (!empty($sellerIds)) {
            $devueltasDesdeLog = ParticipationActivityLog::query()
                ->where('activity_type', 'returned_by_seller')
                ->whereIn('seller_id', $sellerIds)
                ->selectRaw('seller_id, COUNT(DISTINCT participation_id) as total')
                ->groupBy('seller_id')
                ->pluck('total', 'seller_id')
                ->toArray();
        }

        foreach ($sellers as $seller) {
            $seller->setAttribute('deuda_pendiente', $deudas[$seller->id] ?? 0);
            $extraDevueltas = (int) ($devueltasDesdeLog[$seller->id] ?? 0);
            $seller->setAttribute('participaciones_devueltas', ($seller->participaciones_devueltas ?? 0) + $extraDevueltas);
        }

        $user = auth()->user();
        $hideEntityColumn = $user && $user->isEntity() && ! $user->isSuperAdmin() && ! $user->isAdministration();
        $canManageSellers = $user && ($user->isSuperAdmin() || $user->isAdministration());

        return view('sellers.index', compact('sellers', 'hideEntityColumn', 'canManageSellers'));
    }

    /**
     * Calcula el pendiente por liquidar por vendedor (igual lógica que getSettlementSummary, agregado por todos los sorteos).
     * Para cada sorteo: total a liquidar = suma(total_participation_amount) de participaciones liquidables; pendiente = total − suma(paid_amount) de liquidaciones.
     *
     * @param int[] $sellerIds
     * @return array<int, float> seller_id => deuda
     */
    private function getPendingLiquidationBySellers(array $sellerIds): array
    {
        return app(SellerLiquidationService::class)->getPendingLiquidationBySellers($sellerIds);
    }

    /**
     * Participaciones que cuentan para liquidar a un vendedor (opcionalmente filtradas por sorteo).
     */
    private function settlementEligibleParticipationsQuery(int $sellerId, ?int $lotteryId = null)
    {
        $query = Participation::query()
            ->eligibleForSellerSettlement($sellerId)
            ->with('set');

        if ($lotteryId) {
            $query->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId));
        }

        return $query;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if ($redirect = $this->redirectIfImplicitEntity($request, 'sellers.add-information', [], 'sellers')) {
            return $redirect;
        }

        $entities = Entity::with('administration')
            ->forUser(auth()->user())
            ->get();
        return view('sellers.add', compact('entities'));
    }

    /**
     * Store the selected entity in session
     */
    public function store_entity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id'
        ]);

        $entity = Entity::with('administration')
            ->forUser(auth()->user())
            ->findOrFail($request->entity_id);

        if ($entity->status != 1) {
            return redirect()->back()->with('error', 'Solo se puede seleccionar una entidad activa.');
        }

        session(['selected_entity' => $entity]);

        return redirect()->route('sellers.add-information');
    }

    /**
     * Show the add information form
     */
    public function add_information()
    {
        $entity = session('selected_entity');

        if (!$entity || !auth()->user()->canAccessEntity($entity->id)) {
            return redirect()->route('sellers.create');
        }

        return view('sellers.add_information');
    }

    /**
     * Store a seller with existing user
     */
    public function store_existing_user(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|email',
            'entity_id' => 'required|exists:entities,id',
            'name' => 'nullable|string|max:255', // No requerido, puede estar vacío
            'last_name' => 'nullable|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['nullable', 'string', 'max:255', new \App\Rules\SpanishDocument, 'unique:users,nif_cif', 'unique:sellers,nif_cif'],
            'birthday' => ['nullable', 'date', new \App\Rules\MinimumAge(18)],
            'phone' => 'nullable|string|max:255',
            'comment' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            // Asegurar que la entidad esté en sesión
            if ($request->entity_id) {
                $entity = Entity::with('administration')
                    ->forUser(auth()->user())
                    ->find($request->entity_id);
                if ($entity) {
                    session(['selected_entity' => $entity]);
                }
            }
            return redirect()->route('sellers.add-information')
                ->withErrors($validator)
                ->withInput();
        }

        if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
            abort(403, 'No tienes permisos para gestionar vendedores de esta entidad.');
        }

        $entity = Entity::find($request->entity_id);
        if (!$entity || $entity->status != 1) {
            return redirect()->route('sellers.add-information')->withErrors(['entity_id' => 'Solo se puede asignar un vendedor a una entidad activa.'])->withInput();
        }

        try {
            $sellerService = new SellerService();
            
            // Verificar si el seller ya existe antes de crearlo
            $existingSeller = \App\Models\Seller::where('email', $request->email)->first();
            $wasExisting = $existingSeller !== null;
            
            $seller = $sellerService->createSeller($request->all(), $request->entity_id, 'partilot');

            session()->forget('selected_entity');
            
            // Determinar el mensaje
            if ($wasExisting) {
                $message = 'Vendedor existente agregado a la entidad seleccionada';
            } else {
                $message = $seller->isLinkedToUser() 
                    ? 'Vendedor PARTILOT creado y vinculado exitosamente'
                    : 'Vendedor PARTILOT creado pendiente de vinculación';
            }
                
            return redirect()->route('sellers.index')->with('success', $message);

        } catch (\Exception $e) {
            // Asegurar que la entidad esté en sesión
            if ($request->entity_id) {
                $entity = Entity::with('administration')
                    ->forUser(auth()->user())
                    ->find($request->entity_id);
                if ($entity) {
                    session(['selected_entity' => $entity]);
                }
            }
            return redirect()->route('sellers.add-information')
                ->withErrors(['error' => 'Error al crear el vendedor: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Store a seller with new user
     */
    public function store_new_user(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'nullable|string|max:255', // No requerido
            'last_name' => 'nullable|string|max:255', // No requerido
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['required', 'string', 'max:255', new \App\Rules\SpanishDocument, 'unique:users,nif_cif', 'unique:sellers,nif_cif'],
            'birthday' => ['nullable', 'date', new \App\Rules\MinimumAge(18)],
            'email' => 'required|email',
            'phone' => 'nullable|string|max:255',
            'entity_id' => 'required|exists:entities,id'
        ]);

        if ($validator->fails()) {
            // Asegurar que la entidad esté en sesión
            if ($request->entity_id) {
                $entity = Entity::with('administration')
                    ->forUser(auth()->user())
                    ->find($request->entity_id);
                if ($entity) {
                    session(['selected_entity' => $entity]);
                }
            }
            return redirect()->route('sellers.add-information')
                ->withErrors($validator)
                ->withInput();
        }

        if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
            abort(403, 'No tienes permisos para gestionar vendedores de esta entidad.');
        }

        $entity = Entity::find($request->entity_id);
        if (!$entity || $entity->status != 1) {
            return redirect()->route('sellers.add-information')->withErrors(['entity_id' => 'Solo se puede asignar un vendedor a una entidad activa.'])->withInput();
        }

        try {
            $sellerService = new SellerService();
            
            // Verificar si el seller ya existe antes de crearlo
            $existingSeller = \App\Models\Seller::where('email', $request->email)->first();
            $wasExisting = $existingSeller !== null;
            
            $seller = $sellerService->createSeller($request->all(), $request->entity_id, 'externo');

            session()->forget('selected_entity');
            
            $message = $wasExisting 
                ? 'Vendedor existente agregado a la entidad seleccionada'
                : 'Vendedor EXTERNO creado exitosamente';
            
            return redirect()->route('sellers.index')->with('success', $message);

        } catch (\Exception $e) {
            // Asegurar que la entidad esté en sesión
            if ($request->entity_id) {
                $entity = Entity::with('administration')
                    ->forUser(auth()->user())
                    ->find($request->entity_id);
                if ($entity) {
                    session(['selected_entity' => $entity]);
                }
            }
            return redirect()->route('sellers.add-information')
                ->withErrors(['error' => 'Error al crear el vendedor: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Check if user email exists
     */
    public function check_user_email(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Verificar si el email ya está en uso en vendedores (para validación AJAX)
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'exclude_id' => 'nullable|integer'
        ]);

        $query = Seller::where('email', $request->email);
        
        // Excluir el ID actual si se está editando
        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este email ya está en uso por otro vendedor' : null
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id, Request $request)
    {
        $seller = Seller::with([
            'entities.administration',
            'groups.entity',
            'user:id,name,last_name,last_name2,image,email,phone,birthday,nif_cif',
        ])
            ->forUser(auth()->user())
            ->findOrFail($id);

        $accessibleEntityIds = auth()->user()->accessibleEntityIds();
        if (!empty($accessibleEntityIds)) {
            $filteredEntities = $seller->entities->whereIn('id', $accessibleEntityIds)->values();
        } else {
            $filteredEntities = collect();
        }

        if ($filteredEntities->isEmpty()) {
            abort(403, 'No tienes permisos para ver las entidades de este vendedor.');
        }

        $seller->setRelation('entities', $filteredEntities);

        // Verificar que el vendedor tenga al menos una entidad
        if ($seller->entities->isEmpty()) {
            return back()->withErrors(['error' => 'El vendedor no tiene entidades asignadas']);
        }

        // Determinar la entidad actual
        // 1. Si viene entity_id por parámetro, usarla (y validar que pertenezca al seller)
        // 2. Si no, usar la primera entidad
        $entityId = $request->query('entity_id');
        
        if ($entityId && $seller->belongsToEntity($entityId)) {
            $currentEntity = $seller->entities->where('id', $entityId)->first();
        } else {
            $currentEntity = $seller->getPrimaryEntity();
        }
        
        // Obtener reservas de la entidad actual
        $reserves = collect();
        if ($currentEntity) {
            $reserves = Reserve::where('entity_id', $currentEntity->id)
                ->where('status', 1) // confirmed
                ->with(['lottery'])
                ->get();
        }

        $user = auth()->user();
        $isEntityRole = $user && $user->isEntity() && ! $user->isSuperAdmin() && ! $user->isAdministration();
        $hideEntitySidebar = $user && (
            $user->isEntityPanelReadOnly()
            || $isEntityRole
        );
        $hideEntityBannerInTab = $hideEntitySidebar;
        $canEditSeller = $user && $user->isSuperAdmin();
        $canEditSellerObservations = $user && ($user->isSuperAdmin() || $isEntityRole);
        $hideSellerPersonalData = $user && $user->isAdministration() && ! $user->isSuperAdmin();
        $hideDatosVendedorTab = $hideSellerPersonalData;
        $hideSellerSidebarProfile = $hideSellerPersonalData;
        $defaultSellerTab = $hideDatosVendedorTab ? 'asignacion' : 'datos_vendedor';
        $canToggleSellerStatus = $canEditSeller;
        $sellerGroup = $seller->groups->first();

        return view('sellers.show', compact(
            'seller',
            'currentEntity',
            'reserves',
            'hideEntitySidebar',
            'hideEntityBannerInTab',
            'canEditSeller',
            'canEditSellerObservations',
            'hideSellerPersonalData',
            'hideDatosVendedorTab',
            'hideSellerSidebarProfile',
            'defaultSellerTab',
            'canToggleSellerStatus',
            'sellerGroup'
        ));
    }

    /**
     * API: Obtener reservas y sets del vendedor autenticado (para app móvil)
     * Solo para usuarios con rol vendedor. Devuelve reservas de entidades del vendedor con sets que tienen participaciones.
     */
    public function apiGetMyReserves(Request $request)
    {
        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $entityIds = $seller->entities()->pluck('entities.id')->toArray();
        if (empty($entityIds)) {
            return response()->json([
                'success' => true,
                'reserves' => []
            ]);
        }

        $setFilterForSeller = function ($q) use ($seller) {
            $q->where('status', 1)
                ->whereHas('designFormats')
                ->where(function ($q2) use ($seller) {
                    // Digitales: todos los sets de la entidad (no requieren asignación al vendedor)
                    $q2->where(function ($digital) {
                        $digital->whereRaw('sets.digital_participations > 0')
                            ->where('sets.physical_participations', '<=', 0);
                    })
                    // Físicas: solo sets con participaciones asignadas a este vendedor
                        ->orWhereHas('participations', fn ($pq) => $pq->where('seller_id', $seller->id));
                });
        };

        $reserves = Reserve::whereIn('entity_id', $entityIds)
            ->where('status', 1)
            ->with(['lottery.lotteryType'])
            ->whereHas('sets', $setFilterForSeller)
            ->with(['sets' => function ($q) use ($setFilterForSeller) {
                $setFilterForSeller($q);
                $q->select(
                    'sets.id',
                    'sets.reserve_id',
                    'sets.set_number',
                    'sets.set_name',
                    'sets.total_participations',
                    'sets.total_participation_amount as played_amount',
                    'sets.physical_participations',
                    'sets.digital_participations'
                );
            }])
            ->orderBy('reservation_date', 'desc')
            ->get();

        $pendingService = app(\App\Services\PendingDigitalSaleService::class);
        foreach ($reserves as $reserve) {
            foreach ($reserve->sets as $set) {
                $isDigital = ($set->digital_participations ?? 0) > 0 && (int) ($set->physical_participations ?? 0) === 0;
                if ($isDigital) {
                    $set->setAttribute('digital_available_to_seller', $pendingService->countDigitalDisponibleForSet((int) $set->id));
                } elseif ((int) ($set->physical_participations ?? 0) > 0) {
                    $set->setAttribute('physical_available_to_seller', (int) DB::table('participations')
                        ->where('set_id', $set->id)
                        ->where('seller_id', $seller->id)
                        ->where('status', 'asignada')
                        ->count());
                }
            }
        }

        return response()->json([
            'success' => true,
            'reserves' => $reserves
        ]);
    }

    /**
     * API: Obtener sorteos del vendedor autenticado por entidad (con reservas, sets y diseño)
     */
    public function apiGetMyLotteries(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        // Verificar que el vendedor pertenece a esta entidad
        if (!$seller->entities()->where('entities.id', $request->entity_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        // Obtener reservas de esta entidad con sets activos y diseño
        $reserves = \App\Models\Reserve::where('entity_id', $request->entity_id)
            ->where('status', 1) // Reserva confirmada
            ->whereHas('sets', function ($setQ) {
                $setQ->where('status', 1) // Set activo
                     ->whereHas('designFormats'); // Que tenga diseño
            })
            ->with(['lottery.lotteryType', 'sets' => function ($q) {
                $q->where('status', 1)
                  ->with('designFormats');
            }])
            ->orderBy('reservation_date', 'desc')
            ->get();
        
        // Agrupar por sorteo y formatear
        $lotteries = $reserves->groupBy('lottery_id')
            ->map(function ($reservesGroup) {
                $reserve = $reservesGroup->first();
                $lottery = $reserve->lottery;
                
                if (!$lottery) {
                    return null;
                }
                
                // Filtrar sets que tienen diseño
                $sets = $reserve->sets->filter(function ($set) {
                    return $set->designFormats && $set->designFormats->isNotEmpty();
                });
                
                if ($sets->isEmpty()) {
                    return null;
                }
                
                return [
                    'id' => $lottery->id,
                    'name' => $lottery->name,
                    'draw_date' => $lottery->draw_date ? $lottery->draw_date->format('Y-m-d') : null,
                    'draw_date_formatted' => $lottery->draw_date ? $lottery->draw_date->format('d/m/Y') : null,
                    'lottery_number' => $lottery->lottery_number ?? '',
                    'lottery_type' => $lottery->lotteryType->name ?? null,
                    'image' => $lottery->image ?? null,
                    'reserve_id' => $reserve->id,
                    'has_design' => true,
                    'sets_count' => $sets->count(),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'lotteries' => $lotteries
        ]);
    }

    /**
     * API: Total de participaciones digitales disponibles (pool de la entidad + sorteo).
     * Las digitales no se asignan; todos los vendedores de la entidad venden del mismo pool.
     */
    public function apiGetTotalDigitalAvailable(Request $request)
    {
        $request->validate([
            'entity_id' => 'nullable|integer|exists:entities,id',
            'lottery_id' => 'nullable|integer|exists:lotteries,id',
            'reserve_id' => 'nullable|integer|exists:reserves,id',
            'set_id' => 'nullable|integer|exists:sets,id',
        ]);

        if ($request->filled('set_id')) {
            return $this->apiGetDigitalAvailableForSet($request);
        }

        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'lottery_id' => 'required|integer|exists:lotteries,id',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller || !$seller->entities()->where('entities.id', $request->entity_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        app(\App\Services\PendingDigitalSaleService::class)->releaseExpiredForDigitalContext(
            (int) $request->entity_id,
            (int) $request->lottery_id,
        );

        $pendingService = app(\App\Services\PendingDigitalSaleService::class);
        $reserveId = $request->filled('reserve_id') ? (int) $request->reserve_id : null;
        if ($reserveId) {
            $reserveOk = \App\Models\Reserve::query()
                ->where('id', $reserveId)
                ->where('entity_id', $request->entity_id)
                ->where('lottery_id', $request->lottery_id)
                ->exists();
            if (! $reserveOk) {
                return response()->json(['success' => false, 'message' => 'La reserva no pertenece a esta entidad y sorteo.'], 422);
            }
        }

        $total = $pendingService->countDigitalDisponibleForPool(
            (int) $request->entity_id,
            (int) $request->lottery_id,
            $reserveId
        );

        $priceSetQuery = Set::query()
            ->join('reserves', 'sets.reserve_id', '=', 'reserves.id')
            ->where('reserves.entity_id', $request->entity_id)
            ->where('reserves.lottery_id', $request->lottery_id)
            ->where('sets.physical_participations', '<=', 0)
            ->whereRaw('sets.digital_participations > 0');
        if ($reserveId) {
            $priceSetQuery->where('sets.reserve_id', $reserveId);
        }
        $priceSet = $priceSetQuery
            ->select('sets.total_participation_amount as played_amount')
            ->first();

        return response()->json([
            'success' => true,
            'total_digital_available' => $total,
            'price_per_participation' => $priceSet ? (float) $priceSet->played_amount : 0,
        ]);
    }

    /**
     * Disponibles digitales de un set (pool del set; sin asignación al vendedor).
     */
    protected function apiGetDigitalAvailableForSet(Request $request)
    {
        $user = $request->user();
        if (! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $set = Set::with('reserve')->findOrFail((int) $request->set_id);
        if (($set->digital_participations ?? 0) <= 0) {
            return response()->json(['success' => false, 'message' => 'Este set no es de participaciones digitales.'], 422);
        }

        if (! $seller->entities()->where('entities.id', $set->entity_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        app(\App\Services\PendingDigitalSaleService::class)->releaseExpiredForDigitalContext(
            (int) $set->entity_id,
            (int) ($set->reserve?->lottery_id),
            (int) $set->id,
        );

        $total = app(\App\Services\PendingDigitalSaleService::class)
            ->countDigitalDisponibleForSet((int) $set->id);

        return response()->json([
            'success' => true,
            'total_digital_available' => $total,
            'price_per_participation' => (float) ($set->total_participation_amount ?? $set->played_amount ?? 0),
            'set_id' => $set->id,
        ]);
    }

    /**
     * API: Obtener entidades del vendedor autenticado
     */
    public function apiGetMyEntities(Request $request)
    {
        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $entities = $seller->entities()->where('entities.status', 1)->select('entities.id', 'entities.name', 'entities.image')->get();

        return response()->json([
            'success' => true,
            'entities' => $entities
        ]);
    }

    /**
     * API: Obtener tacos asignados del vendedor autenticado por entidad
     */
    public function apiGetMyTacos(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        // Verificar que el vendedor pertenece a esta entidad
        if (!$seller->entities()->where('entities.id', $request->entity_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        // Obtener todos los sets de esta entidad que tienen participaciones asignadas al vendedor
        $sets = Set::whereHas('reserve', fn ($q) => $q->where('entity_id', $request->entity_id))
            ->whereHas('participations', fn ($q) => $q->where('seller_id', $seller->id))
            ->with(['reserve.lottery', 'designFormats'])
            ->get();

        $tacos = [];
        $totalParticipations = 0;
        $totalAmount = 0;
        $salesRegistered = 0;
        $salesAmount = 0;
        $returnedParticipations = 0;
        $returnedAmount = 0;
        $availableParticipations = 0;
        $availableAmount = 0;
        $paymentBreakdown = ['efectivo' => 0, 'bizum' => 0, 'transferencia' => 0, 'sin_registrar' => 0];

        foreach ($sets as $set) {
            $participations = Participation::where('set_id', $set->id)
                ->where('seller_id', $seller->id)
                ->whereIn('status', ['asignada', 'vendida', 'devuelta','pagada'])
                ->get();

            if ($participations->isEmpty()) continue;

            $pricePerParticipation = (float) ($set->total_participation_amount ?? 0);
            $designFormat = $set->designFormats->first();
            $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
            $participationsPerBook = $output['participations_per_book'] ?? 50;

            // Agrupar por taco
            $tacosByBook = [];
            foreach ($participations as $participation) {
                $bookNumber = (int) ceil($participation->participation_number / $participationsPerBook);
                
                if (!isset($tacosByBook[$bookNumber])) {
                    $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
                    $endParticipation = min($bookNumber * $participationsPerBook, $set->total_participations ?? 1000);
                    $endParticipation = max($startParticipation, $endParticipation);
                    $reservationNumbers = $set->reserve->reservation_numbers ?? [];
                    $reservationNumbersDisplay = is_array($reservationNumbers) ? implode(', ', $reservationNumbers) : '';

                    $physical = (int) ($set->physical_participations ?? 0);
                    $digital = (int) ($set->digital_participations ?? 0);
                    $setType = $physical === 0 ? 'digital' : ($digital === 0 ? 'fisico' : 'mixto');
                    $tacosByBook[$bookNumber] = [
                        'set_id' => $set->id,
                        'set_name' => $set->set_name,
                        'set_number' => $set->set_number ?? $set->id,
                        'book_number' => $bookNumber,
                        'set_type' => $setType,
                        'reservation_numbers' => $reservationNumbers,
                        'reservation_numbers_display' => $reservationNumbersDisplay,
                        'lottery_id' => $set->reserve->lottery_id,
                        'lottery_name' => $set->reserve->lottery->name ?? '',
                        'lottery_date' => $set->reserve->lottery->draw_date ?? null,
                        'start_participation' => $startParticipation,
                        'end_participation' => $endParticipation,
                        'participations_range' => sprintf('%s/%05d-%s/%05d', $set->set_number ?? $set->id, $startParticipation, $set->set_number ?? $set->id, $endParticipation),
                        'total_participations' => 0,
                        'sales_registered' => 0,
                        'returned_participations' => 0,
                        'available_participations' => 0,
                        'sales_amount' => 0,
                        'returned_amount' => 0,
                        'available_amount' => 0,
                        '_min_pn' => $participation->participation_number,
                        '_max_pn' => $participation->participation_number,
                    ];
                }

                $tacosByBook[$bookNumber]['total_participations']++;
                $tacosByBook[$bookNumber]['_min_pn'] = min($tacosByBook[$bookNumber]['_min_pn'], $participation->participation_number);
                $tacosByBook[$bookNumber]['_max_pn'] = max($tacosByBook[$bookNumber]['_max_pn'], $participation->participation_number);
                $totalParticipations++;
                $totalAmount += $pricePerParticipation;

                if (in_array($participation->status, ['vendida', 'pagada'])) {
                    $tacosByBook[$bookNumber]['sales_registered']++;
                    $tacosByBook[$bookNumber]['sales_amount'] += $pricePerParticipation;
                    $salesRegistered++;
                    $salesAmount += $pricePerParticipation;

                    // Método de pago desde participaciones.payment_method (Tarea 3 QR); fallback a settlement para datos antiguos
                    $paymentMethod = $participation->payment_method ?? null;
                    if (($paymentMethod === null || $paymentMethod === '') && $participation->sale_date) {
                        $settlement = SellerSettlement::where('seller_id', $seller->id)
                            ->where('lottery_id', $set->reserve->lottery_id)
                            ->whereDate('settlement_date', '<=', $participation->sale_date)
                            ->whereHas('payments')
                            ->with('payments')
                            ->orderBy('settlement_date', 'desc')
                            ->orderBy('settlement_time', 'desc')
                            ->first();
                        if ($settlement && $settlement->payments->isNotEmpty()) {
                            $paymentMethod = $settlement->payments->first()->payment_method;
                        }
                    }

                    if (in_array($paymentMethod, ['efectivo', 'bizum', 'transferencia'])) {
                        $paymentBreakdown[$paymentMethod] += $pricePerParticipation;
                    } else {
                        $paymentBreakdown['sin_registrar'] += $pricePerParticipation;
                    }
                } elseif ($participation->status === 'devuelta') {
                    $tacosByBook[$bookNumber]['returned_participations']++;
                    $tacosByBook[$bookNumber]['returned_amount'] += $pricePerParticipation;
                    $returnedParticipations++;
                    $returnedAmount += $pricePerParticipation;
                } else {
                    $tacosByBook[$bookNumber]['available_participations']++;
                    $tacosByBook[$bookNumber]['available_amount'] += $pricePerParticipation;
                    $availableParticipations++;
                    $availableAmount += $pricePerParticipation;
                }
            }

            foreach ($tacosByBook as $bookNum => $taco) {
                if (isset($taco['_min_pn'], $taco['_max_pn'])) {
                    $tacosByBook[$bookNum]['start_participation'] = $taco['_min_pn'];
                    $tacosByBook[$bookNum]['end_participation'] = $taco['_max_pn'];
                    $tacosByBook[$bookNum]['participations_range'] = sprintf('%s/%05d-%s/%05d', $taco['set_number'] ?? $taco['set_id'], $taco['_min_pn'], $taco['set_number'] ?? $taco['set_id'], $taco['_max_pn']);
                    unset($tacosByBook[$bookNum]['_min_pn'], $tacosByBook[$bookNum]['_max_pn']);
                }
            }

            $tacos = array_merge($tacos, array_values($tacosByBook));
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total_participations' => $totalParticipations,
                'total_amount' => round($totalAmount, 2),
                'sales_registered' => $salesRegistered,
                'sales_amount' => round($salesAmount, 2),
                'returned_participations' => $returnedParticipations,
                'returned_amount' => round($returnedAmount, 2),
                'available_participations' => $availableParticipations,
                'available_amount' => round($availableAmount, 2),
                'payment_breakdown' => [
                    'efectivo' => round($paymentBreakdown['efectivo'], 2),
                    'bizum' => round($paymentBreakdown['bizum'], 2),
                    'transferencia' => round($paymentBreakdown['transferencia'], 2),
                    'sin_registrar' => round($paymentBreakdown['sin_registrar'], 2),
                ]
            ],
            'tacos' => $tacos
        ]);
    }

    /**
     * API: Obtener participaciones de un taco específico
     */
    public function apiGetTacoParticipations(Request $request, $setId, $bookNumber)
    {
        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $set = Set::with(['reserve.lottery', 'designFormats'])->findOrFail($setId);
        $designFormat = $set->designFormats->first();
        $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
        $participationsPerBook = $output['participations_per_book'] ?? 50;
        $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
        $endParticipation = $bookNumber * $participationsPerBook;

        $participations = Participation::with('activityLogs')
            ->where('set_id', $setId)
            ->where('seller_id', $seller->id)
            ->whereBetween('participation_number', [$startParticipation, $endParticipation])
            ->whereIn('status', ['disponible', 'asignada', 'vendida', 'devuelta', 'pagada'])
            ->orderBy('participation_number')
            ->get();

        // Método de pago desde participaciones.payment_method (Tarea 3 QR); fallback a settlement para datos antiguos
        $lotteryId = $set->reserve->lottery_id ?? null;
        $settlements = SellerSettlement::where('seller_id', $seller->id)
            ->where('lottery_id', $lotteryId)
            ->whereHas('payments')
            ->with('payments')
            ->orderBy('settlement_date', 'desc')
            ->orderBy('settlement_time', 'desc')
            ->get();

        $formattedParticipations = $participations->map(function ($p) use ($set, $settlements, $lotteryId) {
            $paymentMethod = null;
            if (in_array($p->status, ['vendida', 'pagada'])) {
                $paymentMethod = $p->payment_method ?? null;
                if (($paymentMethod === null || $paymentMethod === '') && $p->sale_date) {
                    $saleDate = $p->sale_date->format('Y-m-d');
                    $settlement = $settlements->first(function ($s) use ($saleDate) {
                        return $s->settlement_date->format('Y-m-d') <= $saleDate;
                    });
                    if ($settlement && $settlement->payments->isNotEmpty()) {
                        $payment = $settlement->payments->first();
                        $paymentMethod = $payment ? $payment->payment_method : null;
                    }
                }
            }

            return [
                'id' => $p->id,
                'participation_code' => $p->display_participation_code,
                'participation_number' => $p->participation_number,
                'status' => $p->status,
                'status_text' => $p->status_text,
                'payment_method' => $paymentMethod,
                'sale_date' => $p->sale_date ? $p->sale_date->format('d/m/Y') : null,
                'sale_time' => $p->sale_time ? $p->sale_time->format('H:i') : null,
            ];
        });

        $reservationNumbers = $set->reserve->reservation_numbers ?? [];
        $reservationNumbersDisplay = is_array($reservationNumbers) ? implode(', ', $reservationNumbers) : '';

        $physical = (int) ($set->physical_participations ?? 0);
        $digital = (int) ($set->digital_participations ?? 0);
        $setType = $physical === 0 ? 'digital' : ($digital === 0 ? 'fisico' : 'mixto');
        return response()->json([
            'success' => true,
            'taco_info' => [
                'set_id' => $set->id,
                'set_name' => $set->set_name,
                'set_number' => $set->set_number ?? $set->id,
                'book_number' => $bookNumber,
                'set_type' => $setType,
                'reservation_numbers' => $reservationNumbers,
                'reservation_numbers_display' => $reservationNumbersDisplay,
                'lottery_name' => $set->reserve->lottery->name ?? '',
                'lottery_date' => $set->reserve->lottery->draw_date ? $set->reserve->lottery->draw_date->format('d/m/Y') : null,
                'participations_range' => sprintf('%s/%05d-%s/%05d', $set->set_number ?? $set->id, $startParticipation, $set->set_number ?? $set->id, $endParticipation),
                'price_per_participation' => (float) ($set->total_participation_amount ?? 0),
                'donation_per_participation' => (float) ($set->donation_amount ?? 0),
                'total_per_participation' => (float) (($set->total_participation_amount ?? 0) + ($set->donation_amount ?? 0)),
            ],
            'participations' => $formattedParticipations
        ]);
    }

    /**
     * API Gestor: Entidades que el usuario gestiona (según tabla managers).
     */
    public function apiGetManagerEntities(Request $request)
    {
        $user = $request->user();
        $entityIds = $user->getManagerEntityIds();
        if (empty($entityIds)) {
            return response()->json(['success' => false, 'message' => 'No tienes entidades asignadas como gestor.'], 403);
        }
        $entities = Entity::whereIn('id', $entityIds)->where('status', 1)->select('id', 'name', 'image')->get();
        return response()->json(['success' => true, 'entities' => $entities]);
    }

    /**
     * API Gestor: sorteos de una entidad para asignar participaciones (permiso vendedores, no devoluciones).
     */
    public function apiManagerAssignmentLotteries(Request $request, $entityId)
    {
        $entityId = (int) $entityId;
        if ($denied = $this->jsonUnlessManagerSellersPermission($request->user(), $entityId)) {
            return $denied;
        }

        $lotteries = Lottery::select(['lotteries.id', 'lotteries.name', 'lotteries.description', 'lotteries.draw_date', 'lotteries.image'])
            ->join('reserves', 'lotteries.id', '=', 'reserves.lottery_id')
            ->where('reserves.entity_id', $entityId)
            ->distinct()
            ->orderBy('lotteries.draw_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'lotteries' => $lotteries]);
    }

    /**
     * API Gestor: sets físicos con participaciones asignables (permiso vendedores).
     */
    public function apiManagerAssignmentSets(Request $request, $entityId)
    {
        $entityId = (int) $entityId;
        $request->validate(['lottery_id' => 'required|integer|exists:lotteries,id']);

        if ($denied = $this->jsonUnlessManagerSellersPermission($request->user(), $entityId)) {
            return $denied;
        }

        $lotteryId = (int) $request->lottery_id;

        $sets = Set::with(['reserve:id,lottery_id,reservation_numbers', 'reserve.lottery:id,name,draw_date'])
            ->forUser($request->user())
            ->where('entity_id', $entityId)
            ->where('sets.physical_participations', '>', 0)
            ->whereHas('reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->whereHas('participations', function ($query) {
                $query->where('status', 'disponible');
            })
            ->select([
                'sets.id',
                'sets.set_name',
                'sets.set_number',
                'sets.reserve_id',
                'sets.digital_participations',
                'sets.physical_participations',
            ])
            ->orderBy('sets.set_number')
            ->get();

        return response()->json(['success' => true, 'sets' => $sets]);
    }

    /**
     * API Gestor: resolver referencia QR/código para asignación (permiso vendedores).
     */
    public function apiManagerAssignmentValidateReference(Request $request, $entityId)
    {
        $entityId = (int) $entityId;
        $request->validate([
            'lottery_id' => 'required|integer|exists:lotteries,id',
            'referencia' => 'required|string|max:120',
            'sig' => 'nullable|string|max:16',
        ]);

        if ($denied = $this->jsonUnlessManagerSellersPermission($request->user(), $entityId)) {
            return $denied;
        }

        $lotteryId = (int) $request->lottery_id;
        $found = $this->findSetAndParticipationByReferenceForUser(
            $request->user(),
            $request->referencia,
            $request->input('sig')
        );

        if (! $found) {
            return response()->json([
                'success' => false,
                'message' => 'No se encuentra ninguna participación con esa referencia.',
                'participations' => [],
            ], 404);
        }

        $set = $found['set'];
        if ((int) $set->entity_id !== $entityId) {
            return response()->json([
                'success' => false,
                'message' => 'La participación no pertenece a la entidad seleccionada.',
                'participations' => [],
            ], 422);
        }

        $reserve = $set->reserve ?? $set->reserve()->first();
        if (! $reserve || (int) $reserve->lottery_id !== $lotteryId) {
            return response()->json([
                'success' => false,
                'message' => 'La participación no pertenece al sorteo seleccionado.',
                'participations' => [],
            ], 422);
        }

        $participation = Participation::forUser($request->user())
            ->where('set_id', $set->id)
            ->where('participation_number', $found['participation_number'])
            ->where('status', 'disponible')
            ->first();

        if (! $participation) {
            return response()->json([
                'success' => false,
                'message' => 'La participación no está disponible para asignar.',
                'participations' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'participations' => [[
                'id' => $participation->id,
                'number' => $participation->participation_number,
                'participation_code' => $participation->participation_code,
                'set_id' => $set->id,
                'set_name' => $set->set_name ?? 'Set '.$set->set_number,
            ]],
        ]);
    }

    /**
     * API Gestor: Vendedores de una entidad (para listado en app).
     * Devuelve: id, name, image, participations_count, pending_amount, group_name, is_external.
     */
    public function apiGetManagerEntitySellers(Request $request, $entityId)
    {
        $entityId = (int) $entityId;
        $user = $request->user();
        $managerEntityIds = $user->getManagerEntityIds();
        if (!in_array($entityId, $managerEntityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        $sellers = Seller::whereHas('entities', fn ($q) => $q->where('entities.id', $entityId))
            ->where('status', Seller::STATUS_ACTIVE)
            ->with(['user:id,name,last_name,image','groups'])
            ->orderBy('group_priority', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $sellerIds = $sellers->pluck('id')->all();
        $pendingBySeller = $this->getPendingLiquidationBySellers($sellerIds);

        $setIdsEntity = Set::whereHas('reserve', fn ($q) => $q->where('entity_id', $entityId))->pluck('id')->all();
        $participationCounts = [];
        if (!empty($setIdsEntity)) {
            $counts = Participation::whereIn('seller_id', $sellerIds)
                ->whereIn('set_id', $setIdsEntity)
                ->whereIn('status', ['asignada', 'vendida', 'devuelta', 'pagada'])
                ->selectRaw('seller_id, count(*) as cnt')
                ->groupBy('seller_id')
                ->pluck('cnt', 'seller_id');
            foreach ($counts as $sid => $cnt) {
                $participationCounts[(int) $sid] = (int) $cnt;
            }
        }

        $list = $sellers->map(function ($seller) use ($pendingBySeller, $participationCounts) {
            $groupNames = $seller->relationLoaded('groups')
                ? $seller->groups->pluck('name')->values()->all()
                : [];
            if (empty($groupNames)) {
                $col = trim((string) ($seller->getRawOriginal('group_name') ?? ''));
                $groupNames = $col !== '' ? [$col] : [];
            }
            return [
                'id' => $seller->id,
                'name' => $seller->full_name,
                'first_name' => $seller->display_name,
                'last_name' => trim(($seller->display_last_name ?? '') . ' ' . ($seller->attributes['last_name2'] ?? '')),
                'image' => $seller->display_image,
                'participations_count' => $participationCounts[$seller->id] ?? 0,
                'pending_amount' => (float) ($pendingBySeller[$seller->id] ?? 0),
                'group_name' => $groupNames,
                'is_external' => $seller->user_id == 0 || $seller->seller_type === 'externo',
            ];
        })->values()->all();

        return response()->json(['success' => true, 'sellers' => $list]);
    }

    /**
     * API Gestor: Detalle de un vendedor (participaciones + liquidación) para la app.
     */
    public function apiGetManagerSellerDetail(Request $request, $entityId, $sellerId)
    {
        $entityId = (int) $entityId;
        $sellerId = (int) $sellerId;
        $user = $request->user();
        $managerEntityIds = $user->getManagerEntityIds();
        if (!in_array($entityId, $managerEntityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        $entityIdInt = (int) $entityId;
        $seller = Seller::with(['user:id,name,last_name,last_name2,image,email,phone,birthday,nif_cif', 'groups'])
            ->whereHas('entities', fn ($q) => $q->where('entities.id', $entityIdInt))
            ->where('status', Seller::STATUS_ACTIVE)
            ->find($sellerId);
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 404);
        }

        $sets = Set::whereHas('reserve', fn ($q) => $q->where('entity_id', $entityId))
            ->whereHas('participations', fn ($q) => $q->where('seller_id', $seller->id))
            ->with(['reserve.lottery', 'designFormats'])
            ->get();

        $totalParticipations = 0;
        $totalAmount = 0;
        $salesRegistered = 0;
        $salesAmount = 0;
        $returnedParticipations = 0;
        $returnedAmount = 0;
        $availableParticipations = 0;
        $availableAmount = 0;

        foreach ($sets as $set) {
            $participations = Participation::where('set_id', $set->id)
                ->where('seller_id', $seller->id)
                ->whereIn('status', ['asignada', 'vendida', 'devuelta', 'pagada'])
                ->get();
            if ($participations->isEmpty()) {
                continue;
            }
            $pricePerParticipation = (float) ($set->total_participation_amount ?? 0);
            foreach ($participations as $p) {
                $totalParticipations++;
                $totalAmount += $pricePerParticipation;
                if (in_array($p->status, ['vendida', 'pagada'])) {
                    $salesRegistered++;
                    $salesAmount += $pricePerParticipation;
                } elseif ($p->status === 'devuelta') {
                    $returnedParticipations++;
                    $returnedAmount += $pricePerParticipation;
                } else {
                    $availableParticipations++;
                    $availableAmount += $pricePerParticipation;
                }
            }
        }

        $pendingBySeller = $this->getPendingLiquidationBySellers([$seller->id]);
        $totalToPay = (float) ($pendingBySeller[$seller->id] ?? 0);
        $totalPaid = SellerSettlement::where('seller_id', $seller->id)->sum('paid_amount');
        $totalToLiquidate = $totalAmount > 0 ? (float) ($salesAmount + $availableAmount) : 0;

        // Loterías con pendiente por liquidar (para modal de liquidación en app)
        $lotteriesWithPending = $this->getLotteriesWithPendingForSeller($seller->id, $entityId);

        $groupNames = $seller->relationLoaded('groups')
            ? $seller->groups->pluck('name')->values()->all()
            : [];
        if (empty($groupNames)) {
            $col = trim((string) ($seller->getRawOriginal('group_name') ?? ''));
            $groupNames = $col !== '' ? [$col] : [];
        }

        $birthday = $seller->birthday ?? ($seller->relationLoaded('user') && $seller->user ? $seller->user->birthday : null);
        $birthdayFormatted = $birthday ? $birthday->format('d/m/Y') : '';

        $first_name = $seller->seller_type === 'externo' ? ($seller->getRawOriginal('name') ?? '') : ($seller->user ? $seller->user->name : '');
        $last_name = $seller->display_last_name ?? '';
        $last_name2 = $seller->seller_type === 'externo' ? ($seller->getRawOriginal('last_name2') ?? '') : ($seller->user ? ($seller->user->last_name2 ?? '') : '');

        return response()->json([
            'success' => true,
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->full_name,
                'image' => $seller->display_image,
                'is_external' => $seller->user_id == 0 || $seller->seller_type === 'externo',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'last_name2' => $last_name2,
                'email' => $seller->display_email ?? '',
                'phone' => $seller->display_phone ?? '',
                'birthday' => $birthdayFormatted,
                'nif_cif' => $seller->getRawOriginal('nif_cif') ?: ($seller->user ? ($seller->user->nif_cif ?? '') : ''),
                'group_name' => $groupNames,
            ],
            'participations_summary' => [
                'total_participations' => $totalParticipations,
                'total_amount' => round($totalAmount, 2),
                'sales_registered' => $salesRegistered,
                'sales_amount' => round($salesAmount, 2),
                'returned_participations' => $returnedParticipations,
                'returned_amount' => round($returnedAmount, 2),
                'available_participations' => $availableParticipations,
                'available_amount' => round($availableAmount, 2),
            ],
            'liquidation_summary' => [
                'total_to_liquidate' => round($totalToLiquidate, 2),
                'total_paid' => round((float) $totalPaid, 2),
                'total_to_pay' => round($totalToPay, 2),
            ],
            'lotteries_with_pending' => $lotteriesWithPending,
        ]);
    }

    /**
     * Para un vendedor y una entidad, devuelve las loterías que tienen pendiente por liquidar.
     *
     * @return array<int, array{lottery_id: int, lottery_name: string, pending_amount: float}>
     */
    private function getLotteriesWithPendingForSeller(int $sellerId, int $entityId): array
    {
        $participations = Participation::query()
            ->eligibleForSellerSettlement($sellerId)
            ->whereHas('set.reserve', fn ($q) => $q->where('entity_id', $entityId))
            ->with('set.reserve')
            ->get();

        $byLottery = [];
        foreach ($participations as $participation) {
            $lotteryId = (int) ($participation->set->reserve->lottery_id ?? 0);
            if ($lotteryId <= 0) {
                continue;
            }
            $byLottery[$lotteryId] = ($byLottery[$lotteryId] ?? 0)
                + (float) ($participation->set->total_participation_amount ?? 0);
        }

        $paidByLottery = SellerSettlement::where('seller_id', $sellerId)
            ->whereIn('lottery_id', array_keys($byLottery))
            ->selectRaw('lottery_id, SUM(paid_amount) as total_paid')
            ->groupBy('lottery_id')
            ->pluck('total_paid', 'lottery_id');

        $lotteries = $byLottery ? Lottery::whereIn('id', array_keys($byLottery))->pluck('name', 'id') : collect();

        $result = [];
        foreach ($byLottery as $lotteryId => $totalToLiquidate) {
            $totalPaid = (float) ($paidByLottery[$lotteryId] ?? 0);
            $pending = $totalToLiquidate - $totalPaid;
            if ($pending > 0.001) {
                $result[] = [
                    'lottery_id' => (int) $lotteryId,
                    'lottery_name' => $lotteries[$lotteryId] ?? 'Sorteo #' . $lotteryId,
                    'pending_amount' => round($pending, 2),
                ];
            }
        }
        usort($result, fn ($a, $b) => $b['pending_amount'] <=> $a['pending_amount']);

        return $result;
    }

    /**
     * API Gestor: Registrar liquidación de un vendedor (solo seller_settlements; no toca participaciones).
     * Misma lógica que storeSettlement, con comprobación de acceso por entidad.
     */
    public function apiManagerStoreSettlement(Request $request, $entityId, $sellerId)
    {
        $entityId = (int) $entityId;
        $sellerId = (int) $sellerId;
        $user = $request->user();
        $managerEntityIds = $user->getManagerEntityIds();
        if (! in_array($entityId, $managerEntityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }

        $seller = Seller::whereHas('entities', fn ($q) => $q->where('entities.id', $entityId))
            ->where('status', Seller::STATUS_ACTIVE)
            ->find($sellerId);
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 404);
        }

        $data = $request->validate([
            'lottery_id' => 'required|exists:lotteries,id',
            'pagos' => 'required|array',
            'pagos.*.payment_method' => 'required|string|in:efectivo,bizum,transferencia',
            'pagos.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $totalPagoNuevo = collect($data['pagos'])->sum('amount');

            $participations = $this->settlementEligibleParticipationsQuery($seller->id, (int) $data['lottery_id'])->get();

            $totalParticipations = $participations->count();
            $pricePerParticipation = $participations->first()->set->total_participation_amount ?? 0;
            $totalAmount = $participations->sum(fn ($p) => (float) ($p->set->total_participation_amount ?? 0));

            $previousPaid = SellerSettlement::where('seller_id', $seller->id)
                ->where('lottery_id', $data['lottery_id'])
                ->sum('paid_amount');

            $totalPaidWithNew = $previousPaid + $totalPagoNuevo;
            $pendingAmount = $totalAmount - $totalPaidWithNew;
            $calculatedParticipations = $pricePerParticipation > 0 ? ($totalPagoNuevo / $pricePerParticipation) : 0;

            $now = Carbon::now();

            $settlement = SellerSettlement::create([
                'seller_id' => $seller->id,
                'lottery_id' => $data['lottery_id'],
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalPagoNuevo,
                'pending_amount' => $pendingAmount,
                'total_participations' => $totalParticipations,
                'calculated_participations' => round($calculatedParticipations, 2),
                'settlement_date' => $now->format('Y-m-d'),
                'settlement_time' => $now->format('H:i:s'),
                'notes' => 'Liquidación de vendedor (app gestor)',
            ]);

            foreach ($data['pagos'] as $pago) {
                SellerSettlementPayment::create([
                    'seller_settlement_id' => $settlement->id,
                    'amount' => $pago['amount'],
                    'payment_method' => $pago['payment_method'],
                    'notes' => 'Pago de liquidación - ' . ucfirst($pago['payment_method']),
                    'payment_date' => $now,
                ]);
            }

            DB::commit();

            // Email liquidación parcial / total 0 al vendedor, y copia informativa a entidad principal.
            try {
                $seller = Seller::with(['user', 'entities.manager.user'])->find($data['seller_id']);
                $isFullySettled = (float) $pendingAmount <= 0.0001;
                $communicationEmailService = app(CommunicationEmailService::class);

                if ($seller && $seller->user && !empty($seller->user->email)) {
                    $communicationEmailService->sendAndLog(
                        recipientEmail: (string) $seller->user->email,
                        recipientRole: 'vendedor',
                        recipientUser: $seller->user,
                        messageType: $isFullySettled ? 'seller_settlement_full' : 'seller_settlement_partial',
                        templateKey: null,
                        mailClass: SellerSettlementStatusMail::class,
                        mailPayload: [
                            'seller_id' => $seller->id,
                            'settlement_id' => $settlement->id,
                            'is_fully_settled' => $isFullySettled,
                        ],
                        context: ['seller_id' => $seller->id, 'lottery_id' => $data['lottery_id'], 'entity_id' => $primaryEntity?->id],
                    );
                }

                // ¿También a entidad? Sí, envío informativo al gestor principal de la primera entidad vinculada.
                $entityManagerUser = $seller?->entities?->first()?->manager?->user;
                $primaryEntity = $seller?->entities?->first();
                $contextEntityId = $primaryEntity?->id;
                if ($entityManagerUser && !empty($entityManagerUser->email)) {
                    $communicationEmailService->sendAndLog(
                        recipientEmail: (string) $entityManagerUser->email,
                        recipientRole: 'gestor_entidad',
                        recipientUser: $entityManagerUser,
                        messageType: $isFullySettled ? 'seller_settlement_full_copy_entity' : 'seller_settlement_partial_copy_entity',
                        templateKey: null,
                        mailClass: SellerSettlementStatusMail::class,
                        mailPayload: [
                            'seller_id' => $seller->id,
                            'settlement_id' => $settlement->id,
                            'is_fully_settled' => $isFullySettled,
                        ],
                        context: ['seller_id' => $seller->id, 'lottery_id' => $data['lottery_id'], 'entity_id' => $contextEntityId],
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando emails de liquidación: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Liquidación registrada correctamente',
                'settlement_id' => $settlement->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la liquidación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API Gestor: Comprobar si existe un usuario por email (para flujo Añadir Vendedor SIPART).
     */
    public function apiManagerCheckUserEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $exists = User::where('email', $request->email)->exists();
        return response()->json(['exists' => $exists]);
    }

    /**
     * API Gestor: Añadir vendedor PARTILOT (usuario existente). entity_id debe estar en getManagerEntityIds().
     */
    public function apiManagerStoreExistingUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'entity_id' => 'required|integer|exists:entities,id',
        ]);
        $user = $request->user();
        if (!in_array((int) $request->entity_id, $user->getManagerEntityIds(), true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }
        try {
            $sellerService = new SellerService();
            $seller = $sellerService->createSeller($request->only(['email']), (int) $request->entity_id, 'partilot');
            return response()->json(['success' => true, 'message' => 'Vendedor PARTILOT añadido.', 'seller_id' => $seller->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API Gestor: Invitar vendedor (0 coincidencias): crear seller externo con email para invitación.
     */
    public function apiManagerStoreNewUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'entity_id' => 'required|integer|exists:entities,id',
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);
        $user = $request->user();
        if (!in_array((int) $request->entity_id, $user->getManagerEntityIds(), true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }
        try {
            $sellerService = new SellerService();
            $seller = $sellerService->createSeller($request->only(['email', 'name', 'last_name']), (int) $request->entity_id, 'externo');
            return response()->json(['success' => true, 'message' => 'Invitación enviada.', 'seller_id' => $seller->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API Gestor: Crear vendedor externo (formulario completo). entity_id debe estar en getManagerEntityIds().
     */
    public function apiManagerStoreExternalSeller(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'nif_cif' => ['required', 'string', 'max:255', new \App\Rules\SpanishDocument],
        ]);
        $user = $request->user();
        if (!in_array((int) $request->entity_id, $user->getManagerEntityIds(), true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }
        try {
            $sellerService = new SellerService();
            $data = $request->only(['name', 'last_name', 'last_name2', 'email', 'phone', 'birthday', 'nif_cif']);
            $seller = $sellerService->createSeller($data, (int) $request->entity_id, 'externo');
            return response()->json(['success' => true, 'message' => 'Vendedor externo creado.', 'seller_id' => $seller->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API Gestor: Tacos de una entidad (participaciones asignadas a vendedores de esa entidad), con nombre de vendedor.
     */
    public function apiGetManagerTacos(Request $request)
    {
        $request->validate(['entity_id' => 'required|integer|exists:entities,id']);
        $user = $request->user();
        $entityIds = $user->getManagerEntityIds();
        if (!in_array((int) $request->entity_id, $entityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }
        $entityId = (int) $request->entity_id;
        $sellerIds = Seller::whereHas('entities', fn ($q) => $q->where('entities.id', $entityId))
            ->where('status', Seller::STATUS_ACTIVE)
            ->pluck('id')
            ->all();
        if (empty($sellerIds)) {
            return response()->json([
                'success' => true,
                'summary' => [
                    'total_participations' => 0, 'total_amount' => 0,
                    'sales_registered' => 0, 'sales_amount' => 0,
                    'returned_participations' => 0, 'returned_amount' => 0,
                    'available_participations' => 0, 'available_amount' => 0,
                    'payment_breakdown' => ['efectivo' => 0, 'bizum' => 0, 'transferencia' => 0, 'sin_registrar' => 0],
                ],
                'tacos' => [],
            ]);
        }
        $sets = Set::whereHas('reserve', fn ($q) => $q->where('entity_id', $entityId))
            ->whereHas('participations', fn ($q) => $q->whereIn('seller_id', $sellerIds))
            ->with(['reserve.lottery', 'designFormats'])
            ->get();
        $sellersById = Seller::whereIn('id', $sellerIds)->get()->keyBy('id');
        $tacos = [];
        $totals = ['participations' => 0, 'amount' => 0, 'sales' => 0, 'salesAmount' => 0, 'returned' => 0, 'returnedAmount' => 0, 'available' => 0, 'availableAmount' => 0];
        $paymentBreakdown = ['efectivo' => 0, 'bizum' => 0, 'transferencia' => 0, 'sin_registrar' => 0];
        foreach ($sets as $set) {
            $participations = Participation::where('set_id', $set->id)
                ->whereIn('seller_id', $sellerIds)
                ->whereIn('status', ['asignada', 'vendida', 'devuelta','pagada'])
                ->get();
            if ($participations->isEmpty()) continue;
            $pricePerParticipation = (float) ($set->total_participation_amount ?? 0);
            $designFormat = $set->designFormats->first();
            $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
            $participationsPerBook = $output['participations_per_book'] ?? 50;
            $tacosByBookSeller = [];
            foreach ($participations as $p) {
                $bookNumber = (int) ceil($p->participation_number / $participationsPerBook);
                $key = $bookNumber . '_' . $p->seller_id;
                if (!isset($tacosByBookSeller[$key])) {
                    $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
                    $endParticipation = $bookNumber * $participationsPerBook;
                    $endParticipation = max($startParticipation, min($endParticipation, $set->total_participations ?? 1000));
                    $seller = $sellersById->get($p->seller_id);
                    $sellerName = $seller ? trim($seller->name . ' ' . ($seller->last_name ?? '')) : 'Vendedor';
                    $reservationNumbers = $set->reserve->reservation_numbers ?? [];
                    $reservationNumbersDisplay = is_array($reservationNumbers) ? implode(', ', $reservationNumbers) : '';
                    $physical = (int) ($set->physical_participations ?? 0);
                    $digital = (int) ($set->digital_participations ?? 0);
                    $setType = $physical === 0 ? 'digital' : ($digital === 0 ? 'fisico' : 'mixto');
                    $tacosByBookSeller[$key] = [
                        'set_id' => $set->id,
                        'set_name' => $set->set_name,
                        'set_number' => $set->set_number ?? $set->id,
                        'book_number' => $bookNumber,
                        'seller_id' => $p->seller_id,
                        'seller_name' => $sellerName,
                        'set_type' => $setType,
                        'reservation_numbers' => $reservationNumbers,
                        'reservation_numbers_display' => $reservationNumbersDisplay,
                        'lottery_id' => $set->reserve->lottery_id,
                        'lottery_name' => $set->reserve->lottery->name ?? '',
                        'lottery_date' => $set->reserve->lottery->draw_date ?? null,
                        'start_participation' => $startParticipation,
                        'end_participation' => $endParticipation,
                        'participations_range' => sprintf('%s/%05d-%s/%05d', $set->set_number ?? $set->id, $startParticipation, $set->set_number ?? $set->id, $endParticipation),
                        'total_participations' => 0, 'sales_registered' => 0, 'returned_participations' => 0, 'available_participations' => 0,
                        'sales_amount' => 0, 'returned_amount' => 0, 'available_amount' => 0,
                        '_min_pn' => $p->participation_number,
                        '_max_pn' => $p->participation_number,
                    ];
                }
                $tacosByBookSeller[$key]['total_participations']++;
                $tacosByBookSeller[$key]['_min_pn'] = min($tacosByBookSeller[$key]['_min_pn'], $p->participation_number);
                $tacosByBookSeller[$key]['_max_pn'] = max($tacosByBookSeller[$key]['_max_pn'], $p->participation_number);
                $totals['participations']++;
                $totals['amount'] += $pricePerParticipation;
                if (in_array($p->status, ['vendida', 'pagada'])) {
                    $tacosByBookSeller[$key]['sales_registered']++;
                    $tacosByBookSeller[$key]['sales_amount'] += $pricePerParticipation;
                    $totals['sales']++;
                    $totals['salesAmount'] += $pricePerParticipation;
                    $pm = $p->payment_method ?? '';
                    if (in_array($pm, ['efectivo', 'bizum', 'transferencia'])) {
                        $paymentBreakdown[$pm] += $pricePerParticipation;
                    } else {
                        $paymentBreakdown['sin_registrar'] += $pricePerParticipation;
                    }
                } elseif ($p->status === 'devuelta') {
                    $tacosByBookSeller[$key]['returned_participations']++;
                    $tacosByBookSeller[$key]['returned_amount'] += $pricePerParticipation;
                    $totals['returned']++;
                    $totals['returnedAmount'] += $pricePerParticipation;
                } else {
                    $tacosByBookSeller[$key]['available_participations']++;
                    $tacosByBookSeller[$key]['available_amount'] += $pricePerParticipation;
                    $totals['available']++;
                    $totals['availableAmount'] += $pricePerParticipation;
                }
            }
            foreach ($tacosByBookSeller as $key => $taco) {
                if (isset($taco['_min_pn'], $taco['_max_pn'])) {
                    $tacosByBookSeller[$key]['start_participation'] = $taco['_min_pn'];
                    $tacosByBookSeller[$key]['end_participation'] = $taco['_max_pn'];
                    $tacosByBookSeller[$key]['participations_range'] = sprintf('%s/%05d-%s/%05d', $taco['set_number'] ?? $taco['set_id'], $taco['_min_pn'], $taco['set_number'] ?? $taco['set_id'], $taco['_max_pn']);
                    unset($tacosByBookSeller[$key]['_min_pn'], $tacosByBookSeller[$key]['_max_pn']);
                }
            }
            $tacos = array_merge($tacos, array_values($tacosByBookSeller));
        }
        return response()->json([
            'success' => true,
            'summary' => [
                'total_participations' => $totals['participations'],
                'total_amount' => round($totals['amount'], 2),
                'sales_registered' => $totals['sales'],
                'sales_amount' => round($totals['salesAmount'], 2),
                'returned_participations' => $totals['returned'],
                'returned_amount' => round($totals['returnedAmount'], 2),
                'available_participations' => $totals['available'],
                'available_amount' => round($totals['availableAmount'], 2),
                'payment_breakdown' => [
                    'efectivo' => round($paymentBreakdown['efectivo'], 2),
                    'bizum' => round($paymentBreakdown['bizum'], 2),
                    'transferencia' => round($paymentBreakdown['transferencia'], 2),
                    'sin_registrar' => round($paymentBreakdown['sin_registrar'], 2),
                ],
            ],
            'tacos' => $tacos,
        ]);
    }

    /**
     * API Gestor: Participaciones de un taco (set + book + seller); el gestor debe indicar seller_id por query.
     */
    public function apiGetManagerTacoParticipations(Request $request, $setId, $bookNumber)
    {
        $request->validate(['seller_id' => 'required|integer|exists:sellers,id']);
        $sellerId = (int) $request->seller_id;
        $user = $request->user();
        $entityIds = $user->getManagerEntityIds();
        if (empty($entityIds)) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos.'], 403);
        }
        $seller = Seller::with('entities:id')->find($sellerId);
        if (!$seller || !$seller->entities->contains(fn ($e) => in_array($e->id, $entityIds, true))) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a este vendedor.'], 403);
        }
        $set = Set::with(['reserve.lottery', 'designFormats'])->findOrFail($setId);
        if (!in_array((int) $set->reserve->entity_id, $entityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a este set.'], 403);
        }
        $designFormat = $set->designFormats->first();
        $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
        $participationsPerBook = $output['participations_per_book'] ?? 50;
        $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
        $endParticipation = $bookNumber * $participationsPerBook;
        $participations = Participation::where('set_id', $setId)
            ->where('seller_id', $sellerId)
            ->whereBetween('participation_number', [$startParticipation, $endParticipation])
            ->whereIn('status', ['disponible', 'asignada', 'vendida', 'devuelta', 'pagada'])
            ->orderBy('participation_number')
            ->get();
        $lotteryId = $set->reserve->lottery_id ?? null;
        $settlements = SellerSettlement::where('seller_id', $sellerId)
            ->where('lottery_id', $lotteryId)
            ->whereHas('payments')
            ->with('payments')
            ->orderBy('settlement_date', 'desc')
            ->orderBy('settlement_time', 'desc')
            ->get();
        $formattedParticipations = $participations->map(function ($p) use ($settlements) {
            $paymentMethod = null;
            if (in_array($p->status, ['vendida', 'pagada'])) {
                $paymentMethod = $p->payment_method ?? null;
                if (($paymentMethod === null || $paymentMethod === '') && $p->sale_date) {
                    $saleDate = $p->sale_date->format('Y-m-d');
                    $settlement = $settlements->first(fn ($s) => $s->settlement_date->format('Y-m-d') <= $saleDate);
                    if ($settlement && $settlement->payments->isNotEmpty()) {
                        $paymentMethod = $settlement->payments->first()->payment_method ?? null;
                    }
                }
            }
            return [
                'id' => $p->id,
                'participation_code' => $p->display_participation_code,
                'participation_number' => $p->participation_number,
                'status' => $p->status,
                'payment_method' => $paymentMethod,
                'sale_date' => $p->sale_date ? $p->sale_date->format('d/m/Y') : null,
                'sale_time' => $p->sale_time ? $p->sale_time->format('H:i') : null,
            ];
        });
        $reservationNumbers = $set->reserve->reservation_numbers ?? [];
        $reservationNumbersDisplay = is_array($reservationNumbers) ? implode(', ', $reservationNumbers) : '';
        $sellerName = trim($seller->name . ' ' . ($seller->last_name ?? ''));
        $physical = (int) ($set->physical_participations ?? 0);
        $digital = (int) ($set->digital_participations ?? 0);
        $setType = $physical === 0 ? 'digital' : ($digital === 0 ? 'fisico' : 'mixto');
        return response()->json([
            'success' => true,
            'taco_info' => [
                'set_id' => $set->id,
                'set_name' => $set->set_name,
                'set_number' => $set->set_number ?? $set->id,
                'book_number' => (int) $bookNumber,
                'seller_id' => $sellerId,
                'seller_name' => $sellerName,
                'set_type' => $setType,
                'reservation_numbers' => $reservationNumbers,
                'reservation_numbers_display' => $reservationNumbersDisplay,
                'lottery_name' => $set->reserve->lottery->name ?? '',
                'lottery_date' => $set->reserve->lottery->draw_date ? $set->reserve->lottery->draw_date->format('d/m/Y') : null,
                'participations_range' => sprintf('%s/%05d-%s/%05d', $set->set_number ?? $set->id, $startParticipation, $set->set_number ?? $set->id, $endParticipation),
                'played_amount' => (float) ($set->played_amount ?? 0),
                'price_per_participation' => (float) ($set->total_participation_amount ?? 0),
                'donation_per_participation' => (float) ($set->donation_amount ?? 0),
                'total_per_participation' => (float) (($set->played_amount ?? 0) + ($set->donation_amount ?? 0)),
            ],
            'participations' => $formattedParticipations,
        ]);
    }

    /**
     * API: Resolver taco_ref (QR del taco) y devolver rangos de participaciones disponibles para el vendedor.
     * Tarea 2 tacos: al escanear el QR de la portada del taco, la app llama aquí y muestra confirmación con rangos a vender.
     */
    public function apiTacoByQr(Request $request)
    {
        $request->validate([
            'taco_ref' => 'required|string|max:120',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $parsed = DesignFormat::parseTacoRef($request->taco_ref);
        if (!$parsed) {
            return response()->json([
                'success' => false,
                'message' => 'Código de taco no válido o corrupto.',
            ], 422);
        }

        $setId = $parsed['set_id'];
        $bookNumber = $parsed['book_number'];

        $set = Set::with(['reserve.lottery', 'designFormats'])->find($setId);
        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado.'], 404);
        }

        $designFormat = $set->designFormats->first();
        $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
        $participationsPerBook = (int) ($output['participations_per_book'] ?? 50);
        $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
        $endParticipation = min($bookNumber * $participationsPerBook, (int) ($set->total_participations ?? 0));

        if ($endParticipation < $startParticipation) {
            return response()->json(['success' => false, 'message' => 'Rango de taco inválido.'], 422);
        }

        $participations = Participation::where('set_id', $setId)
            ->where('seller_id', $seller->id)
            ->whereBetween('participation_number', [$startParticipation, $endParticipation])
            ->where('status', 'asignada')
            ->orderBy('participation_number')
            ->get();

        if ($participations->isEmpty()) {
            return response()->json([
                'success' => true,
                'taco_ref' => $request->taco_ref,
                'set_id' => $setId,
                'book_number' => $bookNumber,
                'set_name' => $set->set_name,
                'lottery_name' => $set->reserve->lottery->name ?? '',
                'lottery_date' => $set->reserve->lottery->draw_date ? $set->reserve->lottery->draw_date->format('d/m/Y') : null,
                'rangos_disponibles' => [],
                'total_disponibles' => 0,
                'importe_por_participacion' => (float) ($set->total_participation_amount ?? 0),
                'importe_total' => 0,
                'primera_referencia' => null,
                'message' => 'No tienes participaciones disponibles en este taco (ya vendidas o no asignadas).',
            ]);
        }

        $numbers = $participations->pluck('participation_number')->sort()->values()->all();
        $rangos = $this->buildConsecutiveRanges($numbers);

        $pricePerParticipation = (float) ($set->total_participation_amount ?? 0);
        $totalDisponibles = $participations->count();
        $importeTotal = round($totalDisponibles * $pricePerParticipation, 2);

        $primeraReferencia = null;
        if ($set->tickets && !empty($numbers)) {
            $tickets = is_array($set->tickets) ? $set->tickets : json_decode($set->tickets, true);
            if (is_array($tickets)) {
                $firstNumGlobal = (int) $numbers[0];
                foreach ($tickets as $ticket) {
                    if (isset($ticket['n']) && (int) $ticket['n'] === $firstNumGlobal) {
                        $primeraReferencia = $ticket['r'] ?? null;
                        break;
                    }
                }
                // Fallback: si no se encontró por n, buscar por participation_code de la primera participación
                if (!$primeraReferencia && $participations->isNotEmpty()) {
                    $firstParticipation = $participations->first();
                    $participationCode = $firstParticipation->participation_code;
                    if ($participationCode) {
                        foreach ($tickets as $ticket) {
                            if (isset($ticket['r']) && $ticket['r'] === $participationCode) {
                                $primeraReferencia = $ticket['r'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'taco_ref' => $request->taco_ref,
            'set_id' => $setId,
            'book_number' => $bookNumber,
            'set_name' => $set->set_name,
            'lottery_name' => $set->reserve->lottery->name ?? '',
            'lottery_date' => $set->reserve->lottery->draw_date ? $set->reserve->lottery->draw_date->format('d/m/Y') : null,
            'rangos_disponibles' => $rangos,
            'total_disponibles' => $totalDisponibles,
            'importe_por_participacion' => $pricePerParticipation,
            'importe_total' => $importeTotal,
            'primera_referencia' => $primeraReferencia,
            'participations_per_book' => $participationsPerBook,
        ]);
    }

    /**
     * API gestor: resolver taco_ref para asignar participaciones libres del libro (taco) al vendedor seleccionado.
     * Devuelve sorteo, set y rangos consecutivos de números en estado disponible sin vendedor dentro del taco.
     */
    public function apiManagerTacoForAssign(Request $request)
    {
        $request->validate([
            'taco_ref' => 'required|string|max:120',
            'entity_id' => 'required|integer|exists:entities,id',
            'seller_id' => 'required|integer|exists:sellers,id',
        ]);

        $user = $request->user();
        $entityId = (int) $request->entity_id;
        $sellerId = (int) $request->seller_id;

        $entityIds = $user->getManagerEntityIds();
        if (! in_array($entityId, $entityIds, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
        }
        if (! $user->canAccessSeller($sellerId)) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para gestionar este vendedor.'], 403);
        }

        $parsed = DesignFormat::parseTacoRef($request->taco_ref);
        if (! $parsed || (int) $parsed['entity_id'] !== $entityId) {
            return response()->json([
                'success' => false,
                'message' => 'Código de taco no válido o no corresponde a esta entidad.',
            ], 422);
        }

        $setId = $parsed['set_id'];
        $bookNumber = $parsed['book_number'];

        $set = Set::with(['reserve.lottery', 'designFormats'])->find($setId);
        if (! $set || (int) $set->entity_id !== $entityId) {
            return response()->json(['success' => false, 'message' => 'Set no encontrado o no pertenece a la entidad.'], 404);
        }

        if ((int) ($set->physical_participations ?? 0) <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La asignación por taco desde la app solo aplica a sets con participaciones físicas.',
            ], 422);
        }

        $designFormat = $set->designFormats->first();
        $output = $designFormat && is_array($designFormat->output) ? $designFormat->output : [];
        $participationsPerBook = (int) ($output['participations_per_book'] ?? 50);
        $startParticipation = ($bookNumber - 1) * $participationsPerBook + 1;
        $endParticipation = min($bookNumber * $participationsPerBook, (int) ($set->total_participations ?? 0));

        if ($endParticipation < $startParticipation) {
            return response()->json(['success' => false, 'message' => 'Rango de taco inválido.'], 422);
        }

        $participations = Participation::query()
            ->where('set_id', $setId)
            ->whereBetween('participation_number', [$startParticipation, $endParticipation])
            ->where('status', 'disponible')
            ->whereNull('seller_id')
            ->orderBy('participation_number')
            ->get();

        if ($participations->isEmpty()) {
            $lottery = $set->reserve->lottery ?? null;

            return response()->json([
                'success' => true,
                'taco_ref' => $request->taco_ref,
                'lottery_id' => $lottery?->id,
                'lottery_name' => $lottery?->name ?? '',
                'set_id' => $setId,
                'book_number' => $bookNumber,
                'set_name' => $set->set_name,
                'rangos_disponibles' => [],
                'total_disponibles' => 0,
                'participations_per_book' => $participationsPerBook,
                'message' => 'No hay participaciones disponibles para asignar en este taco (libro).',
            ]);
        }

        $numbers = $participations->pluck('participation_number')->sort()->values()->all();
        $rangos = $this->buildConsecutiveRanges($numbers);
        $lottery = $set->reserve->lottery ?? null;

        return response()->json([
            'success' => true,
            'taco_ref' => $request->taco_ref,
            'lottery_id' => $lottery?->id,
            'lottery_name' => $lottery?->name ?? '',
            'lottery_date' => $lottery?->draw_date ? $lottery->draw_date->format('d/m/Y') : null,
            'set_id' => $setId,
            'book_number' => $bookNumber,
            'set_name' => $set->set_name,
            'rangos_disponibles' => $rangos,
            'total_disponibles' => $participations->count(),
            'participations_per_book' => $participationsPerBook,
        ]);
    }

    /**
     * Convierte una lista ordenada de números en rangos consecutivos [['desde' => n, 'hasta' => m], ...].
     */
    private function buildConsecutiveRanges(array $numbers): array
    {
        if (empty($numbers)) {
            return [];
        }
        $rangos = [];
        $desde = $numbers[0];
        $hasta = $numbers[0];
        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] === $hasta + 1) {
                $hasta = $numbers[$i];
            } else {
                $rangos[] = ['desde' => $desde, 'hasta' => $hasta];
                $desde = $numbers[$i];
                $hasta = $numbers[$i];
            }
        }
        $rangos[] = ['desde' => $desde, 'hasta' => $hasta];
        return $rangos;
    }

    /**
     * API: Validar rango de participaciones para venta (vendedor autenticado)
     * Comprueba que las participaciones desde-hasta estén asignadas al vendedor y disponibles para marcar como vendidas.
     */
    public function apiValidateSale(Request $request)
    {
        $request->validate([
            'set_id' => 'required|integer|exists:sets,id',
            'desde' => 'required|integer|min:1',
            'hasta' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        if ($request->desde > $request->hasta) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'El rango desde no puede ser mayor que hasta.'
            ]);
        }

        $participations = Participation::where('set_id', $request->set_id)
            ->whereBetween('participation_number', [$request->desde, $request->hasta])
            ->where('seller_id', $seller->id)
            ->where('status', 'asignada')
            ->get();

        $totalEnRango = $request->hasta - $request->desde + 1;

        if ($participations->count() < $totalEnRango) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'message' => "Hay " . ($totalEnRango - $participations->count()) . " participaciones en el rango que no están asignadas a ti.",
                'count' => $participations->count(),
                'expected' => $totalEnRango
            ]);
        }

        $set = Set::find($request->set_id);
        $importeTotal = $participations->count() * ($set->total_participation_amount ?? 0);

        return response()->json([
            'success' => true,
            'valid' => true,
            'message' => "Rango válido. {$participations->count()} participaciones listas para marcar como vendidas.",
            'count' => $participations->count(),
            'importe_total' => round($importeTotal, 2),
            'participations' => $participations->map(fn ($p) => ['id' => $p->id, 'participation_code' => $p->display_participation_code])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function updateComment(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! ($user->isEntity() && ! $user->isAdministration()))) {
            abort(403);
        }

        $seller = Seller::forUser($user)->findOrFail($id);
        $validated = $request->validate([
            'comment' => 'nullable|string|max:5000',
        ]);
        $seller->update(['comment' => $validated['comment'] ?? null]);

        return redirect()
            ->route('sellers.show', ['id' => $seller->id, 'entity_id' => $request->query('entity_id')])
            ->with('success', 'Observaciones guardadas.');
    }

    public function edit($id)
    {
        $user = auth()->user();
        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Solo el superadministrador puede editar la ficha completa del vendedor.');
        }

        $seller = Seller::with('entities')
            ->forUser(auth()->user())
            ->findOrFail($id);
        $entities = Entity::forUser(auth()->user())->get();
        
        // Obtener grupos de la nueva tabla groups
        $groups = \App\Models\Group::with('entity')
            ->forUser(auth()->user())
            ->orderBy('name', 'asc')
            ->get();
            
        return view('sellers.edit', compact('seller', 'entities', 'groups'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Solo el superadministrador puede editar la ficha completa del vendedor.');
        }

        $seller = Seller::forUser($user)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['nullable', 'string', 'max:255', new \App\Rules\SpanishDocument, 'unique:users,nif_cif,' . ($seller->user_id ?? 0), 'unique:sellers,nif_cif,' . $seller->id],
            'birthday' => ['nullable', 'date', new \App\Rules\MinimumAge(18)],
            'email' => 'required|email|unique:users,email,' . ($seller->user_id ?? 0),
            'phone' => 'nullable|string|max:255',
            'group_id' => 'nullable|exists:groups,id',
            'status' => 'required|integer|in:0,1,3', // 0=Inactivo, 1=Activo, 3=Bloqueado (2=Pendiente solo en creación)
        ]);

        try {
            DB::beginTransaction();

            // Actualizar el vendedor
            $seller->update([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'nif_cif' => $request->nif_cif,
                'birthday' => $request->birthday,
                'email' => $request->email,
                'phone' => $request->phone,
                // Nuevo FIX: status guarda el valor real, no sólo su existencia
                'status' => $request->input('status', 0),
            ]);

            // Actualizar la relación con grupos solo si group_id viene en la petición (evitar desvincular en ediciones parciales)
            if ($request->has('group_id')) {
                if (!empty($request->group_id)) {
                    $seller->groups()->sync([$request->group_id]);
                } else {
                    $seller->groups()->detach();
                }
            }

            // Actualizar el usuario si existe
            if ($seller->user_id) {
                $user = User::find($seller->user_id);
                if ($user) {
                    $user->update([
                        'name' => $request->name,
                        'last_name' => $request->last_name,
                        'last_name2' => $request->last_name2,
                        'nif_cif' => $request->nif_cif,
                        'birthday' => $request->birthday,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'role' => User::ROLE_SELLER
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('sellers.index')->with('success', 'Vendedor actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Error al actualizar el vendedor: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $seller = Seller::forUser(auth()->user())->findOrFail($id);
            $seller->delete();

            return redirect()->route('sellers.index')->with('success', 'Vendedor eliminado exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar el vendedor: ' . $e->getMessage()]);
        }
    }

    /**
     * Participaciones digitales vinculadas a un vendedor (vendidas o reservadas en venta pendiente).
     */
    private function applySellerDigitalParticipationsScope($query, int $sellerId): void
    {
        $query->where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
                ->whereIn('status', ['vendida', 'pagada', 'devuelta'])
                ->orWhere(function ($q2) use ($sellerId) {
                    $q2->where('status', 'reserva_venta_digital')
                        ->whereHas('pendingDigitalSales', function ($pds) use ($sellerId) {
                            $pds->where('pending_digital_sales.seller_id', $sellerId)
                                ->where('pending_digital_sales.status', PendingDigitalSale::STATUS_PENDING);
                        });
                });
        });
    }

    /**
     * Obtener sets por reserva
     */
    public function getSetsByReserve(Request $request)
    {
        $request->validate([
            'reserve_id' => 'required|integer|exists:reserves,id',
            'include_digital' => 'nullable|boolean',
            'seller_id' => 'nullable|integer|exists:sellers,id',
        ]);

        $reserve = Reserve::forUser(auth()->user())->with('lottery:id,name')->findOrFail($request->reserve_id);

        $includeDigital = $request->boolean('include_digital');
        $sellerId = $request->integer('seller_id');

        if ($includeDigital) {
            if (! $sellerId || ! auth()->user()->canAccessSeller($sellerId)) {
                abort(403, 'No tienes permisos para consultar las participaciones de este vendedor.');
            }
        }

        $setsQuery = Set::forUser(auth()->user())
            ->where('reserve_id', $reserve->id)
            ->where('status', 1)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('participations')
                      ->whereRaw('participations.set_id = sets.id');
            });

        if ($includeDigital && $sellerId) {
            // Tab participaciones: físicos asignados + digitales vendidos por el vendedor
            $setsQuery->where(function ($q) use ($sellerId) {
                $q->where('physical_participations', '>', 0)
                    ->orWhere(function ($q2) use ($sellerId) {
                        $q2->where('physical_participations', '<=', 0)
                            ->whereRaw('sets.digital_participations > 0')
                            ->whereHas('participations', fn ($p) => $this->applySellerDigitalParticipationsScope($p, $sellerId));
                    });
            });
        } else {
            // Tab asignación: solo sets FÍSICOS (las digitales no se asignan)
            $setsQuery->where('physical_participations', '>', 0);
        }

        $sets = $setsQuery->get();

        return response()->json(['sets' => $sets, 'reserve' => $reserve]);
    }

    /**
     * Validar participaciones disponibles para asignación.
     * - Sets físicos: requiere desde/hasta (rango) o participación unidad (desde=hasta).
     * - Sets digitales: requiere cantidad (número de participaciones a asignar de las disponibles).
     */
    public function validateParticipations(Request $request)
    {
        $rules = [
            'set_id' => 'nullable|integer|exists:sets,id',
            'reserve_id' => 'nullable|integer|exists:reserves,id',
            'seller_id' => 'required|integer|exists:sellers,id'
        ];
        if ($request->has('cantidad') && $request->cantidad !== '' && $request->cantidad !== null) {
            $rules['cantidad'] = 'required|integer|min:0';
        } else {
            $rules['desde'] = 'required|integer|min:1';
            $rules['hasta'] = 'required|integer|min:1';
        }
        $request->validate($rules);

        if (empty($request->set_id) && empty($request->reserve_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Se requiere set_id o reserve_id.'
            ], 422);
        }

        try {
            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para gestionar este vendedor.');
            }

            $setId = $request->set_id;
            $reserveId = $request->reserve_id;

            if ($reserveId) {
                $reserve = Reserve::forUser(auth()->user())->findOrFail($reserveId);
                $sets = $reserve->sets()->orderBy('set_number')->orderBy('id')->get();
                if ($sets->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta reserva no tiene sets.'
                    ]);
                }
                $totalInReserve = $sets->sum(fn ($s) => (int) ($s->total_participations ?? 0));
                if ($totalInReserve === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta reserva no tiene participaciones creadas (diseño).'
                    ]);
                }
                $set = $sets->first();
                $setId = $set->id;
            } else {
                $set = Set::forUser(auth()->user())->findOrFail($setId);
            }

            // Verificar que el set tiene participaciones creadas
            $totalParticipationsInSet = DB::table('participations')
                ->where('set_id', $setId)
                ->count();

            if ($totalParticipationsInSet === 0 && !$reserveId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este set no tiene participaciones creadas (diseño)'
                ]);
            }

            // Rango por reserva (varios sets): cuando se envía reserve_id y desde/hasta
            if ($reserveId && $request->has('desde') && $request->has('hasta')) {
                $desde = (int) $request->desde;
                $hasta = (int) $request->hasta;
                if ($hasta < $desde) {
                    return response()->json(['success' => false, 'message' => 'El rango hasta debe ser mayor o igual que desde.']);
                }
                $offset = 1;
                $segments = [];
                foreach ($sets as $s) {
                    $total = (int) ($s->total_participations ?? 0);
                    if ($total <= 0) {
                        continue;
                    }
                    $end = $offset + $total - 1;
                    if ($hasta >= $offset && $desde <= $end) {
                        $localFrom = max(1, $desde - $offset + 1);
                        $localTo = min($total, $hasta - $offset + 1);
                        $segments[] = ['set_id' => $s->id, 'from' => $localFrom, 'to' => $localTo];
                    }
                    $offset = $end + 1;
                }
                $participationsCollect = collect();
                foreach ($segments as $seg) {
                    $rows = DB::table('participations')
                        ->where('set_id', $seg['set_id'])
                        ->whereBetween('participation_number', [$seg['from'], $seg['to']])
                        ->where('status', '!=', 'anulada')
                        ->where(function ($q) use ($request) {
                            $q->where('status', 'disponible')->whereNull('seller_id')
                              ->orWhere(function ($q2) use ($request) {
                                  $q2->where('status', 'asignada')->where('seller_id', $request->seller_id);
                              });
                        })
                        ->select('id', 'participation_number as number', 'participation_code', 'status', 'set_id')
                        ->get();
                    foreach ($rows as $r) {
                        $r->set_id = $seg['set_id'];
                        $participationsCollect->push($r);
                    }
                }
                $expected = $hasta - $desde + 1;
                if ($participationsCollect->count() < $expected) {
                    return response()->json([
                        'success' => false,
                        'message' => "Solo hay {$participationsCollect->count()} participaciones disponibles en el rango {$desde}-{$hasta}. Se esperaban {$expected}."
                    ]);
                }
                return response()->json([
                    'success' => true,
                    'participations' => $participationsCollect->toArray(),
                ]);
            }

            // Asignación por cantidad: solo sets digitales (cantidad=0 solo devuelve disponibles)
            $isDigitalOnly = $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
            if ($request->has('cantidad') && $request->cantidad !== '' && $request->cantidad !== null) {
                if (!$isDigitalOnly) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo los sets digitales permiten asignar por cantidad.'
                    ]);
                }
                $cantidad = (int) $request->cantidad;
                // Participaciones disponibles para ASIGNAR: solo las que no están asignadas a nadie (gestor o web)
                $disponiblesQuery = DB::table('participations')
                    ->where('set_id', $setId)
                    ->where('status', '!=', 'anulada')
                    ->where('status', 'disponible')
                    ->whereNull('seller_id');
                $totalDisponibles = (clone $disponiblesQuery)->count();
                // cantidad=0: solo consultar disponibles (para app móvil)
                if ($cantidad === 0) {
                    return response()->json([
                        'success' => true,
                        'participations' => [],
                        'disponibles_restantes' => $totalDisponibles,
                    ]);
                }
                if ($cantidad > $totalDisponibles) {
                    return response()->json([
                        'success' => false,
                        'message' => "Solo hay {$totalDisponibles} participaciones disponibles. No puedes asignar {$cantidad}."
                    ]);
                }
                $participations = (clone $disponiblesQuery)
                    ->orderBy('participation_number')
                    ->limit($cantidad)
                    ->select('id', 'participation_number as number', 'participation_code', 'status')
                    ->get();
                $disponiblesRestantes = $totalDisponibles - $participations->count();
                return response()->json([
                    'success' => true,
                    'participations' => $participations,
                    'disponibles_restantes' => $disponiblesRestantes,
                ]);
            }
            
            // Rango (sets físicos o mixtos) — un solo set
            // Verificar que el rango solicitado existe en este set
            $minParticipation = DB::table('participations')
                ->where('set_id', $setId)
                ->min('participation_number');
                
            $maxParticipation = DB::table('participations')
                ->where('set_id', $setId)
                ->max('participation_number');
                
            if ($request->desde < $minParticipation || $request->hasta > $maxParticipation) {
                return response()->json([
                    'success' => false,
                    'message' => "El rango debe estar entre {$minParticipation} y {$maxParticipation} para este set"
                ]);
            }
            
            // Obtener todas las participaciones del set en el rango especificado (para debug)
            $allParticipationsInRange = DB::table('participations')
                ->where('set_id', $setId)
                ->whereBetween('participation_number', [$request->desde, $request->hasta])
                ->select('id', 'participation_number as number', 'status', 'seller_id')
                ->get();

            // Debug: Mostrar todas las participaciones en el rango
            \Log::info('Debug participaciones en rango:', [
                'set_id' => $setId,
                'range' => "{$request->desde} - {$request->hasta}",
                'participations' => $allParticipationsInRange->toArray()
            ]);

            // Obtener las participaciones disponibles del set en el rango especificado
            // EXCLUIR explícitamente las participaciones anuladas
            $participations = DB::table('participations')
                ->where('set_id', $setId)
                ->whereBetween('participation_number', [$request->desde, $request->hasta])
                ->where('status', '!=', 'anulada') // Excluir participaciones anuladas
                ->where(function($query) use ($request) {
                    $query->where('status', 'disponible')
                          ->whereNull('seller_id') // No asignadas a ningún vendedor
                          ->orWhere(function($subQuery) use ($request) {
                              $subQuery->where('status', 'asignada')
                                      ->where('seller_id', $request->seller_id); // Asignadas al vendedor actual
                          });
                })
                ->select('id', 'participation_number as number', 'participation_code', 'status')
                ->get();

            // Verificar que todas las participaciones del rango estén disponibles o asignadas al vendedor actual
            $totalParticipations = $request->hasta - $request->desde + 1;
            $availableParticipations = $participations->count();

            if ($availableParticipations < $totalParticipations) {
                $assignedCount = $totalParticipations - $availableParticipations;
                
                // Obtener información detallada de las participaciones asignadas
                $assignedParticipations = $allParticipationsInRange->where('seller_id', '!=', null);
                $assignedToOthers = $assignedParticipations->where('seller_id', '!=', $request->seller_id);
                $assignedToThisSeller = $assignedParticipations->where('seller_id', $request->seller_id);
                
                $message = "Hay {$assignedCount} participaciones en este rango que no están disponibles";
                if ($assignedToOthers->count() > 0) {
                    $message .= " (asignadas a otros vendedores)";
                } else {
                    $message .= " (ya asignadas a este vendedor)";
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'debug' => [
                        'set_id' => $setId,
                        'total_in_range' => $totalParticipations,
                        'available' => $availableParticipations,
                        'assigned_to_others' => $assignedToOthers->count(),
                        'assigned_to_this_seller' => $assignedToThisSeller->count(),
                        'range_requested' => "{$request->desde} - {$request->hasta}",
                        'set_range' => "{$minParticipation} - {$maxParticipation}"
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'participations' => $participations,
                'set_id' => $setId,
                'debug' => [
                    'set_id' => $setId,
                    'range' => "{$request->desde} - {$request->hasta}",
                    'total_in_range' => $totalParticipations,
                    'available' => $availableParticipations,
                    'all_participations_in_range' => $allParticipationsInRange->toArray()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar participaciones: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Guardar asignaciones de participaciones
     */
    public function saveAssignments(Request $request)
    {
        $request->validate([
            'participations_json' => 'required|string',
            'seller_id' => 'required|integer|exists:sellers,id',
            'background' => 'nullable|boolean',
            'force_sync' => 'nullable|boolean',
        ]);

        // Decodificar el JSON de participaciones
        $participations = json_decode($request->participations_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar los datos de participaciones: ' . json_last_error_msg()
            ]);
        }

        // Validar que participations sea un array y tenga al menos un elemento
        if (!is_array($participations) || empty($participations)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar al menos una participación'
            ]);
        }

        // Por defecto procesamos en background para evitar bloquear la UI.
        // Solo se procesa en síncrono cuando se fuerza explícitamente.
        $runInBackground = !$request->boolean('force_sync');
        if ($runInBackground) {
            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para gestionar este vendedor.');
            }

            $resourceSetIds = array_values(array_unique(array_filter(array_map(
                fn ($p) => (int) ($p['set_id'] ?? 0),
                $participations
            ))));
            $resourceKey = count($resourceSetIds) === 1
                ? ('set:' . $resourceSetIds[0])
                : ('seller_assignment:' . (int) $request->seller_id);

            $task = app(BackgroundTaskService::class)->createTask(auth()->user(), [
                'type' => BackgroundTask::TYPE_PARTICIPATION_ASSIGNMENT,
                'payload' => [
                    'seller_id' => (int) $request->seller_id,
                    'participations' => $participations,
                    'set_id' => count($resourceSetIds) === 1 ? $resourceSetIds[0] : null,
                ],
                'set_id' => count($resourceSetIds) === 1 ? $resourceSetIds[0] : null,
                'resource_key' => $resourceKey,
            ]);

            if ($task->status === BackgroundTask::STATUS_PENDING) {
                ProcessParticipationAssignmentTask::dispatch($task->uuid);
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Asignación enviada a segundo plano.',
                'task_uuid' => $task->uuid,
                'status' => $task->status,
                'poll_url' => route('background-tasks.show', ['uuid' => $task->uuid]),
            ]);
        }

        // Validar cada participación
        foreach ($participations as $participation) {
            if (!isset($participation['id']) || !isset($participation['number']) || !isset($participation['set_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de participación incompletos'
                ]);
            }
        }

        try {
            DB::beginTransaction();

            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para gestionar este vendedor.');
            }

            $seller = Seller::forUser(auth()->user())->findOrFail($request->seller_id);

            if ($seller->status !== Seller::STATUS_ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'El vendedor no está activo.'
                ]);
            }

            $assignedCount = 0;
            $assignedParticipations = []; // Para agrupar por set

            // Una sola consulta: obtener todas las participaciones candidatas (disponible sin vendedor o ya asignadas a este vendedor)
            $ids = array_column($participations, 'id');
            $setIds = array_unique(array_column($participations, 'set_id'));
            $participationsToUpdate = Participation::with(['set.reserve.lottery'])
                ->whereIn('id', $ids)
                ->whereIn('set_id', $setIds)
                ->where(function ($query) use ($seller) {
                    $query->where(function ($q) {
                        $q->where('status', 'disponible')->whereNull('seller_id');
                    })->orWhere(function ($q) use ($seller) {
                        $q->where('status', 'asignada')->where('seller_id', $seller->id);
                    });
                })
                ->get()
                ->keyBy('id');

            foreach ($participations as $participationData) {
                $participation = $participationsToUpdate->get($participationData['id']);
                if (!$participation || $participation->set_id != $participationData['set_id']) {
                    continue;
                }
                // USAR update() del modelo para disparar el Observer
                $participation->update([
                    'seller_id' => $seller->id,
                    'sale_date' => now()->toDateString(),
                    'sale_time' => now()->toTimeString(),
                    'status' => 'asignada'
                ]);
                $assignedCount++;
                $assignedParticipations[] = $participation;
            }

            DB::commit();

            // Enviar email de notificación si se asignaron participaciones
            if ($assignedCount > 0 && $seller->email) {
                // Agrupar participaciones por set
                $assignmentsBySet = [];
                foreach ($assignedParticipations as $participation) {
                    $setId = $participation->set_id;

                    if (!isset($assignmentsBySet[$setId])) {
                        // Usar el set ya cargado desde la participación
                        $set = $participation->set;

                        $assignmentsBySet[$setId] = [
                            'set' => $set,
                            'lottery' => $set->reserve->lottery ?? null,
                            'count' => 0,
                        ];
                    }

                    $assignmentsBySet[$setId]['count']++;
                }

                $assignmentsList = [];
                foreach ($assignmentsBySet as $setId => $data) {
                    $assignmentsList[] = [
                        'set_id' => (int) $setId,
                        'count' => (int) ($data['count'] ?? 0),
                    ];
                }

                $communicationEmailService = app(CommunicationEmailService::class);
                $log = $communicationEmailService->sendAndLog(
                    recipientEmail: (string) $seller->email,
                    recipientRole: 'vendedor',
                    recipientUser: null,
                    messageType: 'participation_assignment',
                    templateKey: null,
                    mailClass: \App\Mail\ParticipationAssignmentMail::class,
                    mailPayload: [
                        'seller_id' => $seller->id,
                        'assignments' => $assignmentsList,
                    ],
                    context: [
                        'seller_id' => $seller->id,
                        'assigned_count' => $assignedCount,
                    ],
                );

                if ($log->status === EmailCommunicationLog::STATUS_CANCELLED) {
                    \Log::error('Error enviando email de asignación de participaciones: ' . ($log->error_message ?? 'unknown'));
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se asignaron {$assignedCount} participaciones correctamente",
                'assigned_count' => $assignedCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar las asignaciones: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener participaciones asignadas del vendedor por set
     */
    public function getAssignedParticipations(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|integer|exists:sellers,id',
            'set_id' => 'required|integer|exists:sets,id'
        ]);

        try {
            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para consultar este vendedor.');
            }

            $set = Set::forUser(auth()->user())->findOrFail($request->set_id);

            $isDigitalOnly = ($set->digital_participations ?? 0) > 0 && (int) ($set->physical_participations ?? 0) === 0;

            $participationsQuery = Participation::with('activityLogs')
                ->where('set_id', $request->set_id);

            if ($isDigitalOnly) {
                $this->applySellerDigitalParticipationsScope($participationsQuery, (int) $request->seller_id);
            } else {
                $participationsQuery
                    ->where('seller_id', $request->seller_id)
                    ->whereIn('status', ['asignada', 'vendida', 'devuelta', 'pagada', 'disponible']);
            }

            $participations = $participationsQuery
                ->orderBy('participation_number')
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'number' => $p->participation_number,
                        'participation_code' => $p->participation_code,
                        'set_id' => $p->set_id,
                        'status' => $p->status,
                        'status_text' => $p->status_text,
                        'sale_date' => $p->sale_date?->format('Y-m-d'),
                        'sale_time' => $p->sale_time?->format('H:i:s'),
                        'updated_at' => $p->updated_at?->toIso8601String(),
                        'created_at' => $p->created_at?->toIso8601String(),
                    ];
                });

            $payload = ['success' => true, 'participations' => $participations];
            // Para sets digitales: incluir cantidad de participaciones disponibles (sin asignar) en el set
            if ($isDigitalOnly) {
                $payload['set_disponibles'] = DB::table('participations')
                    ->where('set_id', $request->set_id)
                    ->where('status', '!=', 'anulada')
                    ->where('status', 'disponible')
                    ->whereNull('seller_id')
                    ->count();
            } else {
                // Para sets físicos: rangos de participaciones disponibles (de la X a la Y)
                $numeros = DB::table('participations')
                    ->where('set_id', $request->set_id)
                    ->where('status', '!=', 'anulada')
                    ->where('status', 'disponible')
                    ->whereNull('seller_id')
                    ->orderBy('participation_number')
                    ->pluck('participation_number')
                    ->map(fn ($n) => (int) $n)
                    ->values()
                    ->toArray();
                $ranges = [];
                foreach ($numeros as $num) {
                    if (empty($ranges) || $num > $ranges[count($ranges) - 1][1] + 1) {
                        $ranges[] = [$num, $num];
                    } else {
                        $ranges[count($ranges) - 1][1] = $num;
                    }
                }
                $payload['available_ranges'] = $ranges;
            }

            return response()->json($payload);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener participaciones: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * API: Rangos de participaciones disponibles en un set (para asignación).
     * GET ?set_id= — devuelve available_ranges para mostrar "Disponibles: de la X a la Y".
     */
    public function getAvailableRangesForSet(Request $request)
    {
        $setId = $request->get('set_id');
        if (!$setId) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }
        try {
            $set = Set::forUser(auth()->user())->findOrFail($setId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }
        $isDigitalOnly = ($set->digital_participations ?? 0) > 0 && (int) ($set->physical_participations ?? 0) === 0;
        if ($isDigitalOnly) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }
        $numeros = DB::table('participations')
            ->where('set_id', $setId)
            ->where('status', '!=', 'anulada')
            ->where('status', 'disponible')
            ->whereNull('seller_id')
            ->orderBy('participation_number')
            ->pluck('participation_number')
            ->map(fn ($n) => (int) $n)
            ->values()
            ->toArray();
        $ranges = [];
        foreach ($numeros as $num) {
            if (empty($ranges) || $num > $ranges[count($ranges) - 1][1] + 1) {
                $ranges[] = [$num, $num];
            } else {
                $ranges[count($ranges) - 1][1] = $num;
            }
        }
        return response()->json(['success' => true, 'available_ranges' => $ranges]);
    }

    /**
     * Eliminar asignación de participación
     */
    public function removeAssignment(Request $request)
    {
        $request->validate([
            'participation_id' => 'required|integer|exists:participations,id',
            'seller_id' => 'required|integer|exists:sellers,id'
        ]);

        try {
            DB::beginTransaction();

            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para gestionar este vendedor.');
            }

            // Verificar que la participación pertenece al vendedor
            // USAR MODELO ELOQUENT para que se dispare el Observer
            $participation = Participation::where('id', $request->participation_id)
                ->where('seller_id', $request->seller_id)
                ->whereIn('status', ['asignada', 'disponible'])
                ->first();

            if (!$participation) {
                return response()->json([
                    'success' => false,
                    'message' => 'La participación no pertenece a este vendedor o no está asignada'
                ]);
            }

            // Restaurar la participación a estado disponible
            // USAR update() del modelo para disparar el Observer
            $participation->update([
                'seller_id' => null,
                'sale_date' => null,
                'sale_time' => null,
                'status' => 'disponible'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asignación eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la asignación: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener participaciones por taco (book)
     */
    public function getParticipationsByBook(Request $request)
    {
        try {
            $request->validate([
                'seller_id' => 'required|integer',
                'set_id' => 'required|integer',
                'book_number' => 'required|integer'
            ]);

            if (!auth()->user()->canAccessSeller((int) $request->seller_id)) {
                abort(403, 'No tienes permisos para consultar este vendedor.');
            }

            // Obtener información del set
            $set = Set::forUser(auth()->user())->findOrFail($request->set_id);

            if (!$set) {
                return response()->json([
                    'success' => false,
                    'message' => 'Set no encontrado'
                ]);
            }

            // Obtener el formato de diseño para saber cuántas participaciones por taco
            $designFormat = DB::table('design_formats')
                ->where('set_id', $request->set_id)
                ->first();

            if (!$designFormat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de diseño no encontrado'
                ]);
            }

            $participationsPerBook = $designFormat->output['participations_per_book'] ?? 50;
            $startParticipation = ($request->book_number - 1) * $participationsPerBook + 1;
            $endParticipation = $request->book_number * $participationsPerBook;

            // Obtener participaciones del vendedor en este taco
            $participations = DB::table('participations')
                ->where('seller_id', $request->seller_id)
                ->where('set_id', $request->set_id)
                ->where('participation_number', '>=', $startParticipation)
                ->where('participation_number', '<=', $endParticipation)
                ->where('status', 'asignada')
                ->select('id', 'participation_number as number', 'participation_code', 'sale_date', 'sale_time', 'updated_at', 'created_at')
                ->orderBy('participation_number')
                ->get();

            return response()->json([
                'success' => true,
                'participations' => $participations,
                'book_info' => [
                    'book_number' => $request->book_number,
                    'start_participation' => $startParticipation,
                    'end_participation' => $endParticipation,
                    'participations_per_book' => $participationsPerBook,
                    'total_assigned' => $participations->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener participaciones del taco: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener resumen de liquidación para un vendedor y sorteo
     */
    public function getSettlementSummary(Request $request)
    {
        $sellerId = $request->get('seller_id');
        $lotteryId = $request->get('lottery_id');

        if (!auth()->user()->canAccessSeller((int) $sellerId)) {
            abort(403, 'No tienes permisos para consultar este vendedor.');
        }

        if (!auth()->user()->canAccessSeller((int) $sellerId)) {
            abort(403, 'No tienes permisos para consultar este vendedor.');
        }

        \Log::info('=== SELLER SETTLEMENT SUMMARY ===');
        \Log::info('Seller ID:', [$sellerId]);
        \Log::info('Lottery ID:', [$lotteryId]);

        // Obtener participaciones liquidables del vendedor para este sorteo
        $participations = $this->settlementEligibleParticipationsQuery((int) $sellerId, (int) $lotteryId)->get();

        \Log::info('Participaciones asignadas encontradas:', [$participations->count()]);

        $totalParticipations = $participations->count();
        
        // Calcular el total a liquidar (suma del precio de cada participación)
        $totalAmount = $participations->sum(function($participation) {
            return $participation->set->total_participation_amount ?? 0;
        });

        // Obtener liquidaciones previas para este vendedor y sorteo
        $previousSettlements = SellerSettlement::where('seller_id', $sellerId)
            ->where('lottery_id', $lotteryId)
            ->with('payments')
            ->get();

        $totalPaid = $previousSettlements->sum('paid_amount');
        $pendingAmount = $totalAmount - $totalPaid;

        // Calcular participaciones liquidadas (pagos / precio por participación)
        $pricePerParticipation = $participations->isNotEmpty()
            ? (float) ($participations->first()->set->total_participation_amount ?? 0)
            : 0;
        $liquidatedParticipations = $pricePerParticipation > 0 ? ($totalPaid / $pricePerParticipation) : 0;

        \Log::info('Resumen calculado:', [
            'total_participations' => $totalParticipations,
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'pending_amount' => $pendingAmount,
            'liquidated_participations' => $liquidatedParticipations
        ]);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_participations' => $totalParticipations,
                'price_per_participation' => $pricePerParticipation,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'pending_amount' => $pendingAmount,
                'liquidated_participations' => round($liquidatedParticipations, 2),
                'pending_participations' => $totalParticipations - round($liquidatedParticipations, 2)
            ]
        ]);
    }

    /**
     * Guardar nueva liquidación de vendedor
     */
    public function storeSettlement(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validate([
                'seller_id' => 'required|exists:sellers,id',
                'lottery_id' => 'required|exists:lotteries,id',
                'pagos' => 'required|array',
                'pagos.*.payment_method' => 'required|string',
                'pagos.*.amount' => 'required|numeric|min:0.01'
            ]);

            if (!auth()->user()->canAccessSeller((int) $data['seller_id'])) {
                abort(403, 'No tienes permisos para gestionar este vendedor.');
            }

            // Calcular totales
            $totalPagoNuevo = collect($data['pagos'])->sum('amount');

            // Obtener participaciones liquidables del vendedor para este sorteo
            $participations = $this->settlementEligibleParticipationsQuery((int) $data['seller_id'], (int) $data['lottery_id'])->get();

            $totalParticipations = $participations->count();
            $pricePerParticipation = $participations->first()->set->total_participation_amount ?? 0;
            $totalAmount = $participations->sum(function($participation) {
                return $participation->set->total_participation_amount ?? 0;
            });

            // Obtener liquidaciones previas
            $previousSettlements = SellerSettlement::where('seller_id', $data['seller_id'])
                ->where('lottery_id', $data['lottery_id'])
                ->sum('paid_amount');

            $totalPaidWithNew = $previousSettlements + $totalPagoNuevo;
            $pendingAmount = $totalAmount - $totalPaidWithNew;
            $calculatedParticipations = $pricePerParticipation > 0 ? ($totalPagoNuevo / $pricePerParticipation) : 0;

            $now = Carbon::now();

            // Crear registro de liquidación
            $settlement = SellerSettlement::create([
                'seller_id' => $data['seller_id'],
                'lottery_id' => $data['lottery_id'],
                'user_id' => auth()->id(),
                'total_amount' => $totalAmount,
                'paid_amount' => $totalPagoNuevo,
                'pending_amount' => $pendingAmount,
                'total_participations' => $totalParticipations,
                'calculated_participations' => round($calculatedParticipations, 2),
                'settlement_date' => $now->format('Y-m-d'),
                'settlement_time' => $now->format('H:i:s'),
                'notes' => 'Liquidación de vendedor'
            ]);

            // Crear registros de pago
            foreach ($data['pagos'] as $pago) {
                SellerSettlementPayment::create([
                    'seller_settlement_id' => $settlement->id,
                    'amount' => $pago['amount'],
                    'payment_method' => $pago['payment_method'],
                    'notes' => 'Pago de liquidación - ' . ucfirst($pago['payment_method']),
                    'payment_date' => $now
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Liquidación registrada correctamente',
                'settlement_id' => $settlement->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la liquidación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de liquidaciones de un vendedor
     */
    public function getSettlementHistory(Request $request)
    {
        $sellerId = $request->get('seller_id');
        $lotteryId = $request->get('lottery_id');

        $settlements = SellerSettlement::where('seller_id', $sellerId)
            ->where('lottery_id', $lotteryId)
            ->with(['payments', 'user'])
            ->orderBy('settlement_date', 'desc')
            ->orderBy('settlement_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'settlements' => $settlements
        ]);
    }

    /**
     * Actualizar grupo de vendedor
     */
    public function updateGroup(Request $request, $id)
    {
        $request->validate([
            'group_name' => 'nullable|string|max:255',
            'group_color' => 'nullable|string|max:7',
            'group_priority' => 'nullable|integer|min:0'
        ]);

        try {
            $seller = Seller::forUser(auth()->user())->findOrFail($id);
            $seller->update([
                'group_name' => $request->group_name,
                'group_color' => $request->group_color,
                'group_priority' => $request->group_priority ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el grupo: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener vendedores por grupo
     */
    public function getByGroup(Request $request)
    {
        $groupName = $request->get('group');
        
        if ($groupName) {
            $sellers = Seller::with('entities')
                ->byGroup($groupName)
                ->forUser(auth()->user())
                ->orderByGroup()
                ->get();
        } else {
            $sellers = Seller::with('entities')
                ->forUser(auth()->user())
                ->orderByGroup()
                ->get();
        }

        return response()->json([
            'success' => true,
            'sellers' => $sellers
        ]);
    }

    /**
     * Obtener estadísticas de grupos
     */
    public function getGroupStats()
    {
        $query = Seller::select('group_name', 'group_color')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->groupBy('group_name', 'group_color')
            ->orderBy('count', 'desc');

        if (!auth()->user()->isSuperAdmin()) {
            $sellerIds = auth()->user()->accessibleSellerIds();

            if (empty($sellerIds)) {
                return response()->json([
                    'success' => true,
                    'stats' => collect()
                ]);
            }

            $query->whereIn('id', $sellerIds);
        }

        $stats = $query->get();

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Confirmar aceptación de solicitud de vendedor
     */
    public function confirmAccept($token)
    {
        $seller = Seller::where('confirmation_token', $token)
            ->where('status', Seller::STATUS_PENDING)
            ->first();

        if (!$seller) {
            return view('sellers.confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
                'type' => 'error'
            ]);
        }

        // Actualizar status a ACTIVO
        $seller->update([
            'status' => Seller::STATUS_ACTIVE,
            'confirmation_token' => null,
            'confirmation_sent_at' => null
        ]);

        \Illuminate\Support\Facades\Log::info("Vendedor {$seller->id} ({$seller->email}) ha aceptado la solicitud de vendedor");

        return view('sellers.confirmation-success', [
            'message' => '¡Solicitud aceptada correctamente!',
            'seller' => $seller,
            'type' => 'accept'
        ]);
    }

    /**
     * Confirmar rechazo de solicitud de vendedor
     */
    public function confirmReject($token)
    {
        $seller = Seller::where('confirmation_token', $token)
            ->where('status', Seller::STATUS_PENDING)
            ->first();

        if (!$seller) {
            return view('sellers.confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
                'type' => 'error'
            ]);
        }

        $email = $seller->email;
        $sellerId = $seller->id;

        // Eliminar el vendedor
        $seller->delete();

        \Illuminate\Support\Facades\Log::info("Vendedor {$sellerId} ({$email}) ha rechazado la solicitud de vendedor - Eliminado");

        return view('sellers.confirmation-success', [
            'message' => 'Solicitud rechazada. El vendedor ha sido eliminado del sistema.',
            'seller' => null,
            'type' => 'reject'
        ]);
    }

    /**
     * Cambiar estado (Activo/Inactivo/Bloqueado) del vendedor vía AJAX.
     */
    public function toggleStatus(Request $request, Seller $seller)
    {
        // Verificar permisos
        $seller = Seller::forUser(auth()->user())->findOrFail($seller->id);
        
        // Determinar el nuevo estado según el estado actual
        $currentStatus = $seller->getRawOriginal('status');
        
        // Lógica de toggle: 0 (Inactivo) -> 1 (Activo), 1 (Activo) -> 3 (Bloqueado), 3 (Bloqueado) -> 0 (Inactivo)
        // No permitir cambiar si está en PENDING (2)
        if ($currentStatus == Seller::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el estado de un vendedor pendiente'
            ], 400);
        }
        
        $newStatus = match($currentStatus) {
            0 => 1,  // Inactivo -> Activo
            1 => 3,  // Activo -> Bloqueado
            3 => 0,  // Bloqueado -> Inactivo
            default => 1
        };
        
        $seller->update(['status' => $newStatus]);
        
        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'status_text' => $seller->fresh()->status_text,
            'status_class' => $seller->fresh()->status_class,
        ]);
    }

    /**
     * Gestor con permiso «Administrar vendedores» (permission_sellers) en la entidad.
     */
    private function jsonUnlessManagerSellersPermission(User $user, int $entityId): ?\Illuminate\Http\JsonResponse
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->isAdministration() && $user->canAccessEntity($entityId)) {
            return null;
        }

        $allowed = $user->accessibleEntityIdsByPermission('sellers');
        if (! in_array($entityId, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para administrar vendedores en esta entidad.',
            ], 403);
        }

        return null;
    }

    private function findSetAndParticipationByReferenceForUser(User $user, string $referencia, ?string $signature = null): ?array
    {
        $referencia = ParticipationTicketReference::normalize($referencia) ?? '';
        if ($referencia === '' || ParticipationTicketReference::authenticationError($referencia, $signature) !== null) {
            return null;
        }

        $set = Set::forUser($user)->whereNotNull('tickets')->get()->first(function ($s) use ($referencia) {
            if (! is_array($s->tickets)) {
                return false;
            }
            foreach ($s->tickets as $ticket) {
                if (isset($ticket['r']) && $ticket['r'] == $referencia) {
                    return true;
                }
            }

            return false;
        });

        if (! $set) {
            return null;
        }

        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] == $referencia) {
                $participationNumber = $ticket['n'] ?? null;
                break;
            }
        }

        return $participationNumber !== null
            ? ['set' => $set, 'participation_number' => $participationNumber]
            : null;
    }
} 