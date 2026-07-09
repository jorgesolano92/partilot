<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Entity;
use App\Models\Administration;
use App\Models\Manager;
use App\Models\PendingEntityManagerInvitation;
use App\Models\User;
use App\Mail\EntityManagerInvitationMail;
use App\Mail\EntityManagerPreregisterInviteMail;
use App\Services\EntityPanelAccessService;
use App\Services\EntityContractService;
use App\Services\ManagerAccountService;
use App\Services\ProvisionalPasswordService;
use App\Services\AuditLogService;
use App\Services\CommunicationEmailService;
use App\Services\RoleLegalAcceptanceService;
use App\Support\ContactEmailRegistry;
use App\Support\PanelSelectionResolver;
use App\Rules\ValidCalendarDate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EntityController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $filterAdministration = \App\Support\AdministrationListFilter::resolve($request, $user);

        $query = Entity::with(['administration', 'manager.user'])
            ->forUser($user);

        if ($filterAdministration) {
            $query->where('administration_id', $filterAdministration->id);
        }

        // Los gestores de entidad (sin cuenta panel) solo ven entidades activas en el listado.
        if ($user && $user->isEntityManagerWithoutPanelAccount()) {
            $query->where('status', 1);
        }

        $entities = $query
            ->orderBy('created_at', 'desc')
            ->get();

        $hideAdministrationColumn = $user && (
            $user->isEntityPanelReadOnly()
            || ($user->isEntity() && ! $user->isSuperAdmin() && ! $user->isAdministration())
            || ($user->isAdministration() && ! $user->isSuperAdmin())
        );
        $canAddEntity = $user && ($user->isSuperAdmin() || $user->isAdministration());

        return view('entities.index', compact('entities', 'hideAdministrationColumn', 'canAddEntity', 'filterAdministration'));
    }

    /**
     * Show the form for creating a new resource - Paso 1: Seleccionar administración
     * Al iniciar una nueva entidad se limpia entity_information (y la imagen) para no arrastrar datos anteriores.
     */
    public function create(Request $request)
    {
        request()->session()->forget('entity_information');

        if ($redirect = $this->redirectIfImplicitAdministration($request, 'entities.add-information')) {
            return $redirect;
        }

        $administrations = Administration::forUser(auth()->user())->get();
        return view('entities.add', compact('administrations'));
    }

    /**
     * Store administration selection and show entity information form - Paso 2: Datos de la entidad
     */
    public function store_administration(Request $request)
    {
        $request->validate([
            'administration_id' => 'required|integer|exists:administrations,id'
        ]);

        $administration = Administration::with('manager.user')
            ->forUser(auth()->user())
            ->findOrFail($request->administration_id);
        $request->session()->put('selected_administration', $administration);

        return redirect()->route('entities.add-information');
    }

    /**
     * Show entity information form - Paso 2: Datos de la entidad
     */
    public function create_information()
    {
        $administration = $this->resolveWizardAdministration();

        if (! $administration) {
            return redirect()->route('entities.create')
                ->with('error', 'Sesión expirada. Por favor, seleccione una administración.');
        }

        [$provinces, $provinceCityMap] = $this->getProvinceCityData();

        return view('entities.add_information', compact('provinces', 'provinceCityMap'));
    }

    /**
     * Store entity information and show manager form - Paso 3: Datos del gestor
     */
    public function store_information(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'address' => 'required|string|max:500',
            'nif_cif' => ['required', 'string', 'max:20', new \App\Rules\EntityDocument],
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'comments' => 'nullable|string|max:1000',
            'is_non_profit' => 'nullable|boolean',
            'entity_pays_management_fee' => 'nullable|boolean',
            'entity_pays_print_fee' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_image' => 'nullable|in:0,1',
        ], [
            'name.required' => 'Indique el nombre comercial de la entidad.',
            'province.required' => 'Seleccione una provincia.',
            'city.required' => 'Seleccione una localidad.',
            'postal_code.required' => 'Indique el código postal.',
            'address.required' => 'Indique la dirección.',
            'nif_cif.required' => 'Indique el NIF/CIF.',
            'phone.required' => 'Indique un teléfono de contacto.',
            'email.required' => 'Indique el email de acceso al panel.',
            'email.email' => 'El email de acceso al panel no es válido.',
        ]);

        $validated['comments'] = \App\Support\HtmlText::sanitizePlainText($validated['comments'] ?? null);
        $validated['is_non_profit'] = $request->boolean('is_non_profit');
        $validated['entity_pays_management_fee'] = $request->boolean('entity_pays_management_fee');
        $validated['entity_pays_print_fee'] = $request->boolean('entity_pays_print_fee');

        // Manejo de imagen: nueva subida o marcar para quitar
        if ($request->boolean('remove_image')) {
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = $file->hashName();
            $file->move(public_path('uploads'), $filename);
            $validated['image'] = $filename;
        } else {
            // Mantener imagen previa de la sesión si no se sube ni se elimina
            $validated['image'] = session('entity_information.image');
        }
        unset($validated['remove_image']);

        if (ContactEmailRegistry::isTaken($validated['email'])) {
            return back()->withErrors([
                'email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.',
            ])->withInput();
        }

        $request->session()->put('entity_information', $validated);

        return redirect()->route('entities.add-manager');
    }

    /**
     * Show manager form - Paso 3: Datos del gestor (Invitar o Registrar)
     */
    public function create_manager()
    {
        $administration = $this->resolveWizardAdministration();
        $entityInformation = session('entity_information');

        if (! $administration || ! $entityInformation) {
            return redirect()->route('entities.create')
                ->with('error', 'Sesión expirada. Por favor, vuelva a empezar.');
        }

        // Inicializar datos del gestor en sesión si no existen (persistencia como en administrations)
        if (!session()->has('entity_manager')) {
            session()->put('entity_manager', [
                'manager_name' => '',
                'manager_last_name' => '',
                'manager_last_name2' => '',
                'manager_nif_cif' => '',
                'manager_birthday' => '',
                'manager_email' => '',
                'manager_phone' => '',
            ]);
        }

        return view('entities.add_manager');
    }

    /**
     * Guardar borrador del formulario de gestor externo en sesión y volver a la elección Invitar/Registrar
     */
    public function save_manager_draft(Request $request)
    {
        $request->session()->put('entity_manager', [
            'manager_name' => $request->input('manager_name', ''),
            'manager_last_name' => $request->input('manager_last_name', ''),
            'manager_last_name2' => $request->input('manager_last_name2', ''),
            'manager_nif_cif' => $request->input('manager_nif_cif', ''),
            'manager_birthday' => $request->input('manager_birthday', ''),
            'manager_email' => $request->input('manager_email', ''),
            'manager_phone' => $request->input('manager_phone', ''),
        ]);

        return redirect()->route('entities.add-manager');
    }

    /**
     * Store manager information and create the complete entity - Paso final
     */
    public function store_manager(Request $request)
    {
        $request->validate([
            'manager_name' => 'required|string|max:255',
            'manager_last_name' => 'required|string|max:255',
            'manager_last_name2' => 'nullable|string|max:255',
            'manager_nif_cif' => ['nullable', 'string', 'max:20', new \App\Rules\SpanishDocument],
            'manager_birthday' => ValidCalendarDate::birthday(),
            'manager_email' => 'required|email|max:255',
            'manager_phone' => 'nullable|string|max:20',
        ]);

        $administration = $request->session()->get('selected_administration');
        $entityInformation = $request->session()->get('entity_information');

        if (! $administration || ! auth()->user()->canAccessAdministration($administration->id) || ! $entityInformation) {
            return redirect()->route('entities.create')
                ->with('error', 'Sesión expirada o permisos insuficientes. Por favor, vuelva a empezar.');
        }

        $email = $entityInformation['email'] ?? null;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('entities.add-information')
                ->with('error', 'La entidad debe tener un email de contacto válido para el acceso al panel.');
        }

        if (ContactEmailRegistry::isTaken($email)) {
            return back()->withErrors([
                'manager_email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario. Cambie el email en el paso anterior.',
            ])->withInput();
        }

        if (strcasecmp((string) $request->input('manager_email'), (string) $email) === 0) {
            return back()->withErrors([
                'manager_email' => 'El email del gestor debe ser distinto al email de acceso del panel de la entidad.',
            ])->withInput();
        }

        $managerUser = User::where('email', $request->input('manager_email'))->first();
        $managerUserWasNew = false;
        $managerPlainPassword = null;
        if ($managerUser && $managerUser->isPanelAccount()) {
            return back()->withErrors([
                'manager_email' => 'Ese email corresponde a una cuenta de acceso de panel. Use otro email para el gestor.',
            ])->withInput();
        }

        if (! $managerUser) {
            $managerUserWasNew = true;
            $managerPlainPassword = app(ProvisionalPasswordService::class)->generate();
            $managerUser = User::create([
                'name' => $request->input('manager_name'),
                'last_name' => $request->input('manager_last_name'),
                'last_name2' => $request->input('manager_last_name2'),
                'email' => $request->input('manager_email'),
                'password' => $managerPlainPassword,
                'must_change_password' => true,
                'role' => User::ROLE_ENTITY,
                'status' => true,
                'phone' => $request->input('manager_phone') ?: null,
                'nif_cif' => $request->input('manager_nif_cif') ?: null,
                'birthday' => $request->input('manager_birthday') ?: null,
            ]);
        }

        try {
            $entity = DB::transaction(function () use ($administration, $entityInformation) {
                $entity = app(EntityPanelAccessService::class)->createEntityWithPanelAccess($administration, $entityInformation);

                return app(EntityContractService::class)->initializeForNewEntity($entity);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('entities.add-information')->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->route('entities.add-information')->with('error', $e->getMessage());
        }

        // Gestor responsable: pendiente de aceptación por email (mismo flujo que invitar gestor).
        $primaryManager = Manager::create([
            'user_id' => $managerUser->id,
            'entity_id' => $entity->id,
            'is_primary' => true,
            'permission_sellers' => true,
            'permission_design' => true,
            'permission_statistics' => true,
            'permission_payments' => true,
            'confirmation_token' => Str::random(64),
            'confirmation_sent_at' => now(),
            'requires_password_setup' => false,
            'user_created_for_invitation' => $managerUserWasNew,
            'status' => null,
        ]);

        if ($managerUser->role !== User::ROLE_ENTITY) {
            $managerUser->update(['role' => User::ROLE_ENTITY]);
        }

        try {
            if (! empty($managerUser->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $managerUser->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $managerUser,
                    messageType: 'entity_manager_invitation',
                    templateKey: null,
                    mailClass: EntityManagerInvitationMail::class,
                    mailPayload: array_filter([
                        'entity_id' => $entity->id,
                        'user_id' => $managerUser->id,
                        'manager_id' => $primaryManager->id,
                        'plain_password' => $managerPlainPassword,
                    ]),
                    context: ['entity_id' => $entity->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación al gestor responsable (alta entidad): '.$e->getMessage());
        }

        $request->session()->forget(['selected_administration', 'entity_information', 'entity_manager']);

        return redirect()->route('entities.index')
            ->with(
                'success',
                'Entidad creada. Se ha enviado un correo al email de la entidad con la contraseña provisional del panel. También se ha enviado un correo al gestor responsable con sus datos de acceso y la invitación.'
            );
    }

    /**
     * Verificar si existe un gestor con el email proporcionado
     */
    public function check_manager_email(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->email;
        
        // Buscar si existe un usuario con ese email
        $user = User::where('email', $email)->first();
        
        if ($user && $user->isPanelAccount()) {
            return response()->json([
                'exists' => true,
                'user_id' => null,
                'is_panel_account' => true,
                'manager_name' => null,
            ]);
        }

        return response()->json([
            'exists' => $user ? true : false,
            'user_id' => $user ? $user->id : null,
            'is_panel_account' => false,
            'manager_name' => $user ? trim(($user->name ?? '') . ' ' . ($user->last_name ?? '')) : null,
        ]);
    }

    /**
     * Invitar gestor existente a una entidad (o email aún no registrado → invitación pendiente).
     */
    public function invite_manager(Request $request)
    {
        $isCreationFlow = ! $request->filled('entity_id');

        $permissionRules = [
            'permission_sellers' => 'nullable|boolean',
            'permission_design' => 'nullable|boolean',
            'permission_statistics' => 'nullable|boolean',
            'permission_payments' => 'nullable|boolean',
        ];

        if ($isCreationFlow) {
            $request->validate(array_merge([
                'user_id' => 'required|integer|exists:users,id',
            ], $permissionRules));
        } else {
            $request->validate(array_merge([
                'entity_id' => 'required|integer|exists:entities,id',
                'user_id' => 'nullable|integer|exists:users,id',
                'pending_invite_email' => 'nullable|email|max:255',
            ], $permissionRules));

            if (! $request->filled('user_id') && ! $request->filled('pending_invite_email')) {
                return redirect()->back()->with('error', 'Indique un usuario existente o un email para la invitación.');
            }

            if ($request->filled('user_id') && $request->filled('pending_invite_email')) {
                return redirect()->back()->with('error', 'Use solo una opción: usuario existente o email sin registrar.');
            }
        }

        $entity = null;
        if (! $isCreationFlow) {
            $entity = Entity::forUser(auth()->user())->findOrFail($request->entity_id);
            if (! $this->canManageSecondaryManagers($entity)) {
                return redirect()->route('entities.show', $entity->id)
                    ->with('error', 'Solo el gestor responsable aceptado puede invitar gestores secundarios.');
            }
        } else {
            $administration = $request->session()->get('selected_administration');
            $entityInformation = $request->session()->get('entity_information');

            if (! $administration || !auth()->user()->canAccessAdministration($administration->id) || ! $entityInformation) {
                return redirect()->route('entities.create')
                    ->with('error', 'Sesión expirada. Vuelva a iniciar la creación de la entidad.');
            }

            $panelEmail = $entityInformation['email'] ?? null;
            if (! $panelEmail) {
                return redirect()->route('entities.add-information')
                    ->with('error', 'Falta el email de acceso al panel para crear la entidad.');
            }

            try {
                $entity = DB::transaction(function () use ($administration, $entityInformation) {
                    $entity = app(EntityPanelAccessService::class)->createEntityWithPanelAccess($administration, $entityInformation);

                    return app(EntityContractService::class)->initializeForNewEntity($entity);
                });
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('entities.add-information')->with('error', $e->getMessage());
            } catch (\RuntimeException $e) {
                return redirect()->route('entities.add-information')->with('error', $e->getMessage());
            }
        }

        if (! $isCreationFlow && $request->filled('pending_invite_email') && ! $request->filled('user_id')) {
            return $this->invitePendingManagerByEmail($request, $entity);
        }

        $invited = User::findOrFail($request->user_id);
        if ($invited->isPanelAccount()) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'No se puede asignar como gestor a la cuenta de acceso al panel de una administración o entidad.');
        }

        // Verificar si ya existe un manager con este usuario para esta entidad
        $existingManager = Manager::where('user_id', $request->user_id)
            ->where('entity_id', $entity->id)
            ->first();

        if ($existingManager) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Este usuario ya es gestor de esta entidad.');
        }

        // En creación inicial de entidad, el gestor invitado debe quedar como principal.
        if ($isCreationFlow) {
            Manager::where('entity_id', $entity->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        // Crear la relación manager-entity (pendiente de confirmación por email)
        $manager = Manager::create([
            'user_id' => $request->user_id,
            'entity_id' => $entity->id,
            'is_primary' => $isCreationFlow ? true : false,
            'permission_sellers' => $request->boolean('permission_sellers'),
            'permission_design' => $request->boolean('permission_design'),
            'permission_statistics' => $request->boolean('permission_statistics'),
            'permission_payments' => $request->boolean('permission_payments'),
            'confirmation_token' => Str::random(64),
            'confirmation_sent_at' => now(),
            'requires_password_setup' => false,
            'user_created_for_invitation' => false,
            'status' => null, // Pendiente por defecto
        ]);

        $user = User::find($request->user_id);
        if ($user && $user->role !== User::ROLE_ENTITY) {
            $user->update(['role' => User::ROLE_ENTITY]);
        }

        // Cadena de alta entidad/gestor: email al gestor invitado
        try {
            if ($user && !empty($user->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $user->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $user,
                    messageType: 'entity_manager_invitation',
                    templateKey: null,
                    mailClass: EntityManagerInvitationMail::class,
                    mailPayload: ['entity_id' => $entity->id, 'user_id' => $user->id, 'manager_id' => $manager->id],
                    context: ['entity_id' => $entity->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación a gestor existente: '.$e->getMessage());
        }

        // Si venimos del wizard de creación, limpiar sesión al completar la invitación.
        if ($isCreationFlow) {
            $request->session()->forget(['selected_administration', 'entity_information', 'entity_manager']);
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', $isCreationFlow
                ? 'Entidad creada. Se ha enviado un correo al email de la entidad con la contraseña provisional del panel. Gestor invitado exitosamente.'
                : 'Gestor invitado exitosamente.');
    }

    /**
     * Registrar nuevo gestor para una entidad
     */
    public function register_manager(Request $request, $id)
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdministration())) {
            abort(403, 'Solo la administración puede registrar gestores desde el panel.');
        }

        $entity = Entity::forUser($user)->findOrFail($id);
        if (! $this->canManageSecondaryManagers($entity)) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Solo el gestor responsable aceptado puede registrar gestores secundarios.');
        }

        if (User::where('email', $request->manager_email)->whereNotNull('panel_account_type')->exists()) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Ese email corresponde a una cuenta de acceso al panel (administración o entidad) y no puede usarse como gestor.');
        }

        // Buscar usuario primero para excluirlo de la validación unique si existe
        $user = User::where('email', $request->manager_email)->first();
        if ($user && $user->isPanelAccount()) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Ese usuario es una cuenta de acceso al panel y no puede añadirse como gestor.');
        }
        $userId = $user ? $user->id : null;

        $validated = $request->validate([
            'manager_name' => 'required|string|max:255',
            'manager_last_name' => 'required|string|max:255',
            'manager_last_name2' => 'nullable|string|max:255',
            'manager_nif_cif' => ['nullable', 'string', 'max:20', 'unique:users,nif_cif' . ($userId ? ',' . $userId : '')],
            'manager_birthday' => ValidCalendarDate::birthday(),
            'manager_email' => 'required|email|max:255',
            'manager_phone' => 'nullable|string|max:20',
            'permission_sellers' => 'nullable|boolean',
            'permission_design' => 'nullable|boolean',
            'permission_statistics' => 'nullable|boolean',
            'permission_payments' => 'nullable|boolean',
        ]);
        $userWasNew = false;
        $managerPlainPassword = null;
        if (! $user) {
            $userWasNew = true;
            $managerPlainPassword = app(ProvisionalPasswordService::class)->generate();
            $user = new User;
            $user->name = $validated['manager_name'] . ' ' . $validated['manager_last_name'];
            $user->email = $validated['manager_email'];
            $user->password = $managerPlainPassword;
            $user->must_change_password = true;
            $user->role = User::ROLE_ENTITY;
            $user->save();
        }

        // Actualizar datos del usuario
        $user->update([
            'name' => $validated['manager_name'],
            'last_name' => $validated['manager_last_name'],
            'last_name2' => $validated['manager_last_name2'] ?? null,
            'nif_cif' => $validated['manager_nif_cif'] ?? null,
            'birthday' => $validated['manager_birthday'] ?? null,
            'phone' => $validated['manager_phone'] ?? null,
            'role' => User::ROLE_ENTITY,
        ]);

        // Verificar si ya existe un manager con este usuario para esta entidad
        $existingManager = Manager::where('user_id', $user->id)
            ->where('entity_id', $entity->id)
            ->first();

        if ($existingManager) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Este usuario ya es gestor de esta entidad.');
        }

        // Crear la relación manager-entity (gestor secundario pendiente de confirmación)
        $manager = Manager::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'is_primary' => false,
            'permission_sellers' => $request->has('permission_sellers') ? true : false,
            'permission_design' => $request->has('permission_design') ? true : false,
            'permission_statistics' => $request->has('permission_statistics') ? true : false,
            'permission_payments' => $request->has('permission_payments') ? true : false,
            'confirmation_token' => Str::random(64),
            'confirmation_sent_at' => now(),
            'requires_password_setup' => false,
            'user_created_for_invitation' => $userWasNew,
            'status' => null, // Pendiente por defecto
        ]);

        // Cadena de alta entidad/gestor: email al gestor recién registrado/invitado
        try {
            if (!empty($user->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $user->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $user,
                    messageType: 'entity_manager_invitation',
                    templateKey: null,
                    mailClass: EntityManagerInvitationMail::class,
                    mailPayload: array_filter([
                        'entity_id' => $entity->id,
                        'user_id' => $user->id,
                        'manager_id' => $manager->id,
                        'plain_password' => $managerPlainPassword,
                    ]),
                    context: ['entity_id' => $entity->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación a gestor nuevo: '.$e->getMessage());
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', $userWasNew
                ? 'Gestor registrado. Se ha enviado un correo con los datos de acceso y la invitación para aceptar o rechazar.'
                : 'Gestor invitado exitosamente.');
    }

    /**
     * Crear entidad con gestor pendiente de registro (mismo email al darse de alta en app/web).
     */
    public function create_pending_entity(Request $request)
    {
        $request->validate([
            'invite_email' => 'required|email',
        ]);

        $inviteEmail = PendingEntityManagerInvitation::normalizeEmail((string) $request->invite_email);

        $administration = $request->session()->get('selected_administration');
        $entityInformation = $request->session()->get('entity_information');

        if (! $administration || ! auth()->user()->canAccessAdministration($administration->id) || ! $entityInformation) {
            return redirect()->route('entities.create')
                ->with('error', 'Sesión expirada. Por favor, vuelva a empezar.');
        }

        $panelEmail = $entityInformation['email'] ?? null;
        if (! $panelEmail) {
            return redirect()->route('entities.add-information')
                ->with('error', 'Falta el email de acceso al panel para crear la entidad.');
        }

        if (strcasecmp($inviteEmail, strtolower(trim((string) $panelEmail))) === 0) {
            return redirect()->route('entities.add-manager')
                ->with('error', 'El email del gestor invitado debe ser distinto al email de acceso al panel de la entidad.');
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$inviteEmail])->exists()) {
            return redirect()->route('entities.add-manager')
                ->with('error', 'Ese email ya está registrado. Use “Invitar gestor” cuando haya coincidencia.');
        }

        try {
            $entity = DB::transaction(function () use ($administration, $entityInformation) {
                $entity = app(EntityPanelAccessService::class)->createEntityWithPanelAccess($administration, $entityInformation);

                return app(EntityContractService::class)->initializeForNewEntity($entity);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('entities.add-information')->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->route('entities.add-information')->with('error', $e->getMessage());
        }

        $pending = PendingEntityManagerInvitation::storeInvitation($entity->id, $inviteEmail, [
            'is_primary' => true,
            'permission_sellers' => true,
            'permission_design' => true,
            'permission_statistics' => true,
            'permission_payments' => true,
        ]);

        try {
            Mail::to($inviteEmail)->send(new EntityManagerPreregisterInviteMail($entity, $pending));
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación pre-registro (alta entidad): '.$e->getMessage());
        }

        $request->session()->forget(['selected_administration', 'entity_information', 'entity_manager']);

        return redirect()->route('entities.index')
            ->with(
                'success',
                'Entidad creada. Se ha enviado un correo al email de la entidad con la contraseña provisional del panel. El futuro gestor recibirá un correo para aceptar o rechazar y completar su registro.'
            );
    }

    /**
     * Método temporal para crear un gestor de prueba
     */
    public function create_test_manager()
    {
        // Crear usuario de prueba
        $user = User::firstOrCreate(
            ['email' => 'test@manager.com'],
            [
                'name' => 'Test Manager',
                'email' => 'test@manager.com',
                'password' => bcrypt(12345678)
            ]
        );

        // Crear entidad de prueba
        $entity = Entity::firstOrCreate(
            ['name' => 'Test Entity'],
            [
                'administration_id' => 1,
                'name' => 'Test Entity',
                'province' => 'Test Province',
                'city' => 'Test City',
                'postal_code' => '12345',
                'address' => 'Test Address',
                'nif_cif' => '12345678B',
                'phone' => '123456789',
                'email' => 'test@entity.com',
                'comments' => 'Test Comments',
            ]
        );

        // Crear la relación manager-entity
        $manager = Manager::firstOrCreate(
            ['user_id' => $user->id, 'entity_id' => $entity->id],
            [
                'user_id' => $user->id,
                'entity_id' => $entity->id
            ]
        );

        return response()->json([
            'message' => 'Gestor de prueba creado exitosamente',
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $entity = Entity::with(['administration', 'manager.user', 'managers.user', 'pendingManagerInvitations'])
            ->forUser(auth()->user())
            ->findOrFail($id);

        $pendingManagerInvitations = $entity->pendingManagerInvitations;
        $primaryPendingInvitation = $pendingManagerInvitations->firstWhere('is_primary', true)
            ?? $pendingManagerInvitations->first();

        $managersVisible = $entity->managers->filter(function ($m) use ($entity) {
            $u = $m->user;
            if (! $u) {
                return true;
            }

            // Mostrar siempre el gestor principal aunque sea la cuenta panel de la entidad.
            if ($m->is_primary) {
                return true;
            }

            return ! ($u->panel_account_type === 'entity' && (int) $u->panel_account_id === (int) $entity->id);
        })->values();

        $entityPanelUser = User::where('panel_account_type', 'entity')
            ->where('panel_account_id', $entity->id)
            ->first();

        $canManageManagers = $this->canManageSecondaryManagers($entity);

        $user = auth()->user();
        $canToggleEntityStatus = $user && ($user->isSuperAdmin() || $user->isAdministration());
        $entityPanelReadOnly = $user && $user->isEntityPanelReadOnly();
        $canEditEntityData = $user && ($user->isSuperAdmin() || $user->isAdministration());
        $canEditManagerData = $canEditEntityData;
        $isEntityRole = $user && $user->isEntity() && ! $user->isSuperAdmin() && ! $user->isAdministration();
        $hideGestoresTab = $user && $user->isAdministration() && ! $user->isSuperAdmin();
        $canSeeAdminComments = $user && ($user->isSuperAdmin() || $user->isAdministration());
        $hideRegisterManager = ! ($user && ($user->isSuperAdmin() || $user->isAdministration()));
        $managerTabLabel = $isEntityRole ? 'Gestor responsable' : 'Datos Gestor';
        $hideAdministrationDetail = $user && (
            $entityPanelReadOnly
            || $isEntityRole
        );
        $canManageBillingSwitches = $user && ($user->isSuperAdmin() || $user->isAdministration());

        return view('entities.show', compact(
            'entity',
            'managersVisible',
            'pendingManagerInvitations',
            'primaryPendingInvitation',
            'entityPanelUser',
            'canManageManagers',
            'canToggleEntityStatus',
            'entityPanelReadOnly',
            'canEditEntityData',
            'canEditManagerData',
            'hideAdministrationDetail',
            'hideGestoresTab',
            'canSeeAdminComments',
            'hideRegisterManager',
            'managerTabLabel',
            'isEntityRole',
            'canManageBillingSwitches'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $this->assertCanEditEntityData();

        $entity = Entity::forUser(auth()->user())->findOrFail($id);
        $administrations = Administration::forUser(auth()->user())->get();
        $users = User::whereNull('panel_account_type')->orderBy('name')->get();
        [$provinces, $provinceCityMap] = $this->getProvinceCityData();
        $hideBillingSwitchesModal = (bool) (auth()->user()->hide_entity_billing_switches_modal ?? false);

        return view('entities.edit', compact(
            'entity',
            'administrations',
            'users',
            'provinces',
            'provinceCityMap',
            'hideBillingSwitchesModal'
        ));
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
                if (! $provincesResponse->ok() || ! $citiesResponse->ok()) {
                    throw new \RuntimeException('No se pudo descargar catálogo de provincias/municipios.');
                }

                $provincesData = $provincesResponse->json();
                $citiesData = $citiesResponse->json();
                if (! is_array($provincesData) || ! is_array($citiesData)) {
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
                    if ($provinceId === '' || $cityName === '' || ! isset($provinceNamesById[$provinceId])) {
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
            Log::warning('No se pudo cargar catálogo oficial provincias/localidades para entidades, usando fallback local.', [
                'error' => $e->getMessage(),
            ]);

            $entityData = Entity::query()
                ->select('province', 'city')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();
            $adminData = Administration::query()
                ->select('province', 'city')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();
            $rows = $entityData->concat($adminData);

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $this->assertCanEditEntityData();

        $entity = Entity::forUser(auth()->user())->findOrFail($id);
        
        $validated = $request->validate([
            'administration_id' => 'nullable|integer|exists:administrations,id',
            'name' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'nif_cif' => [
                Rule::requiredIf(function () use ($request, $entity) {
                    $status = $request->input('status', $entity->status);

                    return (string) $status === '1' || (int) $status === 1;
                }),
                'nullable',
                'string',
                'max:20',
                new \App\Rules\EntityDocument,
            ],
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'comments' => 'nullable|string|max:1000',
            'status' => 'nullable|in:-1,0,1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_image' => 'nullable|in:0,1',
            'panel_password' => 'nullable|string|min:8|confirmed',
            'entity_pays_management_fee' => 'nullable|boolean',
            'entity_pays_print_fee' => 'nullable|boolean',
            'is_non_profit' => 'nullable|boolean',
        ]);

        // Convertir status: -1 = null (pendiente), 1 = activo, 0 = inactivo
        $validated['status'] = $validated['status'] === '-1' ? null : ($validated['status'] ?? null);
        $validated['entity_pays_management_fee'] = $request->boolean('entity_pays_management_fee');
        $validated['entity_pays_print_fee'] = $request->boolean('entity_pays_print_fee');
        $validated['is_non_profit'] = $request->boolean('is_non_profit');

        // Eliminar imagen si el usuario pulsó "Eliminar imagen"
        if ($request->input('remove_image') === '1') {
            if ($entity->image && file_exists(public_path('uploads/' . $entity->image))) {
                unlink(public_path('uploads/' . $entity->image));
            }
            $validated['image'] = null;
        }
        // Sustituir por nueva imagen si se subió un fichero
        elseif ($request->hasFile('image')) {
            if ($entity->image && file_exists(public_path('uploads/' . $entity->image))) {
                unlink(public_path('uploads/' . $entity->image));
            }
            $file = $request->file('image');
            $filename = $file->hashName();
            $file->move(public_path('uploads'), $filename);
            $validated['image'] = $filename;
        }

        unset($validated['remove_image'], $validated['panel_password']);

        $panelUser = User::where('panel_account_type', 'entity')
            ->where('panel_account_id', $entity->id)
            ->first();

        $newEntityEmail = isset($validated['email']) ? trim((string) $validated['email']) : '';

        if ($panelUser && $newEntityEmail !== '' && $newEntityEmail !== $panelUser->email) {
            if (ContactEmailRegistry::isTaken($newEntityEmail, $panelUser->id, null, $entity->id)) {
                return back()->withInput()
                    ->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.']);
            }
        } elseif (! $panelUser && $newEntityEmail !== '' && strcasecmp($newEntityEmail, (string) $entity->email) !== 0
            && ContactEmailRegistry::isTaken($newEntityEmail, null, null, $entity->id)) {
            return back()->withInput()
                ->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.']);
        }

        $entity->update($validated);
        $entity->refresh();

        if ($panelUser) {
            $panelUser->update([
                'email' => $entity->email,
                'name' => trim((string) $entity->name) ?: 'Entidad',
                'phone' => $entity->phone,
                'nif_cif' => $entity->nif_cif,
            ]);
            if ($request->filled('panel_password')) {
                $panelUser->password = $request->panel_password;
                $panelUser->save();
            }
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', 'Entidad actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Entity $entity)
    {
        if (!auth()->user()->canAccessEntity($entity->id)) {
            abort(403, 'No tienes permisos para eliminar esta entidad.');
        }

        // Eliminar imagen si existe
        if ($entity->image && file_exists(public_path('uploads/' . $entity->image))) {
            unlink(public_path('uploads/' . $entity->image));
        }

        $entity->delete();

        return redirect()->route('entities.index')
            ->with('success', 'Entidad eliminada exitosamente.');
    }

    /**
     * Show the form for editing the manager of an entity.
     */
    public function edit_manager($id)
    {
        $this->assertCanEditEntityData();

        $entity = Entity::with(['administration', 'manager'])
            ->forUser(auth()->user())
            ->findOrFail($id);
        return view('entities.edit_manager', compact('entity'));
    }

    /**
     * Update the manager of an entity.
     */
    public function update_manager(Request $request, $id)
    {
        $this->assertCanEditEntityData();

        $entity = Entity::with('manager')
            ->forUser(auth()->user())
            ->findOrFail($id);
        
        // Buscar usuario primero para excluirlo de la validación unique si existe
        $user = User::where('email', $request->manager_email)->first();
        $userId = $user ? $user->id : null;
        
        $request->validate([
            'manager_name' => 'required|string|max:255',
            'manager_last_name' => 'required|string|max:255',
            'manager_last_name2' => 'nullable|string|max:255',
            'manager_nif_cif' => ['nullable', 'string', 'max:20', 'unique:users,nif_cif' . ($userId ? ',' . $userId : '')],
            'manager_birthday' => ValidCalendarDate::birthday(),
            'manager_email' => 'required|email|max:255',
            'manager_phone' => 'nullable|string|max:20',
            'manager_comment' => 'nullable|string|max:1000',
            'manager_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        if (!$user) {
            $user = app(ManagerAccountService::class)->createUser([
                'name' => $request->manager_name,
                'last_name' => $request->manager_last_name,
                'last_name2' => $request->manager_last_name2,
                'email' => $request->manager_email,
                'role' => User::ROLE_ENTITY,
            ], 'entidad');
        }

        // Actualizar datos del usuario
        $user->update([
            'name' => $request->manager_name,
            'last_name' => $request->manager_last_name,
            'last_name2' => $request->manager_last_name2,
            'nif_cif' => $request->manager_nif_cif,
            'birthday' => $request->manager_birthday,
            'phone' => $request->manager_phone,
            'comment' => $request->manager_comment,
            'role' => User::ROLE_ENTITY,
        ]);

        // Manejo de imagen del manager
        if ($request->hasFile('manager_image')) {
            // Eliminar imagen anterior si existe
            if ($user->image && file_exists(public_path('manager/' . $user->image))) {
                unlink(public_path('manager/' . $user->image));
            }
            
            $file = $request->file('manager_image');
            $filename = $file->hashName();
            $file->move(public_path('manager'), $filename);
            $user->update(['image' => $filename]);
        }

        // Actualizar o crear relación manager-entity
        $manager = Manager::where('entity_id', $entity->id)->first();
        if ($manager) {
            $manager->update(['user_id' => $user->id]);
        } else {
            Manager::create([
                'user_id' => $user->id,
                'entity_id' => $entity->id,
                'is_primary' => true,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
                'status' => 1, // Activo por defecto para el gestor principal
            ]);
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', 'Gestor actualizado correctamente.');
    }

    /**
     * Show the form for editing manager permissions.
     * Si es gestor principal (is_primary), se muestra la vista en solo lectura con aviso.
     */
    public function edit_manager_permissions($entity_id, $manager_id)
    {
        $entity = Entity::with('managers.user')
            ->forUser(auth()->user())
            ->findOrFail($entity_id);
        if (! $this->canManageSecondaryManagers($entity)) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Solo el gestor responsable aceptado puede gestionar permisos de otros gestores.');
        }

        $manager = Manager::with('user')
            ->where('id', $manager_id)
            ->where('entity_id', $entity_id)
            ->firstOrFail();

        return view('entities.edit_manager_permissions', compact('entity', 'manager'));
    }

    /**
     * Update manager permissions.
     * El gestor principal no puede tener permisos restringidos: se ignoran cambios y se mantienen todos en true.
     */
    public function update_manager_permissions(Request $request, $entity_id, $manager_id)
    {
        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if (! $this->canManageSecondaryManagers($entity)) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Solo el gestor responsable aceptado puede gestionar permisos de otros gestores.');
        }

        $manager = Manager::where('id', $manager_id)
            ->where('entity_id', $entity_id)
            ->firstOrFail();

        if ($manager->is_primary) {
            return redirect()->route('entities.edit-manager-permissions', ['entity_id' => $entity->id, 'manager_id' => $manager->id])
                ->with('error', 'El gestor principal tiene todos los permisos y no se pueden restringir. Para cambiar los permisos de este gestor, primero debe asignar otro gestor como principal desde la ficha de la entidad.');
        }

        $request->validate([
            'permission_sellers' => 'nullable|boolean',
            'permission_design' => 'nullable|boolean',
            'permission_statistics' => 'nullable|boolean',
            'permission_payments' => 'nullable|boolean',
        ]);

        $permissionFields = [
            'permission_sellers',
            'permission_design',
            'permission_statistics',
            'permission_payments',
        ];

        $before = $manager->only($permissionFields);
        $after = [
            'permission_sellers' => $request->has('permission_sellers'),
            'permission_design' => $request->has('permission_design'),
            'permission_statistics' => $request->has('permission_statistics'),
            'permission_payments' => $request->has('permission_payments'),
        ];

        $manager->update($after);

        $audit = app(AuditLogService::class);
        foreach ($permissionFields as $field) {
            $audit->logManagerPermissionChange(
                $manager,
                auth()->user(),
                $field,
                $before[$field] ?? null,
                $after[$field],
                $request
            );
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', 'Permisos del gestor actualizados correctamente.');
    }

    /**
     * Asignar otro gestor como principal. El actual principal deja de serlo.
     * Requiere que se envíe el ID del nuevo gestor principal (no puede quedar sin principal).
     */
    public function set_primary_manager(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'new_primary_manager_id' => 'required|integer|exists:managers,id',
        ]);

        $entity = Entity::forUser(auth()->user())->findOrFail($request->entity_id);
        if (! $this->canManageSecondaryManagers($entity)) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Solo el gestor responsable aceptado puede cambiar el gestor principal.');
        }

        // Buscar gestor principal actual (puede no existir)
        $currentPrimary = Manager::where('entity_id', $entity->id)
            ->where('is_primary', true)
            ->first();

        $newPrimary = Manager::where('id', $request->new_primary_manager_id)
            ->where('entity_id', $entity->id)
            ->firstOrFail();

        // La cuenta técnica de acceso al panel de la entidad no debe ser seleccionable como principal.
        $newPrimary->loadMissing('user');
        if ($newPrimary->user
            && $newPrimary->user->panel_account_type === 'entity'
            && (int) $newPrimary->user->panel_account_id === (int) $entity->id) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'La cuenta de acceso al panel no puede asignarse como gestor principal.');
        }
        
        // Si hay un gestor principal actual, verificar que no sea el mismo que el nuevo
        if ($currentPrimary && $newPrimary->id === $currentPrimary->id) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'El gestor seleccionado ya es el gestor principal.');
        }
        
        // Si hay un gestor principal actual, verificar que hay al menos otro gestor disponible
        if ($currentPrimary) {
            $otherManagers = Manager::where('entity_id', $entity->id)
                ->where('id', '!=', $currentPrimary->id)
                ->count();
            
            if ($otherManagers < 1) {
                return redirect()->route('entities.show', $entity->id)
                    ->with('error', 'No se puede quitar el gestor principal. Debe haber al menos otro gestor disponible para asignar como principal.');
            }
        }

        \DB::transaction(function () use ($entity, $newPrimary) {
            // No cambiar aún el principal actual: se marca como pendiente hasta aceptación por email.
            $newPrimary->update([
                'pending_primary' => true,
                'confirmation_token' => Str::random(64),
                'confirmation_sent_at' => now(),
                'status' => $newPrimary->status ?? 1,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
            ]);
        });

        try {
            $newPrimary->loadMissing('user');
            if ($newPrimary->user && !empty($newPrimary->user->email)) {
                app(CommunicationEmailService::class)->sendAndLog(
                    recipientEmail: (string) $newPrimary->user->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $newPrimary->user,
                    messageType: 'entity_manager_invitation',
                    templateKey: null,
                    mailClass: EntityManagerInvitationMail::class,
                    mailPayload: ['entity_id' => $entity->id, 'user_id' => $newPrimary->user->id, 'manager_id' => $newPrimary->id],
                    context: ['entity_id' => $entity->id],
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación para cambio de gestor principal: '.$e->getMessage());
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', 'Solicitud enviada al nuevo gestor principal. El cambio se aplicará cuando acepte por email.');
    }

    /**
     * Cambiar el status de un manager
     */
    public function toggle_manager_status(Request $request)
    {
        $request->validate([
            'manager_id' => 'required|integer|exists:managers,id',
            'status' => 'required|integer|in:0,1',
        ]);

        $manager = Manager::findOrFail($request->manager_id);
        
        // Verificar que el manager pertenece a una entidad accesible por el usuario
        if ($manager->entity_id) {
            $entity = Entity::forUser(auth()->user())->find($manager->entity_id);
            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para modificar este gestor'
                ], 403);
            }
            if (! $this->canManageSecondaryManagers($entity)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo el gestor responsable aceptado puede modificar otros gestores.',
                ], 403);
            }
        } elseif ($manager->administration_id) {
            $administration = Administration::forUser(auth()->user())->find($manager->administration_id);
            if (!$administration) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para modificar este gestor'
                ], 403);
            }
        }

        $manager->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status del gestor actualizado correctamente'
        ]);
    }

    /**
     * Formulario público: aceptar invitación y definir contraseña de acceso al panel.
     */
    public function confirmManagerAccept(string $token)
    {
        $roleService = app(RoleLegalAcceptanceService::class);
        $manager = $roleService->findManagerByToken($token);

        if (! $manager || ! $manager->user) {
            return view('entities.manager-confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        $manager->loadMissing('entity.administration');
        if (($manager->is_primary || $manager->pending_primary)
            && $manager->entity
            && $manager->entity->contract_status === \App\Models\Entity::CONTRACT_PENDING) {
            return redirect()->route('entity-contract.accept-primary', ['token' => $token]);
        }

        $invitation = $roleService->buildWebManagerPayload($manager);

        if ($manager->requires_password_setup) {
            return view('entities.manager-accept-password', [
                'token' => $token,
                'manager' => $manager,
                'entity' => $manager->entity,
                'invitation' => $invitation,
            ]);
        }

        return view('legal.role-invitation-public', [
            'invitation' => $invitation,
            'acceptUrl' => route('entity-managers.confirm-respond', ['token' => $token]),
            'rejectUrl' => route('entity-managers.confirm-respond', ['token' => $token]),
        ]);
    }

    /**
     * Aceptar o rechazar invitación como gestor (sin formulario de contraseña).
     */
    public function confirmManagerRespond(Request $request, string $token)
    {
        $roleService = app(RoleLegalAcceptanceService::class);
        $manager = $roleService->findManagerByToken($token);

        if (! $manager || ! $manager->user) {
            return view('entities.manager-confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
        ]);

        if ($validated['action'] === 'reject') {
            $roleService->respondManagerInvitation($manager, 'reject', $request);

            return view('entities.manager-confirmation-success', [
                'message' => 'Solicitud rechazada. No tendrás acceso como gestor a esta entidad.',
                'type' => 'reject',
                'manager' => null,
            ]);
        }

        $manager->loadMissing('entity');
        if (($manager->is_primary || $manager->pending_primary)
            && $manager->entity
            && $manager->entity->contract_status === Entity::CONTRACT_PENDING) {
            return redirect()->route('entity-contract.accept-primary', ['token' => $token]);
        }

        $request->validate([
            'role_terms' => 'accepted',
        ], [
            'role_terms.accepted' => 'Debe aceptar las responsabilidades del rol para continuar.',
        ]);

        $result = $roleService->finalizeManagerActivation($manager, $request, $manager->user);
        if (! $result['success']) {
            return view('entities.manager-confirmation-error', [
                'message' => $result['message'],
            ]);
        }

        $manager->refresh()->load('entity');

        return view('entities.manager-confirmation-success', [
            'message' => '¡Invitación aceptada correctamente! Ya puedes iniciar sesión y gestionar la entidad.',
            'type' => 'accept',
            'manager' => $manager,
        ]);
    }

    /**
     * Procesar aceptación: guardar contraseña y activar gestor.
     */
    public function confirmManagerAcceptStore(Request $request, string $token)
    {
        $roleService = app(RoleLegalAcceptanceService::class);
        $manager = $roleService->findManagerByToken($token);

        if (! $manager || ! $manager->user) {
            return view('entities.manager-confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'birthday' => ValidCalendarDate::birthday(false),
            'phone' => 'nullable|string|max:20',
            'terms_accepted' => 'accepted',
        ], [
            'terms_accepted.accepted' => 'Debe aceptar las condiciones de uso para continuar.',
        ]);

        if ($manager->requires_password_setup) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ], [
                'password.required' => 'Indique una contraseña.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            ]);
        }

        DB::transaction(function () use ($manager, $request) {
            $manager->user->update([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'last_name2' => $request->input('last_name2'),
                'birthday' => $request->input('birthday') ?: null,
                'phone' => $request->input('phone') ?: null,
            ]);

            if ($manager->requires_password_setup) {
                $manager->user->update([
                    'password' => $request->input('password'),
                ]);
            }
        });

        $manager->loadMissing('entity');
        if (($manager->is_primary || $manager->pending_primary)
            && $manager->entity
            && $manager->entity->contract_status === Entity::CONTRACT_PENDING) {
            return redirect()->route('entity-contract.accept-primary', ['token' => $token]);
        }

        $result = $roleService->finalizeManagerActivation($manager, $request, $manager->user);
        if (! $result['success']) {
            return view('entities.manager-confirmation-error', [
                'message' => $result['message'],
            ]);
        }

        $manager->refresh()->load('entity');

        return view('entities.manager-confirmation-success', [
            'message' => $manager->requires_password_setup
                ? '¡Invitación aceptada! Ya puede iniciar sesión en el panel con su email y la contraseña indicada.'
                : '¡Invitación aceptada correctamente! Ya puede iniciar sesión y gestionar la entidad.',
            'type' => 'accept',
            'manager' => $manager,
        ]);
    }

    /**
     * Confirmar rechazo de invitación como gestor de entidad.
     */
    public function confirmManagerReject(string $token)
    {
        $roleService = app(RoleLegalAcceptanceService::class);
        $manager = $roleService->findManagerByToken($token);

        if (! $manager) {
            return view('entities.manager-confirmation-error', [
                'message' => 'El enlace de confirmación no es válido o ya ha sido utilizado.',
            ]);
        }

        $roleService->respondManagerInvitation($manager, 'reject', request());

        return view('entities.manager-confirmation-success', [
            'message' => 'Solicitud rechazada. No tendrás acceso como gestor a esta entidad.',
            'type' => 'reject',
            'manager' => null,
        ]);
    }

    /**
     * Eliminar un gestor (manager) de una entidad.
     * Solo elimina la relación manager-entity, NO elimina el usuario.
     * No se puede eliminar el gestor principal si es el único gestor.
     */
    public function destroy_manager($entity_id, $manager_id)
    {
        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if (! $this->canManageSecondaryManagers($entity)) {
            return redirect()->route('entities.show', $entity_id)
                ->with('error', 'Solo el gestor responsable aceptado puede eliminar gestores secundarios.');
        }
        
        $manager = Manager::where('id', $manager_id)
            ->where('entity_id', $entity_id)
            ->firstOrFail();

        $manager->load('user');
        if ($manager->user && $manager->user->panel_account_type === 'entity'
            && (int) $manager->user->panel_account_id === (int) $entity_id) {
            return redirect()->route('entities.show', $entity_id)
                ->with('error', 'No se puede eliminar la cuenta de acceso al panel de la entidad.');
        }

        // Verificar que no se está eliminando el gestor principal si es el único
        if ($manager->is_primary) {
            $totalManagers = Manager::where('entity_id', $entity_id)->count();
            
            if ($totalManagers <= 1) {
                return redirect()->route('entities.show', $entity_id)
                    ->with('error', 'No se puede eliminar el gestor principal. Debe haber al menos otro gestor disponible antes de eliminar el principal.');
            }
            
            // Si hay otros gestores, verificar que al menos uno no sea principal
            $otherManagers = Manager::where('entity_id', $entity_id)
                ->where('id', '!=', $manager_id)
                ->count();
            
            if ($otherManagers < 1) {
                return redirect()->route('entities.show', $entity_id)
                    ->with('error', 'No se puede eliminar el gestor principal. Debe asignar primero otro gestor como principal antes de eliminar este.');
            }
        }
        
        // Eliminar solo el manager (la relación), NO el usuario
        $manager->delete();
        
        return redirect()->route('entities.show', $entity_id)
            ->with('success', 'Gestor eliminado correctamente. El usuario asociado no ha sido eliminado.');
    }

    /**
     * Cambiar estado (Activo/Inactivo/Pendiente) de la entidad vía AJAX.
     */
    public function toggleStatus(Request $request, Entity $entity)
    {
        // Verificar permisos
        $entity = Entity::forUser(auth()->user())->findOrFail($entity->id);
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdministration())) {
            return response()->json([
                'success' => false,
                'message' => 'Solo administración o superadmin pueden cambiar el estado de la entidad.',
            ], 403);
        }
        
        // Determinar el nuevo estado según el estado actual
        $currentStatus = $entity->status;
        
        // Lógica de toggle: null/-1 (Pendiente) -> 1 (Activo), 1 (Activo) -> 0 (Inactivo), 0 (Inactivo) -> 1 (Activo)
        $newStatus = match($currentStatus) {
            null, -1 => 1,  // Pendiente -> Activo
            1 => 0,         // Activo -> Inactivo
            0 => 1,         // Inactivo -> Activo
            default => 1
        };
        
        $entity->update(['status' => $newStatus]);
        
        // Obtener texto y clase del nuevo estado
        $statusValue = $entity->fresh()->status;
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
     * Verificar si el email ya está en uso en entidades (para validación AJAX)
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'exclude_id' => 'nullable|integer'
        ]);

        $excludeEntityId = $request->exclude_id ? (int) $request->exclude_id : null;
        $panelUserId = null;
        if ($excludeEntityId) {
            $panelUserId = User::query()
                ->where('panel_account_type', 'entity')
                ->where('panel_account_id', $excludeEntityId)
                ->value('id');
        }

        $exists = ContactEmailRegistry::isTaken(
            $request->email,
            $panelUserId ? (int) $panelUserId : null,
            null,
            $excludeEntityId
        );

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este correo ya está en uso por otra administración, entidad o cuenta de usuario' : null,
        ]);
    }

    /**
     * Invitar por email cuando el usuario aún no existe (registro + observer + correo de aceptación).
     */
    private function invitePendingManagerByEmail(Request $request, Entity $entity): \Illuminate\Http\RedirectResponse
    {
        $email = PendingEntityManagerInvitation::normalizeEmail((string) $request->pending_invite_email);

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('panel_account_type')->exists()) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Ese email corresponde a una cuenta de acceso al panel y no puede invitarse como gestor.');
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return redirect()->route('entities.show', $entity->id)
                ->with('error', 'Ese email ya está registrado. Use la búsqueda de invitación cuando aparezca la coincidencia.');
        }

        $pending = PendingEntityManagerInvitation::storeInvitation($entity->id, $email, [
            'is_primary' => false,
            'permission_sellers' => $request->boolean('permission_sellers'),
            'permission_design' => $request->boolean('permission_design'),
            'permission_statistics' => $request->boolean('permission_statistics'),
            'permission_payments' => $request->boolean('permission_payments'),
        ]);

        try {
            Mail::to($email)->send(new EntityManagerPreregisterInviteMail($entity, $pending));
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando invitación pre-registro gestor: '.$e->getMessage());
        }

        return redirect()->route('entities.show', $entity->id)
            ->with('success', 'Invitación enviada. El destinatario recibirá un correo para aceptar (registro) o rechazar la invitación.');
    }

    /**
     * Gestión de gestores secundarios: superadmin, administración con acceso o gestor principal aceptado.
     */
    private function canManageSecondaryManagers(Entity $entity): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isPrimaryAcceptedManagerForEntity((int) $entity->id);
    }

    private function assertCanEditEntityData(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdministration())) {
            abort(403, 'No tienes permiso para editar los datos de la entidad.');
        }
    }

    /**
     * Guardar preferencia del usuario para no mostrar el modal de switches comerciales al guardar entidad.
     */
    public function dismissBillingSwitchesModal(Request $request)
    {
        $this->assertCanEditEntityData();

        $user = auth()->user();
        if ($request->boolean('hide')) {
            $user->hide_entity_billing_switches_modal = true;
            $user->save();
        }

        return response()->json(['success' => true]);
    }
    // Modificar esta funcion para acceder tanto con email como con usuario
    private function resolveWizardAdministration(): ?Administration
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $session = session('selected_administration');
        if ($session) {
            $id = is_object($session) ? ($session->id ?? null) : ($session['id'] ?? null);
            if ($id) {
                $administration = Administration::with('manager.user')
                    ->forUser($user)
                    ->find($id);

                if ($administration && $user->canAccessAdministration((int) $administration->id)) {
                    session(['selected_administration' => $administration]);

                    return $administration;
                }
            }
        }

        $administration = PanelSelectionResolver::resolveAdministration($user);
        if (! $administration) {
            return null;
        }

        $administration->loadMissing('manager.user');
        session(['selected_administration' => $administration]);

        return $administration;
    }
}
