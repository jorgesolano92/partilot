<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Http\Requests\CreateAdmin;
use App\Models\Administration;
use App\Support\ContactEmailRegistry;
use App\Rules\ValidCalendarDate;
use App\Support\SecureImageUpload;
use App\Models\User;
use App\Models\Manager;
use App\Mail\AdministrationWelcomeMail;
use App\Services\AdministrationBillingService;
use App\Services\AdministrationContractService;
use App\Services\AuditLogService;
use App\Services\CommunicationEmailService;
use App\Services\ManagerAccountService;

class AdministratorController extends Controller
{
    //

    public function create()
    {
        [$provinces, $provinceCityMap] = $this->getProvinceCityData();
        return view('admins.add', compact('provinces', 'provinceCityMap'));
    }

    public function edit($id)
    {
        $administration = Administration::with('manager')
            ->forUser(auth()->user())
            ->findOrFail($id);
        [$provinces, $provinceCityMap] = $this->getProvinceCityData();
        return view('admins.edit', compact('administration', 'provinces', 'provinceCityMap'));
    }

    public function update(Request $request, $id)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($id);

        // Saneamiento IBAN: quitar espacios, prefijo ES duplicado y dejar solo dígitos
        $accountValue = $this->sanitizeIbanAccount($request->account);
        $request->merge(['account' => $accountValue ?? '']);

        // Validar formato básico primero
        $request->validate([
            'web' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'receiving' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'admin_number' => ['nullable', 'string', 'regex:/^[0-9]{9}$/'],
            'society' => 'required|string|max:255',
            'nif_cif' => ['required', 'string', 'max:255', new \App\Rules\SpanishDocument],
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'address' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'account' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $digits = preg_replace('/\D/', '', (string) $value);
                    if ($digits !== '' && strlen($digits) !== 22) {
                        $fail('La cuenta bancaria debe estar vacía o tener exactamente 22 dígitos.');
                    }
                },
            ],
            'status' => 'nullable|in:-1,0,1',
            'panel_password' => 'nullable|string|min:8|confirmed',
            ...SecureImageUpload::rules('image'),
        ]);

        // Validar IBAN completo solo si se proporciona cuenta
        if ($accountValue) {
            $iban = 'ES' . $accountValue;
            $validator = \Validator::make(['iban' => $iban], [
                'iban' => [new \App\Rules\SpanishIban],
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }
        }

        $newEmail = $request->email;
        $panelUser = User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->first();

        if ($panelUser) {
            if (strcasecmp((string) $panelUser->email, (string) $newEmail) !== 0
                && ContactEmailRegistry::isTaken(
                    $newEmail,
                    $panelUser->id,
                    $administration->id,
                    null
                )) {
                return back()->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.'])->withInput();
            }
        } elseif (strcasecmp((string) $administration->email, (string) $newEmail) !== 0
            && ContactEmailRegistry::isTaken($newEmail, null, $administration->id, null)) {
            return back()->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.'])->withInput();
        }

        $data = [
            'web' => $request->web ?? '',
            'name' => $request->name,
            'receiving' => $request->receiving,
            'admin_number' => $request->admin_number ?? null,
            'society' => $request->society,
            'nif_cif' => $request->nif_cif,
            'province' => $request->province,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'address' => $request->address,
            'email' => $newEmail,
            'phone' => $request->phone,
            'account' => $accountValue ? ('ES' . $accountValue) : null,
            'status' => $request->status === '-1' ? null : ($request->status ?? null),
        ];

        if ($request->file('image')) {
            $data['image'] = SecureImageUpload::store($request->file('image'), 'images');
        }

        $previousAccount = $administration->account;
        $administration->update($data);

        app(AuditLogService::class)->logAdministrationFieldChange(
            $administration,
            auth()->user(),
            'account',
            $previousAccount,
            $data['account'] ?? null,
            $request
        );

        if ($panelUser) {
            $u = [
                'email' => $newEmail,
                'name' => Administration::panelDisplayNameFromParts($data['name'] ?? '', $data['society'] ?? ''),
                'phone' => $request->phone,
                'nif_cif' => $request->nif_cif,
            ];
            if ($request->filled('panel_password')) {
                $u['password'] = $request->panel_password;
            }
            $panelUser->update($u);
        }

        // Contraseña de panel definida: si la administración seguía pendiente, pasar a activa.
        if ($request->filled('panel_password')) {
            $administration->refresh();
            if ($administration->status === null || $administration->status === -1) {
                $administration->update(['status' => 1]);
            }
        }

        return redirect()->route('administrations.show', $administration->id)
            ->with('success', 'Administración actualizada correctamente');
    }

    /**
     * Envío manual (superadmin): correo con usuario de panel y enlace mágico para establecer contraseña.
     */
    public function sendPanelAccessEmail(Administration $administration)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($administration->id);

        $panelUser = User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->firstOrFail();

        try {
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $panelUser->email,
                recipientRole: 'gestor_administracion',
                recipientUser: $panelUser,
                messageType: 'administration_welcome',
                templateKey: null,
                mailClass: AdministrationWelcomeMail::class,
                mailPayload: ['administration_id' => $administration->id, 'user_id' => $panelUser->id],
                context: ['administration_id' => $administration->id],
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando acceso panel administración: '.$e->getMessage());

            return back()->with('error', 'No se pudo enviar el correo. Inténtelo más tarde o revise la configuración de correo.');
        }

        return back()->with('success', 'Se ha enviado el correo con el usuario de acceso y el enlace para establecer la contraseña.');
    }

    /**
     * Reenvío manual del contrato SaaS (superadmin).
     */
    public function sendContract(Administration $administration)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($administration->id);

        try {
            app(AdministrationContractService::class)->sendContractInvitation($administration, (int) auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Se ha enviado el correo con el enlace para firmar el contrato SaaS.');
    }

    public function editApi($id)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($id);
        $partilotDefaultAvailable = app(\App\Services\PrepagoCodigosService::class)->partilotConfigIsComplete();

        return view('admins.edit_api', compact('administration', 'partilotDefaultAvailable'));
    }

    public function updateApi(Request $request, $id)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($id);

        $validated = $request->validate([
            'prepago_integration_name' => 'nullable|string|max:255',
            'prepago_api_url' => 'nullable|string|max:500|url',
            'prepago_auth_method' => 'nullable|in:apikey',
            'prepago_api_prefix' => 'nullable|string|max:32',
            'prepago_api_key' => 'nullable|string|max:255',
            'prepago_use_partilot_default' => 'nullable|boolean',
            'prepago_integration_enabled' => 'nullable|boolean',
        ]);

        $data = [
            'prepago_integration_name' => $validated['prepago_integration_name'] ?? null,
            'prepago_api_url' => $validated['prepago_api_url'] ?? null,
            'prepago_auth_method' => $validated['prepago_auth_method'] ?? 'apikey',
            'prepago_api_prefix' => $validated['prepago_api_prefix'] ?? null,
            'prepago_use_partilot_default' => $request->boolean('prepago_use_partilot_default'),
            'prepago_integration_enabled' => $request->boolean('prepago_integration_enabled'),
        ];

        if ($request->filled('prepago_api_key')) {
            $data['prepago_api_key'] = $request->input('prepago_api_key');
        }

        $administration->update($data);

        return redirect()
            ->route('administrations.show', $administration->id)
            ->with('success', 'Configuración API de códigos de recarga actualizada correctamente.')
            ->withFragment('configuracion_api');
    }

    public function store_information(CreateAdmin $request)
    {
        // Saneamiento IBAN: quitar espacios, prefijo ES duplicado y dejar solo dígitos
        $accountValue = $this->sanitizeIbanAccount($request->account);

        $data = [
            'web' => isset($request->web) ? $request->validated()['web'] : '',
            'name' => $request->validated()['name'],
            'receiving' => $request->validated()['receiving'],
            'admin_number' => $request->validated()['admin_number'] ?? null,
            'society' => $request->validated()['society'],
            'nif_cif' => $request->validated()['nif_cif'],
            'province' => $request->validated()['province'],
            'city' => $request->validated()['city'],
            'postal_code' => $request->validated()['postal_code'],
            'address' => $request->validated()['address'],
            'email' => $request->validated()['email'],
            'phone' => $request->validated()['phone'],
            'account' => $accountValue ? ('ES' . $accountValue) : null,
        ];
        if ($request->file('image')) {
            $data['image'] = SecureImageUpload::store($request->file('image'), 'images');
        }

        $request->session()->put('administration', $data);

        return redirect()->route('administrations.add-manager');
    }

    public function store(Request $request)
    {
        if ($request->filled('web')) {
            $administrationSession = $request->session()->get('administration', []);
            $administrationSession['web'] = $request->input('web');
            $request->session()->put('administration', $administrationSession);
        }

        $administrationData = $request->session()->get('administration');
        if (! $administrationData || empty($administrationData['email'])) {
            return redirect()->route('administrations.create')
                ->with('error', 'Sesión expirada. Vuelva a introducir los datos de la administración.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['nullable', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'birthday' => ValidCalendarDate::birthday(false),
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
        ]);

        $email = $administrationData['email'];
        if (ContactEmailRegistry::isTaken($email)) {
            return back()->withErrors([
                'email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario. Use otro correo en el paso anterior.',
            ])->withInput();
        }

        $administrationData['status'] = null;

        $newAdministration = Administration::create($administrationData);

        $panelLoginBase = Administration::panelLoginUsernameFromParts(
            $newAdministration->receiving,
            $newAdministration->admin_number
        );
        $panelLoginUsername = Administration::ensureUniquePanelLoginUsername($panelLoginBase, null);

        if (strcasecmp((string) $request->input('email'), (string) $email) === 0) {
            return back()->withErrors([
                'email' => 'El email del gestor debe ser distinto al email de acceso del panel de la administración.',
            ])->withInput();
        }

        $managerUser = User::where('email', $request->input('email'))->first();
        if ($managerUser && $managerUser->isPanelAccount()) {
            return back()->withErrors([
                'email' => 'Ese email corresponde a una cuenta de acceso de panel. Use otro email para el gestor.',
            ])->withInput();
        }

        if (! $managerUser) {
            $managerUser = app(ManagerAccountService::class)->createUser([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'last_name2' => $request->input('last_name2'),
                'email' => $request->input('email'),
                'role' => User::ROLE_ADMINISTRATION,
                'status' => true,
                'phone' => $request->input('phone') ?: null,
                'nif_cif' => $request->input('nif_cif') ?: null,
                'birthday' => $request->input('birthday') ?: null,
            ], 'administración de lotería');
        }

        $panelUser = User::create([
            'name' => Administration::panelDisplayNameFromParts($administrationData['name'] ?? '', $administrationData['society'] ?? ''),
            'email' => $email,
            'password' => Str::password(32),
            'role' => User::ROLE_ADMINISTRATION,
            'panel_account_type' => 'administration',
            'panel_account_id' => $newAdministration->id,
            'panel_login_username' => $panelLoginUsername,
            'status' => true,
            'phone' => $administrationData['phone'] ?? null,
            'nif_cif' => $administrationData['nif_cif'] ?? null,
        ]);

        Manager::firstOrCreate([
            'user_id' => $panelUser->id,
            'administration_id' => $newAdministration->id,
            'entity_id' => null,
        ], [
            'is_primary' => false,
            'permission_sellers' => true,
            'permission_design' => true,
            'permission_statistics' => true,
            'permission_payments' => true,
            'status' => 1,
        ]);

        Manager::firstOrCreate([
            'user_id' => $managerUser->id,
            'administration_id' => $newAdministration->id,
            'entity_id' => null,
        ], [
            'is_primary' => true,
            'permission_sellers' => true,
            'permission_design' => true,
            'permission_statistics' => true,
            'permission_payments' => true,
            'status' => 1,
        ]);

        $request->session()->forget(['administration', 'manager']);

        app(AdministrationContractService::class)->initializeForNewAdministration($newAdministration->fresh(['manager.user']));

        return redirect('administrations')->with(
            'success',
            'Administración creada correctamente. Se ha enviado el contrato SaaS al correo de contacto. Envíe el correo de acceso al panel desde la ficha cuando corresponda.'
        );
    }

    /**
     * Asignar gestor principal cuando ya no existe (p. ej. usuario eliminado).
     */
    public function assignPrimaryManager(Request $request, $id)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($id);

        $existingPrimary = Manager::query()
            ->where('administration_id', $administration->id)
            ->where('is_primary', true)
            ->first();

        if ($existingPrimary && User::query()->whereKey($existingPrimary->user_id)->exists()) {
            return redirect()->route('administrations.edit-manager', $administration->id)
                ->with('info', 'Esta administración ya tiene un gestor principal asignado.');
        }

        if ($existingPrimary) {
            $existingPrimary->delete();
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => [
                'nullable',
                'string',
                'max:20',
                new \App\Rules\SpanishDocument,
                new \App\Rules\ManagerContactNif((int) $administration->id, null, null),
            ],
            'birthday' => ValidCalendarDate::birthday(false),
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'comment' => 'nullable|string|max:10000',
        ]);

        $panelEmail = trim((string) $administration->email);
        $managerEmail = trim((string) $request->input('email'));

        if ($panelEmail !== '' && strcasecmp($panelEmail, $managerEmail) === 0) {
            return back()->withErrors([
                'email' => 'El email del gestor debe ser distinto al correo de acceso al panel de la administración.',
            ])->withInput();
        }

        $norm = ContactEmailRegistry::normalize($managerEmail);
        $managerUser = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$norm])->first();

        if ($managerUser) {
            if ($managerUser->isPanelAccount()) {
                return back()->withErrors([
                    'email' => 'Ese email corresponde a una cuenta de acceso al panel. Use otro email para el gestor.',
                ])->withInput();
            }
        } else {
            if (ContactEmailRegistry::isTaken($managerEmail)) {
                return back()->withErrors([
                    'email' => 'Este correo no puede usarse (duplicado en el sistema).',
                ])->withInput();
            }

            $managerUser = app(ManagerAccountService::class)->createUser([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'last_name2' => $request->input('last_name2'),
                'email' => $managerEmail,
                'role' => User::ROLE_ADMINISTRATION,
                'status' => true,
                'phone' => $request->input('phone') ?: null,
                'nif_cif' => $request->input('nif_cif') ?: null,
                'birthday' => $request->input('birthday') ?: null,
                'comment' => $request->input('comment') ?: null,
            ], 'administración de lotería');
        }

        Manager::query()
            ->where('administration_id', $administration->id)
            ->where('user_id', '!=', $managerUser->id)
            ->update(['is_primary' => false]);

        Manager::updateOrCreate(
            [
                'user_id' => $managerUser->id,
                'administration_id' => $administration->id,
                'entity_id' => null,
            ],
            [
                'is_primary' => true,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
                'status' => 1,
            ]
        );

        return redirect()->route('administrations.edit-manager', $administration->id)
            ->with('success', 'Gestor principal registrado correctamente.');
    }

    private function sanitizeIbanAccount($value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $raw = preg_replace('/\s+/', '', trim($value));
        $raw = preg_replace('/^ES/i', '', $raw); // quitar prefijo ES si el usuario lo pegó
        $digits = preg_replace('/\D/', '', $raw);

        return $digits !== '' ? $digits : null;
    }

    private function getProvinceCityData(): array
    {
        try {
            return Cache::rememberForever('es_official_province_city_catalog', function () {
                $cachePath = 'catalogs/es_province_city_catalog.json';
                if (Storage::disk('local')->exists($cachePath)) {
                    $stored = json_decode((string) Storage::disk('local')->get($cachePath), true);
                    if (is_array($stored)
                        && isset($stored['provinces'], $stored['provinceCityMap'])
                        && is_array($stored['provinces'])
                        && is_array($stored['provinceCityMap'])) {
                        return [$stored['provinces'], $stored['provinceCityMap']];
                    }
                }

                $provincesUrl = 'https://raw.githubusercontent.com/codeforspain/ds-organizacion-administrativa/master/data/provincias.json';
                $citiesUrl = 'https://raw.githubusercontent.com/codeforspain/ds-organizacion-administrativa/master/data/municipios.json';

                $provincesResponse = Http::timeout(20)->get($provincesUrl);
                $citiesResponse = Http::timeout(25)->get($citiesUrl);
                if (!$provincesResponse->ok() || !$citiesResponse->ok()) {
                    throw new \RuntimeException('No se pudo descargar catálogo de provincias/municipios.');
                }

                $provincesData = $provincesResponse->json();
                $citiesData = $citiesResponse->json();
                if (!is_array($provincesData) || !is_array($citiesData)) {
                    throw new \RuntimeException('Catálogo de provincias/municipios inválido.');
                }

                $provinceNamesById = [];
                foreach ($provincesData as $p) {
                    $pid = trim((string) ($p['provincia_id'] ?? ''));
                    $name = trim((string) ($p['nombre'] ?? ''));
                    if ($pid !== '' && $name !== '') {
                        $provinceNamesById[$pid] = $name;
                    }
                }

                $provinceCityMap = [];
                foreach ($citiesData as $city) {
                    $provinceId = trim((string) ($city['provincia_id'] ?? ''));
                    $cityName = trim((string) ($city['nombre'] ?? ''));
                    if ($provinceId === '' || $cityName === '' || !isset($provinceNamesById[$provinceId])) {
                        continue;
                    }
                    $provinceName = $provinceNamesById[$provinceId];
                    $provinceCityMap[$provinceName] ??= [];
                    $provinceCityMap[$provinceName][$cityName] = true;
                }

                ksort($provinceCityMap, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($provinceCityMap as $province => $citiesSet) {
                    $cities = array_keys($citiesSet);
                    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
                    $provinceCityMap[$province] = $cities;
                }

                $provinces = array_keys($provinceCityMap);
                Storage::disk('local')->put($cachePath, json_encode([
                    'provinces' => $provinces,
                    'provinceCityMap' => $provinceCityMap,
                ], JSON_UNESCAPED_UNICODE));
                return [$provinces, $provinceCityMap];
            });
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar catálogo oficial provincias/localidades, usando fallback local.', [
                'error' => $e->getMessage(),
            ]);

            $administrationData = Administration::query()
                ->select('province', 'city')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();
            $entityData = \App\Models\Entity::query()
                ->select('province', 'city')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();
            $rows = $administrationData->concat($entityData);
            $provinceCityMap = [];
            foreach ($rows as $row) {
                $province = trim((string) $row->province);
                $city = trim((string) $row->city);
                if ($province === '' || $city === '') {
                    continue;
                }
                $provinceCityMap[$province] ??= [];
                $provinceCityMap[$province][$city] = true;
            }
            ksort($provinceCityMap);
            foreach ($provinceCityMap as $province => $citiesSet) {
                $cities = array_keys($citiesSet);
                sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
                $provinceCityMap[$province] = $cities;
            }
            return [array_keys($provinceCityMap), $provinceCityMap];
        }
    }

    /**
     * Cambiar estado (Activo/Inactivo/Pendiente) de la administración vía AJAX.
     */
    public function toggleStatus(Request $request, Administration $administration)
    {
        // Verificar permisos
        $administration = Administration::forUser(auth()->user())->findOrFail($administration->id);

        // Determinar el nuevo estado según el estado actual
        $currentStatus = $administration->status;

        // Lógica de toggle: null/-1 (Pendiente) -> 1 (Activo), 1 (Activo) -> 0 (Inactivo), 0 (Inactivo) -> 1 (Activo)
        $newStatus = match ($currentStatus) {
            null, -1 => 1,  // Pendiente -> Activo
            1 => 0,         // Activo -> Inactivo
            0 => 1,         // Inactivo -> Activo
            default => 1
        };

        $administration->update(['status' => $newStatus]);

        // Obtener texto y clase del nuevo estado
        $statusValue = $administration->fresh()->status;
        if ($statusValue === null || $statusValue === -1) {
            $statusText = 'Pendiente';
            $statusClass = 'secondary';
        } elseif ($statusValue == 1) {
            $statusText = 'Activo';
            $statusClass = 'success';
        } else {
            $statusText = 'Inactivo';
            $statusClass = 'danger';
        }

        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'status_text' => $statusText,
            'status_class' => $statusClass,
        ]);
    }

    /**
     * Verificar si el email ya está en uso en administraciones (para validación AJAX)
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'exclude_id' => 'nullable|integer',
        ]);

        $excludeAdminId = $request->exclude_id ? (int) $request->exclude_id : null;
        $panelUserId = null;
        if ($excludeAdminId) {
            $panelUserId = User::query()
                ->where('panel_account_type', 'administration')
                ->where('panel_account_id', $excludeAdminId)
                ->value('id');
        }

        $exists = ContactEmailRegistry::isTaken(
            $request->email,
            $panelUserId ? (int) $panelUserId : null,
            $excludeAdminId,
            null
        );

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este correo ya está en uso por otra administración, entidad o usuario' : null,
        ]);
    }

    public function updateBillingPayment(Request $request, Administration $administration)
    {
        $administration = Administration::forUser(auth()->user())->findOrFail($administration->id);

        $data = $request->validate([
            'billing_payment_mode' => 'required|in:card,remittance',
            'billing_remittance_frequency' => 'nullable|required_if:billing_payment_mode,remittance|in:monthly,biweekly',
        ]);

        if ($data['billing_payment_mode'] === AdministrationBillingService::MODE_REMITTANCE
            && ! app(AdministrationBillingService::class)->hasValidBillingIban($administration)) {
            return back()->with('error', 'Para activar remesa debe configurar un IBAN válido en los datos legales de la administración.');
        }

        $administration->forceFill([
            'billing_payment_mode' => $data['billing_payment_mode'],
            'billing_remittance_frequency' => $data['billing_payment_mode'] === AdministrationBillingService::MODE_REMITTANCE
                ? ($data['billing_remittance_frequency'] ?? AdministrationBillingService::FREQUENCY_MONTHLY)
                : null,
        ])->save();

        return back()->with('success', 'Modalidad de cobro de la administración actualizada.');
    }
}
