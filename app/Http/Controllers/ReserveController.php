<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLotteryDrawDateGuard;
use App\Models\Reserve;
use App\Models\Entity;
use App\Models\Lottery;
use App\Services\CommunicationEmailService;
use App\Mail\ReserveSavedToEntityManagerMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReserveController extends Controller
{
    use HandlesLotteryDrawDateGuard;
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Mostrar lista de reservas
     */
    public function index()
    {
        $reserves = Reserve::with(['entity', 'lottery'])
            ->forUser(auth()->user())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('reserves.index', compact('reserves'));
    }

    /**
     * Mostrar formulario para crear reserva - Paso 1: Seleccionar entidad
     */
    public function create(Request $request)
    {
        if ($entity = \App\Support\PanelSelectionResolver::resolveEntity($request->user())) {
            return $this->showLotterySelectionAfterEntity($request, $entity);
        }

        $entities = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->get();
        return view('reserves.add', compact('entities'));
    }

    /**
     * Guardar selección de entidad y mostrar formulario de sorteo - Paso 2
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

        return $this->showLotterySelectionAfterEntity($request, $entity);
    }

    private function showLotterySelectionAfterEntity(Request $request, Entity $entity)
    {
        $this->putSelectedEntityInSession($request, $entity);
        $request->session()->forget(['selected_lottery', 'selected_lottery_id']);

        $lotteries = Lottery::where('status', 1)
            ->openForOperations()
            ->with(['lotteryType'])
            ->orderBy('draw_date', 'desc')
            ->get();

        return view('reserves.add_lottery', compact('lotteries', 'entity'));
    }

    /**
     * Guardar selección de entidad via AJAX
     */
    public function store_entity_ajax(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($request->entity_id);

        if ($entity->status != 1) {
            return response()->json(['success' => false, 'message' => 'Solo se puede seleccionar una entidad activa.'], 422);
        }

        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_entity_id', $entity->id);

        return response()->json(['success' => true]);
    }

    /**
     * Guardar selección de sorteo y mostrar formulario de datos - Paso 3
     */
    public function store_lottery(Request $request)
    {
        $request->validate([
            'lottery_id' => 'required|integer|exists:lotteries,id'
        ]);

        $entityId = $request->session()->get('selected_entity_id');
        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('reserves.create')
                ->with('error', 'Debe seleccionar una entidad válida antes de elegir el sorteo.');
        }

        $lottery = Lottery::with(['lotteryType'])->findOrFail($request->lottery_id);
        if ($response = $this->redirectIfLotteryDrawDateBlocked($lottery, 'reserves.create')) {
            return $response;
        }
        $request->session()->put('selected_lottery', $lottery);
        $request->session()->put('selected_lottery_id', $lottery->id);

        // Redirigir a la ruta GET donde está el formulario final
        return redirect()->route('reserves.add-information');
    }

    /**
     * Guardar selección de sorteo via AJAX
     */
    public function store_lottery_ajax(Request $request)
    {
        $request->validate([
            'lottery_id' => 'required|integer|exists:lotteries,id'
        ]);

        $entityId = $request->session()->get('selected_entity_id');
        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar una entidad válida antes de elegir el sorteo.'
            ], 422);
        }

        $lottery = Lottery::with(['lotteryType'])->findOrFail($request->lottery_id);
        if ($response = $this->jsonIfLotteryDrawDateBlocked($lottery)) {
            return $response;
        }
        $request->session()->put('selected_lottery', $lottery);
        $request->session()->put('selected_lottery_id', $lottery->id);

        return response()->json(['success' => true]);
    }

    /**
     * Validar que la suma de décimos reservados para cada número no exceda el máximo permitido
     * @param array $reservationNumbers
     * @param int $lotteryId
     * @param int $reservationTickets
     * @param int|null $excludeReserveId (opcional, para edición)
     * @return array ['success' => bool, 'messages' => array]
     */
    private function validateReservationTickets(array $reservationNumbers, int $lotteryId, int $reservationTickets, $excludeReserveId = null)
    {
        $lottery = \App\Models\Lottery::with('lotteryType')->find($lotteryId);
        if (!$lottery || !$lottery->lotteryType) {
            return [
                'success' => false,
                'messages' => ['No se encontró el sorteo o su tipo.']
            ];
        }
        $series = $lottery->lotteryType->series;
        $maxTicketsPerNumber = $series * 10;
        $messages = [];
        foreach ($reservationNumbers as $number) {
            // Sumar todos los décimos reservados para este número en este sorteo
            $query = Reserve::where('lottery_id', $lotteryId)
                ->whereJsonContains('reservation_numbers', $number);
            if ($excludeReserveId) {
                $query->where('id', '!=', $excludeReserveId);
            }
            $alreadyReserved = $query->sum('reservation_tickets');
            // Si estamos editando, sumar los tickets de la reserva actual
            $totalAfter = $alreadyReserved + $reservationTickets;
            if ($totalAfter > $maxTicketsPerNumber) {
                $disponibles = max(0, $maxTicketsPerNumber - $alreadyReserved);
                $messages[] = "El número $number solo tiene $disponibles décimos disponibles para reservar en este sorteo.";
            }
        }
        return [
            'success' => count($messages) === 0,
            'messages' => $messages
        ];
    }

    /**
     * Guardar reserva completa - Paso final
     */
    public function store_information(Request $request)
    {
        $validated = $request->validate([
            'reservation_numbers' => 'required|array|min:1',
            'reservation_numbers.*' => 'required|string|max:10',
            'reservation_amount' => 'required|numeric|min:0',
            'reservation_tickets' => 'required|integer|min:1'
        ]);
        $entityId = $request->session()->get('selected_entity_id');
        $lotteryId = $request->session()->get('selected_lottery_id');

        if (!$entityId || !$lotteryId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('reserves.create')
                ->with('error', 'Error: No se encontraron los datos de entidad o sorteo');
        }

        $entity = Entity::forUser(auth()->user())->findOrFail($entityId);
        $lottery = Lottery::with('lotteryType')->findOrFail($lotteryId);
        if ($response = $this->redirectIfLotteryDrawDateBlocked($lottery, 'reserves.create')) {
            return $response;
        }
        // Importe debe ser múltiplo del precio del décimo; siempre redondear al alza
        $ticketPrice = (float) $lottery->ticket_price;
        if ($ticketPrice > 0) {
            $ticketsFromAmount = (int) ceil($validated['reservation_amount'] / $ticketPrice);
            if ($ticketsFromAmount < 1) {
                $ticketsFromAmount = 1;
            }
            $validated['reservation_amount'] = round($ticketsFromAmount * $ticketPrice, 2);
            $validated['reservation_tickets'] = $ticketsFromAmount;
        }
        // Mantener datos actualizados en sesión para la interfaz
        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_lottery', $lottery);
        // Validar décimos disponibles
        $validation = $this->validateReservationTickets($validated['reservation_numbers'], $lottery->id, $validated['reservation_tickets']);
        if (!$validation['success']) {
            return redirect()->back()->withErrors($validation['messages'])->withInput();
        }
        // Total de la reserva = importe por número × cantidad de números
        $totalTickets = count($validated['reservation_numbers']);
        $totalAmount = round($totalTickets * (float) $validated['reservation_amount'], 2);

        // Crear reserva
        $reserveData = array_merge($validated, [
            'entity_id' => $entity->id,
            'lottery_id' => $lottery->id,
            'total_amount' => $totalAmount,
            'total_tickets' => $totalTickets,
            'status' => 1, // pending
            'reservation_date' => now(),
            'expiration_date' => now()->addDays(7) // 7 días por defecto
        ]);

        $reserve = Reserve::create($reserveData);

        // Comunicación pendiente (según especificación): email al gestor principal de la entidad
        // al guardar la reserva.
        try {
            $entityManagerUser = $entity->manager?->user;
            if ($entityManagerUser && !empty($entityManagerUser->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $entityManagerUser->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $entityManagerUser,
                    messageType: 'reservation_saved',
                    templateKey: null,
                    mailClass: ReserveSavedToEntityManagerMail::class,
                    mailPayload: ['reserve_id' => $reserve->id],
                    context: ['reserve_id' => $reserve->id, 'entity_id' => $entity->id, 'lottery_id' => $lottery->id],
                );
            }
        } catch (\Throwable $e) {
            // No bloquear creación de la reserva si falla el email
            \Log::warning('Fallo enviando email reserva a gestor entidad: ' . $e->getMessage());
        }

        // Limpiar sesión
        $request->session()->forget(['selected_entity', 'selected_lottery', 'selected_entity_id', 'selected_lottery_id']);

        return redirect()->route('reserves.index')
            ->with('success', 'Reserva creada exitosamente');
    }

    /**
     * Mostrar reserva específica
     */
    public function show(Reserve $reserve)
    {
        if (!auth()->user()->canAccessEntity($reserve->entity_id)) {
            abort(403, 'No tienes permisos para ver esta reserva.');
        }

        $reserve->load(['entity', 'lottery']);
        return view('reserves.show', compact('reserve'));
    }

    /**
     * Mostrar formulario para editar reserva
     */
    public function edit(Reserve $reserve)
    {
        if (!auth()->user()->canAccessEntity($reserve->entity_id)) {
            abort(403, 'No tienes permisos para editar esta reserva.');
        }

        $entities = Entity::forUser(auth()->user())->get();
        $lotteries = Lottery::all();
        return view('reserves.edit', compact('reserve', 'entities', 'lotteries'));
    }

    /**
     * Actualizar reserva
     */
    public function update(Request $request, Reserve $reserve)
    {
        if (!auth()->user()->canAccessEntity($reserve->entity_id)) {
            abort(403, 'No tienes permisos para actualizar esta reserva.');
        }

        $reserve->loadMissing('lottery');
        if ($response = $this->redirectIfReserveLotteryBlocked($reserve)) {
            return $response;
        }

        $validated = $request->validate([
            'reservation_numbers' => 'required|array|min:1',
            'reservation_numbers.*' => 'required|string|max:10',
            'reservation_amount' => 'required|numeric|min:0',
            'reservation_tickets' => 'required|integer|min:1'
        ]);
        // Importe debe ser múltiplo del precio del décimo; siempre redondear al alza
        $ticketPrice = (float) $reserve->lottery->ticket_price;
        if ($ticketPrice > 0) {
            $ticketsFromAmount = (int) ceil($validated['reservation_amount'] / $ticketPrice);
            if ($ticketsFromAmount < 1) {
                $ticketsFromAmount = 1;
            }
            $validated['reservation_amount'] = round($ticketsFromAmount * $ticketPrice, 2);
            $validated['reservation_tickets'] = $ticketsFromAmount;
        }
        // Validar décimos disponibles (excluyendo la reserva actual)
        $validation = $this->validateReservationTickets($validated['reservation_numbers'], $reserve->lottery_id, $validated['reservation_tickets'], $reserve->id);
        if (!$validation['success']) {
            return redirect()->back()->withErrors($validation['messages'])->withInput();
        }
        // Recalcular total de la reserva = importe por número × cantidad de números
        $validated['total_tickets'] = count($validated['reservation_numbers']);
        $validated['total_amount'] = round($validated['total_tickets'] * (float) $validated['reservation_amount'], 2);
        $reserve->update($validated);

        return redirect()->route('reserves.show',$reserve->id)
            ->with('success', 'Reserva actualizada exitosamente');
    }

    /**
     * Eliminar reserva
     */
    public function destroy(Reserve $reserve)
    {
        if (!auth()->user()->canAccessEntity($reserve->entity_id)) {
            abort(403, 'No tienes permisos para eliminar esta reserva.');
        }

        if ($response = $this->redirectIfReserveLotteryBlocked($reserve)) {
            return $response;
        }

        $reserve->delete();

        return redirect()->route('reserves.index')
            ->with('success', 'Reserva eliminada exitosamente');
    }

    /**
     * Cambiar estado de la reserva
     */
    public function changeStatus(Request $request, Reserve $reserve)
    {
        if (!auth()->user()->canAccessEntity($reserve->entity_id)) {
            abort(403, 'No tienes permisos para actualizar esta reserva.');
        }

        if ($response = $this->redirectIfReserveLotteryBlocked($reserve)) {
            return $response;
        }

        $request->validate([
            'status' => 'required|in:0,1,2,3'
        ]);

        $reserve->update(['status' => (int)$request->status]);

        return redirect()->back()
            ->with('success', 'Estado de la reserva actualizado');
    }

    /**
     * Obtener sorteos disponibles para una entidad
     */
    public function getLotteriesByEntity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
            return response()->json([], 403);
        }

        $lotteries = Lottery::where('status', 1) // Solo sorteos activos
            ->openForOperations()
            ->with(['lotteryType'])
            ->get();

        return response()->json($lotteries);
    }

    /**
     * Mostrar formulario para agregar información de la reserva (números a reservar)
     */
    public function add_information(Request $request)
    {
        $entityId = $request->session()->get('selected_entity_id');
        $lotteryId = $request->session()->get('selected_lottery_id');

        if (!$entityId || !$lotteryId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('reserves.create')->with('error', 'Debe seleccionar entidad y sorteo primero.');
        }

        $entity = Entity::forUser(auth()->user())->findOrFail($entityId);
        $lottery = Lottery::with('lotteryType')->findOrFail($lotteryId);
        if ($response = $this->redirectIfLotteryDrawDateBlocked($lottery, 'reserves.create')) {
            return $response;
        }
        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_lottery', $lottery);

        return view('reserves.add_information', compact('entity', 'lottery'));
    }
}