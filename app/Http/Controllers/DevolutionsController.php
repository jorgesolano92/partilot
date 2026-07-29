<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Participation;
use App\Models\Entity;
use App\Models\Seller;
use App\Models\Lottery;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\Devolution;
use App\Models\DevolutionDetail;
use App\Models\DevolutionPayment;
use App\Models\EntityLotteryPrizeSetting;
use App\Mail\DevolutionReturnedToAdministrationMail;
use App\Mail\DevolutionReturnedToEntityManagerMail;
use App\Jobs\ProcessDevolutionDeleteTask;
use App\Jobs\ProcessDevolutionTask;
use App\Models\BackgroundTask;
use App\Services\CommunicationEmailService;
use App\Services\BackgroundTaskService;
use App\Services\EntityLotteryPrizePaymentService;
use App\Services\LegalAcceptanceService;
use App\Services\SellerLiquidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DevolutionsController extends Controller
{
    /**
     * Precio por participación para liquidación: total_participation_amount (jugado+donación) del set.
     */
    private static function pricePerParticipationSet(Set $set): float
    {
        return $set->pricePerParticipation();
    }

    /**
     * Resumen entidad→administración: las digitales disponibles/asignadas del pool
     * se devuelven automáticamente al confirmar, por tanto cuentan como "devueltas" en la UI.
     * "Disponibles" en resumen = solo físicas que aún no se devuelven manualmente.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Participation>  $baseQuery
     * @param  iterable<\App\Models\Participation>  $manualReturnParticipations
     * @return array{returned_participations: int, available_participations: int, returned_digitales_auto: int, returned_fisicas_manual: int}
     */
    private function buildAdministrationSummaryParticipationCounts(
        $baseQuery,
        iterable $manualReturnParticipations = [],
        int $alreadyReturnedInDb = 0
    ): array {
        $manual = collect($manualReturnParticipations);
        $manualIds = $manual->pluck('id')->filter()->values()->all();
        $returnedFisicasManual = $manual->filter(
            fn ($p) => $p->participation_code === null || ! str_starts_with((string) $p->participation_code, '1D/')
        )->count();

        $digitalesAutoQuery = (clone $baseQuery)
            ->whereIn('status', ['disponible', 'asignada'])
            ->whereRaw("participation_code LIKE '1D/%'");
        if ($manualIds !== []) {
            $digitalesAutoQuery->whereNotIn('id', $manualIds);
        }
        $digitalesAutoDevolver = $digitalesAutoQuery->count();

        $disponiblesFisicas = (clone $baseQuery)
            ->whereIn('status', ['disponible', 'asignada'])
            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
            ->count();

        return [
            'returned_participations' => $alreadyReturnedInDb + $returnedFisicasManual + $digitalesAutoDevolver,
            'available_participations' => max(0, $disponiblesFisicas - $returnedFisicasManual),
            'returned_digitales_auto' => $digitalesAutoDevolver,
            'returned_fisicas_manual' => $returnedFisicasManual,
        ];
    }

    /**
     * Determina si el sorteo requiere captura obligatoria de serie/fracción
     * por tener premio especial con serie y fracción.
     */
    private function buildSpecialPrizeRequirement(?int $lotteryId): array
    {
        if (! $lotteryId) {
            return ['required' => false];
        }

        $lottery = Lottery::query()
            ->with(['result', 'lotteryType'])
            ->find($lotteryId);

        if (! $lottery) {
            return ['required' => false];
        }

        $premioEspecial = $lottery->result?->premio_especial;
        $primerPremio = $lottery->result?->primer_premio;

        $specialSerie = null;
        $specialFraccion = null;
        $specialNumero = null;

        // Priorizar premio_especial cuando tenga serie/fracción
        if (is_array($premioEspecial) && !empty($premioEspecial['serie']) && !empty($premioEspecial['fraccion'])) {
            $specialSerie = $premioEspecial['serie'];
            $specialFraccion = $premioEspecial['fraccion'];
            $specialNumero = $premioEspecial['numero'] ?? $premioEspecial['decimo'] ?? null;
        }

        // Fallback: muchos sorteos guardan serie/fracción en primer_premio
        if (($specialSerie === null || $specialFraccion === null) && is_array($primerPremio) && !empty($primerPremio['serie']) && !empty($primerPremio['fraccion'])) {
            $specialSerie = $primerPremio['serie'];
            $specialFraccion = $primerPremio['fraccion'];
            $specialNumero = $specialNumero ?? ($primerPremio['numero'] ?? $primerPremio['decimo'] ?? null);
        }

        $hasSerie = !empty($specialSerie);
        $hasFraccion = !empty($specialFraccion);
        $seriesConfigured = (int) ($lottery->lotteryType?->series ?? 0);

        $required = $hasSerie && $hasFraccion && $seriesConfigured > 0;

        return [
            'required' => $required,
            'max_series' => $seriesConfigured,
            'premio_especial_numero' => $specialNumero,
            'premio_especial_serie' => $specialSerie,
            'premio_especial_fraccion' => $specialFraccion,
        ];
    }

    /**
     * Valida y normaliza la distribución de serie/fracción enviada en liquidación.
     */
    private function validateSpecialPrizeSettlementData(
        $rawSpecialPrizeData,
        array $requirement,
        int $requiredFractions,
        float $totalLiquidation
    ): array {
        $isStrictRequired = (bool) ($requirement['required'] ?? false);

        if (!$isStrictRequired && (!is_array($rawSpecialPrizeData) || empty($rawSpecialPrizeData['assignments']))) {
            return ['valid' => true, 'normalized' => null];
        }

        if (!is_array($rawSpecialPrizeData) || empty($rawSpecialPrizeData['assignments']) || !is_array($rawSpecialPrizeData['assignments'])) {
            return ['valid' => false, 'message' => 'Debes completar la asignación de series/fracciones para el premio especial.'];
        }

        $maxSeries = (int) ($requirement['max_series'] ?? 0);
        $normalizedAssignments = [];
        $used = [];
        $totalAssignedFractions = 0;

        foreach ($rawSpecialPrizeData['assignments'] as $assignment) {
            $serie = (int) ($assignment['serie'] ?? 0);
            $fracciones = $assignment['fracciones'] ?? [];

            if ($serie < 1 || $serie > $maxSeries) {
                return ['valid' => false, 'message' => "La serie {$serie} está fuera de rango. Solo se permiten series entre 1 y {$maxSeries}."];
            }
            if (!is_array($fracciones) || empty($fracciones)) {
                return ['valid' => false, 'message' => "La serie {$serie} no tiene fracciones válidas asignadas."];
            }

            $normalizedFractions = [];
            foreach ($fracciones as $fraction) {
                $fractionInt = (int) $fraction;
                if ($fractionInt < 1 || $fractionInt > 10) {
                    return ['valid' => false, 'message' => "La fracción {$fractionInt} de la serie {$serie} es inválida. Debe estar entre 1 y 10."];
                }

                $key = $serie . '-' . $fractionInt;
                if (isset($used[$key])) {
                    return ['valid' => false, 'message' => "La serie {$serie}, fracción {$fractionInt}, está duplicada en la liquidación."];
                }
                $used[$key] = true;
                $normalizedFractions[] = $fractionInt;
            }

            sort($normalizedFractions);
            $normalizedFractions = array_values(array_unique($normalizedFractions));
            $totalAssignedFractions += count($normalizedFractions);

            $normalizedAssignments[] = [
                'serie' => $serie,
                'fracciones' => $normalizedFractions,
            ];
        }

        usort($normalizedAssignments, fn ($a, $b) => $a['serie'] <=> $b['serie']);

        if ($isStrictRequired) {
            if ($requiredFractions <= 0) {
                return ['valid' => false, 'message' => 'No hay participaciones para liquidar en el premio especial.'];
            }

            if ($totalAssignedFractions !== $requiredFractions) {
                return [
                    'valid' => false,
                    'message' => "La asignación de series/fracciones debe cubrir exactamente {$requiredFractions} décimos. Actualmente llevas {$totalAssignedFractions}.",
                ];
            }
        }

        return [
            'valid' => true,
            'normalized' => [
                'required_fractions' => $requiredFractions,
                'assigned_fractions' => $totalAssignedFractions,
                'max_series' => $maxSeries,
                'premio_especial_numero' => $requirement['premio_especial_numero'] ?? null,
                'premio_especial_serie' => $requirement['premio_especial_serie'] ?? null,
                'premio_especial_fraccion' => $requirement['premio_especial_fraccion'] ?? null,
                'total_liquidation' => round($totalLiquidation, 2),
                'assignments' => $normalizedAssignments,
            ],
        ];
    }

    /**
     * Mutaciones de devoluciones/anulaciones: superadmin, administración (sus entidades) y gestor responsable aceptado.
     * La cuenta de panel de la entidad no (solo consulta en web); el mensaje lo indica si aplica.
     */
    private function devolutionEntityPermissionErrorIfAny(int $entityId): ?\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        if ($user->canManageEntityDevolutions($entityId)) {
            return null;
        }

        $isEntityPanel = $user->isPanelAccount()
            && $user->panel_account_type === 'entity'
            && (int) $user->panel_account_id === $entityId;

        return response()->json([
            'success' => false,
            'message' => $isEntityPanel
                ? 'Las devoluciones y anulaciones las tramita el gestor responsable. Use el usuario personal del responsable (tras aceptar la invitación) o un usuario de su administración.'
                : 'Solo el gestor responsable de la entidad (invitación aceptada) puede tramitar devoluciones y anulaciones.',
        ], 403);
    }

    private function redirectUnlessDevolutionsWebAccess(): ?\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAccessToDevolutionsModule()) {
            return redirect()->route('dashboard')->with(
                'error',
                'No tiene acceso al módulo de devoluciones.'
            );
        }

        return null;
    }

    private function jsonUnlessDevolutionsWebAccess(): ?\Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAccessToDevolutionsModule()) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso al módulo de devoluciones.',
            ], 403);
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Devolution>  $query
     */
    private function scopeDevolutionsToManagedEntities($query): void
    {
        $user = auth()->user();
        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        $ids = $user->devolutionManagedEntityIds();
        if ($ids === null) {
            return;
        }
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn('entity_id', $ids);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($redirect = $this->redirectUnlessDevolutionsWebAccess()) {
            return $redirect;
        }

        $entityFilterIdRaw = $request->query('entity_id');
        $entityFilterId = $entityFilterIdRaw !== null && $entityFilterIdRaw !== ''
            ? (int) $entityFilterIdRaw
            : null;

        $query = Devolution::with(['entity', 'lottery', 'seller', 'user', 'payments'])
            ->forUser(auth()->user());

        if ($entityFilterId !== null) {
            if (! auth()->user()->canManageEntityDevolutions($entityFilterId)) {
                abort(403, 'No tienes permisos para gestionar devoluciones de esta entidad.');
            }

            $query->where('entity_id', $entityFilterId);
        }

        $this->scopeDevolutionsToManagedEntities($query);
        $devolutions = $query
            ->orderBy('devolution_date', 'desc')
            ->orderBy('devolution_time', 'desc')
            ->get();

        return view('devolutions.index', compact('devolutions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($redirect = $this->redirectUnlessDevolutionsWebAccess()) {
            return $redirect;
        }

        if (auth()->user()->isEntityPanelReadOnly()) {
            return redirect()->route('devolutions.index')->with(
                'error',
                'La cuenta de panel de la entidad solo puede consultar devoluciones. Para tramitarlas use el usuario del gestor responsable.'
            );
        }

        $autoSelectEntityId = \App\Support\PanelSelectionResolver::implicitEntityId(auth()->user());

        return view('devolutions.create', compact('autoSelectEntityId'));
    }

    /**
     * Store a newly created resource in storage.
     * Actualizado para soportar devoluciones con o sin vendedor
     */
    public function store(Request $request)
    {
        // Verificar si es una anulación ANTES de iniciar transacción
        if ($request->input('tipo_devolucion') === 'anulacion') {
            return $this->procesarAnulacion($request);
        }

        if ($warning = $this->sellerLiquidationWarningIfAny($request)) {
            return $warning;
        }

        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $forceSync = $request->boolean('force_sync', false);
        // Procesar en background por defecto para evitar bloqueos en UI.
        // Solo ejecutar en síncrono cuando se fuerce explícitamente.
        $runInBackground = !$forceSync;
        if ($runInBackground) {
            if ($resp = $this->validateAdministrationLiquidationLegalRequirements($request)) {
                return $resp;
            }

            $payload = $request->all();
            unset($payload['background']);
            $payload['force_sync'] = true;

            $resourceKey = null;
            if (!empty($payload['set_id'])) {
                $resourceKey = 'set:' . (int) $payload['set_id'];
            } elseif (!empty($payload['entity_id'])) {
                $resourceKey = 'entity:' . (int) $payload['entity_id'];
            }

            $task = app(BackgroundTaskService::class)->createTask(auth()->user(), [
                'type' => BackgroundTask::TYPE_DEVOLUTION,
                'payload' => $payload,
                'entity_id' => isset($payload['entity_id']) ? (int) $payload['entity_id'] : null,
                'set_id' => isset($payload['set_id']) ? (int) $payload['set_id'] : null,
                'resource_key' => $resourceKey,
            ]);

            if ($task->status === BackgroundTask::STATUS_PENDING) {
                ProcessDevolutionTask::dispatch($task->uuid);
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Devolución enviada a segundo plano.',
                'task_uuid' => $task->uuid,
                'status' => $task->status,
                'poll_url' => route('background-tasks.show', ['uuid' => $task->uuid]),
            ]);
        }

        try {
            DB::beginTransaction();

            // Normalizar solo_devolucion para que la validación boolean acepte "true"/"1" del front
            $request->merge([
                'solo_devolucion' => filter_var($request->input('solo_devolucion', false), FILTER_VALIDATE_BOOLEAN)
            ]);

            $data = $request->validate([
                'entity_id' => 'required|exists:entities,id',
                'lottery_id' => 'required|exists:lotteries,id',
                'seller_id' => 'nullable|exists:sellers,id', // Ahora es opcional
                'reserve_id' => 'nullable|exists:reserves,id',
                'set_id' => 'nullable|exists:sets,id', // Para liquidar set completo sin devoluciones
                'solo_devolucion' => 'nullable|boolean', // Solo devolver participaciones, sin liquidar
                'participations' => 'nullable|array',
                'participations.*' => 'nullable|integer|exists:participations,id',
                'return_reason' => 'nullable|string|max:255',
                'liquidacion' => 'required|array',
                'liquidacion.devolver' => 'nullable|array',
                'liquidacion.vender' => 'nullable|array',
                'liquidacion.pagos' => 'nullable|array',
                'liquidacion.pagos.*.payment_method' => 'required_with:liquidacion.pagos.*|string',
                'liquidacion.pagos.*.amount' => 'required_with:liquidacion.pagos.*|numeric',
                'liquidacion.special_prize' => 'nullable|array',
                'liquidacion.special_prize.assignments' => 'nullable|array',
                'prize_payment_mode' => 'nullable|in:presencial,online',
                'online_payer' => 'nullable|in:partilot,entity',
                'confirmacion_liquidacion_definitiva' => 'nullable|string|max:255',
            ]);

            $soloDevolucion = !empty($data['solo_devolucion']);
            $tipoDevolucionEarly = (string) $request->input('tipo_devolucion', 'administracion');
            $requiresPrizePaymentMode = ! $soloDevolucion && $tipoDevolucionEarly !== 'vendedor';

            if ($requiresPrizePaymentMode && empty($data['prize_payment_mode'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Debes seleccionar la modalidad de pago de premios (presencial u online) antes de liquidar.',
                ], 422);
            }

            if ($resp = $this->validateDefinitiveLiquidationPhrase($request, $requiresPrizePaymentMode)) {
                DB::rollBack();

                return $resp;
            }

            if ($soloDevolucion) {
                $devolver = $data['liquidacion']['devolver'] ?? $data['participations'] ?? [];
                if (empty($devolver) || !is_array($devolver)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Selecciona al menos una participación para devolver.'
                    ], 422);
                }
            }

            // Si no es solo devolución: exigir al menos un pago con importe para completar la liquidación
            if (!$soloDevolucion) {
                $pagos = $data['liquidacion']['pagos'] ?? [];
                if (empty($pagos) || !is_array($pagos)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes registrar al menos un pago para completar la liquidación.'
                    ], 422);
                }
                $tieneImporte = collect($pagos)->contains(fn ($p) => (float) ($p['amount'] ?? 0) > 0);
                if (!$tieneImporte) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes registrar al menos un pago con importe.'
                    ], 422);
                }
            }

            if (!auth()->user()->canAccessEntity((int) $data['entity_id'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para gestionar devoluciones de esta entidad'
                ], 403);
            }

            if ($response = $this->devolutionEntityPermissionErrorIfAny((int) $data['entity_id'])) {
                DB::rollBack();

                return $response;
            }

            // VALIDAR: Si hay seller_id, verificar que pertenezca a la entidad
            if (!empty($data['seller_id'])) {
                if (!auth()->user()->canAccessSeller((int) $data['seller_id'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'No tienes permisos para gestionar devoluciones de este vendedor'
                    ], 403);
                }

                $seller = Seller::with('entities')->find($data['seller_id']);
                
                if (!$seller || !$seller->belongsToEntity($data['entity_id'])) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'El vendedor seleccionado no pertenece a la entidad especificada'
                    ], 400);
                }
            }

            // Participaciones a devolver: aceptar liquidacion.devolver o, si no viene, el array participations
            $devolverIds = $data['liquidacion']['devolver'] ?? $data['participations'] ?? [];
            $devolverIds = is_array($devolverIds) ? $devolverIds : [];
            $venderIds = $data['liquidacion']['vender'] ?? [];
            $venderIds = is_array($venderIds) ? $venderIds : [];

            // VALIDAR: Rechazar participaciones anuladas
            $allParticipationIds = array_merge($devolverIds, $venderIds);
            
            if (!empty($allParticipationIds)) {
                $anuladas = Participation::forUser(auth()->user())
                    ->whereIn('id', $allParticipationIds)
                    ->where('status', 'anulada')
                    ->get()
                    ->map(fn ($p) => $p->display_participation_code)
                    ->toArray();
                
                if (!empty($anuladas)) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pueden procesar participaciones anuladas: ' . implode(', ', $anuladas)
                    ], 400);
                }
            }

            // Participaciones a devolver: solo permitir asignada o disponible (nunca vendida ni pagada)
            $requestedToReturn = $devolverIds;
            $allowedToReturn = Participation::forUser(auth()->user())
                ->whereIn('id', $requestedToReturn)
                ->whereIn('status', ['asignada', 'disponible'])
                ->pluck('id')
                ->toArray();
            $vendidasOPagadas = array_diff($requestedToReturn, $allowedToReturn);
            if (!empty($vendidasOPagadas)) {
                $codigos = Participation::forUser(auth()->user())
                    ->whereIn('id', $vendidasOPagadas)
                    ->get()
                    ->map(fn ($p) => $p->display_participation_code)
                    ->toArray();
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden devolver participaciones ya vendidas o pagadas: ' . implode(', ', $codigos)
                ], 400);
            }
            $participationsToReturn = $allowedToReturn;
            $tipoDevolucion = $request->input('tipo_devolucion');

            // Calcular participaciones a vender
            $participationsToSell = [];
            $sellerIdForSell = $data['seller_id'] ?? null;
            if ($tipoDevolucion === 'vendedor' && !empty($participationsToReturn) && $sellerIdForSell) {
                // Devolución vendedor: solo las FÍSICAS no devueltas se marcan como vendida (las digitales no vendidas se devuelven aparte)
                $participations = Participation::forUser(auth()->user())
                    ->whereIn('id', $participationsToReturn)->get();
                $setIds = $participations->pluck('set_id')->unique()->toArray();
                foreach ($setIds as $setId) {
                    $returnedInSet = $participations->where('set_id', $setId)->pluck('id')->toArray();
                    $allSellerInSet = Participation::forUser(auth()->user())
                        ->where('set_id', $setId)
                        ->where('seller_id', $sellerIdForSell)
                        ->where('status', '!=', 'anulada')
                        ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                        ->pluck('id')
                        ->toArray();
                    $toSellInSet = array_values(array_diff($allSellerInSet, $returnedInSet));
                    $participationsToSell = array_merge($participationsToSell, $toSellInSet);
                }
            }
            // Caso 1: No hay participaciones a devolver pero hay set_id (liquidar set completo)
            elseif (empty($participationsToReturn) && isset($data['set_id'])) {
                $setId = $data['set_id'];

                Set::forUser(auth()->user())->findOrFail($setId);
                $query = Participation::forUser(auth()->user())
                    ->where('set_id', $setId)
                    ->whereIn('status', ['disponible', 'asignada', 'vendida'])
                    ->where('status', '!=', 'anulada')
                    ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')");
                if ($tipoDevolucion === 'vendedor' && !empty($data['seller_id'])) {
                    $query->where('seller_id', $data['seller_id']);
                }
                $participationsToSell = $query->pluck('id')->toArray();
            }
            // Caso 1b: No hay participaciones a devolver pero hay reserve_id (liquidar toda la reserva, todos los sets)
            elseif (empty($participationsToReturn) && !empty($data['reserve_id']) && $tipoDevolucion !== 'vendedor') {
                $reserveIdForSell = $data['reserve_id'];
                $reserve = Reserve::forUser(auth()->user())->find($reserveIdForSell);
                if ($reserve && $reserve->lottery_id == ($data['lottery_id'] ?? null)) {
                    $setIdsReserve = Set::forUser(auth()->user())
                        ->where('reserve_id', $reserveIdForSell)
                        ->where('status', 1)
                        ->pluck('id')
                        ->toArray();
                    $queryReserve = Participation::forUser(auth()->user())
                        ->whereIn('set_id', $setIdsReserve)
                        ->whereIn('status', ['disponible', 'asignada'])
                        ->where('status', '!=', 'anulada')
                        ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')");
                    $participationsToSell = $queryReserve->pluck('id')->toArray();
                }
            }
            // Caso 2: Hay participaciones a devolver. Entidad→admin: el resto de la reserva (todos los sets) se marca vendida. Vendedor→entidad: solo se devuelven las seleccionadas.
            elseif (!empty($participationsToReturn) && $tipoDevolucion !== 'vendedor') {
                $participations = Participation::forUser(auth()->user())
                    ->whereIn('id', $participationsToReturn)->get();
                $returnedIds = $participations->pluck('id')->toArray();
                // Si hay reserve_id: considerar todos los sets de la reserva para "vender" el resto (no solo los sets de las devueltas)
                $setIdsToProcess = [];
                if (!empty($data['reserve_id'])) {
                    $setIdsToProcess = Set::forUser(auth()->user())
                        ->where('reserve_id', $data['reserve_id'])
                        ->where('status', 1)
                        ->pluck('id')
                        ->toArray();
                }
                if (empty($setIdsToProcess)) {
                    $setIdsToProcess = $participations->pluck('set_id')->unique()->toArray();
                }
                foreach ($setIdsToProcess as $setId) {
                    $returnedInSet = $participations->where('set_id', $setId)->pluck('id')->toArray();
                    $allInSet = Participation::forUser(auth()->user())
                        ->where('set_id', $setId)
                        ->whereIn('status', ['disponible', 'asignada'])
                        ->where('status', '!=', 'anulada')
                        ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                        ->pluck('id')
                        ->toArray();
                    $toSellInSet = array_diff($allInSet, $returnedInSet);
                    $participationsToSell = array_merge($participationsToSell, $toSellInSet);
                }
            }

            // Vendedor→entidad: las no devueltas se quedan asignadas (no se marcan como vendida)
            if ($tipoDevolucion === 'vendedor') {
                $participationsToSell = [];
            }
            // Solo devolución: no marcar ninguna como vendida ni liquidar
            if ($soloDevolucion) {
                $participationsToSell = [];
            }

            $now = Carbon::now();
            $userId = auth()->id();

            // Calcular total real del set/s y liquidación (misma lógica que getLiquidationSummary)
            $totalParticipations = 0;
            $totalLiquidation = 0.0;

            if (empty($participationsToReturn) && isset($data['set_id'])) {
                $setId = (int) $data['set_id'];
                $set = Set::forUser(auth()->user())->find($setId);
                if ($set) {
                    $baseCount = Participation::forUser(auth()->user())
                        ->where('set_id', $setId)
                        ->where('status', '!=', 'anulada');
                    if ($tipoDevolucion === 'vendedor' && !empty($data['seller_id'])) {
                        $baseCount->where('seller_id', $data['seller_id']);
                    }
                    $totalInSet = $baseCount->count();
                    $price = self::pricePerParticipationSet($set);
                    $totalParticipations = $totalInSet;
                    if ($tipoDevolucion === 'vendedor' && !empty($data['seller_id'])) {
                        $totalLiquidation = $totalInSet * $price;
                    } else {
                        // Entidad→admin: físicas no devueltas + digitales SOLO vendidas
                        $fisicasNoDevueltas = Participation::forUser(auth()->user())
                            ->where('set_id', $setId)
                            ->where('status', '!=', 'anulada')
                            ->where('status', '!=', 'devuelta')
                            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                            ->count();
                        $digitalesVendidas = Participation::forUser(auth()->user())
                            ->where('set_id', $setId)
                            ->sold()
                            ->whereRaw("participation_code LIKE '1D/%'")
                            ->count();
                        $totalLiquidation = ($fisicasNoDevueltas + $digitalesVendidas) * $price;
                    }
                }
            } elseif (empty($participationsToReturn) && !empty($data['reserve_id']) && $tipoDevolucion !== 'vendedor') {
                // Liquidar toda la reserva sin devolver: total y liquidación por todos los sets de la reserva
                $reserveIdCalc = $data['reserve_id'];
                $reserveCalc = Reserve::forUser(auth()->user())->find($reserveIdCalc);
                if ($reserveCalc && $reserveCalc->lottery_id == ($data['lottery_id'] ?? null)) {
                    $setsReserve = Set::forUser(auth()->user())->where('reserve_id', $reserveIdCalc)->where('status', 1)->get();
                    foreach ($setsReserve as $set) {
                        $fisicas = Participation::forUser(auth()->user())
                            ->where('set_id', $set->id)
                            ->whereIn('status', ['disponible', 'asignada'])
                            ->where('status', '!=', 'anulada')
                            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                            ->count();
                        $digitalesVendidas = Participation::forUser(auth()->user())
                            ->where('set_id', $set->id)
                            ->sold()
                            ->whereRaw("participation_code LIKE '1D/%'")
                            ->count();
                        $count = $fisicas + $digitalesVendidas;
                        $price = self::pricePerParticipationSet($set);
                        $totalParticipations += $count;
                        $totalLiquidation += $count * $price;
                    }
                }
            } elseif (!empty($participationsToReturn)) {
                $participations = Participation::forUser(auth()->user())
                    ->whereIn('id', $participationsToReturn)->get();
                $setIds = $participations->pluck('set_id')->unique()->toArray();
                // Entidad→admin con reserve_id: liquidación sobre toda la reserva (todos los sets)
                if ($tipoDevolucion !== 'vendedor' && !empty($data['reserve_id'])) {
                    $setIds = Set::forUser(auth()->user())
                        ->where('reserve_id', $data['reserve_id'])
                        ->where('status', 1)
                        ->pluck('id')
                        ->toArray();
                }
                $sellerIdForCalc = $data['seller_id'] ?? null;
                foreach ($setIds as $setId) {
                    $set = Set::forUser(auth()->user())->find($setId);
                    if (!$set) {
                        continue;
                    }
                    $totalInSet = Participation::forUser(auth()->user())
                        ->where('set_id', $setId)
                        ->where('status', '!=', 'anulada')
                        ->count();
                    $returnedInSet = $participations->where('set_id', $setId)->count();
                    $price = self::pricePerParticipationSet($set);
                    $totalParticipations += $totalInSet;
                    if ($tipoDevolucion === 'vendedor' && $sellerIdForCalc) {
                        $totalSellerInSet = Participation::forUser(auth()->user())
                            ->where('set_id', $setId)
                            ->where('seller_id', $sellerIdForCalc)
                            ->where('status', '!=', 'anulada')
                            ->count();
                        $remainingWithSeller = max(0, $totalSellerInSet - $returnedInSet);
                        $totalLiquidation += $remainingWithSeller * $price;
                    } else {
                        // Entidad→admin: físicas no devueltas + digitales SOLO vendidas (digitales disponibles = devueltas, no cuentan)
                        $fisicasInSet = Participation::forUser(auth()->user())
                            ->where('set_id', $setId)
                            ->where('status', '!=', 'anulada')
                            ->where('status', '!=', 'devuelta')
                            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                            ->count();
                        $returnedFisicasInSet = $participations->where('set_id', $setId)
                            ->filter(fn ($p) => $p->participation_code === null || !str_starts_with((string) $p->participation_code, '1D/'))
                            ->count();
                        $toLiquidateFisicas = max(0, $fisicasInSet - $returnedFisicasInSet);
                        $digitalesVendidasInSet = Participation::forUser(auth()->user())
                            ->where('set_id', $setId)
                            ->sold()
                            ->whereRaw("participation_code LIKE '1D/%'")
                            ->count();
                        $totalLiquidation += ($toLiquidateFisicas + $digitalesVendidasInSet) * $price;
                    }
                }
                if ($tipoDevolucion === 'vendedor') {
                    // En el registro: total participaciones = las que se liquidan (quedan con vendedor); el detalle de devolución ya tiene las devueltas
                    $totalParticipations = (int) round($totalLiquidation / ($price ?? 1)) ?: count($participationsToReturn);
                    if ($sellerIdForCalc && count($setIds) > 0) {
                        $totalParticipations = 0;
                        foreach ($setIds as $setId) {
                            $totalSellerInSet = Participation::forUser(auth()->user())
                                ->where('set_id', $setId)
                                ->where('seller_id', $sellerIdForCalc)
                                ->where('status', '!=', 'anulada')
                                ->count();
                            $returnedInSet = $participations->where('set_id', $setId)->count();
                            $totalParticipations += max(0, $totalSellerInSet - $returnedInSet);
                        }
                    }
                }
            } else {
                // Sin set ni participaciones a devolver: usar conteo de las procesadas
                $totalParticipations = count($participationsToReturn) + count($participationsToSell);
                $totalLiquidation = 0;
            }

            if ($soloDevolucion) {
                $totalLiquidation = 0;
            }

            $specialPrizeRequirement = $this->buildSpecialPrizeRequirement((int) $data['lottery_id']);
            $specialPrizeSettlement = null;
            if (!$soloDevolucion) {
                $specialPrizeRaw = $data['liquidacion']['special_prize'] ?? null;
                $shouldValidateSpecialPrize = ($specialPrizeRequirement['required'] ?? false)
                    || (is_array($specialPrizeRaw) && !empty($specialPrizeRaw['assignments']));

                // La referencia para serie/fracción debe ser la cantidad disponible a liquidar,
                // no el total histórico ni las participaciones ya devueltas.
                $requiredFractionsForSpecialPrize = count($participationsToSell);
                if ($requiredFractionsForSpecialPrize <= 0) {
                    $requiredFractionsForSpecialPrize = (int) $totalParticipations;
                }

                if ($shouldValidateSpecialPrize) {
                    $specialPrizeValidation = $this->validateSpecialPrizeSettlementData(
                        $specialPrizeRaw,
                        $specialPrizeRequirement,
                        (int) $requiredFractionsForSpecialPrize,
                        (float) $totalLiquidation
                    );
                    if (!$specialPrizeValidation['valid']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $specialPrizeValidation['message'] ?? 'Debes completar la asignación del premio especial para continuar.',
                        ], 422);
                    }
                    $specialPrizeSettlement = $specialPrizeValidation['normalized'];
                }
            }

            // Crear registro de devolución
            $devolution = Devolution::create([
                'entity_id' => $data['entity_id'],
                'lottery_id' => $data['lottery_id'],
                'seller_id' => $data['seller_id'] ?? null,
                'user_id' => $userId,
                'total_participations' => $totalParticipations,
                'total_liquidation' => $totalLiquidation,
                'return_reason' => $data['return_reason'] ?? 'Devolución de entidad a administración',
                'devolution_date' => $now->format('Y-m-d'),
                'devolution_time' => $now->format('H:i:s'),
                'status' => 'procesada',
                'notes' => 'Devolución procesada automáticamente',
                'special_prize_settlement' => $specialPrizeSettlement,
            ]);

            // Procesar participaciones a devolver
            if (!empty($participationsToReturn)) {
                // USAR MODELO ELOQUENT para disparar el Observer
                $participationsToUpdateReturn = Participation::forUser(auth()->user())
                    ->whereIn('id', $participationsToReturn)->get();

                foreach ($participationsToUpdateReturn as $participation) {
                    if ($tipoDevolucion === 'vendedor' && !empty($data['seller_id'])) {
                        // Devolución de vendedor a entidad: dejar la participación disponible y sin vendedor
                        $participation->update([
                            'status' => 'disponible',
                            'seller_id' => null,
                            'return_date' => $now->format('Y-m-d'),
                            'return_time' => $now->format('H:i:s'),
                            'return_reason' => $data['return_reason'] ?? 'Devolución de vendedor a entidad',
                            'returned_by' => $userId,
                        ]);

                        DevolutionDetail::create([
                            'devolution_id' => $devolution->id,
                            'participation_id' => $participation->id,
                            'action' => 'devolver_vendedor'
                        ]);
                    } else {
                        // Devolución de entidad a administración (comportamiento existente)
                        $participation->update([
                            'status' => 'devuelta',
                            'return_date' => $now->format('Y-m-d'),
                            'return_time' => $now->format('H:i:s'),
                            'return_reason' => $data['return_reason'] ?? 'Devolución de entidad a administración',
                            'returned_by' => $userId,
                        ]);

                        // Crear detalle de devolución
                        DevolutionDetail::create([
                            'devolution_id' => $devolution->id,
                            'participation_id' => $participation->id,
                            'action' => 'devolver'
                        ]);
                    }
                }
            }

            // Devolución entidad→administración: participaciones DIGITALES no vendidas de la reserva se marcan como devueltas
            $reserveIdForDigital = $data['reserve_id'] ?? null;
            if ($reserveIdForDigital === null && !empty($participationsToReturn)) {
                $firstSetId = Participation::forUser(auth()->user())->whereIn('id', $participationsToReturn)->value('set_id');
                if ($firstSetId) {
                    $reserveIdForDigital = Set::forUser(auth()->user())->find($firstSetId)?->reserve_id;
                }
            }
            if ($reserveIdForDigital === null && !empty($data['set_id'])) {
                $reserveIdForDigital = Set::forUser(auth()->user())->find((int) $data['set_id'])?->reserve_id;
            }
            if ($tipoDevolucion !== 'vendedor' && $reserveIdForDigital && !empty($data['entity_id']) && !empty($data['lottery_id'])) {
                $setIdsReserve = Set::forUser(auth()->user())
                    ->where('reserve_id', $reserveIdForDigital)
                    ->where('status', 1)
                    ->pluck('id')
                    ->toArray();
                if (!empty($setIdsReserve)) {
                    $digitalEntityToReturn = Participation::forUser(auth()->user())
                        ->whereIn('set_id', $setIdsReserve)
                        ->where('entity_id', $data['entity_id'])
                        ->whereRaw("participation_code LIKE '1D/%'")
                        ->whereIn('status', ['asignada', 'disponible'])
                        ->where('status', '!=', 'anulada')
                        ->pluck('id')
                        ->toArray();
                    $digitalEntityToReturn = array_diff($digitalEntityToReturn, $participationsToReturn ?? []);
                    if (!empty($digitalEntityToReturn)) {
                        $digitalEntityUpdate = Participation::forUser(auth()->user())->whereIn('id', $digitalEntityToReturn)->get();
                        foreach ($digitalEntityUpdate as $participation) {
                            $participation->update([
                                'status' => 'devuelta',
                                'return_date' => $now->format('Y-m-d'),
                                'return_time' => $now->format('H:i:s'),
                                'return_reason' => $data['return_reason'] ?? 'Devolución de entidad a administración (digitales no vendidas)',
                                'returned_by' => $userId,
                            ]);
                            DevolutionDetail::create([
                                'devolution_id' => $devolution->id,
                                'participation_id' => $participation->id,
                                'action' => 'devolver',
                            ]);
                        }
                    }
                }
            }

            // Devolución vendedor: participaciones DIGITALES no vendidas de ese vendedor se marcan como devueltas (no se liquidan)
            $digitalToReturnIds = [];
            if ($tipoDevolucion === 'vendedor' && $sellerIdForSell && !empty($data['entity_id']) && !empty($data['lottery_id'])) {
                $digitalToReturnIds = Participation::forUser(auth()->user())
                    ->where('seller_id', $sellerIdForSell)
                    ->where('entity_id', $data['entity_id'])
                    ->whereRaw("participation_code LIKE '1D/%'")
                    ->whereIn('status', ['asignada', 'disponible'])
                    ->where('status', '!=', 'anulada')
                    ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $data['lottery_id']))
                    ->pluck('id')
                    ->toArray();
            }
            if (!empty($digitalToReturnIds)) {
                $digitalToUpdate = Participation::forUser(auth()->user())->whereIn('id', $digitalToReturnIds)->get();
                foreach ($digitalToUpdate as $participation) {
                    $participation->update([
                        'status' => 'disponible',
                        'seller_id' => null,
                        'return_date' => $now->format('Y-m-d'),
                        'return_time' => $now->format('H:i:s'),
                        'return_reason' => $data['return_reason'] ?? 'Devolución de participaciones digitales no vendidas',
                        'returned_by' => $userId,
                    ]);
                    DevolutionDetail::create([
                        'devolution_id' => $devolution->id,
                        'participation_id' => $participation->id,
                        'action' => 'devolver_vendedor',
                    ]);
                }
            }

            // Procesar participaciones a vender (solo FÍSICAS no devueltas; las digitales no vendidas ya se devolvieron arriba)
            if (!empty($participationsToSell)) {
                // USAR MODELO ELOQUENT para disparar el Observer
                $participationsToUpdateSell = Participation::with('set')
                    ->forUser(auth()->user())
                    ->whereIn('id', $participationsToSell)->get();
                
                foreach ($participationsToUpdateSell as $participation) {
                    $saleAmount = $participation->set ? self::pricePerParticipationSet($participation->set) : 0;
                    // No limpiar buyer_name ni datos del comprador si ya tienen valor (digitalización previa)
                    $participation->update([
                        'status' => 'vendida',
                        'sale_date' => $now->format('Y-m-d'),
                        'sale_time' => $now->format('H:i:s'),
                        'sale_amount' => $saleAmount,
                        'notes' => 'Liquidación automática',
                    ]);

                    DevolutionDetail::create([
                        'devolution_id' => $devolution->id,
                        'participation_id' => $participation->id,
                        'action' => 'vender'
                    ]);
                }
            }

            // Crear registros de pago múltiples si se proporcionaron
            if (isset($data['liquidacion']['pagos']) && !empty($data['liquidacion']['pagos'])) {
                foreach ($data['liquidacion']['pagos'] as $pago) {
                    DevolutionPayment::create([
                        'devolution_id' => $devolution->id,
                        'amount' => $pago['amount'],
                        'payment_method' => $pago['payment_method'],
                        'from_number' => null,
                        'notes' => 'Pago de liquidación - ' . ucfirst($pago['payment_method']),
                        'payment_date' => $now
                    ]);
                }
            }

            if ($requiresPrizePaymentMode && ! empty($data['prize_payment_mode'])) {
                $onlinePayer = ($data['prize_payment_mode'] ?? '') === 'online'
                    ? ($data['online_payer'] ?? EntityLotteryPrizeSetting::PAYER_PARTILOT)
                    : null;

                app(EntityLotteryPrizePaymentService::class)->lockModeFromDevolution(
                    (int) $data['entity_id'],
                    (int) $data['lottery_id'],
                    (string) $data['prize_payment_mode'],
                    (int) $userId,
                    $onlinePayer
                );

                $entity = Entity::find($data['entity_id']);
                app(LegalAcceptanceService::class)->recordDefinitiveLiquidationConfirmation(
                    auth()->user(),
                    $request,
                    [
                        'entity_id' => (int) $data['entity_id'],
                        'lottery_id' => (int) $data['lottery_id'],
                        'devolution_id' => $devolution->id,
                        'gestor_responsable_id' => $userId,
                        'texto_confirmacion' => trim((string) $request->input('confirmacion_liquidacion_definitiva', '')),
                        'participaciones_devueltas' => $totalParticipations,
                        'importe_total' => $totalLiquidation,
                    ],
                    $entity?->administration_id ? (int) $entity->administration_id : null
                );
            }

            DB::commit();

            // Comunicación pendiente (según especificación):
            // "Transmisión de devolución a la administración" con email a gestor principal de:
            // - administración (panel)
            // - entidad
            try {
                $hasReturnedToAdministration = DevolutionDetail::query()
                    ->where('devolution_id', $devolution->id)
                    ->where('action', 'devolver')
                    ->exists();

                $hasReturnedToEntity = DevolutionDetail::query()
                    ->where('devolution_id', $devolution->id)
                    ->whereIn('action', ['devolver', 'devolver_vendedor'])
                    ->exists();

                if ($hasReturnedToAdministration || $hasReturnedToEntity) {
                    $devolution->loadMissing(['entity.administration', 'entity.manager.user', 'lottery']);

                    $entityManagerUser = $devolution->entity?->manager?->user;
                    $admin = $devolution->entity?->administration;

                    $adminManagerUser = null;
                    if ($admin) {
                        $adminManagerUser = \App\Models\Manager::query()
                            ->where('administration_id', $admin->id)
                            ->where('is_primary', true)
                            ->with('user')
                            ->first()?->user;
                    }

                    $communicationEmailService = app(CommunicationEmailService::class);

                    if ($adminManagerUser && !empty($adminManagerUser->email)) {
                        $communicationEmailService->sendAndLog(
                            recipientEmail: (string) $adminManagerUser->email,
                            recipientRole: 'gestor_administracion',
                            recipientUser: $adminManagerUser,
                            messageType: 'devolution_return_to_administration',
                            templateKey: null,
                            mailClass: DevolutionReturnedToAdministrationMail::class,
                            mailPayload: ['devolution_id' => $devolution->id],
                            context: ['devolution_id' => $devolution->id, 'entity_id' => $devolution->entity_id],
                        );
                    }

                    if ($entityManagerUser && !empty($entityManagerUser->email) && $hasReturnedToEntity) {
                        $communicationEmailService->sendAndLog(
                            recipientEmail: (string) $entityManagerUser->email,
                            recipientRole: 'gestor_entidad',
                            recipientUser: $entityManagerUser,
                            messageType: 'devolution_return_to_entity',
                            templateKey: null,
                            mailClass: DevolutionReturnedToEntityManagerMail::class,
                            mailPayload: ['devolution_id' => $devolution->id],
                            context: ['devolution_id' => $devolution->id, 'entity_id' => $devolution->entity_id],
                        );
                    }
                }
            } catch (\Throwable $e) {
                // No bloquear la devolución si fallan los emails
                \Log::warning('Fallo enviando emails devolución: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Devolución procesada correctamente',
                'devolution_id' => $devolution->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la devolución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if ($redirect = $this->redirectUnlessDevolutionsWebAccess()) {
            return $redirect;
        }

        $devolution = Devolution::with([
            'entity',
            'lottery',
            'seller',
            'user',
            'details.participation.set',
            'payments',
        ])
            ->forUser(auth()->user())
            ->findOrFail($id);

        if (! auth()->user()->canViewDevolutionForEntity((int) $devolution->entity_id)) {
            return redirect()->route('dashboard')->with(
                'error',
                'No puede ver esta devolución.'
            );
        }

        return view('devolutions.show', compact('devolution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        if ($redirect = $this->redirectUnlessDevolutionsWebAccess()) {
            return $redirect;
        }

        $devolution = Devolution::with(['entity', 'lottery', 'seller', 'user', 'details.participation.set', 'payments'])
            ->forUser(auth()->user())
            ->findOrFail($id);

        if (auth()->user()->isEntityPanelReadOnly()) {
            return redirect()->route('devolutions.show', $id);
        }

        if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
            return redirect()->route('dashboard')->with(
                'error',
                'No puede editar esta devolución. Solo el gestor responsable o la administración pueden acceder.'
            );
        }

        return view('devolutions.edit', compact('devolution'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Implementar actualización de devolución
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $forceSync = $request->boolean('force_sync', false);
        if (! $forceSync) {
            $devolution = Devolution::with('details')
                ->forUser(auth()->user())
                ->findOrFail($id);

            if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para eliminar esta devolución.',
                ], 403);
            }

            $task = app(BackgroundTaskService::class)->createTask(auth()->user(), [
                'type' => BackgroundTask::TYPE_DEVOLUTION_DELETE,
                'payload' => [
                    'devolution_id' => (int) $id,
                    'entity_id' => (int) $devolution->entity_id,
                ],
                'entity_id' => (int) $devolution->entity_id,
                'resource_key' => 'devolution_delete:'.$id,
            ]);

            if ($task->status === BackgroundTask::STATUS_PENDING) {
                ProcessDevolutionDeleteTask::dispatch($task->uuid);
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Eliminación enviada a segundo plano.',
                'task_uuid' => $task->uuid,
                'poll_url' => route('background-tasks.show', ['uuid' => $task->uuid]),
            ]);
        }

        try {
            DB::beginTransaction();

            $devolution = Devolution::with('details')
                ->forUser(auth()->user())
                ->findOrFail($id);

            if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permiso para eliminar esta devolución.',
                ], 403);
            }

            // Revertir cada participación según el tipo de acción y devolution.seller_id (sin nuevos campos)
            // Si la devolución tiene seller_id → participaciones asignadas a ese vendedor (asignada); si no → disponible
            $sellerId = $devolution->seller_id;

            foreach ($devolution->details as $detail) {
                $participation = Participation::forUser(auth()->user())->find($detail->participation_id);
                if (!$participation) {
                    continue;
                }

                $updateData = [
                    'return_date' => null,
                    'return_time' => null,
                    'return_reason' => null,
                    'returned_by' => null,
                ];

                if ($detail->action === 'devolver_vendedor') {
                    // Restaurar: estaba asignada al vendedor de la devolución
                    $updateData['status'] = $sellerId ? 'asignada' : 'disponible';
                    $updateData['seller_id'] = $sellerId;
                } elseif ($detail->action === 'devolver') {
                    // Entidad→administración: quedan disponibles sin vendedor
                    $updateData['status'] = 'disponible';
                    $updateData['seller_id'] = null;
                } else {
                    // action === 'vender': deshacer liquidación; si la devolución es de vendedor, quedan asignadas a él
                    $updateData['status'] = $sellerId ? 'asignada' : 'disponible';
                    $updateData['seller_id'] = $sellerId;
                    $updateData['sale_date'] = null;
                    $updateData['sale_time'] = null;
                    $updateData['sale_amount'] = null;
                    $updateData['notes'] = null;
                    // No tocar buyer_name, buyer_phone, buyer_email, buyer_nif (pueden estar digitalizados)
                }

                $participation->update($updateData);
            }

            // Eliminar detalles de devolución
            $devolution->details()->delete();

            // Eliminar la devolución
            $devolution->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Devolución eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la devolución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Listado de devoluciones (JSON)
     */
    public function apiIndex()
    {
        return $this->data();
    }

    /**
     * API: Crear devolución (delega en store; store ya devuelve JSON)
     */
    public function apiStore(Request $request)
    {
        return $this->store($request);
    }

    /**
     * API: Ver una devolución (JSON)
     */
    public function apiShow(string $id)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $devolution = Devolution::with([
            'entity',
            'lottery',
            'seller',
            'user',
            'details.participation.set',
            'payments',
        ])
            ->forUser(auth()->user())
            ->findOrFail($id);

        if (! auth()->user()->canViewDevolutionForEntity((int) $devolution->entity_id)) {
            return response()->json(['success' => false, 'message' => 'Sin permiso para esta devolución.'], 403);
        }

        return response()->json([
            'success' => true,
            'devolution' => $devolution,
        ]);
    }

    /**
     * API: Actualizar devolución (JSON)
     */
    public function apiUpdate(Request $request, string $id)
    {
        $this->update($request, $id);
        return response()->json(['success' => true, 'message' => 'Actualizado']);
    }

    /**
     * API: Eliminar devolución (delega en destroy; destroy ya devuelve JSON)
     */
    public function apiDestroy(Request $request, string $id)
    {
        return $this->destroy($request, $id);
    }

    /**
     * Obtener datos para la tabla de devoluciones (DataTable)
     */
    public function data()
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $query = Devolution::with(['entity', 'lottery', 'seller', 'user', 'details', 'payments'])
            ->forUser(auth()->user());
        $this->scopeDevolutionsToManagedEntities($query);

        $devolutions = $query
            ->select([
                'devolutions.id',
                'devolutions.entity_id',
                'devolutions.lottery_id',
                'devolutions.seller_id',
                'devolutions.user_id',
                'devolutions.total_participations',
                'devolutions.total_liquidation',
                'devolutions.return_reason',
                'devolutions.devolution_date',
                'devolutions.devolution_time',
                'devolutions.status',
                'devolutions.notes',
                'devolutions.created_at',
                'devolutions.updated_at',
            ])
            ->orderBy('devolutions.created_at', 'desc')
            ->get()
            ->map(function ($devolution) {
                // Contar participaciones devueltas vs vendidas
                $returnedCount = $devolution->details()->where('action', 'devolver')->count();
                $soldCount = $devolution->details()->where('action', 'vender')->count();
                
                // Total liquidación: usar valor guardado si existe, si no calcular desde detalles
                $totalLiquidation = $devolution->total_liquidation !== null
                    ? (float) $devolution->total_liquidation
                    : 0;
                if ($totalLiquidation == 0 && $soldCount > 0) {
                    $soldParticipations = $devolution->details()->where('action', 'vender')->with('participation.set')->get();
                    foreach ($soldParticipations as $detail) {
                        if ($detail->participation && $detail->participation->set) {
                            $totalLiquidation += $detail->participation->set ? self::pricePerParticipationSet($detail->participation->set) : 0;
                        }
                    }
                }
                $totalPayments = 0;
                
                // Calcular pagos registrados (suma de amount en pagos de la devolución)
                $totalPayments = $devolution->payments()->sum('amount') ?? 0;

                return [
                    'id' => $devolution->id,
                    'entity_name' => $devolution->entity->name ?? 'N/A',
                    'lottery_name' => $devolution->lottery->name ?? 'N/A',
                    'total_participations' => $devolution->total_participations,
                    'returned_participations' => $returnedCount,
                    'return_date' => $devolution->devolution_date ? Carbon::parse($devolution->devolution_date)->format('d/m/Y') : '-',
                    'total_liquidation' => number_format($totalLiquidation, 2),
                    'total_payments' => number_format($totalPayments, 2),
                    'user_name' => $devolution->user->name ?? 'N/A',
                    'actions' => $this->generateActions($devolution->id)
                ];
            });

        return response()->json([
            'data' => $devolutions
        ]);
    }

    /**
     * Obtener entidades disponibles.
     * Si el usuario es gestor (tiene registros en managers), solo devuelve entidades donde es gestor.
     * Así en devoluciones solo puede devolver participaciones de sus entidades gestionadas.
     */
    public function getEntities()
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $user = auth()->user();
        // Superadmin y administración ven todas sus entidades accesibles; el resto prioriza managers (gestor).
        if ($user->isSuperAdmin() || $user->isAdministration()) {
            $entityIds = $user->accessibleEntityIds();
        } else {
            $entityIds = $user->getManagerEntityIds();
            if (empty($entityIds)) {
                $entityIds = $user->accessibleEntityIds();
            }
        }
        $entityIds = array_values(array_filter(
            $entityIds,
            fn ($id) => $user->canViewDevolutionForEntity((int) $id)
        ));
        if (empty($entityIds)) {
            return response()->json(['success' => true, 'entities' => []]);
        }

        $query = Entity::with('administration')->whereIn('id', $entityIds);
        // En la app (API) solo se devuelven entidades activas; en la web se devuelven todas para mostrarlas y bloquear las inactivas
        if (request()->is('api/*')) {
            $query->where('status', 1);
        }
        $entities = $query->get()
            ->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'image' => $entity->image,
                    'province' => $entity->province ?? 'N/A',
                    'city' => $entity->city ?? 'N/A',
                    'administration_name' => $entity->administration->name ?? 'Sin administración',
                    'status' => $entity->status == 1 ? 'activo' : ($entity->status == 0 ? 'inactivo' : 'pendiente'),
                ];
            });

        return response()->json([
            'success' => true,
            'entities' => $entities,
        ]);
    }

    /**
     * Obtener sorteos de una entidad
     */
    public function getLotteriesByEntity(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $entityId = $request->get('entity_id');
        if (! auth()->user()->canAccessEntity((int) $entityId)
            || ! auth()->user()->canViewDevolutionForEntity((int) $entityId)) {
            return response()->json([
                'success' => true,
                'lotteries' => collect(),
            ]);
        }

        $lotteries = Lottery::select(['lotteries.id', 'lotteries.name', 'lotteries.description', 'lotteries.draw_date', 'lotteries.image'])
            ->join('reserves', 'lotteries.id', '=', 'reserves.lottery_id')
            ->where('reserves.entity_id', $entityId)
            ->distinct()
            ->get();

        return response()->json([
            'success' => true,
            'lotteries' => $lotteries
        ]);
    }

    /**
     * Obtener vendedores de una entidad
     */
    public function getSellersByEntity(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $entityId = $request->get('entity_id');
        if (! auth()->user()->canAccessEntity((int) $entityId)
            || ! auth()->user()->canViewDevolutionForEntity((int) $entityId)) {
            return response()->json([
                'success' => true,
                'sellers' => collect(),
            ]);
        }
        
        // Ahora usamos la relación many-to-many. Mostrar Activo, Pendiente y Bloqueado (solo ocultar Inactivo)
        $sellers = Seller::with(['user:id,name,last_name,email,phone,image'])
            ->forUser(auth()->user())
            ->whereHas('entities', function($query) use ($entityId) {
                $query->where('entities.id', $entityId);
            })
            ->whereIn('status', [\App\Models\Seller::STATUS_ACTIVE/*, \App\Models\Seller::STATUS_PENDING, \App\Models\Seller::STATUS_BLOCKED*/])
            ->get()
            ->map(function($seller) {
                $statusMap = [
                    \App\Models\Seller::STATUS_ACTIVE => 'active',
                    \App\Models\Seller::STATUS_PENDING => 'pending',
                    \App\Models\Seller::STATUS_BLOCKED => 'blocked',
                ];
                $status = $statusMap[(int) $seller->status] ?? 'inactive';
                return [
                    'id' => $seller->id,
                    'user_id' => $seller->user_id,
                    'seller_type' => $seller->seller_type,
                    'status' => $status,
                    'user' => [
                        'id' => $seller->user ? $seller->user->id : null,
                        'name' => $seller->display_name,
                        'last_name' => $seller->display_last_name,
                        'email' => $seller->display_email,
                        'phone' => $seller->display_phone,
                        'image' => $seller->user ? $seller->user->image : null,
                    ],
                    'image' => $seller->user ? $seller->user->image : null,
                ];
            });

        return response()->json([
            'success' => true,
            'sellers' => $sellers
        ]);
    }

    /**
     * Obtener sets por vendedor y sorteo (legacy - para devoluciones de vendedores)
     */
    public function getSetsBySellerAndLottery(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $sellerId = $request->get('seller_id');
        $lotteryId = $request->get('lottery_id');

        if (! auth()->user()->canAccessSeller((int) $sellerId)) {
            return response()->json([]);
        }

        $sets = Set::with(['reserve'])
            ->forUser(auth()->user())
            ->whereHas('reserve', function($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId);
            })
            ->whereHas('participations', function($query) use ($sellerId) {
                $query->where('seller_id', $sellerId)
                      ->where('status', 'asignada');
            })
            ->select([
                'sets.id',
                'sets.set_name',
                'sets.set_number',
                'sets.reserve_id'
            ])
            ->orderBy('sets.set_number')
            ->get();

        return response()->json($sets);
    }

    /**
     * Obtener sets por entidad y sorteo (para devoluciones de entidad)
     * Solo muestra sets que tienen participaciones sin devolver
     */
    public function getSetsByEntityAndLottery(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $entityId = $request->get('entity_id');
        $lotteryId = $request->get('lottery_id');

        if (! auth()->user()->canAccessEntity((int) $entityId)
            || ! auth()->user()->canViewDevolutionForEntity((int) $entityId)) {
            return response()->json([
                'success' => true,
                'sets' => collect(),
            ]);
        }

        // Solo sets FÍSICOS (asignación: no se asignan sets digitales; los digitales se venden en pool por entidad)
        $sets = Set::with(['reserve.lottery:id,name'])
            ->forUser(auth()->user())
            ->where('entity_id', $entityId)
            ->where('sets.physical_participations', '>', 0)
            ->whereHas('reserve', function($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId);
            })
            ->whereHas('participations', function($query) {
                $query->whereIn('status', ['disponible', 'asignada'])
                      ->where('status', '!=', 'anulada');
            })
            ->select([
                'sets.id',
                'sets.set_name',
                'sets.set_number',
                'sets.reserve_id',
                'sets.digital_participations',
                'sets.physical_participations'
            ])
            ->orderBy('sets.set_number')
            ->get();

        return response()->json([
            'success' => true,
            'sets' => $sets
        ]);
    }

    /**
     * Obtener reservas por entidad y sorteo (para asignación/devolución por reserva).
     * Si solo hay una reserva, el front puede preseleccionarla.
     * Opcional: seller_id para devolución vendedor→entidad (solo reservas con participaciones de ese vendedor).
     */
    public function getReservesByEntityAndLottery(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $entityId = $request->get('entity_id');
        $lotteryId = $request->get('lottery_id');
        $sellerId = $request->get('seller_id');

        if (! $entityId || ! $lotteryId) {
            return response()->json(['success' => true, 'reserves' => []]);
        }

        if (! auth()->user()->canAccessEntity((int) $entityId)
            || ! auth()->user()->canViewDevolutionForEntity((int) $entityId)) {
            return response()->json(['success' => true, 'reserves' => []]);
        }

        if ($sellerId && !auth()->user()->canAccessSeller((int) $sellerId)) {
            return response()->json(['success' => true, 'reserves' => []]);
        }

        $reservesQuery = Reserve::forUser(auth()->user())
            ->where('entity_id', $entityId)
            ->where('lottery_id', $lotteryId)
            ->whereHas('sets', function ($q) use ($sellerId) {
                $q->whereHas('participations', function ($p) use ($sellerId) {
                    $p->whereIn('status', ['disponible', 'asignada'])
                      ->where('status', '!=', 'anulada');
                    if ($sellerId) {
                        $p->where('seller_id', $sellerId);
                    }
                });
            })
            ->orderBy('id');

        $reserves = $reservesQuery->get()->map(function ($reserve) {
            $nums = $reserve->reservation_numbers;
            if (is_array($nums) && count($nums) > 0) {
                $label = implode(' - ', $nums);
            } else {
                $label = 'Reserva #' . str_pad((string) $reserve->id, 5, '0', STR_PAD_LEFT);
            }
            return [
                'id' => $reserve->id,
                'reservation_numbers' => $reserve->reservation_numbers,
                'display_label' => $label,
            ];
        });

        return response()->json([
            'success' => true,
            'reserves' => $reserves,
        ]);
    }

    /**
     * Rangos de participaciones disponibles para devolver en una reserva (numeración global de la reserva).
     * Para devolución vendedor: participaciones asignadas a ese vendedor en la reserva.
     * Sin seller_id: no se devuelven rangos (o se podrían devolver participaciones disponibles de la entidad).
     */
    public function getAvailableRangesForReserve(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $reserveId = $request->get('reserve_id');
        $sellerId = $request->get('seller_id');

        if (! $reserveId) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }

        $reserve = Reserve::forUser(auth()->user())->find($reserveId);
        if (! $reserve) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }

        if (! auth()->user()->canViewDevolutionForEntity((int) $reserve->entity_id)) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }

        if ($sellerId && !auth()->user()->canAccessSeller((int) $sellerId)) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }

        $sets = $reserve->sets()->orderBy('set_number')->orderBy('id')->get();
        if ($sets->isEmpty()) {
            return response()->json(['success' => true, 'available_ranges' => []]);
        }

        $offsetBySet = [];
        $running = 1;
        foreach ($sets as $set) {
            $offsetBySet[$set->id] = $running;
            $running += (int) ($set->total_participations ?? 0);
        }

        $query = Participation::query()
            ->whereIn('set_id', $sets->pluck('id'))
            ->where('status', '!=', 'anulada');

        if ($sellerId) {
            $query->where('seller_id', $sellerId)->where('status', 'asignada');
        } else {
            $query->where('status', 'disponible')->whereNull('seller_id');
        }

        $numeros = $query->get()->map(function ($p) use ($offsetBySet) {
            $offset = $offsetBySet[$p->set_id] ?? 0;
            return $offset + (int) $p->participation_number;
        })->sort()->values()->toArray();

        $ranges = [];
        foreach ($numeros as $num) {
            $n = (int) $num;
            if (empty($ranges) || $n > $ranges[count($ranges) - 1][1] + 1) {
                $ranges[] = [$n, $n];
            } else {
                $ranges[count($ranges) - 1][1] = $n;
            }
        }

        return response()->json([
            'success' => true,
            'available_ranges' => $ranges,
        ]);
    }

    /**
     * Obtener participaciones de un vendedor en un sorteo
     */
    public function getParticipationsBySellerAndLottery(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $sellerId = $request->get('seller_id');
        $lotteryId = $request->get('lottery_id');

        if (! auth()->user()->canAccessSeller((int) $sellerId)) {
            return response()->json([
                'success' => true,
                'participations' => collect()
            ]);
        }
        
        $query = Participation::select([
                'participations.id',
                'participations.number',
                'participations.participation_code',
                'participations.sale_date',
                'participations.sale_time',
                'participations.return_date',
                'participations.return_time'
            ])
            ->join('sets', 'participations.set_id', '=', 'sets.id')
            ->join('reserves', 'sets.reserve_id', '=', 'reserves.id')
            ->where('participations.seller_id', $sellerId)
            ->where('reserves.lottery_id', $lotteryId)
            ->where(function($q) {
                $q->whereNull('participations.sale_date')
                  ->whereNull('participations.return_date');
            });

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $entityIds = $user->accessibleEntityIds();
            $sellerIds = $user->accessibleSellerIds();
            if (empty($entityIds) && empty($sellerIds)) {
                return response()->json(['success' => true, 'participations' => collect()]);
            }
            $query->where(function ($q) use ($entityIds, $sellerIds) {
                if (!empty($entityIds)) {
                    $q->whereIn('participations.entity_id', $entityIds);
                }
                if (!empty($sellerIds)) {
                    $q->orWhereIn('participations.seller_id', $sellerIds);
                }
            });
        }

        $participations = $query->get();

        return response()->json([
            'success' => true,
            'participations' => $participations
        ]);
    }

    /**
     * Validar participaciones disponibles
     * Actualizado para soportar devoluciones sin vendedor específico
     */
    public function validateParticipations(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $data = $request->validate([
            'seller_id' => 'nullable|exists:sellers,id', // Ahora es opcional
            'entity_id' => 'nullable|exists:entities,id', // Para devoluciones de entidad
            'lottery_id' => 'required|exists:lotteries,id',
            'set_id' => 'nullable|exists:sets,id', // Opcional si se envía reserve_id o referencia
            'reserve_id' => 'nullable|exists:reserves,id', // Alternativa a set_id: participaciones de toda la reserva
            'desde' => 'nullable|integer',
            'hasta' => 'nullable|integer',
            'participation_id' => 'nullable|integer', // Número de participación, no ID de base de datos
            'referencia' => 'nullable|string' // QR: referencia para resolver set + participación
        ]);

        if (!empty($data['seller_id']) && !auth()->user()->canAccessSeller((int) $data['seller_id'])) {
            return response()->json([
                'success' => true,
                'participations' => collect()
            ]);
        }

        if (! empty($data['entity_id']) && (! auth()->user()->canAccessEntity((int) $data['entity_id'])
            || ! auth()->user()->canManageEntityDevolutions((int) $data['entity_id']))) {
            return response()->json([
                'success' => true,
                'participations' => collect(),
            ]);
        }

        // Si viene referencia (QR), resolver y devolver esa participación si pertenece al sorteo/entidad
        if (!empty($data['referencia'])) {
            return $this->validateByReference(
                $data['referencia'],
                (int) ($data['entity_id'] ?? 0),
                (int) $data['lottery_id']
            );
        }

        if (empty($data['set_id']) && empty($data['reserve_id'])) {
            return response()->json(['success' => false, 'message' => 'Falta set_id, reserve_id o referencia.'], 422);
        }

        if (! empty($data['set_id'])) {
            $setForPerm = Set::forUser(auth()->user())->findOrFail($data['set_id']);
            if (! auth()->user()->canManageEntityDevolutions((int) $setForPerm->entity_id)) {
                return response()->json(['success' => true, 'participations' => collect()]);
            }
        }
        if (! empty($data['reserve_id'])) {
            $reserveForPerm = Reserve::forUser(auth()->user())->findOrFail($data['reserve_id']);
            if (! auth()->user()->canManageEntityDevolutions((int) $reserveForPerm->entity_id)) {
                return response()->json(['success' => true, 'participations' => collect()]);
            }
        }

        $query = Participation::select([
                'participations.id',
                'participations.participation_number as number',
                'participations.participation_code',
                'participations.set_id',
                'sets.set_name'
            ])
            ->join('sets', 'participations.set_id', '=', 'sets.id')
            ->join('reserves', 'sets.reserve_id', '=', 'reserves.id')
            ->where('reserves.lottery_id', $data['lottery_id']);

        if (!empty($data['set_id'])) {
            $query->where('participations.set_id', $data['set_id']);
        } else {
            $query->where('reserves.id', $data['reserve_id']);
        }

        // Filtro de acceso (mismo criterio que Participation::forUser, con columnas calificadas para evitar ambigüedad con sets.entity_id)
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $entityIds = $user->accessibleEntityIds();
            $sellerIds = $user->accessibleSellerIds();
            if (empty($entityIds) && empty($sellerIds)) {
                return response()->json(['success' => true, 'participations' => collect()]);
            }
            $query->where(function ($q) use ($entityIds, $sellerIds) {
                if (!empty($entityIds)) {
                    $q->whereIn('participations.entity_id', $entityIds);
                }
                if (!empty($sellerIds)) {
                    $q->orWhereIn('participations.seller_id', $sellerIds);
                }
            });
        }

        // Solo se pueden devolver participaciones asignadas o disponibles (nunca vendidas ni pagadas)
        $returnableStatuses = Participation::returnableDevolutionStatuses();
        if (isset($data['seller_id'])) {
            $query->where('participations.seller_id', $data['seller_id'])
                  ->whereIn('participations.status', $returnableStatuses);
        } else {
            $query->whereIn('participations.status', $returnableStatuses);
        }

        // EXCLUIR ANULADAS
        $query->where('participations.status', '!=', 'anulada');

        // Filtrar por rango
        if (isset($data['desde']) && isset($data['hasta'])) {
            $query->whereBetween('participations.participation_number', [$data['desde'], $data['hasta']]);
        }

        // Filtrar por participación específica (por número de participación, no por ID)
        if (isset($data['participation_id'])) {
            $query->where('participations.participation_number', $data['participation_id']);
        }

        $participations = $query->get();

        return response()->json([
            'success' => true,
            'participations' => $participations
        ]);
    }

    /**
     * Resolver referencia (QR) y validar participación para devolución en contexto entity/lottery.
     */
    private function validateByReference(string $referencia, int $entityId, int $lotteryId)
    {
        $found = $this->findSetAndParticipationByReference($referencia);
        if (! $found) {
            return response()->json([
                'success' => false,
                'message' => 'No se encuentra ninguna participación con esa referencia.',
                'participations' => [],
            ], 404);
        }
        $set = $found['set'];
        $participationNumber = $found['participation_number'];

        if (! auth()->user()->canManageEntityDevolutions((int) $set->entity_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para validar devoluciones en esta entidad.',
                'participations' => [],
            ], 403);
        }

        if ($entityId && $set->entity_id != $entityId) {
            return response()->json([
                'success' => false,
                'message' => 'La participación no pertenece a la entidad seleccionada.',
                'participations' => []
            ], 422);
        }
        $reserve = $set->reserve ?? $set->reserve()->first();
        if (!$reserve || $reserve->lottery_id != $lotteryId) {
            return response()->json([
                'success' => false,
                'message' => 'La participación no pertenece al sorteo seleccionado.',
                'participations' => []
            ], 422);
        }

        $participation = Participation::forUser(auth()->user())
            ->where('set_id', $set->id)
            ->where('participation_number', $participationNumber)
            ->whereIn('status', Participation::returnableDevolutionStatuses())
            ->where('status', '!=', 'anulada')
            ->first();

        if (! $participation) {
            $blocked = Participation::forUser(auth()->user())
                ->where('set_id', $set->id)
                ->where('participation_number', $participationNumber)
                ->whereIn('status', ['vendida', 'pagada', 'reserva_venta_digital'])
                ->exists();

            return response()->json([
                'success' => false,
                'message' => $blocked
                    ? 'No se pueden devolver participaciones ya vendidas o pagadas.'
                    : 'Esa participación no está disponible para devolución.',
                'participations' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'participations' => [[
                'id' => $participation->id,
                'number' => $participation->participation_number,
                'participation_code' => $participation->display_participation_code,
                'set_id' => $set->id,
                'set_name' => $set->set_name ?? $set->name ?? 'Set ' . $set->set_number,
            ]]
        ]);
    }

    private function findSetAndParticipationByReference(string $referencia): ?array
    {
        $set = Set::forUser(auth()->user())->whereNotNull('tickets')->get()->first(function ($s) use ($referencia) {
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
                $participationNumber = $ticket['n'] ?? null;
                break;
            }
        }
        return $participationNumber !== null ? ['set' => $set, 'participation_number' => $participationNumber] : null;
    }

    /**
     * Obtener resumen de liquidación para los sets seleccionados
     */
    public function getLiquidationSummary(Request $request)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $entityId = $request->get('entity_id');
        $lotteryId = $request->get('lottery_id');
        $setId = $request->get('set_id');
        $reserveId = $request->get('reserve_id');
        $selectedParticipations = $request->get('participations', []);
        $selectedParticipations = is_array($selectedParticipations) ? $selectedParticipations : array_filter([$selectedParticipations]);
        $tipoDevolucion = $request->get('tipo_devolucion');
        $sellerId = $request->get('seller_id');
        $specialPrizeRequirement = $this->buildSpecialPrizeRequirement((int) $lotteryId);
        // Respetar siempre el tipo de devolución elegido (no forzar vendedor si es entidad→admin)

        if ($entityId && (! auth()->user()->canAccessEntity((int) $entityId)
            || ! auth()->user()->canViewDevolutionForEntity((int) $entityId))) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar esta entidad',
            ], 403);
        }

        \Log::info('=== LIQUIDATION SUMMARY REQUEST ===');
        \Log::info('Entity ID:', [$entityId]);
        \Log::info('Lottery ID:', [$lotteryId]);
        \Log::info('Set ID:', [$setId]);
        \Log::info('Reserve ID:', [$reserveId]);
        \Log::info('Selected Participations:', $selectedParticipations);

        // Sin participaciones seleccionadas pero con reserve_id: resumen de la reserva (totales entidad o vendedor)
        if (empty($selectedParticipations) && $reserveId) {
            $reserve = Reserve::forUser(auth()->user())->find($reserveId);
            if (! $reserve || $reserve->lottery_id != $lotteryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserva no encontrada o no coincide con el sorteo',
                ], 404);
            }
            if (! auth()->user()->canViewDevolutionForEntity((int) $reserve->entity_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para consultar liquidación de esta reserva.',
                ], 403);
            }
            $setIds = Set::forUser(auth()->user())
                ->where('reserve_id', $reserveId)
                ->where('status', 1)
                ->pluck('id')
                ->toArray();

            if (empty($setIds)) {
                return response()->json([
                    'success' => true,
                    'summary' => [
                        'total_participations' => 0,
                        'total_fisicas' => 0,
                        'total_digitales' => 0,
                        'ventas_registradas' => 0,
                        'sold_participations' => 0,
                        'returned_participations' => 0,
                        'available_participations' => 0,
                        'total_liquidation' => 0,
                        'registered_payments' => 0,
                        'total_to_pay' => 0,
                        'sets_info' => [],
                        'special_prize_requirement' => $specialPrizeRequirement,
                    ]
                ]);
            }
            $baseQuery = Participation::forUser(auth()->user())
                ->whereIn('set_id', $setIds)
                ->where('status', '!=', 'anulada');
            if ($tipoDevolucion === 'vendedor' && $sellerId) {
                $baseQuery->where('seller_id', $sellerId);
            }
            $totalInReserve = (clone $baseQuery)->count();
            $totalFisicas = (clone $baseQuery)->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")->count();
            $totalDigitales = (clone $baseQuery)->whereRaw("participation_code LIKE '1D/%'")->count();
            $ventasRegistradas = (clone $baseQuery)->sold()->count();
            $devueltas = (clone $baseQuery)->where('status', 'devuelta')->count();
            $disponibles = (clone $baseQuery)->whereIn('status', ['disponible', 'asignada'])->count();
            // Disponibles para devolver (excluye vendida, devuelta, anulada): desglose físicas/digitales
            $queryDisponibles = Participation::forUser(auth()->user())
                ->whereIn('set_id', $setIds)
                ->whereIn('status', ['disponible', 'asignada'])
                ->where('status', '!=', 'anulada');
            if ($tipoDevolucion === 'vendedor' && $sellerId) {
                $queryDisponibles->where('seller_id', $sellerId);
            }
            $disponiblesFisicas = (clone $queryDisponibles)->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")->count();
            $disponiblesDigitales = (clone $queryDisponibles)->whereRaw("participation_code LIKE '1D/%'")->count();

            // Liquidar sin devolver: importe = todas las participaciones liquidables de la reserva (físicas disponible/asignada) × precio por set
            $totalLiquidationReserve = 0.0;
            if ($tipoDevolucion !== 'vendedor') {
                // Entidad→admin: físicas (disponible+asignada) + digitales SOLO vendidas (las digitales disponibles = devueltas, no cuentan)
                $setsInReserve = Set::forUser(auth()->user())->where('reserve_id', $reserveId)->where('status', 1)->get();
                foreach ($setsInReserve as $set) {
                    $fisicas = Participation::forUser(auth()->user())
                        ->where('set_id', $set->id)
                        ->whereIn('status', ['disponible', 'asignada'])
                        ->where('status', '!=', 'anulada')
                        ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                        ->count();
                    $digitalesVendidas = Participation::forUser(auth()->user())
                        ->where('set_id', $set->id)
                        ->sold()
                        ->whereRaw("participation_code LIKE '1D/%'")
                        ->count();
                    $count = $fisicas + $digitalesVendidas;
                    $price = self::pricePerParticipationSet($set);
                    $totalLiquidationReserve += $count * $price;
                }
            } else if ($sellerId) {
                // Vendedor: TODAS las participaciones del vendedor (disponible+asignada+vendida) × total_participation_amount
                $setsInReserve = Set::forUser(auth()->user())->where('reserve_id', $reserveId)->where('status', 1)->get();
                foreach ($setsInReserve as $set) {
                    $q = Participation::forUser(auth()->user())
                        ->where('set_id', $set->id)
                        ->where('seller_id', $sellerId)
                        ->where('status', '!=', 'anulada');
                    $count = $q->count();
                    $price = self::pricePerParticipationSet($set);
                    $totalLiquidationReserve += $count * $price;
                }
            }

            $summaryCounts = $tipoDevolucion !== 'vendedor'
                ? $this->buildAdministrationSummaryParticipationCounts($baseQuery, [], $devueltas)
                : [
                    'returned_participations' => $devueltas,
                    'available_participations' => $disponibles,
                    'returned_digitales_auto' => 0,
                    'returned_fisicas_manual' => 0,
                ];

            return response()->json([
                'success' => true,
                'summary' => [
                    'total_participations' => $totalInReserve,
                    'total_fisicas' => $totalFisicas,
                    'total_digitales' => $totalDigitales,
                    'available_to_return' => $disponibles,
                    'available_to_return_fisicas' => $disponiblesFisicas,
                    'available_to_return_digitales' => $disponiblesDigitales,
                    'ventas_registradas' => $ventasRegistradas,
                    'sold_participations' => $ventasRegistradas,
                    'returned_participations' => $summaryCounts['returned_participations'],
                    'returned_digitales_auto' => $summaryCounts['returned_digitales_auto'],
                    'returned_fisicas_manual' => $summaryCounts['returned_fisicas_manual'],
                    'available_participations' => $summaryCounts['available_participations'],
                    'total_liquidation' => $totalLiquidationReserve,
                    'registered_payments' => 0,
                    'total_to_pay' => $totalLiquidationReserve,
                    'sets_info' => [],
                    'special_prize_requirement' => $specialPrizeRequirement,
                ]
            ]);
        }

        // Sin participaciones seleccionadas pero con set_id: resumen del set (liquidar sin devolver nada)
        if (empty($selectedParticipations) && $setId) {
            \Log::info('No participations selected but set provided, returning set breakdown for liquidar sin devolver');
            
            $set = Set::forUser(auth()->user())->find($setId);
            if (!$set) {
                \Log::warning("Set not found: $setId");
                return response()->json([
                    'success' => false,
                    'message' => 'Set no encontrado'
                ], 404);
            }

            $baseQuery = Participation::forUser(auth()->user())
                ->where('set_id', $setId)
                ->where('status', '!=', 'anulada');
            if ($tipoDevolucion === 'vendedor' && $sellerId) {
                $baseQuery->where('seller_id', $sellerId);
            }

            $totalInSet = (clone $baseQuery)->count();
            $ventasRegistradas = (clone $baseQuery)->sold()->count();
            $devueltas = (clone $baseQuery)->where('status', 'devuelta')->count();
            $disponibles = (clone $baseQuery)->whereIn('status', ['disponible', 'asignada'])->count();

            $pricePerParticipation = self::pricePerParticipationSet($set);
            if ($tipoDevolucion === 'vendedor' && $sellerId) {
                $toLiquidate = $totalInSet;
            } else {
                // Entidad→admin: físicas no devueltas + digitales SOLO vendidas (digitales disponibles = devueltas, no cuentan)
                $fisicasNoDevueltas = (clone $baseQuery)->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")->where('status', '!=', 'devuelta')->count();
                $digitalesVendidas = Participation::forUser(auth()->user())->where('set_id', $setId)->sold()->whereRaw("participation_code LIKE '1D/%'")->count();
                $toLiquidate = $fisicasNoDevueltas + $digitalesVendidas;
            }
            $totalLiquidation = $toLiquidate * $pricePerParticipation;

            $summaryCounts = $tipoDevolucion !== 'vendedor'
                ? $this->buildAdministrationSummaryParticipationCounts($baseQuery, [], $devueltas)
                : [
                    'returned_participations' => $devueltas,
                    'available_participations' => $disponibles,
                    'returned_digitales_auto' => 0,
                    'returned_fisicas_manual' => 0,
                ];

            return response()->json([
                'success' => true,
                'summary' => [
                    'total_participations' => $totalInSet,
                    'ventas_registradas' => $ventasRegistradas,
                    'sold_participations' => $ventasRegistradas,
                    'returned_participations' => $summaryCounts['returned_participations'],
                    'returned_digitales_auto' => $summaryCounts['returned_digitales_auto'],
                    'returned_fisicas_manual' => $summaryCounts['returned_fisicas_manual'],
                    'available_participations' => $summaryCounts['available_participations'],
                    'total_liquidation' => $totalLiquidation,
                    'registered_payments' => 0,
                    'total_to_pay' => $totalLiquidation,
                    'special_prize_requirement' => $specialPrizeRequirement,
                    'sets_info' => [[
                        'set_id' => $setId,
                        'set_name' => $set->set_name,
                        'total_participations' => $totalInSet,
                        'sold_participations' => $ventasRegistradas,
                        'returned_participations' => $summaryCounts['returned_participations'],
                        'available_participations' => $summaryCounts['available_participations'],
                        'price_per_participation' => $pricePerParticipation,
                        'liquidation' => $totalLiquidation
                    ]]
                ]
            ]);
        }

        if (empty($selectedParticipations)) {
            \Log::info('No participations and no set selected, returning empty summary');
            return response()->json([
                'success' => true,
                'summary' => [
                    'total_participations' => 0,
                    'sold_participations' => 0,
                    'returned_participations' => 0,
                    'available_participations' => 0,
                    'total_liquidation' => 0,
                    'registered_payments' => 0,
                    'total_to_pay' => 0,
                    'sets_info' => [],
                    'special_prize_requirement' => $specialPrizeRequirement,
                ]
            ]);
        }

        // Con reserve_id y participaciones seleccionadas: totales siempre de la reserva completa; liquidación solo por las seleccionadas
        if ($reserveId) {
            $reserve = Reserve::forUser(auth()->user())->find($reserveId);
            if ($reserve && $reserve->lottery_id == $lotteryId) {
                $setIdsReserve = Set::forUser(auth()->user())
                    ->where('reserve_id', $reserveId)
                    ->where('status', 1)
                    ->pluck('id')
                    ->toArray();
                if (!empty($setIdsReserve)) {
                    $baseQueryReserve = Participation::forUser(auth()->user())
                        ->whereIn('set_id', $setIdsReserve)
                        ->where('status', '!=', 'anulada');
                    if ($tipoDevolucion === 'vendedor' && $sellerId) {
                        $baseQueryReserve->where('seller_id', $sellerId);
                    }
                    $totalInReserve = (clone $baseQueryReserve)->count();
                    $totalFisicas = (clone $baseQueryReserve)->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")->count();
                    $totalDigitales = (clone $baseQueryReserve)->whereRaw("participation_code LIKE '1D/%'")->count();
                    $ventasRegistradas = (clone $baseQueryReserve)->sold()->count();
                    $devueltas = (clone $baseQueryReserve)->where('status', 'devuelta')->count();
                    $disponibles = (clone $baseQueryReserve)->whereIn('status', ['disponible', 'asignada'])->count();

                    $participations = Participation::forUser(auth()->user())
                        ->whereIn('id', $selectedParticipations)
                        ->where('status', '!=', 'anulada')
                        ->get();
                    $setIdsFromSelected = $participations->pluck('set_id')->unique()->toArray();
                    $totalLiquidation = 0.0;
                    $totalReturnedThisOp = 0;
                    $totalRemainingWithSeller = 0;
                    $idsToReturn = $participations->pluck('id')->toArray();
                    // Entidad→admin: iterar TODOS los sets de la reserva; liquidar solo FÍSICAS no devueltas × total_participation_amount
                    $setsToIterate = ($tipoDevolucion !== 'vendedor')
                        ? $setIdsReserve
                        : array_values(array_intersect($setIdsFromSelected, $setIdsReserve));
                    foreach ($setsToIterate as $sid) {
                        $set = Set::forUser(auth()->user())->find($sid);
                        if (!$set || !in_array($sid, $setIdsReserve)) {
                            continue;
                        }
                        $price = self::pricePerParticipationSet($set);
                        $returnedInSet = $participations->where('set_id', $sid)->count();
                        if ($tipoDevolucion === 'vendedor' && $sellerId) {
                            $totalSellerInSet = Participation::forUser(auth()->user())
                                ->where('set_id', $sid)
                                ->where('seller_id', $sellerId)
                                ->where('status', '!=', 'anulada')
                                ->count();
                            $remainingWithSeller = max(0, $totalSellerInSet - $returnedInSet);
                            $totalLiquidation += $remainingWithSeller * $price;
                            $totalRemainingWithSeller += $remainingWithSeller;
                        } else {
                            // Físicas: las que quedan (no devueltas en esta operación). Digitales: SOLO las vendidas (disponibles = devueltas, no cuentan)
                            $fisicasInSet = Participation::forUser(auth()->user())
                                ->where('set_id', $sid)
                                ->where('status', '!=', 'anulada')
                                ->where('status', '!=', 'devuelta')
                                ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                                ->count();
                            $returnedFisicasInSet = $participations
                                ->where('set_id', $sid)
                                ->filter(fn ($p) => $p->participation_code === null || !str_starts_with((string) $p->participation_code, '1D/'))
                                ->count();
                            $toLiquidateFisicas = max(0, $fisicasInSet - $returnedFisicasInSet);
                            $digitalesVendidasInSet = Participation::forUser(auth()->user())
                                ->where('set_id', $sid)
                                ->sold()
                                ->whereRaw("participation_code LIKE '1D/%'")
                                ->count();
                            $totalLiquidation += ($toLiquidateFisicas + $digitalesVendidasInSet) * $price;
                        }
                        $totalReturnedThisOp += $returnedInSet;
                    }

                    if ($tipoDevolucion !== 'vendedor') {
                        $summaryCounts = $this->buildAdministrationSummaryParticipationCounts(
                            $baseQueryReserve,
                            $participations,
                            $devueltas
                        );
                    } else {
                        $summaryCounts = [
                            'returned_participations' => $totalReturnedThisOp,
                            'available_participations' => max(0, $disponibles - $totalReturnedThisOp),
                            'returned_digitales_auto' => 0,
                            'returned_fisicas_manual' => $totalReturnedThisOp,
                        ];
                    }
                    return response()->json([
                        'success' => true,
                        'summary' => [
                            'total_participations' => $totalInReserve,
                            'total_fisicas' => $totalFisicas,
                            'total_digitales' => $totalDigitales,
                            'ventas_registradas' => $ventasRegistradas,
                            'sold_participations' => $ventasRegistradas,
                            'returned_participations' => $summaryCounts['returned_participations'],
                            'returned_digitales_auto' => $summaryCounts['returned_digitales_auto'],
                            'returned_fisicas_manual' => $summaryCounts['returned_fisicas_manual'],
                            'available_participations' => $summaryCounts['available_participations'],
                            'total_liquidation' => $totalLiquidation,
                            'registered_payments' => 0,
                            'total_to_pay' => $totalLiquidation,
                            'sets_info' => [],
                            'special_prize_requirement' => $specialPrizeRequirement,
                        ]
                    ]);
                }
            }
        }

        // Obtener las participaciones seleccionadas (EXCLUIR ANULADAS)
        $participations = Participation::forUser(auth()->user())
            ->whereIn('id', $selectedParticipations)
            ->where('status', '!=', 'anulada')
            ->get();
        \Log::info('Found participations (excluding cancelled):', $participations->toArray());
        
        $setIds = $participations->pluck('set_id')->unique()->toArray();
        \Log::info('Unique set IDs:', $setIds);

        $setsInfo = [];
        $totalParticipations = 0;
        $totalSold = 0;
        $totalReturned = 0;
        $totalLiquidation = 0;
        $totalSellerParticipations = 0;   // solo para vendedor: total asignadas al vendedor en el set
        $totalRemainingWithSeller = 0;     // solo para vendedor: las que quedan a liquidar

        foreach ($setIds as $setId) {
            $set = Set::forUser(auth()->user())->find($setId);
            if (!$set) {
                \Log::warning("Set not found: $setId");
                continue;
            }

            \Log::info("Processing set: $setId - " . $set->set_name);

            // Participaciones del set que se van a devolver (las seleccionadas por el usuario)
            $returnedInSet = $participations->where('set_id', $setId)->count();

            // Total real del set: todas las participaciones del set (cualquier estado salvo anulada)
            $totalInSet = Participation::forUser(auth()->user())
                ->where('set_id', $setId)
                ->where('status', '!=', 'anulada')
                ->count();

            // Participaciones en pool para esta liquidación: solo disponible + asignada (las que se pueden devolver o liquidar)
            $allInSet = Participation::forUser(auth()->user())
                ->where('set_id', $setId)
                ->whereIn('status', ['disponible', 'asignada'])
                ->where('status', '!=', 'anulada')
                ->count();

            // No devolver más de las que hay en el pool (evitar ventas registradas negativas)
            $returnedInSet = min($returnedInSet, $allInSet);

            $pricePerParticipation = self::pricePerParticipationSet($set);

            $totalSellerInSet = $totalInSet;
            if ($tipoDevolucion === 'vendedor' && $sellerId) {
                $totalSellerInSet = Participation::forUser(auth()->user())
                    ->where('set_id', $setId)
                    ->where('seller_id', $sellerId)
                    ->where('status', '!=', 'anulada')
                    ->count();
                $remainingWithSeller = max(0, $totalSellerInSet - $returnedInSet);
                $setLiquidation = $remainingWithSeller * $pricePerParticipation;
                $soldInSet = $remainingWithSeller;
                $totalSellerParticipations += $totalSellerInSet;
                $totalRemainingWithSeller += $remainingWithSeller;
            } else {
                // Entidad→admin: físicas no devueltas + digitales SOLO vendidas (digitales disponibles = devueltas, no cuentan)
                $fisicasInSet = Participation::forUser(auth()->user())
                    ->where('set_id', $setId)
                    ->where('status', '!=', 'anulada')
                    ->where('status', '!=', 'devuelta')
                    ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
                    ->count();
                $returnedFisicasInSet = $participations
                    ->where('set_id', $setId)
                    ->filter(fn ($p) => $p->participation_code === null || !str_starts_with((string) $p->participation_code, '1D/'))
                    ->count();
                $toLiquidateFisicas = max(0, $fisicasInSet - $returnedFisicasInSet);
                $digitalesVendidasInSet = Participation::forUser(auth()->user())
                    ->where('set_id', $setId)
                    ->sold()
                    ->whereRaw("participation_code LIKE '1D/%'")
                    ->count();
                $soldInSet = $allInSet - $returnedInSet;
                $setLiquidation = ($toLiquidateFisicas + $digitalesVendidasInSet) * $pricePerParticipation;
            }

            \Log::info("Set $setId stats:", [
                'total_in_set' => $totalInSet,
                'in_pool' => $allInSet,
                'returned' => $returnedInSet,
                'sold' => $soldInSet,
                'price' => $pricePerParticipation,
                'liquidation' => $setLiquidation,
                'tipo_devolucion' => $tipoDevolucion
            ]);

            $setsInfo[] = [
                'set_id' => $setId,
                'set_name' => $set->set_name,
                'total_participations' => $totalSellerInSet,
                'sold_participations' => $soldInSet,
                'returned_participations' => $returnedInSet,
                'available_participations' => 0,
                'price_per_participation' => $pricePerParticipation,
                'total_liquidation' => $setLiquidation
            ];

            $totalParticipations += $totalInSet;
            $totalSold += $soldInSet;
            $totalReturned += $returnedInSet;
            $totalLiquidation += $setLiquidation;
        }

        // Para la UI: administración = ventas que quedarán (Total - Devueltas). Vendedor = participaciones que quedan a liquidar (las que siguen con el vendedor)
        $ventasRegistradasDisplay = $tipoDevolucion === 'vendedor' ? $totalRemainingWithSeller : ($totalParticipations - $totalReturned);

        // Devolución vendedor: mostrar total del vendedor en el set (ej. 100), no solo las devueltas
        $totalParticipationsDisplay = $tipoDevolucion === 'vendedor' ? $totalSellerParticipations : $totalParticipations;

        $summary = [
            'total_participations' => $totalParticipationsDisplay,
            'sold_participations' => $totalSold,
            'ventas_registradas' => $ventasRegistradasDisplay,
            'returned_participations' => $totalReturned,
            'available_participations' => 0,
            'total_liquidation' => $totalLiquidation,
            'registered_payments' => 0, // Por ahora siempre 0
            'total_to_pay' => $totalLiquidation,
            'sets_info' => $setsInfo,
            'special_prize_requirement' => $specialPrizeRequirement,
        ];

        \Log::info('Final summary:', $summary);

        return response()->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    /**
     * Generar botones de acción para DataTable
     */
    private function generateActions($id)
    {
        $user = auth()->user();
        $view = '
            <a href="' . route('devolutions.show', $id) . '" class="btn btn-sm btn-light" title="Ver detalle">
                <img src="' . url('assets/form-groups/eye.svg') . '" alt="" width="12">
                </a>';

        if ($user && $user->isEntityPanelReadOnly()) {
            return $view;
        }

        return $view . '
            <a href="' . route('devolutions.edit', $id) . '" class="btn btn-sm btn-light" title="Editar">
                <img src="' . url('assets/form-groups/edit.svg') . '" alt="" width="12">
                </a>
                <button type="button" class="btn btn-sm btn-danger btn-eliminar-devolucion" 
                        data-id="' . $id . '" data-name="Devolución #' . $id . '" title="Eliminar">
                <i class="ri-delete-bin-6-line"></i>
                </button>
        ';
    }

    /**
     * Obtener pagos de una devolución
     */
    public function getPayments($id)
    {
        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        $devolution = Devolution::forUser(auth()->user())->findOrFail($id);
        if (! auth()->user()->canViewDevolutionForEntity((int) $devolution->entity_id)) {
            return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
        }
        $payments = $devolution->payments()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'payments' => $payments,
        ]);
    }

    /**
     * Agregar nuevos pagos a una devolución (puede ser múltiple)
     */
    public function addPayment(Request $request, $id)
    {
        try {
            if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
                return $resp;
            }

            $devolution = Devolution::forUser(auth()->user())->findOrFail($id);
            if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
                return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
            }

            $data = $request->validate([
                'pagos' => 'required|array',
                'pagos.*.amount' => 'required|numeric|min:0.01',
                'pagos.*.payment_method' => 'required|in:efectivo,bizum,transferencia,otro',
                'notes' => 'nullable|string|max:1000'
            ]);

            $paymentsCreated = [];

            foreach ($data['pagos'] as $pago) {
                $payment = DevolutionPayment::create([
                    'devolution_id' => $devolution->id,
                    'amount' => $pago['amount'],
                    'payment_method' => $pago['payment_method'],
                    'from_number' => null,
                    'notes' => $data['notes'] ?? 'Pago agregado - ' . ucfirst($pago['payment_method']),
                    'payment_date' => now()
                ]);

                $paymentsCreated[] = $payment;
            }

            return response()->json([
                'success' => true,
                'message' => 'Pagos agregados correctamente',
                'payments' => $paymentsCreated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar los pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un pago existente
     */
    public function updatePayment(Request $request, $devolutionId, $paymentId)
    {
        try {
            if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
                return $resp;
            }

            $devolution = Devolution::forUser(auth()->user())->findOrFail($devolutionId);
            if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
                return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
            }

            $payment = DevolutionPayment::where('devolution_id', $devolution->id)
                ->where('id', $paymentId)
                ->firstOrFail();

            $data = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:efectivo,bizum,transferencia,otro',
                'from_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000'
            ]);

            $payment->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Pago actualizado correctamente',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un pago
     */
    public function deletePayment($devolutionId, $paymentId)
    {
        try {
            if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
                return $resp;
            }

            $devolution = Devolution::forUser(auth()->user())->findOrFail($devolutionId);
            if (! auth()->user()->canManageEntityDevolutions((int) $devolution->entity_id)) {
                return response()->json(['success' => false, 'message' => 'Sin permiso.'], 403);
            }

            $payment = DevolutionPayment::where('devolution_id', $devolution->id)
                ->where('id', $paymentId)
                ->firstOrFail();

            $payment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pago eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar anulación de participaciones
     */
    private function procesarAnulacion(Request $request)
    {
        \Log::info("=== INICIANDO PROCESAMIENTO DE ANULACIÓN ===");

        if ($resp = $this->jsonUnlessDevolutionsWebAccess()) {
            return $resp;
        }

        try {
            DB::beginTransaction();
            \Log::info("Transacción de anulación iniciada");

            $data = $request->validate([
                'entity_id' => 'required|exists:entities,id',
                'lottery_id' => 'required|exists:lotteries,id',
                'set_id' => 'nullable|exists:sets,id',
                'participations' => 'required|array|min:1',
                'participations.*' => 'integer|exists:participations,id',
                'motivo' => 'required|string|max:500'
            ]);

            if (!auth()->user()->canAccessEntity((int) $data['entity_id'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para gestionar anulaciones de esta entidad'
                ], 403);
            }

            if ($response = $this->devolutionEntityPermissionErrorIfAny((int) $data['entity_id'])) {
                DB::rollBack();

                return $response;
            }

            if (!empty($data['set_id'])) {
                Set::forUser(auth()->user())->findOrFail($data['set_id']);
            }

            $now = Carbon::now();
            $userId = auth()->id();

            // Obtener las participaciones a anular
            $participations = Participation::forUser(auth()->user())
                ->whereIn('id', $data['participations'])->get();
            
            if ($participations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron participaciones para anular'
                ], 400);
            }

            // VALIDAR: Rechazar participaciones que ya están anuladas
            $yaAnuladas = $participations->where('status', 'anulada');
            if ($yaAnuladas->count() > 0) {
                $codigosAnulados = $yaAnuladas->map(fn ($p) => $p->display_participation_code)->toArray();
                return response()->json([
                    'success' => false,
                    'message' => 'Las siguientes participaciones ya están anuladas: ' . implode(', ', $codigosAnulados)
                ], 400);
            }

            // Anular las participaciones
            foreach ($participations as $participation) {
                try {
                    // Anular la participación (usar update para disparar observer)
                    $participation->update([
                        'status' => 'anulada',
                        'cancellation_reason' => $data['motivo'],
                        'cancelled_by' => $userId,
                        'cancellation_date' => $now->format('Y-m-d'),
                    ]);
                } catch (\Exception $observerError) {
                    // Si el observer falla, continuar sin rollback
                    \Log::warning("Observer falló para participación {$participation->id}", [
                        'error' => $observerError->getMessage()
                    ]);
                    
                    // Actualizar directamente sin observer
                    \DB::table('participations')
                        ->where('id', $participation->id)
                        ->update([
                            'status' => 'anulada',
                            'cancellation_reason' => $data['motivo'],
                            'cancelled_by' => $userId,
                            'cancellation_date' => $now->format('Y-m-d'),
                            'updated_at' => $now,
                        ]);
                }
            }

            // Crear registro de devolución para auditoría
            \Log::info("Creando registro de devolución para anulación", [
                'entity_id' => $data['entity_id'],
                'lottery_id' => $data['lottery_id'],
                'participations_count' => $participations->count()
            ]);
            
            $devolution = Devolution::create([
                'entity_id' => $data['entity_id'],
                'lottery_id' => $data['lottery_id'],
                'seller_id' => null, // Las anulaciones no tienen vendedor
                'user_id' => $userId,
                'total_participations' => $participations->count(),
                'return_reason' => "Anulación: {$data['motivo']}",
                'devolution_date' => $now->format('Y-m-d'),
                'devolution_time' => $now->format('H:i:s'),
                'status' => 'procesada', // Status normal como otras devoluciones
                'notes' => "Anulación de {$participations->count()} participaciones"
            ]);
            
            \Log::info("Devolución creada exitosamente", [
                'devolution_id' => $devolution->id
            ]);

            // Crear detalles de anulación
            \Log::info("Creando detalles de anulación", [
                'devolution_id' => $devolution->id,
                'participations_count' => $participations->count()
            ]);
            
            foreach ($participations as $participation) {
                \Log::info("Creando detalle para participación", [
                    'devolution_id' => $devolution->id,
                    'participation_id' => $participation->id
                ]);
                
                DevolutionDetail::create([
                    'devolution_id' => $devolution->id,
                    'participation_id' => $participation->id,
                    'action' => 'anular'
                ]);
            }
            
            \Log::info("Detalles de anulación creados exitosamente");

            DB::commit();
            
            \Log::info("=== TRANSACCIÓN DE ANULACIÓN CONFIRMADA EXITOSAMENTE ===", [
                'devolution_id' => $devolution->id,
                'participaciones_anuladas' => $participations->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Anulación procesada correctamente. Se anularon {$participations->count()} participaciones.",
                'data' => [
                    'devolution_id' => $devolution->id,
                    'participaciones_anuladas' => $participations->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error en anulación de participaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la anulación: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sellerLiquidationWarningIfAny(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($request->boolean('acknowledge_seller_liquidation_warning')) {
            return null;
        }

        $tipo = (string) $request->input('tipo_devolucion', 'administracion');
        if ($tipo === 'vendedor' || $tipo === 'anulacion') {
            return null;
        }

        $entityId = (int) $request->input('entity_id', 0);
        $lotteryId = (int) $request->input('lottery_id', 0);
        if ($entityId <= 0 || $lotteryId <= 0) {
            return null;
        }

        $pending = app(SellerLiquidationService::class)
            ->sumPendingLiquidationForEntityLottery($entityId, $lotteryId);

        if ($pending <= 0) {
            return null;
        }

        return response()->json([
            'success' => false,
            'requires_confirmation' => true,
            'warning_code' => 'seller_liquidation_pending',
            'message' => 'Hay vendedores con liquidación pendiente para este sorteo ('.number_format($pending, 2, ',', '.').' €). Puedes continuar, pero conviene que liquiden antes.',
            'seller_pending_amount' => round($pending, 2),
        ], 409);
    }

    protected function validateAdministrationLiquidationLegalRequirements(Request $request): ?JsonResponse
    {
        $soloDevolucion = filter_var($request->input('solo_devolucion', false), FILTER_VALIDATE_BOOLEAN);
        $tipo = (string) $request->input('tipo_devolucion', 'administracion');
        if ($tipo === 'anulacion' || $tipo === 'vendedor' || $soloDevolucion) {
            return null;
        }

        if (empty($request->input('prize_payment_mode'))) {
            return response()->json([
                'success' => false,
                'message' => 'Debes seleccionar la modalidad de pago de premios (presencial u online) antes de liquidar.',
            ], 422);
        }

        return $this->validateDefinitiveLiquidationPhrase($request, true);
    }

    protected function validateDefinitiveLiquidationPhrase(Request $request, bool $required): ?JsonResponse
    {
        if (! $required) {
            return null;
        }

        $expected = (string) config('legal_prizes.definitive_liquidation.confirmation_phrase', 'CONFIRMO LIQUIDACIÓN');
        $text = trim((string) $request->input('confirmacion_liquidacion_definitiva', ''));
        if ($text !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Debes escribir exactamente «'.$expected.'» para confirmar la liquidación definitiva.',
            ], 422);
        }

        return null;
    }
}
