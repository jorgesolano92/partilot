<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Entity;
use App\Models\Participation;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementPayment;
use App\Models\ParticipationGift;
use App\Models\ParticipationCollection;
use App\Models\ParticipationCollectionItem;
use App\Models\ParticipationDonation;
use App\Models\ParticipationDonationItem;
use App\Models\User;
use App\Models\PendingDigitalSale;
use App\Http\Controllers\ApiController;
use App\Services\CommunicationEmailService;
use App\Mail\ParticipationGiftRecipientMail;
use App\Mail\ParticipationGiftSenderMail;
use App\Mail\DigitalPurchaseConfirmationMail;
use App\Mail\TransferCollectionVerificationMail;
use App\Mail\DonationCodeConfirmationMail;
use App\Services\AppInboxNotificationService;
use App\Services\PendingDigitalSaleService;
use App\Services\ParticipationGiftService;
use App\Support\ParticipationTicketReference;
use App\Services\ParticipationOwnerService;
use App\Services\ParticipationWalletValidityService;
use App\Services\EntityLotteryPrizePaymentService;
use App\Models\EntityLotteryPrizeActivationLog;

class ParticipationController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Calcula resumen de estados para un set.
     * Garantiza coherencia: vendidas + devueltas + anuladas + disponibles = total_configurado.
     * Cualquier estado no contemplado se considera "disponible" a efectos de suma.
     */
    private function getSetStatusSummary(int $setId, int $totalConfigured): array
    {
        $counts = \App\Models\Participation::where('set_id', $setId)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $sold = (int) ($counts['vendida'] ?? 0) + (int) ($counts['pagada'] ?? 0);
        $returned = (int) ($counts['devuelta'] ?? 0);
        $cancelled = (int) ($counts['anulada'] ?? 0);

        $knownSum = $sold + $returned + $cancelled;
        // Si por datos inconsistentes knownSum supera el configurado, ampliamos el total para no dar negativos.
        $total = max($totalConfigured, $knownSum);
        $available = max(0, $total - $knownSum);

        return [
            'total' => $total,
            'sold' => $sold,
            'returned' => $returned,
            'cancelled' => $cancelled,
            'available' => $available,
        ];
    }
    /**
     * Mostrar lista de participaciones
     */
    public function index(Request $request)
    {
        if ($redirect = $this->redirectIfImplicitEntity($request, 'participations.create')) {
            return $redirect;
        }

        $entities = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->orderBy('created_at', 'desc')
            ->get(); // Mostrar solo entidades accesibles
        
        return view('participations.index', compact('entities'));
    }

    /**
     * Mostrar formulario para buscar participaciones - Paso 1: Seleccionar entidad
     */
    public function create()
    {
        // Si no hay entidad seleccionada en sesión, redirigir al index
        $entityId = session('selected_entity_id');

        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('participations.index');
        }
        
        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);
        session(['selected_entity' => $entity]);
        
        // Obtener los design_formats de la entidad seleccionada
        $designFormats = \App\Models\DesignFormat::where('entity_id', $entity->id)
            ->with(['set.reserve.lottery', 'set.reserve.lottery.lotteryType', 'set.entity:id,name,image'])
            ->get();
        
        // Procesar cada designFormat para calcular los tacos
        foreach ($designFormats as $designFormat) {
            $this->calculateBooks($designFormat);
            if ($designFormat->set) {
                $totalConfigured = (int) ($designFormat->set->total_participations ?? 0);
                $designFormat->set_stats = $this->getSetStatusSummary((int) $designFormat->set->id, $totalConfigured);
                // Sets digitales: cargar participaciones para mostrar directamente (taco implícito)
                if ($designFormat->set->digital_participations > 0 && (int) ($designFormat->set->physical_participations ?? 0) === 0) {
                    $designFormat->digital_participations_list = $this->getFormattedParticipationsForBook($designFormat->set->id, 1);
                } else {
                    $designFormat->digital_participations_list = null;
                }
            } else {
                $designFormat->set_stats = ['total' => 0, 'sold' => 0, 'returned' => 0, 'cancelled' => 0, 'available' => 0];
                $designFormat->digital_participations_list = null;
            }
        }

        return view('participations.add', compact('entity', 'designFormats'));
    }

    /**
     * Guardar selección de entidad y mostrar formulario de búsqueda - Paso 2
     */
    public function store_entity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($request->entity_id);

        if ($entity->status != 1) {
            return redirect()->back()->with('error', 'Solo se puede seleccionar una entidad activa.');
        }

        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_entity_id', $entity->id);

        // Obtener los design_formats de la entidad seleccionada (con entity e image para mostrar imagen en listado)
        $designFormats = \App\Models\DesignFormat::where('entity_id', $entity->id)
            ->with(['set.reserve.lottery', 'set.reserve.lottery.lotteryType', 'set.entity:id,name,image'])
            ->get();

        // Procesar cada designFormat para calcular los tacos
        foreach ($designFormats as $designFormat) {
            $this->calculateBooks($designFormat);
            if ($designFormat->set) {
                $totalConfigured = (int) ($designFormat->set->total_participations ?? 0);
                $designFormat->set_stats = $this->getSetStatusSummary((int) $designFormat->set->id, $totalConfigured);
                if ($designFormat->set->digital_participations > 0 && (int) ($designFormat->set->physical_participations ?? 0) === 0) {
                    $designFormat->digital_participations_list = $this->getFormattedParticipationsForBook($designFormat->set->id, 1);
                } else {
                    $designFormat->digital_participations_list = null;
                }
            } else {
                $designFormat->set_stats = ['total' => 0, 'sold' => 0, 'returned' => 0, 'cancelled' => 0, 'available' => 0];
                $designFormat->digital_participations_list = null;
            }
        }

        return view('participations.add', compact('entity', 'designFormats'));
    }

    /**
     * Mostrar participación específica por ID con todos los datos relacionados
     */
    public function view($id)
    {
        $participation = Participation::with([
            'set.reserve.lottery.lotteryType',
            'set.reserve.entity.administration',
            'seller.user',
            'designFormat'
        ])
        ->forUser(auth()->user())
        ->findOrFail($id);
        
        return view('participations.view', compact('participation'));
    }

    /**
     * Mostrar participación específica
     */
    public function show($id)
    {
        $participation = Participation::with([
            'set.reserve.lottery.lotteryType',
            'set.reserve.entity.administration',
            'seller.user',
            'designFormat',
            'walletOwner',
        ])
        ->forUser(auth()->user())
        ->findOrFail($id);
        
        // Buscar la referencia del ticket en el set (tickets tienen n=1..N por set; participation_number puede ser global en físicos)
        $ticketReference = null;
        if ($participation->set && $participation->set->tickets) {
            $tickets = is_string($participation->set->tickets) ? json_decode($participation->set->tickets, true) : $participation->set->tickets;
            if (is_array($tickets)) {
                $minPn = $participation->set->participations()->min('participation_number');
                $indexInSet = $minPn !== null ? ($participation->participation_number - $minPn + 1) : $participation->participation_number;
                foreach ($tickets as $ticket) {
                    if (isset($ticket['n']) && (int) $ticket['n'] === (int) $indexInSet) {
                        $ticketReference = $ticket['r'] ?? null;
                        break;
                    }
                }
            }
        }

        $pendingDigitalSaleForLinkCode = null;
        $user = auth()->user();
        if ($user && $user->canViewPendingDigitalLinkCode()) {
            $pending = $participation->activePendingDigitalSale();
            if ($pending) {
                $pending->load('seller');
                $pending->ensureLinkCode();
                $pendingDigitalSaleForLinkCode = $pending;
            }
        }

        return view('participations.show', compact(
            'participation',
            'ticketReference',
            'pendingDigitalSaleForLinkCode'
        ));
    }

    /**
     * Actualizar solo el comentario/notas de una participación
     */
    public function updateNotes(Request $request, $id)
    {
        $request->validate([
            'notes' => 'nullable|string|max:65535'
        ]);

        $participation = Participation::forUser(auth()->user())->findOrFail($id);
        $participation->update(['notes' => $request->input('notes', '')]);

        return response()->json([
            'success' => true,
            'message' => 'Comentario guardado correctamente'
        ]);
    }

    /**
     * Mostrar participación para vendedor
     */
    public function show_seller($id)
    {
        $participation = Participation::forUser(auth()->user())->findOrFail($id);
        return view('participations.show_seller', compact('participation'));
    }

    /**
     * Calcular los tacos (books) para un designFormat
     */
    private function calculateBooks($designFormat)
    {
        if (!$designFormat->set) {
            $designFormat->books = [];
            return;
        }

        // Obtener el número de participaciones por taco desde el JSON
        $output = is_string($designFormat->output) ? json_decode($designFormat->output, true) : $designFormat->output;
        $totalParticipations = $designFormat->set->total_participations ?? 0;
        // Sets digitales: un solo "taco" (serie única), no múltiples talonarios
        $isDigitalOnly = $designFormat->set->digital_participations > 0 && (int) ($designFormat->set->physical_participations ?? 0) === 0;
        $participationsPerBook = $isDigitalOnly ? $totalParticipations : (int) ($output['participations_per_book'] ?? 50);
        if ($participationsPerBook <= 0) {
            $participationsPerBook = 50;
        }

        // Calcular cuántos tacos necesitamos
        $totalBooks = ceil($totalParticipations / $participationsPerBook);
        
        // Obtener el número de set (por fecha de creación)
        $setNumber = $this->getSetNumber($designFormat->set);
        
        $books = [];
        for ($i = 1; $i <= $totalBooks; $i++) {
            $expectedTotalInBook = (int) min(
                $participationsPerBook,
                max(0, $totalParticipations - (($i - 1) * $participationsPerBook))
            );

            // Estadísticas del taco por book_number (participation_number es global en BD); pagada = vendida
            $stats = \App\Models\Participation::where('set_id', $designFormat->set->id)
                ->where('book_number', $i)
                ->selectRaw('COUNT(*) as total_db')
                ->selectRaw("SUM(CASE WHEN status IN ('vendida', 'pagada') THEN 1 ELSE 0 END) as sold")
                ->selectRaw("SUM(CASE WHEN status = 'devuelta' THEN 1 ELSE 0 END) as returned")
                ->selectRaw("SUM(CASE WHEN status = 'anulada' THEN 1 ELSE 0 END) as cancelled")
                ->selectRaw('MIN(participation_number) as min_number')
                ->selectRaw('MAX(participation_number) as max_number')
                ->first();

            $salesRegistered = (int) ($stats->sold ?? 0);
            $returnedParticipations = (int) ($stats->returned ?? 0);
            $cancelledParticipations = (int) ($stats->cancelled ?? 0);
            $knownSum = $salesRegistered + $returnedParticipations + $cancelledParticipations;
            $availableParticipations = max(0, $expectedTotalInBook - $knownSum);
            
            // Determinar el estado del taco
            $status = 'Disponible';
            if ($returnedParticipations > 0) {
                $status = 'Con Devoluciones';
            } elseif ($salesRegistered > 0 && $availableParticipations == 0) {
                $status = 'Vendido';
            } elseif ($salesRegistered > 0 && $availableParticipations > 0) {
                $status = 'Parcial';
            }

            // Obtener el vendedor principal: primero el que más ha vendido; si no hay ventas, el que tiene más participaciones asignadas
            $mainSeller = \App\Models\Participation::where('set_id', $designFormat->set->id)
                ->where('book_number', $i)
                ->whereIn('status', ['vendida', 'pagada'])
                ->whereNotNull('seller_id')
                ->select('seller_id', DB::raw('COUNT(*) as c'))
                ->groupBy('seller_id')
                ->orderByDesc('c')
                ->value('seller_id');

            if (!$mainSeller) {
                $mainSeller = \App\Models\Participation::where('set_id', $designFormat->set->id)
                    ->where('book_number', $i)
                    ->whereNotNull('seller_id')
                    ->select('seller_id', DB::raw('COUNT(*) as c'))
                    ->groupBy('seller_id')
                    ->orderByDesc('c')
                    ->value('seller_id');
            }

            $sellerName = 'Sin asignar';
            if ($mainSeller) {
                $seller = \App\Models\Seller::with('user')->find($mainSeller);
                $sellerName = $seller ? $seller->full_name : 'Sin asignar';
            }

            $minNum = (int) ($stats->min_number ?? 0);
            $maxNum = (int) ($stats->max_number ?? 0);
            $rangeText = ($minNum > 0 && $maxNum > 0)
                ? sprintf('%d/%05d - %d/%05d', $setNumber, $minNum, $setNumber, $maxNum)
                : '-';

            $books[] = [
                'book_number' => $i,
                'set_number' => $setNumber,
                'start_participation' => $minNum ?: null,
                'end_participation' => $maxNum ?: null,
                'total_participations' => $expectedTotalInBook,
                'participations_range' => $rangeText,
                'sales_registered' => $salesRegistered,
                'returned_participations' => $returnedParticipations,
                'available_participations' => $availableParticipations,
                'status' => $status,
                'seller' => $sellerName,
            ];
        }
        
        $designFormat->books = $books;
    }

    /**
     * Número de set para mostrar en tacos/participaciones.
     * Usar el set_number del modelo (solo los físicos cuentan 1,2,3...; los digitales muestran 1).
     */
    private function getSetNumber($set)
    {
        return (int) ($set->set_number ?? 1);
    }

    /**
     * Obtener las participaciones de un taco específico
     */
    public function getBookParticipations($set_id, $book_number)
    {
        $set = \App\Models\Set::forUser(auth()->user())->findOrFail($set_id);
        
        // Obtener el designFormat asociado
        $designFormat = \App\Models\DesignFormat::where('set_id', $set_id)->first();
        
        if (!$designFormat) {
            return response()->json(['error' => 'Diseño no encontrado'], 404);
        }
        
        // Calcular los tacos para obtener el rango de participaciones
        $this->calculateBooks($designFormat);
        
        // Encontrar el taco específico
        $book = null;
        foreach ($designFormat->books as $b) {
            if ($b['book_number'] == $book_number) {
                $book = $b;
                break;
            }
        }
        
        if (!$book) {
            return response()->json(['error' => 'Taco no encontrado'], 404);
        }
        
        // Obtener las participaciones del taco por book_number (participation_number es global)
        $participations = \App\Models\Participation::where('set_id', $set_id)
            ->where('book_number', $book_number)
            ->with(['seller.user'])
            ->orderBy('participation_number')
            ->get();
        
                 // Formatear las participaciones para la vista (display_participation_code: 1D/00001 → 1/00001)
         $formattedParticipations = [];
         foreach ($participations as $participation) {
             $formattedParticipations[] = [
                 'id' => $participation->id,
                 'participation_number' => $participation->display_participation_code,
                 'status' => $participation->status_text,
                 'seller' => $participation->seller ? $participation->seller->full_name : 'Sin asignar',
                 'sale_date' => $participation->sale_date ? $participation->sale_date->format('d/m/Y') : '-',
                 'sale_time' => $participation->sale_time ? $participation->sale_time->format('H:i') . 'h' : '-',
             ];
         }
        
        return response()->json([
            'book' => $book,
            'participations' => $formattedParticipations
        ]);
    }

    /**
     * Lista formateada de participaciones de un set/book (para vista server-side, p. ej. sets digitales).
     */
    private function getFormattedParticipationsForBook($set_id, $book_number)
    {
        $participations = \App\Models\Participation::where('set_id', $set_id)
            ->where('book_number', $book_number)
            ->with(['seller.user'])
            ->orderBy('participation_number')
            ->get();

        return $participations->map(function ($participation) {
            return [
                'id' => $participation->id,
                'participation_number' => $participation->display_participation_code,
                'status' => $participation->status_text ?? $participation->status,
                'seller' => $participation->seller ? $participation->seller->full_name : 'Sin asignar',
                'sale_date' => $participation->sale_date ? $participation->sale_date->format('d/m/Y') : '-',
                'sale_time' => $participation->sale_time ? $participation->sale_time->format('H:i') . 'h' : '-',
            ];
        })->values()->all();
    }

    /**
     * API: Marcar participaciones como vendidas (modo manual)
     * Solo para vendedores autenticados. Las participaciones deben estar asignadas al vendedor.
     */
    public function apiSellManual(Request $request)
    {
        $request->validate([
            'set_id' => 'required|integer|exists:sets,id',
            'desde' => 'required|integer|min:1',
            'hasta' => 'required|integer|min:1',
            'payment_method' => 'nullable|string|in:efectivo,bizum,transferencia,omitir,otro',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        if ($request->desde > $request->hasta) {
            return response()->json(['success' => false, 'message' => 'El rango desde no puede ser mayor que hasta.'], 422);
        }

        try {
            $participations = Participation::where('set_id', $request->set_id)
                ->whereBetween('participation_number', [$request->desde, $request->hasta])
                ->where('seller_id', $seller->id)
                ->where('status', 'asignada')
                ->get();

            if ($participations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay participaciones asignadas a ti en ese rango o ya están vendidas.'
                ], 422);
            }

            // Verificar que todas las participaciones del rango estén asignadas al vendedor
            $totalEnRango = $request->hasta - $request->desde + 1;
            if ($participations->count() < $totalEnRango) {
                $noAsignadas = $totalEnRango - $participations->count();
                return response()->json([
                    'success' => false,
                    'message' => "Hay {$noAsignadas} participaciones en el rango que no están asignadas a ti o están anuladas."
                ], 422);
            }

            $set = $participations->first()->set()->with('reserve')->first();
            $pricePerParticipation = (float) ($set->played_amount ?? 0);
            $saleAmount = $participations->count() * $pricePerParticipation;

            $paymentMethod = $request->payment_method;
            DB::beginTransaction();
            foreach ($participations as $participation) {
                $participation->markAsSold($seller->id, $pricePerParticipation, [], $paymentMethod);
            }
            if ($this->shouldCreateSettlement($paymentMethod)) {
                $this->createSellerSettlementFromSale($seller, $participations, $set, $saleAmount, $paymentMethod, $user->id);
            }
            DB::commit();

            // Confirmación compra digital al email del comprador indicado por el vendedor
            try {
                $items = $participations->map(function ($p) {
                    return [
                        'code' => $p->display_participation_code,
                        'entity' => $p->set?->entity?->name ?? '',
                    ];
                })->values()->all();

                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $buyer->email,
                    recipientRole: 'usuario',
                    recipientUser: $buyer,
                    messageType: 'digital_purchase_confirmation',
                    templateKey: null,
                    mailClass: DigitalPurchaseConfirmationMail::class,
                    mailPayload: [
                        'buyer_id' => $buyer->id,
                        'items' => $items,
                        'total_amount' => (float) $saleAmount,
                    ],
                    context: ['source' => 'api', 'seller_id' => $seller->id],
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando confirmación de compra digital: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => "Se marcaron {$participations->count()} participaciones como vendidas.",
                'count' => $participations->count()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Vender participaciones digitales a un usuario existente.
     * Acepta set_id (venta por set asignado) O entity_id + lottery_id (pool: cualquier vendedor de la entidad vende del total disponible).
     */
    public function apiSellDigital(Request $request)
    {
        $request->validate([
            'set_id' => 'nullable|integer|exists:sets,id',
            'entity_id' => 'nullable|integer|exists:entities,id',
            'lottery_id' => 'nullable|integer|exists:lotteries,id',
            'quantity' => 'required|integer|min:1',
            'buyer_email' => 'required|email',
            'payment_method' => 'nullable|string|in:efectivo,bizum,transferencia,omitir,otro',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $buyer = User::where('email', $request->buyer_email)->first();
        if (!$buyer) {
            return response()->json([
                'success' => false,
                'message' => 'El correo no está registrado. Solo se puede vender a usuarios que ya tengan cuenta.',
            ], 422);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $pendingService = app(PendingDigitalSaleService::class);
        $usePool = $request->filled('entity_id') && $request->filled('lottery_id');
        $pendingService->releaseExpiredForDigitalContext(
            $usePool ? (int) $request->entity_id : null,
            $usePool ? (int) $request->lottery_id : null,
            $request->filled('set_id') ? (int) $request->set_id : null,
        );

        if ($usePool) {
            if (!$seller->entities()->where('entities.id', $request->entity_id)->exists()) {
                return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
            }
            $ids = Participation::query()
                ->join('sets', 'participations.set_id', '=', 'sets.id')
                ->join('reserves', 'sets.reserve_id', '=', 'reserves.id')
                ->where('participations.entity_id', $request->entity_id)
                ->where('reserves.lottery_id', $request->lottery_id)
                ->where('sets.physical_participations', '<=', 0)
                ->whereRaw('sets.digital_participations > 0')
                ->whereRaw("participations.participation_code LIKE '1D/%'")
                ->where('participations.status', 'disponible')
                ->select('participations.id')
                ->orderBy('participations.id')
                ->limit($request->quantity)
                ->pluck('participations.id');
            $participations = Participation::with('set.reserve')->whereIn('id', $ids)->orderBy('id')->get();
        } else {
            if (!$request->filled('set_id')) {
                return response()->json(['success' => false, 'message' => 'Indica set_id o entity_id + lottery_id.'], 422);
            }
            $set = \App\Models\Set::with('reserve')->findOrFail($request->set_id);
            if (($set->digital_participations ?? 0) <= 0) {
                return response()->json(['success' => false, 'message' => 'Este set no es de participaciones digitales.'], 422);
            }
            if (! $seller->entities()->where('entities.id', $set->entity_id)->exists()) {
                return response()->json(['success' => false, 'message' => 'No tienes acceso a esta entidad.'], 403);
            }
            $participations = app(PendingDigitalSaleService::class)
                ->queryDigitalDisponibleForSet((int) $request->set_id)
                ->orderBy('participation_number')
                ->limit($request->quantity)
                ->get();
        }

        if ($participations->count() < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'No hay suficientes participaciones digitales disponibles. Disponibles: ' . $participations->count(),
            ], 422);
        }

        $set = $participations->first()->set ?? \App\Models\Set::with('reserve')->find($participations->first()->set_id);
        if (!$set) {
            return response()->json(['success' => false, 'message' => 'Error al obtener el set.'], 500);
        }

        $pricePerParticipation = (float) ($set->played_amount ?? 0);
        $saleAmount = $participations->count() * $pricePerParticipation;
        $paymentMethod = $request->payment_method;

        try {
            DB::beginTransaction();
            foreach ($participations as $p) {
                $p->markAsSold($seller->id, $pricePerParticipation, [
                    'user_id' => $buyer->id,
                    'email' => $buyer->email,
                ], $paymentMethod);
            }
            if ($this->shouldCreateSettlement($paymentMethod)) {
                $this->createSellerSettlementFromSale($seller, $participations, $set, $saleAmount, $paymentMethod, $user->id);
            }
            DB::commit();

            try {
                app(\App\Services\DigitalParticipationNotificationService::class)->sendPurchaseConfirmation(
                    $buyer,
                    $participations,
                    (float) $saleAmount,
                    ['source' => 'api_sell_digital', 'seller_id' => $seller->id]
                );
            } catch (\Throwable $e) {
                \Log::warning('Fallo enviando confirmación venta digital API: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Venta digital registrada. ' . $participations->count() . ' participaciones vinculadas al cliente.',
                'count' => $participations->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Reservar venta digital para comprador no registrado y enviar email de registro web.
     */
    public function apiSellDigitalPending(Request $request, PendingDigitalSaleService $pendingService)
    {
        $request->validate([
            'set_id' => 'nullable|integer|exists:sets,id',
            'entity_id' => 'nullable|integer|exists:entities,id',
            'lottery_id' => 'nullable|integer|exists:lotteries,id',
            'quantity' => 'required|integer|min:1',
            'buyer_email' => 'nullable|email',
            'buyer_phone' => 'nullable|string|max:20',
            'notify_channel' => 'nullable|string|in:email,sms,whatsapp',
            'payment_method' => 'nullable|string|in:efectivo,bizum,transferencia,omitir,otro',
        ]);

        $user = $request->user();
        if (! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        $buyerEmail = trim((string) $request->input('buyer_email', ''));
        $buyerPhone = trim((string) $request->input('buyer_phone', ''));
        $notifyChannel = $request->input('notify_channel');

        if ($buyerEmail === '' && $buyerPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Debes indicar el email o el teléfono del comprador.',
            ], 422);
        }

        if ($buyerEmail !== '' && User::where('email', $buyerEmail)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El correo ya está registrado. Usa la venta directa.',
            ], 422);
        }

        try {
            $pending = $pendingService->createPendingSale(
                $seller,
                $user,
                $buyerEmail !== '' ? $buyerEmail : null,
                (int) $request->quantity,
                $request->payment_method,
                $request->set_id ? (int) $request->set_id : null,
                $request->entity_id ? (int) $request->entity_id : null,
                $request->lottery_id ? (int) $request->lottery_id : null,
                $buyerPhone !== '' ? $buyerPhone : null,
                $notifyChannel,
            );

            $initialSmsSent = false;
            try {
                $initialSmsSent = $pendingService->sendInitialSmsIfNeeded($pending);
                $pending->refresh();
            } catch (\Throwable $e) {
                \Log::warning('SMS inicial venta digital #'.$pending->id.': '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $pending->usesEmailChannel()
                    ? 'Se ha enviado un correo al comprador para completar el registro.'
                    : ($initialSmsSent
                        ? 'Venta registrada y SMS enviado al comprador.'
                        : 'Venta registrada. Envía el mensaje al comprador desde la pantalla de confirmación.'),
                'pending_id' => $pending->id,
                'buyer_registration_url' => $pending->registrationUrlForShare(),
                'valid_until' => $pending->valid_until?->toIso8601String(),
                'quantity' => $pending->quantity,
                'notify_channel' => $pending->notify_channel,
                'masked_buyer_contact' => $pending->maskedBuyerContact(),
                'initial_notify_sent' => $pending->usesEmailChannel() || $initialSmsSent,
                'buyer_sms_sent_count' => (int) ($pending->buyer_sms_sent_count ?? 0),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('apiSellDigitalPending: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al reservar la venta: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     */
    private function shouldCreateSettlement($paymentMethod): bool
    {
        return in_array($paymentMethod, ['efectivo', 'bizum', 'transferencia'], true);
    }

    /**
     * Crear registro en seller_settlements por venta desde la app.
     * Misma lógica que el backoffice: total_amount, paid_amount, pending_amount, total_participations.
     */
    private function createSellerSettlementFromSale($seller, $participations, $set, $saleAmount, $paymentMethod, $userId)
    {
        $lotteryId = $set->reserve->lottery_id ?? null;
        if (!$lotteryId) {
            return;
        }

        $paymentMethod = in_array($paymentMethod, ['efectivo', 'bizum', 'transferencia'], true) ? $paymentMethod : 'otro';
        $pricePerParticipation = (float) ($set->played_amount ?? 0);
        $now = now();

        // Obtener TODAS las participaciones (asignada+vendida+pagada) del vendedor para este sorteo
        $allParticipations = Participation::where('seller_id', $seller->id)
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->whereIn('status', ['asignada', 'vendida', 'pagada'])
            ->with('set')
            ->get();

        $totalParticipations = $allParticipations->count();
        $totalAmount = $allParticipations->sum(fn ($p) => (float) ($p->set->played_amount ?? 0));

        // Obtener lo ya pagado en liquidaciones previas
        $previousPaid = SellerSettlement::where('seller_id', $seller->id)
            ->where('lottery_id', $lotteryId)
            ->sum('paid_amount');

        $totalPaidWithNew = $previousPaid + $saleAmount;
        $pendingAmount = $totalAmount - $totalPaidWithNew;
        $calculatedParticipations = $pricePerParticipation > 0 ? round($saleAmount / $pricePerParticipation, 2) : 0;

        $settlement = SellerSettlement::create([
            'seller_id' => $seller->id,
            'lottery_id' => $lotteryId,
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'paid_amount' => $saleAmount,
            'pending_amount' => $pendingAmount,
            'total_participations' => $totalParticipations,
            'calculated_participations' => $calculatedParticipations,
            'settlement_date' => $now->format('Y-m-d'),
            'settlement_time' => $now->format('H:i:s'),
            'notes' => 'Venta registrada desde app'
        ]);

        SellerSettlementPayment::create([
            'seller_settlement_id' => $settlement->id,
            'amount' => $saleAmount,
            'payment_method' => $paymentMethod,
            'notes' => 'Venta - ' . ucfirst($paymentMethod),
            'payment_date' => $now
        ]);
    }

    /**
     * API: Marcar participación como vendida por escaneo QR
     * La referencia proviene del código QR de la participación física.
     */
    public function apiSellByQr(Request $request)
    {
        $request->validate([
            'referencia' => 'required|string',
            'desde' => 'nullable|integer|min:1',
            'hasta' => 'nullable|integer|min:1',
            'payment_method' => 'nullable|string|in:efectivo,bizum,transferencia,omitir,otro',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        // Buscar set y participación por referencia (contenido del QR)
        $set = \App\Models\Set::whereNotNull('tickets')->get()->first(function ($s) use ($request) {
            if (!is_array($s->tickets)) {
                return false;
            }
            foreach ($s->tickets as $ticket) {
                if (isset($ticket['r']) && $ticket['r'] == $request->referencia) {
                    return true;
                }
            }
            return false;
        });

        if (!$set) {
            return response()->json([
                'success' => false,
                'message' => 'Referencia no encontrada. Verifica que el código QR sea correcto.'
            ], 404);
        }

        // Si se proporciona rango desde/hasta, marcar el rango
        if ($request->filled('desde') && $request->filled('hasta')) {
            if ($request->desde > $request->hasta) {
                return response()->json(['success' => false, 'message' => 'El rango desde no puede ser mayor que hasta.'], 422);
            }

            $participations = Participation::where('set_id', $set->id)
                ->whereBetween('participation_number', [$request->desde, $request->hasta])
                ->where('seller_id', $seller->id)
                ->where('status', 'asignada')
                ->get();

            if ($participations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay participaciones asignadas a ti en ese rango.'
                ], 422);
            }

            try {
                $set = $participations->first()->set()->with('reserve')->first();
                $pricePerParticipation = (float) ($set->played_amount ?? 0);
                $saleAmount = $participations->count() * $pricePerParticipation;
                $paymentMethod = $request->payment_method;

                DB::beginTransaction();
                foreach ($participations as $participation) {
                    $participation->markAsSold($seller->id, $pricePerParticipation, [], $paymentMethod);
                }
                if ($this->shouldCreateSettlement($paymentMethod)) {
                    $this->createSellerSettlementFromSale($seller, $participations, $set, $saleAmount, $paymentMethod, $user->id);
                }
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => "Se marcaron {$participations->count()} participaciones como vendidas.",
                    'count' => $participations->count()
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
            }
        }

        // Marcar solo la participación escaneada
        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] == $request->referencia) {
                $participationNumber = $ticket['n'];
                break;
            }
        }

        if (!$participationNumber) {
            return response()->json(['success' => false, 'message' => 'Referencia no encontrada en el set.'], 404);
        }

        $participation = Participation::where('set_id', $set->id)
            ->where('participation_number', $participationNumber)
            ->where('seller_id', $seller->id)
            ->where('status', 'asignada')
            ->first();

        if (!$participation) {
            return response()->json([
                'success' => false,
                'message' => 'Esta participación no está asignada a ti o ya está vendida.'
            ], 422);
        }

        try {
            $set = $participation->set()->with('reserve')->first();
            $pricePerParticipation = (float) ($set->played_amount ?? 0);
            $saleAmount = $pricePerParticipation;
            $paymentMethod = $request->payment_method;

            DB::beginTransaction();
            $participation->markAsSold($seller->id, $pricePerParticipation, [], $paymentMethod);
            if ($this->shouldCreateSettlement($paymentMethod)) {
                $this->createSellerSettlementFromSale($seller, collect([$participation]), $set, $saleAmount, $paymentMethod, $user->id);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Participación marcada como vendida.',
                'participation' => [
                    'id' => $participation->id,
                    'participation_code' => $participation->display_participation_code,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Digitalizar participación escaneando QR (solo obtener información, no vender)
     * La referencia proviene del código QR de la participación física.
     */
    public function apiDigitalize(Request $request)
    {
        $request->validate([
            'referencia' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado o inactivo.'], 403);
        }

        // Buscar set y participación por referencia (contenido del QR)
        $set = \App\Models\Set::whereNotNull('tickets')->get()->first(function ($s) use ($request) {
            if (!is_array($s->tickets)) {
                return false;
            }
            foreach ($s->tickets as $ticket) {
                if (isset($ticket['r']) && $ticket['r'] == $request->referencia) {
                    return true;
                }
            }
            return false;
        });

        if (!$set) {
            return response()->json([
                'success' => false,
                'message' => 'Referencia no encontrada. Verifica que el código QR sea correcto.'
            ], 404);
        }

        // Obtener número de participación desde el ticket
        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] == $request->referencia) {
                $participationNumber = $ticket['n'];
                break;
            }
        }

        if (!$participationNumber) {
            return response()->json(['success' => false, 'message' => 'Referencia no encontrada en el set.'], 404);
        }

        // Buscar la participación asignada al vendedor
        $participation = Participation::where('set_id', $set->id)
            ->where('participation_number', $participationNumber)
            ->where('seller_id', $seller->id)
            ->where('status', 'asignada')
            ->with(['set.reserve.lottery.lotteryType', 'set.entity', 'set.designFormats'])
            ->first();

        if (!$participation) {
            return response()->json([
                'success' => false,
                'message' => 'Esta participación no está asignada a ti o ya está vendida.'
            ], 422);
        }

        // Obtener información de la participación
        $set = $participation->set;
        $reserve = $set->reserve ?? null;
        $lottery = $reserve ? $reserve->lottery : null;
        $entity = $set->entity ?? null;
        $designFormat = $set->designFormats->first();

        // Obtener snapshot_path del design format
        $snapshotPath = null;
        if ($designFormat && $designFormat->snapshot_path) {
            $snapshotPath = asset('storage/' . $designFormat->snapshot_path);
        }

        // Obtener número reservado de la lotería
        $numeroReservado = '—';
        if ($reserve && $reserve->reservation_numbers) {
            $reservationNumbers = is_array($reserve->reservation_numbers) 
                ? $reserve->reservation_numbers 
                : json_decode($reserve->reservation_numbers, true);
            if (is_array($reservationNumbers) && count($reservationNumbers) > 0) {
                if (count($reservationNumbers) === 1) {
                    $numeroReservado = (string) $reservationNumbers[0];
                } else {
                    $index = $participation->participation_number - 1;
                    if (isset($reservationNumbers[$index])) {
                        $numeroReservado = (string) $reservationNumbers[$index];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Participación digitalizada correctamente.',
            'participation' => [
                'id' => $participation->id,
                'participation_code' => $participation->display_participation_code,
                'numero' => $participation->participation_number,
                'referencia' => $request->referencia,
                'entity_name' => $entity ? $entity->name : '—',
                'entidad' => $entity ? $entity->name : '—',
                'draw_date' => $lottery && $lottery->draw_date ? $lottery->draw_date->format('Y-m-d') : null,
                'fechaSorteo' => $lottery && $lottery->draw_date ? $lottery->draw_date->format('d/m/y') : '—',
                'played_amount' => (float) ($set->played_amount ?? 0),
                'importeJugado' => (float) ($set->played_amount ?? 0),
                'donation_amount' => (float) ($set->donation_amount ?? 0),
                'donativo' => (float) ($set->donation_amount ?? 0),
                'amount' => (float) (($set->played_amount ?? 0) + ($set->donation_amount ?? 0)),
                'importeTotal' => (float) (($set->played_amount ?? 0) + ($set->donation_amount ?? 0)),
                'numeroReservado' => $numeroReservado,
                'image' => $snapshotPath,
                'snapshot_path' => $snapshotPath,
                'set' => [
                    'id' => $set->id,
                    'reserve' => $reserve ? [
                        'entity' => $entity ? [
                            'name' => $entity->name
                        ] : null
                    ] : null
                ]
            ]
        ]);
    }

    /**
     * API: Historial de ventas del vendedor autenticado (para app móvil).
     * Devuelve las participaciones vendidas por el vendedor en formato listado para el historial.
     */
    public function apiGetMySales(Request $request)
    {
        $user = $request->user();
        if (!$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (!$seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 403);
        }

        $participations = Participation::where('seller_id', $seller->id)
            ->where('status', 'vendida')
            ->with(['set.entity', 'set.reserve.lottery.lotteryType', 'set.designFormats'])
            ->orderBy('sale_date', 'desc')
            ->orderBy('sale_time', 'desc')
            ->limit(200)
            ->get();

        $historial = $participations->map(function ($p) use ($seller) {
            $set = $p->set;
            if (!$set) {
                return null;
            }
            $entity = $set->entity ?? null;
            $reserve = $set->reserve ?? null;
            $lottery = $reserve ? $reserve->lottery : null;
            $entidadNombre = $entity ? $entity->name : '—';
            $fechaSorteo = $lottery && $lottery->draw_date
                ? $lottery->draw_date->format('d/m/y')
                : '—';
            $importeJugado = (float) ($set->played_amount ?? 0);
            $donativo = (float) ($set->donation_amount ?? 0);
            $importeTotal = round($importeJugado + $donativo, 2);
            $saleDateTime = $p->sale_date
                ? $p->sale_date->format('Y-m-d') . 'T' . ($p->sale_time ? (is_object($p->sale_time) ? $p->sale_time->format('H:i:s') : substr((string) $p->sale_time, 0, 8)) : '00:00:00') . '.000000Z'
                : $p->updated_at->toIso8601String();

            // Obtener snapshot_path del design format
            $snapshotPath = null;
            $designFormat = $set->designFormats->first();
            if ($designFormat && $designFormat->snapshot_path) {
                $snapshotPath = asset('storage/' . $designFormat->snapshot_path);
            }

            // Obtener número reservado de la lotería
            $numeroReservado = '—';
            if ($reserve && $reserve->reservation_numbers) {
                $reservationNumbers = is_array($reserve->reservation_numbers) 
                    ? $reserve->reservation_numbers 
                    : json_decode($reserve->reservation_numbers, true);
                if (is_array($reservationNumbers)) {
                    // Si solo hay un número reservado, todas las participaciones del set tienen ese número
                    if (count($reservationNumbers) === 1) {
                        $numeroReservado = (string) $reservationNumbers[0];
                    } else {
                        // Si hay múltiples números, usar el índice correspondiente
                        $index = $p->participation_number - 1;
                        if (isset($reservationNumbers[$index])) {
                            $numeroReservado = (string) $reservationNumbers[$index];
                        }
                    }
                }
            }

            // Obtener número de referencia desde set.tickets (n en tickets es 1..N por set; participation_number puede ser global)
            $numeroReferencia = null;
            if ($set->tickets) {
                $tickets = is_array($set->tickets) ? $set->tickets : json_decode($set->tickets, true);
                if (is_array($tickets)) {
                    $minPn = $set->participations()->min('participation_number');
                    $indexInSet = $minPn !== null ? ($p->participation_number - $minPn + 1) : $p->participation_number;
                    foreach ($tickets as $ticket) {
                        if (isset($ticket['n']) && (int) $ticket['n'] === (int) $indexInSet) {
                            $numeroReferencia = $ticket['r'] ?? null;
                            break;
                        }
                    }
                }
            }

            // Método de pago: por participación (Tarea 3 QR); fallback a settlement para datos antiguos
            $formaPago = $p->payment_method ?? null;
            if ($formaPago === null || $formaPago === '') {
                $formaPago = 'efectivo';
                $lotteryId = $lottery ? $lottery->id : null;
                if ($lotteryId) {
                    $settlement = \App\Models\SellerSettlement::where('seller_id', $seller->id)
                        ->where('lottery_id', $lotteryId)
                        ->whereDate('settlement_date', $p->sale_date ?? now())
                        ->with('payments')
                        ->first();
                    if ($settlement && $settlement->payments->isNotEmpty()) {
                        $formaPago = $settlement->payments->first()->payment_method ?? 'efectivo';
                    }
                }
            }

            $isDigitalSet = $this->setIsDigitalOnly($set);
            $setLabel = $this->setHistorialLabel($set);
            $sorteoLabel = $this->lotteryHistorialLabel($lottery);

            return [
                'id' => $p->id,
                'tipo' => $isDigitalSet ? 'venta-digital' : 'venta',
                'fecha' => $saleDateTime,
                'formaPago' => $formaPago,
                'descripcion' => 'Participación ' . $entidadNombre,
                'sorteo' => $sorteoLabel,
                'fechaSorteo' => $fechaSorteo,
                'participacion' => [
                    'entidad' => $entidadNombre,
                    'sorteo' => $sorteoLabel,
                    'numero' => $numeroReservado,
                    'fechaSorteo' => $fechaSorteo,
                    'importeJugado' => $importeJugado,
                    'donativo' => $donativo > 0 ? $donativo : null,
                    'importeTotal' => $importeTotal,
                    'numeroParticipacion' => $p->display_participation_code ?? $p->participation_code ?? $p->participation_number . '/' . str_pad($p->participation_number, 4, '0', STR_PAD_LEFT),
                    'numeroReferencia' => $numeroReferencia ?? str_pad((string) $p->id, 19, '0', STR_PAD_LEFT),
                    'snapshotPath' => $snapshotPath,
                    'esDigital' => $isDigitalSet,
                    'setLabel' => $setLabel,
                    'set_number' => $set->set_number ?? null,
                ],
            ];
        })->filter()->values()->all();

        $pendingHistorial = PendingDigitalSale::where('seller_id', $seller->id)
            ->pendingNotExpired()
            ->with(['entity', 'lottery.lotteryType', 'set'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (PendingDigitalSale $p) {
                $smsNotify = app(\App\Services\DigitalSaleSmsService::class);
                $entity = $p->entity;
                $lottery = $p->lottery;
                if (! $lottery && $p->set) {
                    $p->set->loadMissing('reserve.lottery.lotteryType');
                    $lottery = $p->set->reserve?->lottery;
                }
                $entidadNombre = $entity ? $entity->name : '—';
                $fechaSorteo = $lottery && $lottery->draw_date
                    ? $lottery->draw_date->format('d/m/y')
                    : '—';
                $qty = (int) $p->quantity;
                $importeTotal = (float) $p->sale_amount;
                $importeJugado = $qty > 0 ? round($importeTotal / $qty, 2) : $importeTotal;
                $setLabel = $this->setHistorialLabel($p->set);
                $sorteoLabel = $this->lotteryHistorialLabel($lottery);

                return [
                    'id' => 'p-' . $p->id,
                    'pending_id' => $p->id,
                    'tipo' => 'venta-digital',
                    'fecha' => $p->created_at->toIso8601String(),
                    'formaPago' => $p->payment_method,
                    'quantity' => $qty,
                    'descripcion' => 'Venta digital ' . $entidadNombre . ' · Pendiente de registro',
                    'pendienteRegistro' => true,
                    'notify_channel' => $p->notify_channel,
                    'masked_buyer_contact' => $p->maskedBuyerContact(),
                    'valid_until' => $p->valid_until?->toIso8601String(),
                    'buyer_registration_url' => $p->registrationUrlForShare(),
                    'buyer_sms_sent_count' => (int) ($p->buyer_sms_sent_count ?? 0),
                    'buyer_sms_can_send' => $p->usesPhoneChannel() && $smsNotify->isEnabled() && $smsNotify->canSendToBuyer($p),
                    'buyer_sms_sends_remaining' => $p->usesPhoneChannel() ? $smsNotify->sendsRemaining($p) : 0,
                    'can_resend_email' => $p->usesEmailChannel() && $p->email && $p->isStillValid(),
                    'setLabel' => $setLabel,
                    'sorteo' => $sorteoLabel,
                    'fechaSorteo' => $fechaSorteo,
                    'participacion' => [
                        'entidad' => $entidadNombre,
                        'sorteo' => $sorteoLabel,
                        'numero' => $qty . ' dig.',
                        'fechaSorteo' => $fechaSorteo,
                        'importeJugado' => $importeJugado,
                        'importeTotal' => $importeTotal,
                        'clienteContactoEnmascarado' => $p->maskedBuyerContact(),
                        'notify_channel' => $p->notify_channel,
                        'pendienteRegistro' => true,
                        'validUntil' => $p->valid_until?->format('d/m/Y'),
                        'esDigital' => true,
                        'setLabel' => $setLabel,
                        'set_number' => $p->set?->set_number,
                        'buyer_registration_url' => $p->registrationUrlForShare(),
                    ],
                ];
            })
            ->all();

        $historial = collect($historial)
            ->concat($pendingHistorial)
            ->sortByDesc(fn (array $i) => $i['fecha'] ?? '')
            ->values()
            ->all();

        $notify = app(\App\Services\DigitalSaleBuyerNotifyService::class);

        return response()->json(array_merge([
            'success' => true,
            'historial' => $historial,
        ], $notify->configPayload()));
    }

    public function apiWhatsAppConfig()
    {
        return response()->json(array_merge(
            ['success' => true],
            app(\App\Services\DigitalSaleBuyerNotifyService::class)->configPayload()
        ));
    }

    /**
     * Envía SMS al comprador vía httpSMS (si está activo).
     */
    public function apiSendPendingDigitalNotify(Request $request, int $pendingId)
    {
        return $this->handleSendPendingDigitalNotify($request, $pendingId);
    }

    /**
     * Reenvía el correo de registro al email fijado en la venta pendiente.
     */
    public function apiResendPendingDigitalEmail(Request $request, int $pendingId, PendingDigitalSaleService $pendingService)
    {
        $user = $request->user();
        if (! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 403);
        }

        $pending = PendingDigitalSale::query()
            ->where('seller_id', $seller->id)
            ->where('id', $pendingId)
            ->pendingNotExpired()
            ->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Venta pendiente no encontrada o caducada.',
            ], 422);
        }

        try {
            $pendingService->resendRegistrationEmail($pending);

            return response()->json([
                'success' => true,
                'message' => 'Correo reenviado al comprador.',
                'masked_buyer_contact' => $pending->maskedBuyerContact(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('apiResendPendingDigitalEmail: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo reenviar el correo.',
            ], 500);
        }
    }

    /**
     * Enlace wa.me para reenvío manual (teléfono fijado en la venta; no editable).
     */
    public function apiGetPendingDigitalWhatsAppLink(Request $request, int $pendingId)
    {
        $user = $request->user();
        if (! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 403);
        }

        $pending = PendingDigitalSale::query()
            ->where('seller_id', $seller->id)
            ->where('id', $pendingId)
            ->pendingNotExpired()
            ->first();

        if (! $pending || ! $pending->buyer_phone) {
            return response()->json([
                'success' => false,
                'message' => 'Venta pendiente no encontrada o sin teléfono registrado.',
            ], 422);
        }

        $digits = ltrim((string) $pending->buyer_phone, '+');
        $message = \App\Services\DigitalSaleBuyerMessageBuilder::build($pending);
        $url = 'https://wa.me/'.$digits.'?text='.rawurlencode($message);

        return response()->json([
            'success' => true,
            'whatsapp_url' => $url,
            'masked_buyer_contact' => $pending->maskedBuyerContact(),
        ]);
    }

    /**
     * @deprecated Usar apiSendPendingDigitalNotify (alias histórico).
     */
    public function apiSendPendingDigitalWhatsApp(Request $request, int $pendingId)
    {
        return $this->handleSendPendingDigitalNotify($request, $pendingId);
    }

    private function handleSendPendingDigitalNotify(Request $request, int $pendingId)
    {
        $user = $request->user();
        if (! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta acción.'], 403);
        }

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if (! $seller) {
            return response()->json(['success' => false, 'message' => 'Vendedor no encontrado.'], 403);
        }

        $request->validate([
            'phone' => 'nullable|string|max:20',
        ]);

        $notify = app(\App\Services\DigitalSaleBuyerNotifyService::class);
        if ($notify->preferredChannel() === 'manual') {
            return response()->json([
                'success' => false,
                'message' => 'SMS no está configurado. Activa DIGITAL_SALE_SMS_ENABLED y httpSMS (HTTPSMS_API_KEY, HTTPSMS_FROM_NUMBER) en el servidor.',
            ], 503);
        }

        try {
            $pending = $notify->findPendingForSeller($seller, $pendingId);
            if (! $pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta pendiente no encontrada, caducada o ya reclamada.',
                ], 422);
            }

            $phone = trim((string) $request->input('phone', ''));
            if ($phone === '') {
                $phone = (string) ($pending->buyer_phone ?? '');
            }
            if ($phone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta no tiene teléfono de comprador registrado.',
                ], 422);
            }

            $result = $notify->sendToBuyer($seller, $pendingId, $phone);

            return response()->json([
                'success' => true,
                'message' => 'SMS enviado al comprador correctamente.',
                'channel' => $result['channel'],
                'message_sid' => $result['message_sid'],
                'buyer_sms_sent_count' => $result['buyer_sms_sent_count'] ?? null,
                'buyer_sms_sends_remaining' => $result['buyer_sms_sends_remaining'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('apiSendPendingDigitalNotify: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda global por número de referencia (desplegable del topbar).
     * Solo busca si el término tiene al menos config('partilot.search_min_chars') caracteres.
     */
    public function searchByReference(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $q = trim((string) $request->input('q', ''));
        $minChars = (int) config('partilot.search_min_chars', 6);
        if ($q === '' || strlen($q) < $minChars) {
            return response()->json(['results' => []]);
        }
        $maxResults = 20;
        $candidates = [];
        $sets = \App\Models\Set::whereNotNull('tickets')->get();
        foreach ($sets as $set) {
            if (!is_array($set->tickets)) {
                continue;
            }
            foreach ($set->tickets as $ticket) {
                $ref = $ticket['r'] ?? null;
                if ($ref !== null && (str_starts_with((string) $ref, $q) || str_contains((string) $ref, $q))) {
                    $candidates[] = [
                        'set_id' => $set->id,
                        'participation_number' => $ticket['n'] ?? null,
                        'referencia' => (string) $ref,
                    ];
                    if (count($candidates) >= $maxResults * 2) {
                        break 2;
                    }
                }
            }
        }
        $results = [];
        $user = auth()->user();
        foreach (array_slice($candidates, 0, $maxResults) as $c) {
            if ($c['participation_number'] === null) {
                continue;
            }
            $participation = Participation::where('set_id', $c['set_id'])
                ->where('participation_number', $c['participation_number'])
                ->forUser($user)
                ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats'])
                ->first();
            if (!$participation) {
                continue;
            }
            $row = $this->formatParticipationForWallet($participation, $c['referencia']);
            $row['status'] = $participation->status;
            $row['status_text'] = $participation->status_text;
            $row['detail_url'] = route('participations.show', $participation->id);
            $results[] = $row;
        }
        return response()->json(['results' => $results]);
    }

    /**
     * Buscar set y número de participación por referencia (campo 'r' del ticket).
     */
    private function findSetAndParticipationNumberByReference(string $referencia): ?array
    {
        $referencia = ParticipationTicketReference::normalize($referencia) ?? '';
        if ($referencia === '' || ! ParticipationTicketReference::isValid($referencia)) {
            return null;
        }

        $set = \App\Models\Set::whereNotNull('tickets')->get()->first(function ($s) use ($referencia) {
            if (!is_array($s->tickets)) {
                return false;
            }
            foreach ($s->tickets as $ticket) {
                if (isset($ticket['r']) && $ticket['r'] == $referencia) {
                    return true;
                }
            }
            return false;
        });
        if (!$set) {
            return null;
        }
        $participationNumber = null;
        foreach ($set->tickets as $ticket) {
            if (isset($ticket['r']) && $ticket['r'] == $referencia) {
                $participationNumber = $ticket['n'];
                break;
            }
        }
        return $participationNumber !== null ? ['set' => $set, 'participation_number' => $participationNumber] : null;
    }

    private function setHistorialLabel(?\App\Models\Set $set): ?string
    {
        if (! $set) {
            return null;
        }
        $name = trim((string) ($set->set_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $num = (int) ($set->set_number ?? 0);

        return $num > 0 ? 'Set '.$num : null;
    }

    private function setIsDigitalOnly(?\App\Models\Set $set): bool
    {
        if (! $set) {
            return false;
        }

        return ($set->digital_participations ?? 0) > 0
            && (int) ($set->physical_participations ?? 0) <= 0;
    }

    private function lotteryHistorialLabel(?\App\Models\Lottery $lottery): string
    {
        return $lottery ? $lottery->displayLabel() : '—';
    }

    /**
     * Formatear participación para respuesta de cartera/detalle (entidad, fecha, importes, nº referencia).
     */
    private function formatParticipationForWallet(Participation $participation, string $referencia): array
    {
        $set = $participation->set()->with(['reserve.lottery', 'entity.administration', 'designFormats'])->first();
        $reserve = $set->reserve ?? null;
        $lottery = $reserve ? $reserve->lottery : null;
        $entity = $set->entity ?? null;
        $designFormat = $set->designFormats->first();
        $snapshotPath = null;
        if ($designFormat && $designFormat->snapshot_path) {
            $snapshotPath = asset('storage/' . $designFormat->snapshot_path);
        }
        $numeroReservado = '—';
        if ($reserve && $reserve->reservation_numbers) {
            $nums = is_array($reserve->reservation_numbers) ? $reserve->reservation_numbers : json_decode($reserve->reservation_numbers, true);
            if (is_array($nums) && count($nums) > 0) {
                $numeroReservado = count($nums) === 1 ? (string) $nums[0] : (string) ($nums[$participation->participation_number - 1] ?? $nums[0]);
            }
        }
        $numeroReferencia = $referencia;
        if ($set->tickets) {
            $tickets = is_array($set->tickets) ? $set->tickets : json_decode($set->tickets, true);
            if (is_array($tickets)) {
                $minPn = $set->participations()->min('participation_number');
                $indexInSet = $minPn !== null ? ($participation->participation_number - $minPn + 1) : $participation->participation_number;
                foreach ($tickets as $ticket) {
                    if (isset($ticket['n']) && (int) $ticket['n'] === (int) $indexInSet) {
                        $numeroReferencia = $ticket['r'] ?? $referencia;
                        break;
                    }
                }
            }
        }
        $importeJugado = (float) ($set->played_amount ?? 0);
        $donativo = (float) ($set->donation_amount ?? 0);
        $importeTotal = $importeJugado + $donativo;
        $participationCode = $participation->participation_code ?? '';
        $isDigital = str_starts_with($participationCode, '1D/') || $this->setIsDigitalOnly($set);
        $administration = $entity?->administration;
        $prepagoService = app(\App\Services\PrepagoCodigosService::class);

        return [
            'id' => $participation->id,
            'referencia' => $referencia,
            'entidad' => $entity ? $entity->name : '—',
            'entity_id' => $entity ? (int) $entity->id : null,
            'administration_id' => $administration ? (int) $administration->id : null,
            'can_generate_recharge_code' => $prepagoService->canGenerateCodes($administration),
            'sorteo' => $this->lotteryHistorialLabel($lottery),
            'numero' => $participation->participation_number,
            'numeroReservado' => $numeroReservado,
            'fechaSorteo' => $lottery && $lottery->draw_date ? $lottery->draw_date->format('d/m/y') : '—',
            'importeJugado' => $importeJugado,
            'donativo' => $donativo,
            'importeTotal' => $importeTotal,
            'numeroParticipacion' => $participation->display_participation_code ?? $participation->participation_code ?? ($participation->participation_number . '/0001'),
            'numeroReferencia' => $numeroReferencia,
            'snapshot_path' => $snapshotPath,
            'is_digital' => $isDigital,
            'esDigital' => $isDigital,
            'wallet_mode' => $participation->wallet_mode ?? ($participation->buyerNameIsWalletUserId() ? Participation::WALLET_MODE_DIGITAL : null),
            'is_storage' => $participation->isWalletStorage(),
            'storage_message' => $participation->isWalletStorage()
                ? \App\Services\LotteryDigitalizationService::STORAGE_WALLET_MESSAGE
                : null,
            'setLabel' => $this->setHistorialLabel($set),
            'set_number' => $set->set_number ?? null,
        ];
    }

    /**
     * API: Listar participaciones en la cartera del usuario (propias + recibidas como regalo).
     */
    public function apiGetWalletParticipations(Request $request)
    {
        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Solo los usuarios pueden ver su cartera.'], 403);
        }
        $userId = (string) $user->id;
        $items = [];

        // Propias (buyer_name = user)
        $participations = Participation::where('buyer_name', $userId)
            ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats', 'gift.toUser'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $acceptedReceivedByParticipationId = ParticipationGift::query()
            ->where('to_user_id', $user->id)
            ->where('status', ParticipationGift::STATUS_ACCEPTED)
            ->with('fromUser')
            ->get()
            ->keyBy('participation_id');

        $apiController = app(ApiController::class);
        $giftService = app(ParticipationGiftService::class);
        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $item = $this->formatParticipationForWallet($p, $ref);
            $item['estado'] = 'activa';
            $item['gifted_to_email'] = null;
            if ($p->collected_at) {
                $item['estado'] = 'cobrada';
            } elseif ($p->donated_at) {
                $item['estado'] = 'donada';
            } elseif ($acceptedReceived = $acceptedReceivedByParticipationId->get($p->id)) {
                $item['estado'] = 'regalada';
                $item = array_merge($item, $giftService->walletGiftFields($acceptedReceived, 'recipient'));
            } elseif ($p->relationLoaded('gift') && $p->gift && in_array($p->gift->status, [ParticipationGift::STATUS_PENDING, ParticipationGift::STATUS_ACCEPTED], true)) {
                $item['estado'] = 'regalada';
                $item = array_merge($item, $giftService->walletGiftFields($p->gift, 'sender'));
            }
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $item['premio'] = $prizeInfo['has_won'] ? $prizeInfo['prize_amount'] : null;
            $item = $this->applyPrizePaymentGateToWalletItem($item, $p, $prizeInfo['prize_amount'] ?? null);
            $item = $this->applyWalletValidityToWalletItem($item, $p);
            if (! empty($item['wallet_expired'])) {
                continue;
            }
            $items[] = $item;
        }

        // Pendientes de aceptar (aún no son propiedad del destinatario)
        $userEmail = strtolower((string) $user->email);
        $giftsReceived = ParticipationGift::query()
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->where(function ($q) use ($user, $userEmail) {
                $q->where('to_user_id', $user->id)
                    ->orWhere(function ($q2) use ($userEmail) {
                        $q2->whereNull('to_user_id')
                            ->whereRaw('LOWER(to_email) = ?', [$userEmail]);
                    });
            })
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'fromUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsReceived as $gift) {
            $p = $gift->participation;
            if (! $p) {
                continue;
            }
            $ref = $this->getReferenceFromParticipation($p);
            $item = $this->formatParticipationForWallet($p, $ref);
            if ($p->collected_at) {
                $item['estado'] = 'cobrada';
            } elseif ($p->donated_at) {
                $item['estado'] = 'donada';
            } else {
                $item['estado'] = 'pendiente_regalo';
            }
            $item = array_merge($item, $giftService->walletGiftFields($gift, 'recipient'));
            $item['gifted_to_email'] = null;
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $item['premio'] = $prizeInfo['has_won'] ? $prizeInfo['prize_amount'] : null;
            $item = $this->applyPrizePaymentGateToWalletItem($item, $p, $prizeInfo['prize_amount'] ?? null);
            $item = $this->applyWalletValidityToWalletItem($item, $p);
            if (! empty($item['wallet_expired'])) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, function ($a, $b) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });

        return response()->json(['success' => true, 'participations' => $items]);
    }

    /**
     * API: Participaciones cobrables/donables (tienen premio, no cobradas ni donadas).
     * Incluye propias del usuario (no regaladas) y las recibidas como regalo.
     */
    public function apiGetCobrables(Request $request)
    {
        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Solo los usuarios pueden acceder.'], 403);
        }
        $userId = (string) $user->id;
        $apiController = app(ApiController::class);
        $items = [];
        $addedIds = [];
        $reservedIds = ParticipationCollection::reservedParticipationIds();

        // 1) Propias (buyer_name = user), no regaladas, no cobradas, no donadas, con premio
        $participations = Participation::where('buyer_name', $userId)
            ->whereNull('collected_at')
            ->whereNull('donated_at')
            ->when(!empty($reservedIds), fn ($q) => $q->whereNotIn('id', $reservedIds))
            ->with(['set.reserve.lottery', 'set.entity.administration', 'set.designFormats', 'gift'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($participations as $p) {
            if ($p->isWalletStorage()) {
                continue;
            }
            if ($p->relationLoaded('gift') && $p->gift && in_array($p->gift->status, [ParticipationGift::STATUS_PENDING, ParticipationGift::STATUS_ACCEPTED], true)) {
                continue; // regalada pendiente o aceptada por el destinatario
            }
            if (app(ParticipationWalletValidityService::class)->isParticipationWalletExpired($p)) {
                continue;
            }
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (!($prizeInfo['has_won'] && $prizeInfo['prize_amount'] > 0)) {
                continue; // sin premio
            }
            $item = $this->formatParticipationForWallet($p, $ref);
            $item['premio'] = $prizeInfo['prize_amount'];
            $gate = $this->prizePaymentService()->evaluateOnlineCollection($p, (float) $prizeInfo['prize_amount']);
            if (! $gate['cobrable']) {
                continue;
            }
            $item = array_merge($item, $gate);
            $items[] = $item;
            $addedIds[$p->id] = true;
        }

        // 2) Recibidas y aceptadas (buyer_name = user tras aceptar)
        $giftsReceived = ParticipationGift::where('to_user_id', $user->id)
            ->where('status', ParticipationGift::STATUS_ACCEPTED)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats'])
            ->get();

        foreach ($giftsReceived as $gift) {
            $p = $gift->participation;
            if (! $p || isset($addedIds[$p->id])) {
                continue;
            }
            if ((string) $p->buyer_name !== $userId) {
                continue;
            }
            if ($p->collected_at || $p->donated_at) {
                continue;
            }
            if ($p->isWalletStorage()) {
                continue;
            }
            if (app(ParticipationWalletValidityService::class)->isParticipationWalletExpired($p)) {
                continue;
            }
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (!($prizeInfo['has_won'] && $prizeInfo['prize_amount'] > 0)) {
                continue;
            }
            $item = $this->formatParticipationForWallet($p, $ref);
            $item['premio'] = $prizeInfo['prize_amount'];
            $item['recibida_regalo'] = true;
            $gate = $this->prizePaymentService()->evaluateOnlineCollection($p, (float) $prizeInfo['prize_amount']);
            if (! $gate['cobrable']) {
                continue;
            }
            $item = array_merge($item, $gate);
            $items[] = $item;
            $addedIds[$p->id] = true;
        }

        return response()->json(['success' => true, 'participations' => $items]);
    }

    /**
     * API: Registrar cobro (marca participaciones como cobradas).
     * Valida nombre, apellidos, NIF e IBAN (formato español).
     */
    public function apiRegistrarCobro(Request $request)
    {
        $request->validate([
            'participation_ids' => 'required|array',
            'participation_ids.*' => 'integer|exists:participations,id',
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'nif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'iban' => ['required', 'string', new \App\Rules\SpanishIban],
            'importe_total' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }
        $userId = (string) $user->id;

        $allowedIds = $this->getParticipationIdsOwnedOrReceivedByUser($user);
        $reservedIds = ParticipationCollection::reservedParticipationIds();
        $participations = Participation::whereIn('id', $request->participation_ids)
            ->whereIn('id', $allowedIds)
            ->whereNull('collected_at')
            ->when(!empty($reservedIds), fn ($q) => $q->whereNotIn('id', $reservedIds))
            ->get();

        if ($participations->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Ninguna participación válida para cobrar.'], 422);
        }

        $participations->load('set.reserve.lottery');
        $walletValidity = app(ParticipationWalletValidityService::class);
        foreach ($participations as $p) {
            if ($walletValidity->isParticipationWalletExpired($p)) {
                return $this->walletExpiredJsonResponse();
            }
            if ($p->isWalletStorage()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las participaciones en almacén no se pueden cobrar online.',
                ], 422);
            }
        }

        // Multientidad: permitido solo si todas las entidades están habilitadas para cobro online
        $participations->load('set.entity');
        $multiCheck = $this->prizePaymentService()->canGroupMultientityTransfer(
            $participations->pluck('id')->all()
        );
        if (! $multiCheck['allowed']) {
            return response()->json(['success' => false, 'message' => $multiCheck['message']], 422);
        }

        $apiController = app(ApiController::class);
        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $gate = $this->prizePaymentService()->evaluateOnlineCollection(
                $p,
                (float) ($prizeInfo['prize_amount'] ?? 0)
            );
            if (! $gate['cobrable']) {
                return response()->json([
                    'success' => false,
                    'message' => $gate['user_message'] ?? 'El cobro online no está habilitado para esta participación.',
                ], 422);
            }
        }

        // Usar el importe total enviado desde el frontend
        $importeTotal = (float) $request->importe_total;
        $token = ParticipationCollection::generateConfirmationToken();
        $expiresAt = now()->addHours(ParticipationCollection::verificationExpiryHours());

        // Crear solicitud pendiente de verificación (doble opt-in por email)
        $collection = ParticipationCollection::create([
            'user_id' => $user->id,
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'nif' => $request->nif,
            'iban' => $request->iban,
            'importe_total' => $importeTotal,
            'status' => ParticipationCollection::STATUS_PENDING_VERIFICATION,
            'confirmation_token' => $token,
            'confirmation_sent_at' => now(),
            'expires_at' => $expiresAt,
            'collected_at' => null,
        ]);

        // Reservar participaciones (sin marcar collected_at hasta confirmar email)
        $participationIds = $participations->pluck('id')->toArray();
        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $itemData = [
                'collection_id' => $collection->id,
                'participation_id' => $p->id,
                'entity_id' => $p->set?->entity_id ?? $p->entity_id,
                'amount' => (float) ($prizeInfo['prize_amount'] ?? 0),
            ];
            ParticipationCollectionItem::create($itemData);
        }

        // Email con enlace de confirmación / cancelación
        try {
            $collection->load('user');
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'transfer_collection_verification',
                templateKey: null,
                mailClass: TransferCollectionVerificationMail::class,
                mailPayload: ['collection_id' => $collection->id],
                context: ['source' => 'api', 'user_id' => $user->id],
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando email de verificación de cobro: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Revisa tu email y confirma la solicitud de cobro. Hasta entonces no se procesará el pago.',
            'pending_verification' => true,
            'collected_count' => 0,
        ]);
    }

    /**
     * API: Registrar donación (marca participaciones como donadas y genera código de recarga si aplica).
     * Valida participation_ids, importe_donacion, importe_codigo, y datos personales opcionales.
     */
    public function apiRegistrarDonacion(Request $request)
    {
        $request->validate([
            'participation_ids' => 'required|array',
            'participation_ids.*' => 'integer|exists:participations,id',
            'importe_donacion' => 'required|numeric|min:0',
            'importe_codigo' => 'required|numeric|min:0',
            'nombre' => 'nullable|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'nif' => ['nullable', 'string', 'max:20', new \App\Rules\SpanishDocument],
        ]);

        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }
        $userId = (string) $user->id;

        $allowedIds = $this->getParticipationIdsOwnedOrReceivedByUser($user);
        $reservedIds = ParticipationCollection::reservedParticipationIds();
        $participations = Participation::whereIn('id', $request->participation_ids)
            ->whereIn('id', $allowedIds)
            ->whereNull('collected_at')
            ->whereNull('donated_at')
            ->when(!empty($reservedIds), fn ($q) => $q->whereNotIn('id', $reservedIds))
            ->get();

        if ($participations->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Ninguna participación válida para donar.'], 422);
        }

        $participations->load('set.reserve.lottery');
        $walletValidity = app(ParticipationWalletValidityService::class);
        foreach ($participations as $p) {
            if ($walletValidity->isParticipationWalletExpired($p)) {
                return $this->walletExpiredJsonResponse();
            }
            if ($p->isWalletStorage()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las participaciones en almacén no se pueden donar.',
                ], 422);
            }
        }

        // Todas las participaciones deben ser de la misma entidad
        $participations->load('set.entity.administration');
        $entityIds = $participations->map(fn ($p) => $p->set?->entity_id ?? $p->entity_id)->filter()->unique()->values();
        if ($entityIds->count() > 1) {
            return response()->json(['success' => false, 'message' => 'Solo puedes donar participaciones de la misma entidad.'], 422);
        }

        $apiController = app(ApiController::class);
        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            $gate = $this->prizePaymentService()->evaluateOnlineCollection(
                $p,
                (float) ($prizeInfo['prize_amount'] ?? 0)
            );
            if (! $gate['cobrable']) {
                return response()->json([
                    'success' => false,
                    'message' => $gate['user_message'] ?? 'El cobro online no está habilitado para esta participación.',
                ], 422);
            }
        }

        $importeDonacion = (float) $request->importe_donacion;
        $importeCodigo = (float) $request->importe_codigo;
        $importeTotal = $importeDonacion + $importeCodigo;

        // Mismo criterio que la app (premio si hay premio; si no, importeTotal del set).
        $totalParticipaciones = round($participations->sum(
            fn (Participation $p) => $this->resolveParticipationWalletAmount($p)
        ), 2);

        if (abs($importeTotal - $totalParticipaciones) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'La suma de donación y código no coincide con el importe total de las participaciones.',
            ], 422);
        }

        $entity = Entity::with('administration')->find($entityIds->first());
        $administration = $entity?->administration;
        $prepagoService = app(\App\Services\PrepagoCodigosService::class);

        if ($importeCodigo > 0 && ! $prepagoService->canGenerateCodes($administration)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta administración no tiene configurada la generación de códigos de recarga. Dona el importe total sin generar código.',
            ], 422);
        }

        // Generar código de recarga si hay importe para código (API de la administración o PARTILOT por defecto)
        $codigoRecarga = null;
        if ($importeCodigo > 0) {
            $codigoRecarga = $prepagoService->generateCode($administration, $importeCodigo);
            if ($codigoRecarga === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar el código de recarga. Compruebe la configuración del servicio de códigos prepago o intente de nuevo más tarde.',
                ], 502);
            }
        }

        // Determinar si es anónima (sin datos personales)
        $anonima = empty($request->nombre) || empty($request->apellidos) || empty($request->nif);

        // Crear registro de donación
        $donation = ParticipationDonation::create([
            'user_id' => $user->id,
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'nif' => $request->nif,
            'importe_donacion' => $importeDonacion,
            'importe_codigo' => $importeCodigo,
            'codigo_recarga' => $codigoRecarga,
            'anonima' => $anonima,
            'donated_at' => now(),
        ]);

        // Marcar participaciones como donadas en la tabla participations
        $participationIds = $participations->pluck('id')->toArray();
        Participation::whereIn('id', $participationIds)->update(['donated_at' => now()]);

        // Asociar cada participación al registro de donación
        foreach ($participationIds as $pid) {
            ParticipationDonationItem::create([
                'donation_id' => $donation->id,
                'participation_id' => $pid,
            ]);
        }

        // Donación/código recarga: email de confirmación
        try {
            $donation->load('user');
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'donation_code_confirmation',
                templateKey: null,
                mailClass: DonationCodeConfirmationMail::class,
                mailPayload: ['donation_id' => $donation->id],
                context: ['source' => 'api', 'user_id' => $user->id],
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando email donación/código: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Donación registrada correctamente.',
            'donation_id' => $donation->id,
            'codigo_recarga' => $codigoRecarga,
            'importe_donacion' => $importeDonacion,
            'importe_codigo' => $importeCodigo,
        ]);
    }

    /**
     * API: Historial del usuario (digitalizaciones, regalos enviados; cobros pendiente).
     * Solo clientes. Ordenado por fecha descendente.
     */
    public function apiGetUserHistorial(Request $request)
    {
        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'Solo los usuarios pueden ver su historial.'], 403);
        }
        $userId = (string) $user->id;
        $historial = [];

        // 1. Participaciones vinculadas a la cartera (buyer_name = user): digitalizaciones o ventas digitales recibidas
        $participations = Participation::where('buyer_name', $userId)
            ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats'])
            ->orderBy('updated_at', 'desc')
            ->get();

        // Separar: digitales (set con digital_participations > 0) vs físicas (digitalización por QR)
        $digitales = $participations->filter(fn ($p) => $p->set && ($p->set->digital_participations ?? 0) > 0);
        $fisicas = $participations->filter(fn ($p) => !$p->set || ($p->set->digital_participations ?? 0) <= 0);

        // Digitalizaciones (físicas escaneadas): una entrada por participación
        foreach ($fisicas as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'd-' . $p->id,
                'tipo' => 'digitalizacion',
                'fecha' => $p->updated_at->toIso8601String(),
                'participacion' => $participacion,
                'descripcion' => 'Participación ' . ($participacion['entidad'] ?? 'digitalizada'),
            ];
        }

        // Ventas digitales recibidas: agrupar por set_id + sale_date y una entrada por lote
        foreach ($digitales->groupBy(fn ($p) => ($p->set_id ?? 0) . '-' . ($p->sale_date?->format('Y-m-d') ?? $p->updated_at->format('Y-m-d'))) as $grupo) {
            $p = $grupo->first();
            $participaciones = [];
            foreach ($grupo as $part) {
                $ref = $this->getReferenceFromParticipation($part);
                $participaciones[] = $this->formatParticipationForWallet($part, $ref);
            }
            $count = $grupo->count();
            $entidad = $p->set?->entity?->name ?? $participaciones[0]['entidad'] ?? '—';
            $historial[] = [
                'id' => 'vd-' . $p->id . '-' . $grupo->count(),
                'tipo' => 'venta_digital_recibida',
                'fecha' => $p->updated_at->toIso8601String(),
                'participacion' => $participaciones[0],
                'participaciones' => $participaciones,
                'descripcion' => 'Has recibido ' . $count . ' participación(es) digital(es) - ' . $entidad,
            ];
        }

        // 2. Regalos enviados (participation_gifts donde from_user_id = user)
        $giftsSent = ParticipationGift::where('from_user_id', $user->id)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'toUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsSent as $gift) {
            $p = $gift->participation;
            if (!$p) continue;
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'r-env-' . $gift->id,
                'tipo' => 'regalo',
                'fecha' => $gift->created_at->toIso8601String(),
                'participacion' => $participacion,
                'emailDestinatario' => $gift->toUser->email ?? null,
                'destinatario' => $gift->toUser->email ?? null,
                'direccion' => 'enviado',
                'descripcion' => 'Participación regalada a ' . ($gift->toUser->email ?? '—'),
            ];
        }

        // 2b. Regalos recibidos (participation_gifts donde to_user_id = user)
        $giftsReceived = ParticipationGift::where('to_user_id', $user->id)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'fromUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsReceived as $gift) {
            $p = $gift->participation;
            if (!$p) continue;
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'r-rec-' . $gift->id,
                'tipo' => 'regalo',
                'fecha' => $gift->created_at->toIso8601String(),
                'participacion' => $participacion,
                'emailRemitente' => $gift->fromUser->email ?? null,
                'remitente' => $gift->fromUser->email ?? null,
                'direccion' => 'recibido',
                'descripcion' => 'Participación recibida de ' . ($gift->fromUser->email ?? '—'),
            ];
        }

        // 3. Cobros: solicitudes del usuario (verificadas y pendientes de confirmación)
        $collections = ParticipationCollection::where('user_id', $user->id)
            ->whereIn('status', [
                ParticipationCollection::STATUS_VERIFIED,
                ParticipationCollection::STATUS_PENDING_VERIFICATION,
            ])
            ->with(['items.participation.set.reserve.lottery', 'items.participation.set.entity', 'items.participation.set.designFormats'])
            ->orderByRaw('COALESCE(collected_at, created_at) DESC')
            ->get();

        foreach ($collections as $collection) {
            $participaciones = [];
            foreach ($collection->items as $item) {
                $p = $item->participation;
                if (!$p) continue;
                $ref = $this->getReferenceFromParticipation($p);
                $participaciones[] = $this->formatParticipationForWallet($p, $ref);
            }
            
            if (!empty($participaciones)) {
                $fecha = $collection->collected_at ?? $collection->created_at;
                $historial[] = [
                    'id' => 'c-' . $collection->id,
                    'tipo' => 'cobro',
                    'fecha' => $fecha->toIso8601String(),
                    'participaciones' => $participaciones,
                    'importeTotal' => (float) $collection->importe_total,
                    'estado' => $collection->isPendingVerification() ? 'pendiente_verificacion' : 'confirmado',
                    'datosPersonales' => [
                        'nombre' => $collection->nombre,
                        'apellidos' => $collection->apellidos,
                        'nif' => $collection->nif,
                    ],
                    'iban' => $collection->iban,
                    'descripcion' => ($collection->isPendingVerification()
                        ? 'Cobro pendiente de confirmación por email'
                        : 'Cobro de ' . count($participaciones) . ' participación(es)') . ' - €' . number_format($collection->importe_total, 2, ',', '.'),
                ];
            }
        }

        // 4. Donaciones: participaciones donadas por el usuario
        $donations = ParticipationDonation::where('user_id', $user->id)
            ->with(['items.participation.set.reserve.lottery', 'items.participation.set.entity', 'items.participation.set.designFormats'])
            ->orderByRaw('COALESCE(donated_at, created_at) DESC')
            ->get();

        foreach ($donations as $donation) {
            $participaciones = [];
            if ($donation->items && $donation->items->count() > 0) {
                foreach ($donation->items as $item) {
                    if ($item->participation) {
                        $p = $item->participation;
                        $ref = $this->getReferenceFromParticipation($p);
                        $participaciones[] = $this->formatParticipationForWallet($p, $ref);
                    }
                }
            }
            
            // Añadir entrada de donación siempre (incluso si no tiene participaciones asociadas)
            $fechaDonacion = $donation->donated_at ? $donation->donated_at->toIso8601String() : ($donation->created_at ? $donation->created_at->toIso8601String() : now()->toIso8601String());
            
            $historial[] = [
                'id' => 'don-' . $donation->id,
                'tipo' => 'donacion',
                'fecha' => $fechaDonacion,
                'participaciones' => $participaciones,
                'importeDonacion' => (float) $donation->importe_donacion,
                'importeCodigo' => (float) $donation->importe_codigo,
                'codigoRecarga' => $donation->codigo_recarga ?? null,
                'datosPersonales' => $donation->anonima ? null : [
                    'nombre' => $donation->nombre,
                    'apellidos' => $donation->apellidos,
                    'nif' => $donation->nif,
                ],
                'anonima' => $donation->anonima,
                'descripcion' => 'Donación' . 
                    (count($participaciones) > 0 ? ' de ' . count($participaciones) . ' participación(es)' : '') .
                    ($donation->importe_donacion > 0 ? ' - €' . number_format($donation->importe_donacion, 2, ',', '.') : '') .
                    ($donation->codigo_recarga ? ' - Código: ' . $donation->codigo_recarga : ''),
            ];
        }

        // Ordenar por fecha descendente
        usort($historial, function ($a, $b) {
            $fechaA = $a['fecha'] ?? '';
            $fechaB = $b['fecha'] ?? '';
            return strcmp($fechaB, $fechaA);
        });

        return response()->json(['success' => true, 'historial' => $historial]);
    }

    /**
     * Datos de cartera para un usuario (uso web admin). Misma lógica que apiGetWalletParticipations pero para User $user.
     */
    public function getWalletDataForUser(User $user): array
    {
        $userId = (string) $user->id;
        $items = [];

        $participations = Participation::where('buyer_name', $userId)
            ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats', 'gift.toUser'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $item = $this->formatParticipationForWallet($p, $ref);
            $item['estado'] = 'activa';
            $item['gifted_to_email'] = null;
            if ($p->collected_at) {
                $item['estado'] = 'cobrada';
            } elseif ($p->donated_at) {
                $item['estado'] = 'donada';
            } elseif ($p->relationLoaded('gift') && $p->gift) {
                $item['estado'] = 'regalada';
                $item['gifted_to_email'] = $p->gift->toUser->email ?? null;
            }
            $item['premio'] = null;
            $items[] = $item;
        }

        $giftsReceived = ParticipationGift::where('to_user_id', $user->id)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'fromUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsReceived as $gift) {
            $p = $gift->participation;
            if (!$p) continue;
            $ref = $this->getReferenceFromParticipation($p);
            $item = $this->formatParticipationForWallet($p, $ref);
            $item['estado'] = $p->collected_at ? 'cobrada' : ($p->donated_at ? 'donada' : 'recibida');
            $item['received_from_email'] = $gift->fromUser->email ?? null;
            $item['gifted_to_email'] = null;
            $item['premio'] = null;
            $items[] = $item;
        }

        usort($items, function ($a, $b) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });

        return $items;
    }

    /**
     * Datos de historial para un usuario (uso web admin). Misma lógica que apiGetUserHistorial pero para User $user.
     */
    public function getHistorialDataForUser(User $user): array
    {
        $userId = (string) $user->id;
        $historial = [];

        $participations = Participation::where('buyer_name', $userId)
            ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats'])
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($participations as $p) {
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'd-' . $p->id,
                'tipo' => 'digitalizacion',
                'fecha' => $p->updated_at->toIso8601String(),
                'participacion' => $participacion,
                'descripcion' => 'Participación ' . ($participacion['entidad'] ?? 'digitalizada'),
            ];
        }

        $giftsSent = ParticipationGift::where('from_user_id', $user->id)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'toUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsSent as $gift) {
            $p = $gift->participation;
            if (!$p) continue;
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'r-env-' . $gift->id,
                'tipo' => 'regalo',
                'fecha' => $gift->created_at->toIso8601String(),
                'participacion' => $participacion,
                'destinatario' => $gift->toUser->email ?? '—',
                'direccion' => 'enviado',
                'descripcion' => 'Participación regalada a ' . ($gift->toUser->email ?? '—'),
            ];
        }

        $giftsReceived = ParticipationGift::where('to_user_id', $user->id)
            ->with(['participation.set.reserve.lottery', 'participation.set.entity', 'participation.set.designFormats', 'fromUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($giftsReceived as $gift) {
            $p = $gift->participation;
            if (!$p) continue;
            $ref = $this->getReferenceFromParticipation($p);
            $participacion = $this->formatParticipationForWallet($p, $ref);
            $historial[] = [
                'id' => 'r-rec-' . $gift->id,
                'tipo' => 'regalo',
                'fecha' => $gift->created_at->toIso8601String(),
                'participacion' => $participacion,
                'remitente' => $gift->fromUser->email ?? '—',
                'direccion' => 'recibido',
                'descripcion' => 'Participación recibida de ' . ($gift->fromUser->email ?? '—'),
            ];
        }

        $collections = ParticipationCollection::where('user_id', $user->id)
            ->with(['items.participation.set.reserve.lottery', 'items.participation.set.entity', 'items.participation.set.designFormats'])
            ->orderBy('collected_at', 'desc')
            ->get();

        foreach ($collections as $collection) {
            $participaciones = [];
            foreach ($collection->items as $item) {
                $p = $item->participation;
                if (!$p) continue;
                $ref = $this->getReferenceFromParticipation($p);
                $participaciones[] = $this->formatParticipationForWallet($p, $ref);
            }
            if (!empty($participaciones)) {
                $historial[] = [
                    'id' => 'c-' . $collection->id,
                    'tipo' => 'cobro',
                    'fecha' => $collection->collected_at->toIso8601String(),
                    'participaciones' => $participaciones,
                    'importeTotal' => (float) $collection->importe_total,
                    'descripcion' => 'Cobro de ' . count($participaciones) . ' participación(es) - €' . number_format($collection->importe_total, 2, ',', '.'),
                ];
            }
        }

        $donations = ParticipationDonation::where('user_id', $user->id)
            ->with(['items.participation.set.reserve.lottery', 'items.participation.set.entity', 'items.participation.set.designFormats'])
            ->orderByRaw('COALESCE(donated_at, created_at) DESC')
            ->get();

        foreach ($donations as $donation) {
            $participaciones = [];
            if ($donation->items && $donation->items->count() > 0) {
                foreach ($donation->items as $item) {
                    if ($item->participation) {
                        $ref = $this->getReferenceFromParticipation($item->participation);
                        $participaciones[] = $this->formatParticipationForWallet($item->participation, $ref);
                    }
                }
            }
            $fechaDonacion = $donation->donated_at ? $donation->donated_at->toIso8601String() : ($donation->created_at ? $donation->created_at->toIso8601String() : now()->toIso8601String());
            $historial[] = [
                'id' => 'don-' . $donation->id,
                'tipo' => 'donacion',
                'fecha' => $fechaDonacion,
                'participaciones' => $participaciones,
                'importeDonacion' => (float) $donation->importe_donacion,
                'descripcion' => 'Donación' . (count($participaciones) > 0 ? ' de ' . count($participaciones) . ' participación(es)' : '') . ($donation->importe_donacion > 0 ? ' - €' . number_format($donation->importe_donacion, 2, ',', '.') : ''),
            ];
        }

        usort($historial, function ($a, $b) {
            return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
        });

        return $historial;
    }

    /**
     * Importe que la cartera usa para cobro/donación: premio si la participación tiene premio; si no, jugado + donativo del set.
     */
    private function resolveParticipationWalletAmount(Participation $participation): float
    {
        $participation->loadMissing('set');

        $ref = $this->getReferenceFromParticipation($participation);
        if ($ref !== '') {
            $prizeInfo = app(ApiController::class)->getPrizeInfoForReference($ref);
            if (($prizeInfo['has_won'] ?? false) && (float) ($prizeInfo['prize_amount'] ?? 0) > 0) {
                return (float) $prizeInfo['prize_amount'];
            }
        }

        $set = $participation->set;
        if ($set) {
            return (float) ($set->played_amount ?? 0) + (float) ($set->donation_amount ?? 0);
        }

        return (float) ($participation->sale_amount ?? 0);
    }

    private function getReferenceFromParticipation(Participation $p): string
    {
        if (!$p->set || !is_array($p->set->tickets)) {
            return '';
        }
        foreach ($p->set->tickets as $ticket) {
            if (isset($ticket['n']) && $ticket['n'] == $p->participation_number) {
                return $ticket['r'] ?? '';
            }
        }
        return '';
    }

    /**
     * IDs de participaciones que el usuario puede cobrar/donar: propias (buyer_name) o recibidas como regalo.
     */
    private function getParticipationIdsOwnedOrReceivedByUser(User $user): \Illuminate\Support\Collection
    {
        $userId = (string) $user->id;
        $ownedIds = Participation::where('buyer_name', $userId)->pluck('id');
        $receivedIds = ParticipationGift::where('to_user_id', $user->id)
            ->where('status', ParticipationGift::STATUS_ACCEPTED)
            ->pluck('participation_id');

        return $ownedIds->merge($receivedIds)->unique()->values();
    }

    /**
     * API: Consultar participación por referencia (para usuario, antes de vincular).
     * Devuelve: can_link + datos, o status: already_mine | already_other | not_found.
     */
    public function apiCheckByReference(Request $request)
    {
        $request->validate(['referencia' => 'required|string']);
        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }
        $userId = (string) $user->id;
        $found = $this->findSetAndParticipationNumberByReference($request->referencia);
        if (!$found) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'No se encuentra la participación. Comprueba la referencia o el código QR.',
            ], 404);
        }
        $participation = Participation::where('set_id', $found['set']->id)
            ->where('participation_number', $found['participation_number'])
            ->with(['set.reserve.lottery', 'set.entity', 'set.designFormats'])
            ->first();
        if (!$participation) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'No se encuentra la participación.',
            ], 404);
        }
        // Solo se puede vincular si está disponible o asignada (no devuelta, vendida, anulada, etc.)
        if (!in_array($participation->status, ['disponible', 'asignada', 'vendida'], true)) {
            return response()->json([
                'success' => false,
                'status' => 'not_linkable',
                'message' => 'Esta participación no se puede vincular. No se encuentra disponible.',
            ], 422);
        }
        $currentBuyer = $participation->buyer_name;
        $lottery = $participation->set?->reserve?->lottery;
        $digitalizationService = app(\App\Services\LotteryDigitalizationService::class);
        $walletOptions = $digitalizationService->walletRegistrationOptions($participation, $lottery);
        if ($currentBuyer !== null && $currentBuyer !== '') {
            if ($currentBuyer === $userId) {
                return response()->json([
                    'success' => true,
                    'status' => 'already_mine',
                    'message' => 'Ya la posees en tu cartera.',
                    'participation' => $this->formatParticipationForWallet($participation, $request->referencia),
                    'wallet_options' => $walletOptions,
                    'digitalization_notice' => \App\Services\LotteryDigitalizationService::IRREVERSIBLE_NOTICE,
                    'storage_notice' => \App\Services\LotteryDigitalizationService::STORAGE_NOTICE,
                ]);
            }
            return response()->json([
                'success' => false,
                'status' => 'already_other',
                'message' => 'La participación no se puede vincular porque ya se encuentra leída por otro usuario.',
            ], 422);
        }
        if (! $walletOptions['can_digitalize'] && ! $walletOptions['can_store_in_warehouse']) {
            $message = $walletOptions['notice']
                ?? 'Esta participación no se puede registrar en la app.';
            if (! $digitalizationService->isPhysicalParticipation($participation)) {
                $message = 'Las participaciones digitales nativas no se digitalizan desde este flujo.';
            }

            return response()->json([
                'success' => false,
                'status' => 'not_linkable',
                'message' => $message,
                'wallet_options' => $walletOptions,
            ], 422);
        }
        return response()->json([
            'success' => true,
            'status' => 'can_link',
            'participation' => $this->formatParticipationForWallet($participation, $request->referencia),
            'wallet_options' => $walletOptions,
            'digitalization_notice' => \App\Services\LotteryDigitalizationService::IRREVERSIBLE_NOTICE,
            'storage_notice' => \App\Services\LotteryDigitalizationService::STORAGE_NOTICE,
        ]);
    }

    /**
     * API: Vincular participación a la cartera del usuario (guardar user id en buyer_name).
     */
    public function apiLinkToWallet(Request $request)
    {
        $request->validate(['referencia' => 'required|string']);
        $user = $request->user();
        // Permitir tanto usuarios (client) como vendedores (seller) cuando acceden como usuarios normales
        if (!$user->isClient() && !$user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }
        $userId = (string) $user->id;
        $found = $this->findSetAndParticipationNumberByReference($request->referencia);
        if (!$found) {
            return response()->json([
                'success' => false,
                'message' => 'No se encuentra la participación.',
            ], 404);
        }
        $participation = Participation::where('set_id', $found['set']->id)
            ->where('participation_number', $found['participation_number'])
            ->first();
        if (!$participation) {
            return response()->json(['success' => false, 'message' => 'No se encuentra la participación.'], 404);
        }
        if (!in_array($participation->status, ['disponible', 'asignada','vendida'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta participación no se puede vincular. No se encuentra disponible.',
            ], 422);
        }
        if ($participation->buyer_name !== null && $participation->buyer_name !== '') {
            if ($participation->buyer_name === $userId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ya la tienes en tu cartera.',
                    'participation' => $this->formatParticipationForWallet($participation->load('set'), $request->referencia),
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'La participación no se puede vincular porque ya se encuentra leída por otro usuario.',
            ], 422);
        }

        $participation->load('set.reserve.lottery');
        $lottery = $participation->set?->reserve?->lottery;
        $digitalizationService = app(\App\Services\LotteryDigitalizationService::class);

        try {
            if ($lottery) {
                $digitalizationService->assertCanRegisterInWallet($lottery);
            }
            if (! $digitalizationService->isPhysicalParticipation($participation)) {
                throw new \InvalidArgumentException('Solo se pueden digitalizar participaciones físicas.');
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        \App\Services\ParticipationOwnerService::assignOwner($participation, $user);
        $participation->wallet_mode = Participation::WALLET_MODE_DIGITAL;
        $participation->save();

        try {
            app(\App\Services\DigitalParticipationNotificationService::class)->sendWalletLinked(
                $user,
                $participation->fresh(['set.entity', 'set.reserve.lottery']),
                'api_link_wallet'
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando email vinculación cartera: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Participación añadida a tu cartera.',
            'participation' => $this->formatParticipationForWallet($participation->load(['set.reserve.lottery', 'set.entity', 'set.designFormats']), $request->referencia),
        ]);
    }

    /**
     * API: Guardar participación física en almacén (solo consulta, sin digitalizar).
     */
    public function apiStoreInWarehouse(Request $request)
    {
        $request->validate(['referencia' => 'required|string']);
        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }
        $userId = (string) $user->id;
        $found = $this->findSetAndParticipationNumberByReference($request->referencia);
        if (! $found) {
            return response()->json([
                'success' => false,
                'message' => 'No se encuentra la participación.',
            ], 404);
        }
        $participation = Participation::where('set_id', $found['set']->id)
            ->where('participation_number', $found['participation_number'])
            ->first();
        if (! $participation) {
            return response()->json(['success' => false, 'message' => 'No se encuentra la participación.'], 404);
        }
        if (! in_array($participation->status, ['disponible', 'asignada', 'vendida'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta participación no se puede guardar en almacén.',
            ], 422);
        }
        if ($participation->buyer_name !== null && $participation->buyer_name !== '') {
            if ($participation->buyer_name === $userId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ya la tienes en tu almacén.',
                    'participation' => $this->formatParticipationForWallet($participation->load('set'), $request->referencia),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'La participación ya está registrada por otro usuario.',
            ], 422);
        }

        $participation->load('set.reserve.lottery');
        $lottery = $participation->set?->reserve?->lottery;
        $digitalizationService = app(\App\Services\LotteryDigitalizationService::class);

        try {
            if ($lottery) {
                $digitalizationService->assertCanRegisterInWallet($lottery);
            }
            if (! $digitalizationService->isPhysicalParticipation($participation)) {
                throw new \InvalidArgumentException('Solo se pueden guardar participaciones físicas en almacén.');
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        \App\Services\ParticipationOwnerService::assignOwner($participation, $user);
        $participation->wallet_mode = Participation::WALLET_MODE_STORAGE;
        $participation->save();

        return response()->json([
            'success' => true,
            'message' => 'Participación guardada en almacén.',
            'participation' => $this->formatParticipationForWallet(
                $participation->load(['set.reserve.lottery', 'set.entity', 'set.designFormats']),
                $request->referencia
            ),
        ]);
    }

    /**
     * API: Vincular venta digital pendiente por código (comprador registrado sin email correcto).
     */
    public function apiClaimPendingDigitalByCode(Request $request, PendingDigitalSaleService $pendingService)
    {
        $request->validate([
            'link_code' => 'required|string|min:5|max:12',
        ], [
            'link_code.required' => 'Introduce el código de vinculación.',
        ]);

        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        try {
            $pending = $pendingService->claimByLinkCode($user, (string) $request->link_code);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('apiClaimPendingDigitalByCode: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo vincular las participaciones.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Se han añadido '.$pending->quantity.' participación(es) a tu cartera.',
            'quantity' => $pending->quantity,
            'entity' => $pending->entity?->name,
            'lottery' => $pending->lottery?->name,
        ]);
    }

    /**
     * API: Regalar participación a otro usuario por email.
     * La participación sigue en la cartera del que regala con estado regalada; el destinatario la ve en la suya.
     */
    public function apiGiftToUser(Request $request, ParticipationGiftService $giftService)
    {
        $request->validate([
            'participation_id' => 'required|integer|exists:participations,id',
            'email' => 'required|email',
            'message' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $userId = (string) $user->id;
        $participation = Participation::find($request->participation_id);
        if (! $participation || $participation->buyer_name !== $userId) {
            return response()->json(['success' => false, 'message' => 'La participación no está en tu cartera.'], 404);
        }

        if (ParticipationGift::where('participation_id', $participation->id)
            ->whereIn('status', [ParticipationGift::STATUS_PENDING, ParticipationGift::STATUS_ACCEPTED])
            ->exists()) {
            return response()->json(['success' => false, 'message' => 'Esta participación ya ha sido regalada.'], 422);
        }
        if ($participation->collected_at) {
            return response()->json(['success' => false, 'message' => 'No se puede regalar una participación ya cobrada.'], 422);
        }
        if ($participation->donated_at) {
            return response()->json(['success' => false, 'message' => 'No se puede regalar una participación ya donada.'], 422);
        }
        if ($participation->isWalletStorage()) {
            return response()->json(['success' => false, 'message' => 'Las participaciones en almacén no se pueden regalar.'], 422);
        }
        if (app(ParticipationWalletValidityService::class)->isParticipationWalletExpired($participation)) {
            return $this->walletExpiredJsonResponse();
        }

        try {
            $result = $giftService->createGift(
                $user,
                $participation,
                $request->email,
                $request->input('message')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Participación enviada con éxito. El destinatario debe aceptarla para que pase a su cartera.',
            'gifted_to_email' => $result['gifted_to_email'],
            'gift_id' => $result['gift']->id,
            'gift_status' => $result['gift']->status,
        ]);
    }

    /**
     * API: Aceptar participación regalada.
     */
    public function apiAcceptGift(Request $request, int $giftId, ParticipationGiftService $giftService)
    {
        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $giftService->attachPendingGiftsToUser($user);

        $gift = ParticipationGift::find($giftId);
        if (! $gift) {
            return response()->json(['success' => false, 'message' => 'Regalo no encontrado.'], 404);
        }

        try {
            $gift = $giftService->acceptGift($gift, $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Participación aceptada. Ya forma parte de tu cartera.',
            'gift_id' => $gift->id,
        ]);
    }

    /**
     * API: Rechazar participación regalada.
     */
    public function apiRejectGift(Request $request, int $giftId, ParticipationGiftService $giftService)
    {
        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $giftService->attachPendingGiftsToUser($user);

        $gift = ParticipationGift::find($giftId);
        if (! $gift) {
            return response()->json(['success' => false, 'message' => 'Regalo no encontrado.'], 404);
        }

        try {
            $giftService->rejectGift($gift, $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Has rechazado el regalo. La participación vuelve al remitente.',
        ]);
    }

    /**
     * API: Regalos pendientes de aceptar (para aviso al entrar en la app).
     */
    public function apiPendingGifts(Request $request)
    {
        $user = $request->user();
        if (! $user->isClient() && ! $user->isSeller()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        app(ParticipationGiftService::class)->attachPendingGiftsToUser($user);

        $userEmail = strtolower((string) $user->email);
        $gifts = ParticipationGift::query()
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->where(function ($q) use ($user, $userEmail) {
                $q->where('to_user_id', $user->id)
                    ->orWhere(function ($q2) use ($userEmail) {
                        $q2->whereNull('to_user_id')->whereRaw('LOWER(to_email) = ?', [$userEmail]);
                    });
            })
            ->with(['fromUser', 'participation.set.entity'])
            ->orderByDesc('created_at')
            ->get();

        $items = $gifts->map(function (ParticipationGift $gift) {
            return [
                'gift_id' => $gift->id,
                'participation_id' => $gift->participation_id,
                'from_name' => $gift->fromUser?->name ?? $gift->fromUser?->email,
                'from_email' => $gift->fromUser?->email,
                'message' => $gift->message,
                'gifted_at' => $gift->created_at?->toIso8601String(),
                'entidad' => $gift->participation?->set?->entity?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'gifts' => $items,
        ]);
    }

    /**
     * API Gestor: Entidades para la pantalla de Pago (solo las que el usuario gestiona como gestor).
     * Usa getManagerEntityIds() para que un gestor con una sola entidad vea solo esa;
     * si no tiene registros en managers (ej. admin), usa entidades accesibles.
     */
    public function apiGetEntitiesForPayment()
    {
        $user = auth()->user();
        $entityIds = $user->getManagerEntityIds();
        if (empty($entityIds)) {
            $entityIds = $user->accessibleEntityIds();
        }
        if (empty($entityIds)) {
            return response()->json(['success' => true, 'entities' => []]);
        }

        $entities = Entity::with('administration')
            ->whereIn('id', $entityIds)
            ->where('status', 1)
            ->get()
            ->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'image' => $entity->image,
                    'province' => $entity->province ?? 'N/A',
                    'city' => $entity->city ?? 'N/A',
                    'administration_name' => $entity->administration->name ?? 'Sin administración',
                    'status' => $entity->status ? 'activo' : 'inactivo',
                ];
            });

        return response()->json([
            'success' => true,
            'entities' => $entities,
        ]);
    }

    /**
     * API Gestor: Validar participaciones para pago (solo con premio tras escrutinio).
     * Parámetros: entity_id, lottery_id, set_id, desde?, hasta?, referencia?.
     * Devuelve solo participaciones con premio, no pagadas, de la entidad del gestor.
     */
    public function apiValidateParticipationsForPayment(Request $request)
    {
        $data = $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'lottery_id' => 'required|exists:lotteries,id',
            'set_id' => 'nullable|exists:sets,id',
            'desde' => 'nullable|integer',
            'hasta' => 'nullable|integer',
            'referencia' => 'nullable|string',
        ]);

        if (!auth()->user()->canAccessEntity((int) $data['entity_id'])) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para esta entidad.'], 403);
        }

        $apiController = app(ApiController::class);
        $participations = [];

        if (!empty($data['referencia'])) {
            $req = new Request([
                'entity_id' => $data['entity_id'],
                'lottery_id' => $data['lottery_id'],
                'referencia' => $data['referencia'],
            ]);
            $resp = app(DevolutionsController::class)->validateParticipations($req);
            $content = $resp->getData(true);
            if (!$content['success'] || empty($content['participations'])) {
                return response()->json(['success' => true, 'participations' => []]);
            }
            $participations = $content['participations'];
        } else {
            if (empty($data['set_id'])) {
                return response()->json(['success' => false, 'message' => 'Falta set_id o referencia.'], 422);
            }
            $req = new Request(array_merge($data, ['set_id' => $data['set_id']]));
            $resp = app(DevolutionsController::class)->validateParticipations($req);
            $content = $resp->getData(true);
            if (!$content['success'] || empty($content['participations'])) {
                return response()->json(['success' => true, 'participations' => []]);
            }
            $participations = $content['participations'];
        }

        $entityId = (int) $data['entity_id'];
        $out = [];
        $rejected = [];
        foreach ($participations as $item) {
            $id = $item['id'] ?? null;
            if (!$id) {
                continue;
            }
            $p = Participation::with(['set.reserve.lottery', 'entity'])
                ->forUser(auth()->user())
                ->where('entity_id', $entityId)
                ->where('id', $id)
                ->where('status', '!=', 'pagada')
                ->first();
            if (!$p) {
                $rejected[] = [
                    'participation_code' => $item['participation_code'] ?? $item['code'] ?? null,
                    'reason' => 'not_found',
                    'message' => 'Participación no encontrada o sin permisos.',
                ];
                continue;
            }
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (!($prizeInfo['has_won'] && $prizeInfo['prize_amount'] > 0)) {
                $rejected[] = [
                    'participation_code' => $p->display_participation_code,
                    'reason' => 'no_prize',
                    'message' => 'La participación no tiene premio.',
                ];
                continue;
            }
            $presencialGate = $this->prizePaymentService()->evaluatePresencialPayment(
                $p,
                (float) $prizeInfo['prize_amount']
            );
            if (! $presencialGate['allowed']) {
                $rejected[] = [
                    'participation_code' => $p->display_participation_code,
                    'reason' => $presencialGate['reason'],
                    'message' => $presencialGate['message'],
                ];
                continue;
            }
            $out[] = [
                'id' => $p->id,
                'participation_code' => $p->display_participation_code,
                'participation_number' => $p->participation_number,
                'set_id' => $p->set_id,
                'set_name' => $p->set->set_name ?? ('Set ' . ($p->set->set_number ?? '')),
                'entity_name' => $p->entity->name ?? '',
                'lottery_name' => $p->set && $p->set->reserve && $p->set->reserve->lottery ? $p->set->reserve->lottery->name : '',
                'premio' => $prizeInfo['prize_amount'],
                'premio_categoria' => $prizeInfo['prize_category'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'participations' => $out,
            'rejected' => $rejected,
        ]);
    }

    /**
     * API Gestor: Registrar pago de participaciones (pasan a pagada y se registra en historial).
     */
    public function apiRegisterPayment(Request $request)
    {
        $request->validate([
            'participation_ids' => 'required|array|min:1',
            'participation_ids.*' => 'integer|exists:participations,id',
        ]);

        $user = auth()->user();
        $apiController = app(ApiController::class);
        $valid = [];
        $firstBlockMessage = null;
        foreach ($request->participation_ids as $id) {
            $p = Participation::with('set.entity')
                ->forUser($user)
                ->where('id', $id)
                ->where('status', '!=', 'pagada')
                ->first();
            if (!$p || !$user->canAccessEntity((int) $p->entity_id)) {
                $firstBlockMessage = $firstBlockMessage ?? 'Una o más participaciones no son válidas para tu entidad.';
                continue;
            }
            $ref = $this->getReferenceFromParticipation($p);
            $prizeInfo = $apiController->getPrizeInfoForReference($ref);
            if (!$prizeInfo['has_won'] || $prizeInfo['prize_amount'] <= 0) {
                $firstBlockMessage = $firstBlockMessage ?? 'Una o más participaciones no tienen premio.';
                continue;
            }
            $presencialGate = $this->prizePaymentService()->evaluatePresencialPayment(
                $p,
                (float) $prizeInfo['prize_amount']
            );
            if (! $presencialGate['allowed']) {
                $firstBlockMessage = $presencialGate['message'] ?? 'Pago presencial no permitido.';
                continue;
            }
            $valid[] = $p;
        }

        if (empty($valid)) {
            return response()->json([
                'success' => false,
                'message' => $firstBlockMessage ?? 'Ninguna participación válida para pagar (deben tener premio y no estar ya pagadas).',
            ], 422);
        }

        $now = now();
        foreach ($valid as $p) {
            $oldStatus = $p->status;
            $p->update([
                'status' => 'pagada',
                'collected_at' => $now,
            ]);
            \App\Models\ParticipationActivityLog::log($p->id, 'paid', [
                'user_id' => $user->id,
                'entity_id' => $p->entity_id,
                'old_status' => $oldStatus,
                'new_status' => 'pagada',
                'description' => 'Pago de premio registrado por el gestor.',
            ]);

            $lotteryId = (int) ($p->set?->reserve?->lottery_id ?? 0);
            $setting = $lotteryId
                ? $this->prizePaymentService()->getSettings((int) $p->entity_id, $lotteryId)
                : null;
            if ($setting) {
                EntityLotteryPrizeActivationLog::query()->create([
                    'entity_lottery_prize_setting_id' => $setting->id,
                    'event' => 'payment_registered_presencial',
                    'payload' => ['participation_id' => $p->id],
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado con éxito.',
            'count' => count($valid),
        ]);
    }

    /**
     * Marca caducidad en ítem de cartera (3 meses desde fecha del sorteo).
     */
    protected function applyWalletValidityToWalletItem(array $item, Participation $participation): array
    {
        $validity = app(ParticipationWalletValidityService::class);
        $item['wallet_valid_until'] = $validity->walletValidUntilIso($participation);

        if ($validity->isParticipationWalletExpired($participation)
            && ! in_array($item['estado'] ?? '', ['cobrada', 'donada'], true)) {
            $item['estado'] = 'caducada';
            $item['wallet_expired'] = true;
        }

        return $item;
    }

    protected function applyPrizePaymentGateToWalletItem(
        array $item,
        Participation $participation,
        ?float $prizeAmount
    ): array {
        if ($participation->isWalletStorage()) {
            $item['cobrable'] = false;
            $item['payment_blocked'] = false;
            $item['block_reason'] = 'storage';
            $item['user_message'] = null;
            $item['storage_message'] = \App\Services\LotteryDigitalizationService::STORAGE_WALLET_MESSAGE;
            if ($prizeAmount !== null && $prizeAmount > 0) {
                $item['presencial_orientative_prize'] = $prizeAmount;
                $entityId = (int) ($participation->entity_id ?? $participation->set?->entity_id ?? 0);
                $lotteryId = (int) ($participation->set?->reserve?->lottery_id ?? 0);
                if ($entityId && $lotteryId) {
                    $setting = $this->prizePaymentService()->getSettings($entityId, $lotteryId);
                    if ($setting && $setting->isModePresencial()) {
                        $item['presencial_contact'] = $this->prizePaymentService()->presencialContactPayload($setting);
                    }
                }
            }

            return $item;
        }

        if ($prizeAmount === null || $prizeAmount <= 0) {
            $item['cobrable'] = false;
            $item['payment_blocked'] = false;
            $item['block_reason'] = null;
            $item['user_message'] = null;

            return $item;
        }

        $gate = $this->prizePaymentService()->evaluateOnlineCollection($participation, $prizeAmount);
        $item = array_merge($item, $gate);

        if ($prizeAmount > 0 && empty($item['is_digital'])) {
            $entityId = (int) ($participation->entity_id ?? $participation->set?->entity_id ?? 0);
            $lotteryId = (int) ($participation->set?->reserve?->lottery_id ?? 0);
            if ($entityId && $lotteryId) {
                $setting = $this->prizePaymentService()->getSettings($entityId, $lotteryId);
                if ($setting && $setting->isModePresencial()) {
                    $item['presencial_contact'] = $this->prizePaymentService()->presencialContactPayload($setting);
                }
            }
        }

        return $item;
    }

    protected function prizePaymentService(): EntityLotteryPrizePaymentService
    {
        return app(EntityLotteryPrizePaymentService::class);
    }

    protected function walletExpiredJsonResponse(): \Illuminate\Http\JsonResponse
    {
        $months = (int) config('digital_sale.wallet_validity_months_after_draw', 3);

        return response()->json([
            'success' => false,
            'message' => "Esta participación ha caducado (plazo de {$months} meses desde la fecha del sorteo).",
        ], 422);
    }
}
