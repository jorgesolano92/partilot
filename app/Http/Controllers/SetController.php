<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLotteryDrawDateGuard;
use App\Models\Set;
use App\Models\Entity;
use App\Models\Reserve;
use App\Models\Participation;
use App\Services\CommunicationEmailService;
use App\Mail\SetCreatedToEntityManagerMail;
use App\Support\SafeXml;
use App\Rules\ValidCalendarDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SetController extends Controller
{
    use HandlesLotteryDrawDateGuard;
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Mostrar lista de sets
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $filterAdministration = \App\Support\AdministrationListFilter::resolve($request, $user);

        $query = Set::with(['entity', 'reserve'])
            ->forUser($user);

        if ($filterAdministration) {
            $query->whereHas('entity', fn ($q) => $q->where('administration_id', $filterAdministration->id));
        }

        $sets = $query
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sets.index', compact('sets', 'filterAdministration'));
    }

    /**
     * Mostrar formulario para crear set - Paso 1: Seleccionar entidad
     */
    public function create(Request $request)
    {
        if ($entity = \App\Support\PanelSelectionResolver::resolveEntity($request->user())) {
            return $this->showReserveSelectionAfterEntity($request, $entity);
        }

        $entities = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->get();
        return view('sets.add', compact('entities'));
    }

    /**
     * Guardar selección de entidad y mostrar formulario de reserva - Paso 2
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
        $request->session()->forget(['selected_reserve', 'selected_reserve_id']);

        return $this->showReserveSelectionAfterEntity($request, $entity);
    }

    private function showReserveSelectionAfterEntity(Request $request, Entity $entity)
    {
        $this->putSelectedEntityInSession($request, $entity);
        $request->session()->forget(['selected_reserve', 'selected_reserve_id']);

        $reserves = Reserve::forUser($request->user())
            ->where('entity_id', $entity->id)
            ->where('status', 1)
            ->whereHas('lottery', fn ($q) => $q->openForOperations())
            ->with(['lottery'])
            ->get()
            ->sortByDesc(function ($reserve) {
                return $reserve->lottery->draw_date ?? now();
            });

        $reserveTotalsAndAvailable = [];
        foreach ($reserves as $reserve) {
            $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
            $total = max(
                (float) $reserve->total_amount,
                $numNumbers > 0 ? round($numNumbers * (float) $reserve->reservation_amount, 2) : (float) $reserve->total_amount
            );
            $used = (float) Set::where('reserve_id', $reserve->id)->sum('total_amount');
            $available = max(0, $total - $used);
            $reserveTotalsAndAvailable[$reserve->id] = ['total' => $total, 'available' => $available];
        }

        return view('sets.add_reserve', compact('reserves', 'reserveTotalsAndAvailable'));
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
        $request->session()->forget(['selected_reserve', 'selected_reserve_id']);

        return response()->json(['success' => true]);
    }

    /**
     * Mostrar formulario para seleccionar reserva - Paso 2
     */
    public function add_reserve()
    {
        $entityId = session('selected_entity_id');

        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('sets.create')
                ->with('error', 'Error: No se encontró la entidad seleccionada');
        }

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);
        session(['selected_entity' => $entity]);

        // Obtener reservas activas de la entidad
        $reserves = Reserve::forUser(auth()->user())
            ->where('entity_id', $entity->id)
            ->where('status', 1) // confirmed
            ->whereHas('lottery', fn ($q) => $q->openForOperations())
            ->with(['lottery'])
            ->orderBy('lottery.draw_date','desc')
            ->get();

        // Total y disponible por reserva
        $reserveTotalsAndAvailable = [];
        foreach ($reserves as $reserve) {
            $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
            $total = max(
                (float) $reserve->total_amount,
                $numNumbers > 0 ? round($numNumbers * (float) $reserve->reservation_amount, 2) : (float) $reserve->total_amount
            );
            $used = (float) Set::where('reserve_id', $reserve->id)->sum('total_amount');
            $available = max(0, $total - $used);
            $reserveTotalsAndAvailable[$reserve->id] = ['total' => $total, 'available' => $available];
        }

        return view('sets.add_reserve', compact('reserves', 'reserveTotalsAndAvailable'));
    }

    /**
     * Guardar selección de reserva y mostrar formulario de configuración - Paso 3
     */
    public function store_reserve(Request $request)
    {
        $request->validate([
            'reserve_id' => 'required|integer|exists:reserves,id'
        ]);

        $entityId = session('selected_entity_id');
        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('sets.create')
                ->with('error', 'Error: No se encontraron los datos de entidad o reserva');
        }

        $reserve = Reserve::with(['lottery', 'entity'])
            ->forUser(auth()->user())
            ->findOrFail($request->reserve_id);

        if ($reserve->entity_id !== (int) $entityId) {
            return redirect()->route('sets.create')
                ->with('error', 'La reserva seleccionada no pertenece a la entidad actual.');
        }

        if ($response = $this->redirectIfReserveLotteryBlocked($reserve, 'sets.create')) {
            return $response;
        }

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);

        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_reserve', $reserve);
        $request->session()->put('selected_reserve_id', $reserve->id);

        // return view('sets.add_information', compact('entity', 'reserve'));
        return redirect('sets/add/information');
    }

    /**
     * Guardar selección de reserva via AJAX
     */
    public function store_reserve_ajax(Request $request)
    {
        $request->validate([
            'reserve_id' => 'required|integer|exists:reserves,id'
        ]);

        $entityId = session('selected_entity_id');
        if (!$entityId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar una entidad válida antes de elegir la reserva.'
            ], 422);
        }

        $reserve = Reserve::with(['lottery', 'entity'])
            ->forUser(auth()->user())
            ->findOrFail($request->reserve_id);

        if ($reserve->entity_id !== (int) $entityId) {
            return response()->json([
                'success' => false,
                'message' => 'La reserva seleccionada no pertenece a la entidad actual.'
            ], 422);
        }

        if ($response = $this->jsonIfLotteryDrawDateBlocked($reserve->lottery)) {
            return $response;
        }

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);

        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_reserve', $reserve);
        $request->session()->put('selected_reserve_id', $reserve->id);

        return response()->json(['success' => true]);
    }

    /**
     * Mostrar formulario para configurar set - Paso 3
     */
    public function add_information()
    {
        $entityId = session('selected_entity_id');
        $reserveId = session('selected_reserve_id');

        if (!$entityId || !$reserveId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('sets.create')
                ->with('error', 'Error: No se encontraron los datos de entidad o reserva. Por favor, selecciona una entidad y reserva.');
        }

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);
        $reserve = Reserve::with(['lottery', 'entity'])
            ->forUser(auth()->user())
            ->findOrFail($reserveId);

        if ($reserve->entity_id !== $entity->id) {
            return redirect()->route('sets.create')
                ->with('error', 'La reserva seleccionada no pertenece a la entidad actual.');
        }

        if ($response = $this->redirectIfReserveLotteryBlocked($reserve, 'sets.create')) {
            return $response;
        }

        session([
            'selected_entity' => $entity,
            'selected_reserve' => $reserve,
        ]);

        // Total reserva = importe por número × cantidad de números (por si total_amount se guardó con lógica antigua)
        $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
        $reserveTotalAmount = max(
            (float) $reserve->total_amount,
            $numNumbers > 0 ? round($numNumbers * (float) $reserve->reservation_amount, 2) : (float) $reserve->total_amount
        );
        $usedAmount = (float) Set::where('reserve_id', $reserve->id)->sum('total_amount');
        $availableAmount = $reserveTotalAmount - $usedAmount;
        if ($availableAmount < 0) {
            $availableAmount = 0;
        }

        // Cargar las relaciones necesarias si no están cargadas
        if (!$reserve->relationLoaded('lottery')) {
            $reserve->load('lottery');
        }
        if (!$entity->relationLoaded('administration')) {
            $entity->load('administration');
        }

        return view('sets.add_information', compact('entity', 'reserve', 'availableAmount'));
    }

    /**
     * Guardar set completo - Paso final
     */
    public function store_information(Request $request)
    {
        // Obtener datos de sesión primero para la validación
        $entityId = $request->session()->get('selected_entity_id');
        $reserveId = $request->session()->get('selected_reserve_id');

        if (!$entityId || !$reserveId || !auth()->user()->canAccessEntity((int) $entityId)) {
            return redirect()->route('sets.create')
                ->with('error', 'Error: No se encontraron los datos de entidad o reserva');
        }

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($entityId);
        $reserve = Reserve::with(['lottery', 'entity'])
            ->forUser(auth()->user())
            ->findOrFail($reserveId);

        if ($reserve->entity_id !== $entity->id) {
            return redirect()->route('sets.create')
                ->with('error', 'La reserva seleccionada no pertenece a la entidad actual.');
        }

        if ($response = $this->redirectIfReserveLotteryBlocked($reserve, 'sets.create')) {
            return $response;
        }

        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_reserve', $reserve);

        $validated = $request->validate([
            'set_name' => 'required|string|max:255',
            'played_amount' => 'nullable|numeric|min:0',
            'donation_amount' => 'nullable|numeric|min:0',
            'total_participation_amount' => 'nullable|numeric|min:0',
            'total_participations' => 'required|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'physical_participations' => 'nullable|integer|min:0',
            'digital_participations' => 'nullable|integer|min:0',
            'deadline_date' => array_merge(ValidCalendarDate::rules(true), [new \App\Rules\DeadlineBeforeLottery($reserve->id)])
        ]);


        // Total reserva = importe por número × cantidad de números
        $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
        $reserveTotalAmount = max(
            (float) $reserve->total_amount,
            $numNumbers > 0 ? round($numNumbers * (float) $reserve->reservation_amount, 2) : (float) $reserve->total_amount
        );
        $usedAmount = (float) Set::where('reserve_id', $reserve->id)->sum('total_amount');
        $availableAmount = $reserveTotalAmount - $usedAmount;
        if ($availableAmount < 0) {
            $availableAmount = 0;
        }
        if ($validated['total_amount'] > $availableAmount) {
            return back()->withInput()->withErrors(['total_amount' => 'El importe del set supera el disponible para esta reserva (total reserva: ' . number_format($reserveTotalAmount, 2) . ' €, ya usado: ' . number_format($usedAmount, 2) . ' €, máximo para este set: ' . number_format($availableAmount, 2) . ' €)'])
                ->with(['availableAmount' => $availableAmount, 'entity' => $entity, 'reserve' => $reserve]);
        }
        $createdAt = now();
        $tickets = \App\Models\Set::generateTickets($entity->id, $reserve->id, $createdAt, $validated['total_participations']);
        $setData = array_merge($validated, [
            'entity_id' => $entity->id,
            'reserve_id' => $reserve->id,
            'status' => 1, // Activo por defecto
            'created_at' => $createdAt,
            'tickets' => $tickets
        ]);

        $set = Set::create($setData);

        // Comunicación pendiente (según especificación): email al gestor principal de la entidad
        // con detalles del set al crearlo.
        try {
            $entityManagerUser = $entity->manager?->user;
            if ($entityManagerUser && !empty($entityManagerUser->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $entityManagerUser->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $entityManagerUser,
                    messageType: 'set_created',
                    templateKey: null,
                    mailClass: SetCreatedToEntityManagerMail::class,
                    mailPayload: ['set_id' => $set->id],
                    context: ['set_id' => $set->id, 'entity_id' => $entity->id, 'reserve_id' => $reserve->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando email set a gestor entidad: ' . $e->getMessage());
        }

        // Limpiar sesión
        $request->session()->forget(['selected_entity', 'selected_reserve', 'selected_entity_id', 'selected_reserve_id']);

        return redirect()->route('sets.index')
            ->with('success', 'Set creado exitosamente');
    }

    /**
     * Mostrar set específico
     */
    public function show(Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            abort(403, 'No tienes permisos para ver este set.');
        }

        $set->load(['entity', 'reserve']);
        return view('sets.show', compact('set'));
    }

    /**
     * Mostrar formulario para editar set
     */
    public function edit(Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            abort(403, 'No tienes permisos para editar este set.');
        }

        $entities = Entity::forUser(auth()->user())->get();
        $reserves = Reserve::forUser(auth()->user())->get();
        // Total reserva = importe por número × cantidad de números
        $reserve = $set->reserve;
        $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
        $reserveTotalAmount = max(
            (float) $reserve->total_amount,
            $numNumbers > 0 ? round($numNumbers * (float) $reserve->reservation_amount, 2) : (float) $reserve->total_amount
        );
        $usedByOthers = (float) Set::where('reserve_id', $set->reserve_id)->where('id', '!=', $set->id)->sum('total_amount');
        $availableAmount = $reserveTotalAmount - $usedByOthers;
        if ($availableAmount < 0) {
            $availableAmount = 0;
        }
        return view('sets.edit', compact('set', 'entities', 'reserves', 'availableAmount'));
    }

    /**
     * Actualizar set. Solo se puede modificar la fecha límite de cierre de venta.
     */
    public function update(Request $request, Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            abort(403, 'No tienes permisos para actualizar este set.');
        }

        if ($response = $this->redirectIfSetLotteryBlocked($set)) {
            return $response;
        }

        $validated = $request->validate([
            'deadline_date' => array_merge(ValidCalendarDate::rules(true), [new \App\Rules\DeadlineBeforeLottery($set->reserve_id)])
        ]);

        $set->update(['deadline_date' => $validated['deadline_date']]);

        return redirect()->route('sets.show', $set->id)
            ->with('success', 'Fecha límite actualizada correctamente.');
    }

    /**
     * Eliminar set. Solo se puede eliminar si no hay participaciones asignadas, vendidas o pagadas.
     * Si las hay, el usuario debe realizar la devolución de todas ellas antes de poder eliminar.
     */
    public function destroy(Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            abort(403, 'No tienes permisos para eliminar este set.');
        }

        if ($response = $this->redirectIfSetLotteryBlocked($set)) {
            return $response;
        }

        $countBlocking = Participation::where('set_id', $set->id)
            ->whereIn('status', ['asignada', 'vendida', 'pagada'])
            ->count();

        if ($countBlocking > 0) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar el set: hay participaciones asignadas o vendidas. Debe realizar la devolución de todas ellas antes de poder eliminar el set.');
        }

        $set->delete();

        return redirect()->route('sets.index')
            ->with('success', 'Set eliminado exitosamente');
    }

    /**
     * Cambiar estado del set
     */
    public function changeStatus(Request $request, Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este set.'
            ], 403);
        }

        if ($response = $this->jsonIfSetLotteryBlocked($set)) {
            return $response;
        }

        $request->validate([
            'status' => 'required|in:0,1,2'
        ]);

        $set->update(['status' => (int)$request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Estado del set actualizado exitosamente'
        ]);
    }

    /**
     * Descargar archivo XML con formato parts.xml
     */
    public function downloadXml(Set $set)
    {
        if (!auth()->user()->canAccessEntity($set->entity_id)) {
            abort(403, 'No tienes permisos para exportar este set.');
        }

        // Cargar las relaciones necesarias
        $set->load(['entity.administration', 'reserve.lottery', 'reserve', 'participations', 'designFormats']);

        // Obtener datos necesarios
        $entity = $set->entity;
        $administration = $entity->administration;
        $reserve = $set->reserve;
        $lottery = $reserve->lottery;

        // Determinar si es set físico o digital
        $isDigital = $set->digital_participations > 0 && $set->physical_participations == 0;
        $isPhysical = $set->physical_participations > 0;

        // Crear el contenido XML
        $xmlContent = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xmlContent .= '<set>' . "\n";
        $xmlContent .= '  <titulo><![CDATA[' . $entity->name . ']]></titulo>' . "\n";
        $xmlContent .= '  <precio>' . number_format($set->played_amount, 2) . '</precio>' . "\n";
        $xmlContent .= '  <donativo>' . number_format($set->donation_amount, 2) . '</donativo>' . "\n";
        $xmlContent .= '  <fechasorteo>' . $lottery->draw_date->format('d/m/Y') . '</fechasorteo>' . "\n";

        // Agregar números de reserva con importe por número (Tarea 17)
        if ($reserve->reservation_numbers && count($reserve->reservation_numbers) > 0) {
            $xmlContent .= '  <numeros>';
            $numeroCount = count($reserve->reservation_numbers);
            $importePorNumero = $numeroCount > 0 ? round($set->played_amount / $numeroCount, 2) : 0;
            
            foreach ($reserve->reservation_numbers as $number) {
                // Formato: <numero importe="X.XX"><![CDATA[numero]]></numero>
                // Compatible con importación existente (lee contenido) y nuevo formato (lee atributo importe)
                $xmlContent .= '<numero importe="' . number_format($importePorNumero, 2) . '"><![CDATA[' . $number . ']]></numero>';
            }
            $xmlContent .= '<importe>' . number_format($reserve->total_amount, 2) . '</importe></numeros>' . "\n";
        } else {
            $xmlContent .= '  <numeros><importe>' . number_format($reserve->total_amount, 2) . '</importe></numeros>' . "\n";
        }

        // Tarea 16: Usar valor por defecto si administration->web está vacío
        $webUrl = !empty($administration->web) ? $administration->web : config('app.url', '');
        $xmlContent .= '  <urlweb><![CDATA[' . $webUrl . ']]></urlweb>' . "\n";
        $xmlContent .= '  <pagoweb>si</pagoweb>' . "\n";
        $xmlContent .= '  <pagowebpage><![CDATA[loteria-empresas-parti.php?ref=]]></pagowebpage>' . "\n";
        $xmlContent .= '  <participaciones>' . "\n";

        // Tarea 14 y 15: Generar participaciones con referencias reales
        if ($isPhysical) {
            // Para sets físicos: intentar usar participation_code de las participaciones con diseño
            $participations = $set->participations()
                ->whereNotNull('participation_code')
                ->orderBy('participation_number')
                ->get();

            if ($participations->count() > 0) {
                // Usar referencias reales de participaciones existentes
                foreach ($participations as $participation) {
                    $reference = $participation->participation_code ?? 'REF' . str_pad($participation->participation_number ?? $participation->id, 6, '0', STR_PAD_LEFT);
                    $xmlContent .= '   <p><s>' . ($participation->participation_number ?? $participation->id) . '</s><r>' . htmlspecialchars($reference, ENT_XML1, 'UTF-8') . '</r></p>' . "\n";
                }
            } else {
                // Si no hay participaciones creadas aún, usar tickets del set o generar REF
                if ($set->tickets && is_array($set->tickets) && count($set->tickets) > 0) {
                    foreach ($set->tickets as $ticket) {
                        $reference = $ticket['r'] ?? 'REF' . str_pad($ticket['n'] ?? 0, 6, '0', STR_PAD_LEFT);
                        $xmlContent .= '   <p><s>' . ($ticket['n'] ?? 0) . '</s><r>' . htmlspecialchars($reference, ENT_XML1, 'UTF-8') . '</r></p>' . "\n";
                    }
                } else {
                    // Fallback: generar REF000001, REF000002, etc.
                    for ($i = 1; $i <= $set->total_participations; $i++) {
                        $xmlContent .= '   <p><s>' . $i . '</s><r>REF' . str_pad($i, 6, '0', STR_PAD_LEFT) . '</r></p>' . "\n";
                    }
                }
            }
        } else {
            // Tarea 15: Para sets digitales, generar referencias únicas
            for ($i = 1; $i <= $set->total_participations; $i++) {
                // Generar referencia única: DIG + set_id + número de participación
                $reference = 'DIG' . str_pad($set->id, 6, '0', STR_PAD_LEFT) . str_pad($i, 6, '0', STR_PAD_LEFT);
                $xmlContent .= '   <p><s>' . $i . '</s><r>' . htmlspecialchars($reference, ENT_XML1, 'UTF-8') . '</r></p>' . "\n";
            }
        }

        $xmlContent .= '  </participaciones>' . "\n";
        $xmlContent .= '</set>';

        // Generar nombre del archivo
        $entityName = str_replace(' ', '_', $entity->name);
        $setName = str_replace(' ', '_', $set->set_name);
        $lotteryName = str_replace(' ', '_', $lottery->name);
        $drawDate = str_replace('/', '-', $lottery->draw_date->format('d-m-Y'));
        
        $filename = $entityName . '_' . $setName . '_' . $lotteryName . '_' . $drawDate . '.xml';

        // Retornar respuesta de descarga
        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * Obtener reservas por entidad
     */
    public function getReservesByEntity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);

        if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
            return response()->json([], 403);
        }

        $reserves = Reserve::forUser(auth()->user())
            ->where('entity_id', $request->entity_id)
            ->where('status', 1) // confirmed
            ->with(['lottery'])
            ->get();

        return response()->json($reserves);
    }

    /**
     * Importar participaciones desde un archivo XML y guardarlas en la columna tickets
     */
    public function importXml(Request $request, $id)
    {
        $request->validate([
            'xml_file' => 'required|file|mimes:xml',
        ]);

        $set = Set::with('reserve.lottery')
            ->forUser(auth()->user())
            ->findOrFail($id);

        if ($response = $this->redirectIfSetLotteryBlocked($set)) {
            return $response;
        }

        // Leer el archivo XML (sin resolver entidades externas — mitigación XXE)
        $xml = SafeXml::loadFromFile($request->file('xml_file')->getPathname());
        if ($xml === false) {
            return back()->withErrors(['error' => 'El archivo XML no es válido o no se pudo leer.']);
        }

        // Extraer los números de la reserva desde el XML
        $numerosReservaXml = [];
        if (isset($xml->numeros->numero)) {
            foreach ($xml->numeros->numero as $numero) {
                $numerosReservaXml[] = (string)$numero;
            }
        }

        // Números de la reserva en la base de datos
        $numerosReservaDB = is_array($set->reserve->reservation_numbers) ? $set->reserve->reservation_numbers : [];

        // Validar que ambos arrays sean iguales (sin importar el orden)
        if (count($numerosReservaXml) !== count($numerosReservaDB) || array_diff($numerosReservaXml, $numerosReservaDB)) {
            return back()->withErrors(['error' => 'Los números de la reserva en el XML no coinciden con los de la base de datos.']);
        }

        // Extraer participaciones del XML
        $participaciones = [];
        if (isset($xml->participaciones)) {
            foreach ($xml->participaciones->p as $p) {
                $participaciones[] = [
                    'n' => (string)($p->s ?? ''),
                    'r' => (string)($p->r ?? ''),
                ];
            }
        }

        // Validar cantidad de participaciones
        if (count($participaciones) != $set->total_participations) {
            return back()->withErrors(['error' => 'La cantidad de participaciones no coincide con el total del set.']);
        }

        // Guardar las participaciones en la columna tickets
        $set->tickets = $participaciones;
        $set->save();

        return redirect()->route('sets.edit', $set->id)->with('success', 'XML importado correctamente.');
    }

    /**
     * Obtener el precio de un set
     */
    public function getPrice(Request $request)
    {
        $request->validate([
            'set_id' => 'required|integer|exists:sets,id'
        ]);

        try {
            $set = Set::forUser(auth()->user())->findOrFail($request->set_id);
            
            $totalParticipation = $set->total_participation_amount ?? (($set->played_amount ?? 0) + ($set->donation_amount ?? 0));
            return response()->json([
                'success' => true,
                'played_amount' => $set->played_amount ?? 0,
                'total_participation_amount' => (float) $totalParticipation,
                'set_name' => $set->set_name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el precio del set: ' . $e->getMessage()
            ], 500);
        }
    }
}