<?php

namespace App\Http\Controllers;

use App\Models\Lottery;
use App\Models\LotteryType;
use App\Models\Administration;
use App\Models\LotteryResult;
use App\Jobs\NotifyLotteryResultsPublishedJob;
use App\Services\EntityLotteryPrizeService;
use App\Services\NavidadScrapingService;
use App\Support\LotteryPanelAccess;
use App\Rules\ValidCalendarDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class LotteryController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Mostrar lista de sorteos
     */
    public function index()
    {
        $lotteryAccess = LotteryPanelAccess::for(auth()->user());
        $query = Lottery::with(['lotteryType'])
            ->orderBy('draw_date', 'desc')
            ->orderBy('id', 'desc');

        if ($lotteryAccess['canViewEntityPrizesOnly'] ?? false) {
            $entityIds = auth()->user()->accessibleEntityIds();
            if (empty($entityIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('reserves', fn ($q) => $q->whereIn('entity_id', $entityIds));
            }
        }

        $lotteries = $query->get();

        return view('lottery.index', compact('lotteries', 'lotteryAccess'));
    }

    /**
     * Mostrar formulario para crear sorteo
     */
    public function create()
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $lotteryTypes = LotteryType::where('is_active', true)->get();

        return view('lottery.add', compact('lotteryTypes'));
    }

    /**
     * Guardar nuevo sorteo
     */
    public function store(Request $request)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'draw_date' => ValidCalendarDate::afterToday(),
            'draw_time' => 'required',
            'deadline_date' => array_merge(ValidCalendarDate::afterToday(false), ['before:draw_date']),
            'ticket_price' => 'required|numeric|min:0',
            // 'lottery_type_code' => 'required|string|in:J,X,S,N,B,V',
            'is_special' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'lottery_type_id' => 'required|integer',
            // 'total_tickets' => 'required|integer|min:1',
            // 'prize_description' => 'required|string',
            // 'prize_value' => 'required|numeric|min:0',
        ], [
            'deadline_date.before' => 'La fecha límite debe ser como máximo el día anterior al sorteo (23:59).',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->all();
        $data['sold_tickets'] = 0;
        $data['status'] = 1; // 1 = active
        $data['is_special'] = $request->has('is_special') ? true : false;

        // Manejar la imagen si se subió
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $data['image'] = $imageName;
        }

        Lottery::create($data);

        return redirect()->route('lotteries.index')
            ->with('success', 'Sorteo creado exitosamente');
    }

    /**
     * Mostrar sorteo específico
     */
    public function show(Lottery $lottery, EntityLotteryPrizeService $entityPrizeService)
    {
        $lottery->load(['lotteryType']);
        $lotteryAccess = LotteryPanelAccess::for(auth()->user());

        if ($lotteryAccess['canViewEntityPrizesOnly'] ?? false) {
            $entity = $entityPrizeService->resolveViewEntity(auth()->user());
            if (! $entity || ! $entityPrizeService->entityParticipatesInLottery($entity, $lottery)) {
                abort(403, 'No tiene acceso al premio de este sorteo.');
            }

            $scrutiny = $entityPrizeService->getScrutinyForEntity($entity, $lottery);
            $participationStats = $entityPrizeService->participationStats($entity, $lottery);
            $entityResults = $scrutiny?->detailedResults ?? collect();
            $totalEntityPrize = $entityResults->sum(fn ($result) => $entityPrizeService->recalculateDecimos($result, $lottery)['premio_total']);

            $prizeSetting = app(\App\Services\EntityLotteryPrizePaymentService::class)
                ->getSettings((int) $entity->id, (int) $lottery->id);

            return view('lottery.show_entity_prizes', compact(
                'lottery',
                'entity',
                'scrutiny',
                'participationStats',
                'entityResults',
                'totalEntityPrize',
                'entityPrizeService',
                'prizeSetting'
            ));
        }

        return view('lottery.show', compact('lottery', 'lotteryAccess'));
    }

    /**
     * Mostrar formulario para editar sorteo
     */
    public function edit(Lottery $lottery)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $lotteryTypes = LotteryType::where('is_active', true)->get();

        return view('lottery.edit', compact('lottery', 'lotteryTypes'));
    }

    /**
     * Actualizar sorteo
     */
    public function update(Request $request, Lottery $lottery)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'draw_date' => ValidCalendarDate::rules(true),
            'draw_time' => 'required',
            'deadline_date' => array_merge(ValidCalendarDate::rules(false), ['before:draw_date']),
            'ticket_price' => 'required|numeric|min:0',
            // 'lottery_type_code' => 'required|string|in:J,X,S,N,B,V',
            'is_special' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'lottery_type_id' => 'required|integer',
            'status' => 'nullable|integer|in:1,2,3,4', // 1=active, 2=inactive, 3=completed, 4=cancelled
            // 'total_tickets' => 'required|integer|min:1',
            // 'prize_description' => 'required|string',
            // 'prize_value' => 'required|numeric|min:0',
        ], [
            'deadline_date.before' => 'La fecha límite debe ser como máximo el día anterior al sorteo (23:59).',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $request->all();
        $data['is_special'] = $request->has('is_special') ? true : false;

        // Manejar la imagen si se subió
        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe
            if ($lottery->image && File::exists(public_path('uploads/' . $lottery->image))) {
                File::delete(public_path('uploads/' . $lottery->image));
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads'), $imageName);
            $data['image'] = $imageName;
        }

        $lottery->update($data);

        return redirect()->route('lotteries.index')
            ->with('success', 'Sorteo actualizado exitosamente');
    }

    /**
     * Eliminar sorteo
     */
    public function destroy(Lottery $lottery)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        // Eliminar imagen si existe
        if ($lottery->image && File::exists(public_path('uploads/' . $lottery->image))) {
            File::delete(public_path('uploads/' . $lottery->image));
        }

        $lottery->delete();

        return redirect()->route('lotteries.index')
            ->with('success', 'Sorteo eliminado exitosamente');
    }

    /**
     * Cambiar estado del sorteo
     */
    public function changeStatus(Request $request, Lottery $lottery)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $request->validate([
            'status' => 'required|integer|in:1,2,3,4' // 1=active, 2=inactive, 3=completed, 4=cancelled
        ]);

        $lottery->update(['status' => $request->status]);

        return redirect()->back()
            ->with('success', 'Estado del sorteo actualizado');
    }

    /**
     * Eliminar imagen del sorteo
     */
    public function deleteImage(Lottery $lottery)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        if ($lottery->image && File::exists(public_path('uploads/' . $lottery->image))) {
            // Eliminar archivo físico
            File::delete(public_path('uploads/' . $lottery->image));
            
            // Actualizar base de datos
            $lottery->update(['image' => null]);
            
            return response()->json(['success' => true, 'message' => 'Imagen eliminada correctamente']);
        }
        
        return response()->json(['success' => false, 'message' => 'No hay imagen para eliminar']);
    }

    /**
     * Generar sorteos basado en un rango de fechas
     */
    public function generate(Request $request)
    {
        LotteryPanelAccess::ensureCanManageLotteries();

        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Convertir fechas al formato requerido por la API (YYYYMMDD)
            $dateFrom = date('Ymd', strtotime($request->date_from));
            $dateTo = date('Ymd', strtotime($request->date_to));

            // Construir URL de la API (celebrados=true: en pruebas reduce errores 503 respecto a celebrados=false)
            $apiUrl = "https://www.loteriasyapuestas.es/servicios/buscadorSorteos?game_id=LNAC&celebrados=false&fechaInicioInclusiva={$dateFrom}&fechaFinInclusiva={$dateTo}";

            // Realizar petición HTTP usando Guzzle
            $response = Http::withOptions(['verify' => config('app.http_verify_ssl')])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout(30)
            ->get($apiUrl);
            
            if (!$response->successful()) {
                throw new \Exception('Error HTTP: ' . $response->status() . ' - Respuesta: ' . $response->body());
            }
            
            $responseBody = $response->body();

            // Log para debug
            \Log::info('API Request', [
                'url' => $apiUrl,
                'http_code' => $response->status(),
                'response_length' => strlen($responseBody)
            ]);

            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error al decodificar JSON de la API');
            }

            // Log para debug
            \Log::info('API Response for dates ' . $dateFrom . ' to ' . $dateTo, [
                'data_count' => is_array($data) ? count($data) : 'not array',
                'first_item' => is_array($data) && count($data) > 0 ? $data[0] : 'no items',
                'data_structure' => $data
            ]);

            $createdCount = 0;
            $updatedCount = 0;

            // Procesar cada sorteo del JSON (el JSON es un array directo)
            if (is_array($data) && count($data) > 0) {
                foreach ($data as $sorteo) {
                    // Validar que el sorteo tenga los datos mínimos necesarios
                    if (!isset($sorteo['fecha_sorteo']) || !isset($sorteo['id_sorteo'])) {
                        continue; // Saltar si no tiene fecha o ID de sorteo
                    }

                    // Extraer fecha y hora del sorteo
                    $fechaSorteo = $sorteo['fecha_sorteo'];
                    $horaSorteo = null;
                    
                    // Extraer hora de la fecha_sorteo si contiene hora
                    if (strpos($fechaSorteo, ' ') !== false) {
                        $fechaHora = explode(' ', $fechaSorteo);
                        $fechaSorteo = $fechaHora[0];
                        $horaSorteo = $fechaHora[1];
                    }

                    // Convertir fecha y hora a formato datetime
                    $drawDateTime = null;
                    if ($horaSorteo) {
                        $drawDateTime = $fechaSorteo . ' ' . $horaSorteo;
                    } else {
                        $drawDateTime = $fechaSorteo . ' 00:00:00';
                    }

                    // Preparar datos del sorteo
                    // Generar nombre del sorteo: num_sorteo/últimas 2 cifras del año
                    $numSorteo = $sorteo['num_sorteo'] ?? '';
                    $anyo = $sorteo['anyo'] ?? date('Y');
                    $anioCortado = substr($anyo, -2); // Últimas 2 cifras del año
                    $nombreSorteo = str_pad($numSorteo, 3, '0', STR_PAD_LEFT) . '/' . $anioCortado;
                    
                    $lotteryData = [
                        'name' => $nombreSorteo,
                        'description' => $sorteo['nombre'] ?? '', // Usar día de la semana como descripción
                        'draw_date' => $fechaSorteo,
                        'deadline_date' => $fechaSorteo,
                        'draw_time' => $horaSorteo ? $horaSorteo : '00:00:00',
                        'ticket_price' => $sorteo['precioDecimo'], // Precio del décimo desde JSON
                        'lottery_type_code' => $sorteo['tipoSorteo'] ?? 'S', // Código del tipo desde JSON
                        'is_special' => isset($sorteo['premio_especial']) && $sorteo['premio_especial'] > 0, // Es especial si tiene premio especial
                        'status' => 1,
                        'sold_tickets' => 0,
                    ];

                    // Buscar si ya existe un sorteo para esta fecha o con el mismo nombre
                    $existingLottery = Lottery::where('draw_date', $fechaSorteo)
                        ->orWhere('name', $lotteryData['name'])
                        ->first();

                    // Buscar o crear tipo de sorteo específico
                    $typeIdentifier = $lotteryData['ticket_price'] . '_' . $lotteryData['lottery_type_code'];
                    if ($lotteryData['is_special'] && $lotteryData['lottery_type_code'] == 'S' && $lotteryData['ticket_price'] == 15) {
                        $typeIdentifier .= '_ESPECIAL';
                    }
                    
                    $lotteryType = $this->findOrCreateLotteryType($typeIdentifier, $sorteo);
                    $lotteryData['lottery_type_id'] = $lotteryType->id;

                    if ($existingLottery) {
                        // Actualizar sorteo existente
                        $existingLottery->update($lotteryData);
                        $updatedCount++;
                    } else {
                        // Crear nuevo sorteo
                        Lottery::create($lotteryData);
                        $createdCount++;
                    }
                }
            } else {
                return redirect()->route('lotteries.index')
                    ->with('warning', 'No se encontraron sorteos en el rango de fechas especificado.');
            }

            $message = "Proceso completado. Sorteos creados: {$createdCount}, Sorteos actualizados: {$updatedCount}";
            return redirect()->route('lotteries.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al generar sorteos: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mostrar lista de administraciones para selección
     */
    public function showAdministrations(Request $request)
    {
        if ($redirect = $this->redirectIfImplicitAdministration($request, 'lottery.results')) {
            return $redirect;
        }

        $administrations = Administration::forUser(auth()->user())
            ->where('status', 1)
            ->get();
        return view('lottery.administrations', compact('administrations'));
    }

    /**
     * Procesar selección de administración
     */
    public function selectAdministration(Request $request)
    {
        $request->validate([
            'administration_id' => 'required|integer|exists:administrations,id'
        ]);

        // Guardar la administración seleccionada en la sesión con su manager
        $administration = Administration::with('manager')
            ->forUser(auth()->user())
            ->findOrFail($request->administration_id);
        $request->session()->put('selected_administration', $administration);

        return redirect()->route('lottery.results')
            ->with('success', 'Administración seleccionada: ' . $administration->name);
    }

    /**
     * Obtener y guardar resultados de lotería desde la API
     */
    public function fetchAndSaveResults(Request $request)
    {
        try {
            $request->validate([
                'lottery_id' => 'required|integer|exists:lotteries,id',
                'api_url' => 'required|url'
            ]);

            $lottery = Lottery::findOrFail($request->lottery_id);

            // Realizar petición a la API
            $response = Http::withOptions(['verify' => config('app.http_verify_ssl')])
                ->timeout(30)
                ->get($request->api_url);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener datos de la API: ' . $response->status()
                ], 400);
            }

            $data = $response->json();

            // Verificar si ya existe un resultado para este sorteo
            $existingResult = LotteryResult::where('lottery_id', $lottery->id)->first();

            if ($existingResult) {
                // Actualizar resultado existente
                $this->updateLotteryResult($existingResult, $data);
                $message = 'Resultados actualizados exitosamente';
            } else {
                // Crear nuevo resultado
                $this->createLotteryResult($lottery, $data);
                $message = 'Resultados guardados exitosamente';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'lottery_id' => $lottery->id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resultados específicos de un sorteo desde la API
     */
    public function fetchSpecificResults(Request $request)
    {
        try {
            $request->validate([
                'lottery_id' => 'required|integer|exists:lotteries,id',
                'api_url' => 'required|url'
            ]);

            $lottery = Lottery::findOrFail($request->lottery_id);

            // Realizar petición a la API
            $response = Http::withOptions(['verify' => config('app.http_verify_ssl')])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout(30)
            ->get(html_entity_decode($request->api_url));

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener datos de la API: ' . $response->status()
                ], 400);
            }

            $data = $response->json();

            // return response()->json($data,422);

            // Filtrar por el número de sorteo específico
            $filteredData = null;
            if (is_array($data)) {
                // Extraer el número de sorteo del name (formato: "102/25" -> "102")
                $nameParts = explode('/', $lottery->name);
                $lotteryNumSorteo = isset($nameParts[0]) ? ltrim($nameParts[0], '0') : '';
                
                \Log::info("Buscando sorteo - Lottery name: {$lottery->name}, Num sorteo extraído: {$lotteryNumSorteo}");
                \Log::info("Total sorteos en respuesta: " . count($data));
                
                foreach ($data as $sorteo) {
                    // Comparar el num_sorteo del JSON con el número extraído del name
                    $jsonNumSorteo = isset($sorteo['num_sorteo']) ? ltrim($sorteo['num_sorteo'], '0') : '';
                    
                    \Log::info("Comparando - JSON num_sorteo: '{$jsonNumSorteo}' vs Lottery num_sorteo: '{$lotteryNumSorteo}'");
                    
                    if ($jsonNumSorteo == $lotteryNumSorteo) {
                        $filteredData = $sorteo;
                        \Log::info("¡Sorteo encontrado! num_sorteo: {$jsonNumSorteo}");
                        break;
                    }
                }
            }

            if (!$filteredData || !isset($filteredData['primerPremio']['decimo'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron resultados para el sorteo número ' . $lottery->name
                ], 404);
            }

            // Verificar si ya existe un resultado para este sorteo
            $existingResult = LotteryResult::where('lottery_id', $lottery->id)->first();

            if ($existingResult) {
                // Actualizar resultado existente
                \Log::info("Actualizando resultados para sorteo: " . $lottery->name . " (ID: " . $lottery->id . ")");
                $this->updateLotteryResult($existingResult, $filteredData);
                $message = 'Resultados actualizados exitosamente';
            } else {
                // Crear nuevo resultado
                \Log::info("Creando nuevos resultados para sorteo: " . $lottery->name . " (ID: " . $lottery->id . ")");
                $this->createLotteryResult($lottery, $filteredData);
                $message = 'Resultados guardados exitosamente';
            }

            // Obtener los datos guardados de la base de datos para incluir las pedreas
            $savedResult = LotteryResult::where('lottery_id', $lottery->id)->first();
            
            // Eliminar duplicados de las extracciones antes de devolver los datos
            $responseData = $filteredData; // Datos originales de la API
            
            // Aplicar eliminación de duplicados a las extracciones
            if (isset($responseData['extraccionesDeCincoCifras'])) {
                $responseData['extraccionesDeCincoCifras'] = $this->removeDuplicateExtractions($responseData['extraccionesDeCincoCifras']);
            }
            if (isset($responseData['extraccionesDeCuatroCifras'])) {
                $responseData['extraccionesDeCuatroCifras'] = $this->removeDuplicateExtractions($responseData['extraccionesDeCuatroCifras']);
            }
            if (isset($responseData['extraccionesDeTresCifras'])) {
                $responseData['extraccionesDeTresCifras'] = $this->removeDuplicateExtractions($responseData['extraccionesDeTresCifras']);
            }
            if (isset($responseData['extraccionesDeDosCifras'])) {
                $responseData['extraccionesDeDosCifras'] = $this->removeDuplicateExtractions($responseData['extraccionesDeDosCifras']);
            }
            
            if ($savedResult) {
                // Agregar las pedreas a la respuesta si existen
                if (!empty($savedResult->pedreas)) {
                    $responseData['pedreas'] = $savedResult->pedreas;
                    \Log::info("Pedreas agregadas a la respuesta: " . count($savedResult->pedreas) . " elementos");
                } else {
                    \Log::info("No hay pedreas en el resultado guardado");
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'lottery_id' => $lottery->id,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo resultado de lotería
     */
    private function createLotteryResult(Lottery $lottery, array $data)
    {
        $resultData = [
            'lottery_id' => $lottery->id,
            'results_date' => now(),
            'is_published' => true
        ];

        // Procesar premio especial
        if (isset($data['premioEspecial']) && is_array($data['premioEspecial'])) {
            $resultData['premio_especial'] = $data['premioEspecial'];
        }

        // Procesar otros premios
        $resultData['primer_premio'] = $data['primerPremio'] ?? null;
        $resultData['segundo_premio'] = $data['segundoPremio'] ?? null;
        $resultData['terceros_premios'] = $data['tercerosPremios'] ?? [];
        $resultData['cuartos_premios'] = $data['cuartosPremios'] ?? [];
        $resultData['quintos_premios'] = $data['quintosPremios'] ?? [];

        // Procesar extracciones (eliminar duplicados)
        $resultData['extracciones_cinco_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeCincoCifras'] ?? []);
        $resultData['extracciones_cuatro_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeCuatroCifras'] ?? []);
        $resultData['extracciones_tres_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeTresCifras'] ?? []);
        $resultData['extracciones_dos_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeDosCifras'] ?? []);

        // Procesar reintegros
        $resultData['reintegros'] = $data['reintegros'] ?? [];
        
        // Procesar pedreas para sorteos de Navidad
        if (isset($data['tipoSorteo']) && $data['tipoSorteo'] === 'N') {
            \Log::info("Sorteo de Navidad detectado, obteniendo pedreas...");
            $resultData['pedreas'] = $this->getPedreasForNavidadSorteo($data);
        } else {
            \Log::info("No es sorteo de Navidad (tipoSorteo: " . ($data['tipoSorteo'] ?? 'no definido') . "), saltando pedreas");
            $resultData['pedreas'] = [];
        }

        LotteryResult::create($resultData);

        NotifyLotteryResultsPublishedJob::dispatch($lottery->id, auth()->id());
    }

    /**
     * Actualizar resultado existente de lotería
     */
    private function updateLotteryResult(LotteryResult $result, array $data)
    {
        $updateData = [
            'results_date' => now()
        ];

        // Procesar premio especial
        if (isset($data['premioEspecial']) && is_array($data['premioEspecial'])) {
            $updateData['premio_especial'] = $data['premioEspecial'];
        }

        // Procesar otros premios
        $updateData['primer_premio'] = $data['primerPremio'] ?? null;
        $updateData['segundo_premio'] = $data['segundoPremio'] ?? null;
        $updateData['terceros_premios'] = $data['tercerosPremios'] ?? [];
        $updateData['cuartos_premios'] = $data['cuartosPremios'] ?? [];
        $updateData['quintos_premios'] = $data['quintosPremios'] ?? [];

        // Procesar extracciones (eliminar duplicados)
        $updateData['extracciones_cinco_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeCincoCifras'] ?? []);
        $updateData['extracciones_cuatro_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeCuatroCifras'] ?? []);
        $updateData['extracciones_tres_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeTresCifras'] ?? []);
        $updateData['extracciones_dos_cifras'] = $this->removeDuplicateExtractions($data['extraccionesDeDosCifras'] ?? []);

        // Procesar reintegros
        $updateData['reintegros'] = $data['reintegros'] ?? [];
        
        // Procesar pedreas para sorteos de Navidad
        if (isset($data['tipoSorteo']) && $data['tipoSorteo'] === 'N') {
            \Log::info("Sorteo de Navidad detectado en actualización, obteniendo pedreas...");
            $updateData['pedreas'] = $this->getPedreasForNavidadSorteo($data);
        } else {
            \Log::info("No es sorteo de Navidad en actualización (tipoSorteo: " . ($data['tipoSorteo'] ?? 'no definido') . "), saltando pedreas");
            $updateData['pedreas'] = [];
        }

        $result->update($updateData);

        NotifyLotteryResultsPublishedJob::dispatch((int) $result->lottery_id, auth()->id());
    }

    /**
     * Obtener pedreas para sorteos de Navidad mediante web scraping
     */
    private function getPedreasForNavidadSorteo($data)
    {
        try {
            // Verificar si es un sorteo de Navidad
            if (!isset($data['tipoSorteo']) || $data['tipoSorteo'] !== 'N') {
                return [];
            }
            
            // Obtener drawId del sorteo
            $drawId = $data['id_sorteo'] ?? null;
            if (!$drawId) {
                \Log::warning('No se encontró drawId para sorteo de Navidad');
                return [];
            }
            
            // Usar el servicio de scraping
            $scrapingService = new NavidadScrapingService();
            $pedreas = $scrapingService->getPedreasFromNavidadSorteo($drawId);
            
            // Formatear pedreas para el sistema
            $formattedPedreas = $scrapingService->formatPedreasForSystem($pedreas);
            
            // Filtrar números que ya tienen premios principales
            $filteredPedreas = $this->filterPedreasExcludingMainPrizes($formattedPedreas, $data);
            
            \Log::info("Pedreas obtenidas para sorteo de Navidad $drawId: " . count($filteredPedreas) . " (filtradas de " . count($formattedPedreas) . ")");
            
            return $filteredPedreas;
            
        } catch (\Exception $e) {
            \Log::error("Error obteniendo pedreas para sorteo de Navidad: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Filtrar pedreas excluyendo números que ya tienen premios principales
     */
    private function filterPedreasExcludingMainPrizes($pedreas, $data)
    {
        // Obtener números de premios principales
        $mainPrizeNumbers = [];
        
        // Primer premio
        if (isset($data['primerPremio']['decimo'])) {
            $mainPrizeNumbers[] = $data['primerPremio']['decimo'];
        }
        
        // Segundo premio
        if (isset($data['segundoPremio']['decimo'])) {
            $mainPrizeNumbers[] = $data['segundoPremio']['decimo'];
        }
        
        // Terceros premios
        if (isset($data['tercerosPremios']) && is_array($data['tercerosPremios'])) {
            foreach ($data['tercerosPremios'] as $tercero) {
                if (isset($tercero['decimo'])) {
                    $mainPrizeNumbers[] = $tercero['decimo'];
                }
            }
        }
        
        // Cuartos premios
        if (isset($data['cuartosPremios']) && is_array($data['cuartosPremios'])) {
            foreach ($data['cuartosPremios'] as $cuarto) {
                if (isset($cuarto['decimo'])) {
                    $mainPrizeNumbers[] = $cuarto['decimo'];
                }
            }
        }
        
        // Quintos premios
        if (isset($data['quintosPremios']) && is_array($data['quintosPremios'])) {
            foreach ($data['quintosPremios'] as $quinto) {
                if (isset($quinto['decimo'])) {
                    $mainPrizeNumbers[] = $quinto['decimo'];
                }
            }
        }
        
        // Filtrar pedreas que no estén en premios principales
        $filteredPedreas = [];
        foreach ($pedreas as $pedrea) {
            if (!in_array($pedrea['decimo'], $mainPrizeNumbers)) {
                $filteredPedreas[] = $pedrea;
            }
        }
        
        \Log::info("Números de premios principales excluidos: " . implode(', ', $mainPrizeNumbers));
        
        return $filteredPedreas;
    }

    /**
     * Mostrar resultados de un sorteo específico
     */
    public function showResults(Lottery $lottery)
    {
        $lottery->load(['result', 'lotteryType']);
        
        return view('lottery.show_results', compact('lottery'));
    }

    /**
     * Mostrar tabla de resultados de todos los sorteos
     */
    public function resultsTable()
    {
        $lotteries = Lottery::with(['result', 'lotteryType'])
            ->orderBy('draw_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        return view('lottery.results_table', compact('lotteries'));
    }

    /**
     * Mostrar vista de resultados de lotería (después de seleccionar administración)
     */
    public function showLotteryResults()
    {
        $lotteries = Lottery::with(['result', 'lotteryType'])
            ->orderBy('draw_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        
        return view('lottery.lottery_results', compact('lotteries'));
    }

    /**
     * Mostrar formulario para editar resultados de un sorteo específico
     */
    public function editLotteryResults($id)
    {
        $lottery = Lottery::with(['result', 'lotteryType'])->findOrFail($id);
        
        // Construir la URL de la API para este sorteo específico (celebrados=true: menos 503 al consultar el buscador)
        $apiUrl = "https://www.loteriasyapuestas.es/servicios/buscadorSorteos?game_id=LNAC&celebrados=true&fechaInicioInclusiva=" . $lottery->draw_date->format('Ymd') . "&fechaFinInclusiva=" . $lottery->draw_date->format('Ymd');

        // return $lottery->result;

        return view('lottery.edit_lottery_results', compact('lottery', 'apiUrl'));
    }

    /**
     * Guardar resultados editados manualmente
     */
    public function saveResults(Request $request)
    {
        try {
            $request->validate([
                'lottery_id' => 'required|integer|exists:lotteries,id',
                'premio_especial' => 'nullable|string',
                'primer_premio' => 'nullable|string',
                'primer_premio_serie' => 'nullable|string',
                'primer_premio_fraccion' => 'nullable|string',
                'segundo_premio' => 'nullable|string',
                'reintegros' => 'nullable|string',
                'terceros_premios' => 'nullable|string',
                'cuartos_premios' => 'nullable|string',
                'quintos_premios' => 'nullable|string',
                'extracciones_5_cifras' => 'nullable|string',
                'extracciones_4_cifras' => 'nullable|string',
                'extracciones_3_cifras' => 'nullable|string',
                'extracciones_2_cifras' => 'nullable|string',
                'pedrea' => 'nullable|string'
            ]);

            $lottery = Lottery::findOrFail($request->lottery_id);
            
            // Verificar si ya existe un resultado para este sorteo
            $existingResult = LotteryResult::where('lottery_id', $lottery->id)->first();

            $resultData = [
                'lottery_id' => $lottery->id,
                'results_date' => now(),
                'is_published' => true
            ];

            // Procesar premio especial
            if (!empty($request->premio_especial)) {
                $resultData['premio_especial'] = ['numero' => $request->premio_especial];
            }

            // Procesar primer premio
            if (!empty($request->primer_premio)) {
                $primerPremio = ['decimo' => $request->primer_premio];
                if (!empty($request->primer_premio_serie)) {
                    $primerPremio['serie'] = $request->primer_premio_serie;
                }
                if (!empty($request->primer_premio_fraccion)) {
                    $primerPremio['fraccion'] = $request->primer_premio_fraccion;
                }
                $resultData['primer_premio'] = $primerPremio;
            }

            // Procesar segundo premio
            if (!empty($request->segundo_premio)) {
                $resultData['segundo_premio'] = ['decimo' => $request->segundo_premio];
            }

            // Procesar reintegros
            if (!empty($request->reintegros)) {
                $reintegros = explode('-', $request->reintegros);
                $resultData['reintegros'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $reintegros);
            }

            // Procesar terceros premios
            if (!empty($request->terceros_premios)) {
                $terceros = explode('-', $request->terceros_premios);
                $resultData['terceros_premios'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $terceros);
            }

            // Procesar cuartos premios
            if (!empty($request->cuartos_premios)) {
                $cuartos = explode('-', $request->cuartos_premios);
                $resultData['cuartos_premios'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $cuartos);
            }

            // Procesar quintos premios
            if (!empty($request->quintos_premios)) {
                $quintos = explode('-', $request->quintos_premios);
                $resultData['quintos_premios'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $quintos);
            }

            // Procesar extracciones 5 cifras
            if (!empty($request->extracciones_5_cifras)) {
                $extracciones5 = explode('-', $request->extracciones_5_cifras);
                $resultData['extracciones_cinco_cifras'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $extracciones5);
            }

            // Procesar extracciones 4 cifras
            if (!empty($request->extracciones_4_cifras)) {
                $extracciones4 = explode('-', $request->extracciones_4_cifras);
                $resultData['extracciones_cuatro_cifras'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $extracciones4);
            }

            // Procesar extracciones 3 cifras
            if (!empty($request->extracciones_3_cifras)) {
                $extracciones3 = explode('-', $request->extracciones_3_cifras);
                $resultData['extracciones_tres_cifras'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $extracciones3);
            }

            // Procesar extracciones 2 cifras
            if (!empty($request->extracciones_2_cifras)) {
                $extracciones2 = explode('-', $request->extracciones_2_cifras);
                $resultData['extracciones_dos_cifras'] = array_map(function($numero) {
                    return ['decimo' => trim($numero)];
                }, $extracciones2);
            }

            if ($existingResult) {
                // Actualizar resultado existente
                $existingResult->update($resultData);
                $message = 'Resultados actualizados exitosamente';
            } else {
                // Crear nuevo resultado
                LotteryResult::create($resultData);
                $message = 'Resultados guardados exitosamente';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'results_date' => now()->format('d/m/Y H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar tipo de sorteo por código simple (J, X, S, N, B, V)
     */
    private function findOrCreateLotteryType($typeIdentifier, $sorteoData)
    {
        // Extraer solo el código simple del identificador completo
        $simpleCode = $sorteoData['tipoSorteo'] ?? 'S';
        
        // Buscar tipo existente por identificador simple
        $existingType = LotteryType::where('identificador', $simpleCode)->first();
        
        if ($existingType) {
            return $existingType;
        }
        
        // Si no existe, crear uno nuevo basado en la configuración
        $lotteryTypes = config('lotteryTypes');
        $typeConfig = null;
        
        // Buscar configuración que coincida con el código
        foreach ($lotteryTypes as $key => $config) {
            if ($config['codigo_sorteo'] == $simpleCode) {
                $typeConfig = $config;
                break;
            }
        }
        
        if (!$typeConfig) {
            // Si no hay configuración, crear un tipo genérico
            $typeConfig = [
                'nombre' => $this->getTypeNameByCode($simpleCode),
                'descripcion' => 'Sorteo generado automáticamente',
                'codigo_sorteo' => $simpleCode
            ];
        }
        
        // Crear el nuevo tipo de sorteo con identificador simple
        $newType = LotteryType::create([
            'name' => $typeConfig['nombre'],
            'identificador' => $simpleCode, // Solo el código simple: J, X, S, N, B, V
            'ticket_price' => 0, // Se establecerá en cada sorteo individual
            'prize_categories' => [], // Se calculará dinámicamente
            'is_active' => true
        ]);
        
        return $newType;
    }

    /**
     * Obtener nombre del tipo por código
     */
    private function getTypeNameByCode($code)
    {
        $names = [
            'J' => 'Sorteo de Jueves',
            'X' => 'Sorteo de Sábado', 
            'S' => 'Sorteo Extraordinario',
            'N' => 'Sorteo de Navidad',
            'B' => 'Sorteo del Niño',
            'V' => 'Sorteo de Vacaciones'
        ];
        
        return $names[$code] ?? 'Sorteo ' . $code;
    }
    
    /**
     * Eliminar duplicados de las extracciones
     */
    private function removeDuplicateExtractions($extractions)
    {
        if (!is_array($extractions) || empty($extractions)) {
            return [];
        }
        
        $unique = [];
        $seen = [];
        
        foreach ($extractions as $extraction) {
            if (isset($extraction['decimo'])) {
                $decimo = $extraction['decimo'];
                if (!in_array($decimo, $seen)) {
                    $unique[] = $extraction;
                    $seen[] = $decimo;
                }
            }
        }
        
        \Log::info("Extracciones filtradas: " . count($extractions) . " -> " . count($unique) . " (eliminados " . (count($extractions) - count($unique)) . " duplicados)");
        
        return $unique;
    }

    /**
     * API: Obtener todos los sorteos con resultados para la app móvil
     */
    public function apiGetAllResults()
    {
        $lotteries = Lottery::with(['result', 'lotteryType'])
            ->whereHas('result')
            ->orderBy('draw_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($lotteries);
    }
} 