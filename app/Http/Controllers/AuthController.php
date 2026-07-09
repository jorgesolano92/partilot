<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Seller;
use App\Models\Manager;
use App\Mail\UserWelcomeMail;
use App\Models\ParticipationGift;
use App\Services\CommunicationEmailService;
use App\Services\DashboardService;
use App\Services\ParticipationGiftService;
use App\Services\PendingDigitalSaleService;
use App\Services\RoleLegalAcceptanceService;
use App\Services\UserConsentService;
use App\Support\ActiveEntityContext;
use App\Support\PasswordRules;

class AuthController extends Controller
{
    /**
     * Mostrar el formulario de login
     */
    public function showLoginForm()
    {
        // Si ya está autenticado, redirigir al dashboard
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        
        return view('login');
    }

    /**
     * Procesar el login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|max:255',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'Indique su usuario o email.',
            'password.required' => 'El campo contraseña es obligatorio.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $login = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()
            ->where(function ($q) use ($login) {
                $q->where('email', $login)
                    ->orWhere('panel_login_username', $login);
            })
            ->first();
        // Modificar esta condición para acceder tanto con email como con usuario
        if ($user && $user->isAdministrationPanelAccount()) {
            $panelUsername = trim((string) ($user->panel_login_username ?? ''));
            if ($panelUsername === '' || strcasecmp($login, $panelUsername) !== 0) {
                return back()->withErrors([
                    'email' => 'Para acceder al panel use el usuario asignado en el correo de bienvenida, no el email de la administración.',
                ])->withInput($request->only('email'));
            }
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ])->withInput($request->only('email'));
        }

        if ($user->isAdministrationContactOnly()) {
            return back()->withErrors([
                'email' => 'Esta cuenta no tiene acceso al panel.',
            ])->withInput($request->only('email'));
        }

        if ($user->deletion_requested_at) {
            return back()->withErrors([
                'email' => 'Esta cuenta está desactivada por solicitud de baja.',
            ])->withInput($request->only('email'));
        }

        Auth::login($user, $request->filled('remember'));
        $request->session()->regenerate();

        $user = Auth::user();

        // Cuenta panel de entidad: al primer acceso con credenciales válidas, activar entidad pendiente
        // (sustituye la activación que antes hacía el enlace mágico al establecer contraseña).
        if ($user->isPanelAccount() && $user->panel_account_type === 'entity') {
            $entity = \App\Models\Entity::query()->find($user->panel_account_id);
            if ($entity && ($entity->status === null || (int) $entity->status === -1)) {
                $entity->update(['status' => 1]);
            }
        }

        // Bloquear login si la administración/entidad asociada está pendiente o inactiva.
        // Regla: solo se permite acceso si el panel asociado tiene status == 1 (Activo).
        if (! $user->isSuperAdmin()) {
                $hasActiveAccess = false;

                if ($user->isPanelAccount()) {
                    if ($user->panel_account_type === 'administration') {
                        $hasActiveAccess = \App\Models\Administration::query()
                            ->whereKey($user->panel_account_id)
                            ->where('status', 1)
                            ->exists();
                    } elseif ($user->panel_account_type === 'entity') {
                        $hasActiveAccess = \App\Models\Entity::query()
                            ->whereKey($user->panel_account_id)
                            ->where('status', 1)
                            ->exists();
                    } elseif ($user->panel_account_type === User::PANEL_ACCOUNT_PRINT_SHOP) {
                        $hasActiveAccess = (bool) $user->status;
                    }
                } else {
                    // Gestores (managers) sin panel_account_type.
                    if ($user->isAdministration()) {
                        $hasActiveAccess = $user->managers()
                            ->where('status', 1)
                            ->whereHas('administration', fn ($q) => $q->where('status', 1))
                            ->exists();
                    }

                    if (! $hasActiveAccess && $user->isEntity()) {
                        $hasActiveAccess = $user->managers()
                            ->where('status', 1)
                            ->whereHas('entity', fn ($q) => $q->where('status', 1))
                            ->exists();
                    }
                }

                if (! $hasActiveAccess) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Tu administración o entidad asociada no está activa (pendiente o inactiva).',
                    ])->withInput($request->only('email'));
                }
        }

        // Superadmin, cuentas panel (administración/entidad) y gestores de entidad (tienen entity_id)
        // acceden al panel web.
        if (! $user->isSuperAdmin() && ! $user->isPanelAccount() && ! $user->isEntity()) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Tu cuenta no tiene acceso al panel. Use el usuario o email y contraseña de su administración o entidad.',
            ])->withInput($request->only('email'));
        }

        if ($user->mustChangeEntityManagerLegacyPassword()) {
            return redirect()->route('entity-manager.legacy-password.show');
        }

        $request->session()->forget('provisional_password_skipped');
        if ($user->mustChangeProvisionalPassword()) {
            return redirect()->route('provisional-password.show');
        }

        if ($user->isPrintShop()) {
            return redirect()->intended(route('print-shop.index'));
        }

        ActiveEntityContext::bootstrapSession($request, $user);

        return redirect()->intended('/dashboard');
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Formulario para sustituir la contraseña por defecto (12345678) en gestores de entidad.
     */
    public function showEntityManagerLegacyPassword()
    {
        $user = Auth::user();
        if (! $user || ! $user->mustChangeEntityManagerLegacyPassword()) {
            return redirect()->route('dashboard');
        }

        return view('auth.entity-manager-legacy-password');
    }

    /**
     * Guardar nueva contraseña (sustituye la provisional).
     */
    public function updateEntityManagerLegacyPassword(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->mustChangeEntityManagerLegacyPassword()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((string) $value === User::ENTITY_MANAGER_LEGACY_DEFAULT_PASSWORD) {
                        $fail('La nueva contraseña no puede ser 12345678. Elija otra distinta a la provisional.');
                    }
                },
            ],
        ], [
            'password.required' => 'Indique la nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide.',
        ]);

        $user->password = $request->input('password');
        $user->save();
        $user->refresh();

        Auth::login($user);
        ActiveEntityContext::bootstrapSession($request, $user);

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }

    public function showProvisionalPassword()
    {
        $user = Auth::user();
        if (! $user || ! $user->mustChangeProvisionalPassword()) {
            return redirect()->route('dashboard');
        }

        return view('auth.provisional-password');
    }

    public function updateProvisionalPassword(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->mustChangeProvisionalPassword()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Indique la nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide.',
        ]);

        $user->password = $request->input('password');
        $user->must_change_password = false;
        $user->save();

        $request->session()->forget('provisional_password_skipped');

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }

    public function skipProvisionalPassword(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->mustChangeProvisionalPassword()) {
            return redirect()->route('dashboard');
        }

        $request->session()->put('provisional_password_skipped', true);

        return redirect()->intended('/dashboard');
    }

    /**
     * Mostrar el dashboard
     */
    public function dashboard(DashboardService $dashboardService)
    {
        if (auth()->user()?->isPrintShop()) {
            return redirect()->route('print-shop.index');
        }

        $dashboard = $dashboardService->build(auth()->user());

        return view('welcome', compact('dashboard'));
    }

    /**
     * Crear un usuario administrador por defecto
     */
    public function createDefaultAdmin()
    {
        // Verificar si ya existe un usuario administrador
        $adminExists = User::where('email', 'admin@partilot.com')->exists();
        
        if (!$adminExists) {
            User::create([
                'name' => 'Administrador',
                'email' => 'admin@partilot.com',
                'password' => Hash::make('admin123'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]);
            
            return 'Usuario administrador creado exitosamente. Email: admin@partilot.com, Contraseña: admin123';
        }
        
        return 'El usuario administrador ya existe.';
    }

    /**
     * API: Login para aplicación móvil (solo vendedores activos con usuario vinculado)
     */
    public function apiLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'
            ], 401);
        }

        if ($user->deletion_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta está desactivada por solicitud de baja.',
            ], 403);
        }

        // Obtener el vendedor vinculado al usuario (por tabla sellers, no por rol en users)
        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();

        // Solo vendedores (presencia en tabla sellers activos) pueden acceder a esta ruta de login
        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los vendedores pueden acceder a esta aplicación.'
            ], 403);
        }

        // Verificar que el vendedor esté activo y no bloqueado
        if ((int) $seller->status !== Seller::STATUS_ACTIVE) {
            $message = match ((int) $seller->status) {
                Seller::STATUS_BLOCKED => 'Tu cuenta de vendedor está bloqueada. Contacta con tu administrador.',
                Seller::STATUS_INACTIVE => 'Tu cuenta de vendedor está inactiva. Contacta con tu administrador.',
                Seller::STATUS_PENDING => 'Tu cuenta de vendedor está pendiente de confirmación.',
                default => 'Tu cuenta de vendedor no está habilitada.',
            };
            return response()->json([
                'success' => false,
                'message' => $message
            ], 403);
        }

        // Crear token cifrado (sin tabla personal_access_tokens)
        $payload = [
            'user_id' => $user->id,
            'exp' => now()->addDays(30)->timestamp,
        ];
        $token = Crypt::encrypt($payload);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'seller' => $seller->load('entities'),
            'message' => 'Login exitoso'
        ]);
    }

    /**
     * API: Login para aplicación móvil - perfil Usuario.
     * Permite cualquier usuario con email/password. Las capacidades (cliente, vendedor, gestor)
     * se determinan por las tablas sellers y managers, no por users.role.
     */
    public function apiLoginUsuario(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'
            ], 401);
        }

        if ($user->isAdministrationContactOnly()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta no tiene acceso a la aplicación.',
            ], 403);
        }

        if ($user->deletion_requested_at) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta está desactivada por solicitud de baja.',
            ], 403);
        }

        $payload = [
            'user_id' => $user->id,
            'exp' => now()->addDays(30)->timestamp,
        ];
        $token = Crypt::encrypt($payload);

        $response = [
            'success' => true,
            'token' => $token,
            'user' => $user,
            'message' => 'Login exitoso'
        ];

        // Capacidades por tablas: seller activo → puede actuar como vendedor
        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->first();
        if ($seller) {
            $response['seller'] = $seller->load('entities');
        }

        // Capacidades por tablas: presencia en managers → puede actuar como gestor
        $manager = Manager::where('user_id', $user->id)->first();
        if ($manager) {
            $response['manager'] = $manager;
        }

        app(ParticipationGiftService::class)->attachPendingGiftsToUser($user);
        $response['pending_gifts_count'] = ParticipationGift::query()
            ->where('to_user_id', $user->id)
            ->where('status', ParticipationGift::STATUS_PENDING)
            ->count();

        $response['pending_role_invitations'] = app(RoleLegalAcceptanceService::class)
            ->pendingInvitationsForUser($user);

        return response()->json($response);
    }

    /**
     * API: Registro de cliente sencillo (app móvil).
     * Campos: email, password, fecha_nacimiento, aceptar_condiciones.
     */
    public function apiRegister(Request $request)
    {
        $codeLength = (int) config('sms.code_length', 6);

        $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            // App móvil (stores en revisión): sin campo confirmación; reactivar confirmed cuando la app lo envíe.
            'password' => PasswordRules::registration(confirmed: false),
            'phone' => 'nullable|string|max:20',
            'sms_code' => [
                \Illuminate\Validation\Rule::requiredIf(fn () => app(\App\Services\PhoneVerificationService::class)
                    ->smsVerificationRequired($request->input('phone'))),
                'nullable',
                'string',
                'size:'.$codeLength,
            ],
            'fecha_nacimiento' => 'required|date|before:today',
            'aceptar_condiciones' => 'required|accepted',
            'link_code' => 'nullable|string|min:5|max:12',
        ], [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del email no es válido.',
            'email.unique' => 'Ya existe una cuenta con este email.',
            'password.required' => 'La contraseña es obligatoria.',
            ...PasswordRules::messages(),
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no es válida.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'sms_code.required' => 'Si indicas teléfono, debes verificarlo con el código SMS.',
            'sms_code.size' => 'El código SMS debe tener '.$codeLength.' dígitos.',
            'aceptar_condiciones.required' => 'Debes aceptar las condiciones de uso.',
            'aceptar_condiciones.accepted' => 'Debes aceptar las condiciones de uso.',
        ]);

        $phoneVerification = app(\App\Services\PhoneVerificationService::class);
        try {
            $phone = $phoneVerification->resolveOptionalPhone($request->phone);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['phone' => [$e->getMessage()]],
            ], 422);
        }

        if ($phoneVerification->smsVerificationRequired($request->phone)) {
            if (! $phoneVerification->verifyCode($phone, (string) $request->sms_code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código SMS incorrecto o caducado. Solicita uno nuevo.',
                    'errors' => ['sms_code' => ['Código SMS incorrecto o caducado.']],
                ], 422);
            }
        }

        $name = strstr($request->email, '@', true) ?: 'Usuario';
        $name = substr($name, 0, 255);

        $user = User::create([
            'name' => $name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
            'birthday' => $request->fecha_nacimiento,
            'role' => User::ROLE_CLIENT,
            'status' => true,
        ]);

        app(UserConsentService::class)->record(
            $user,
            \App\Models\UserConsent::TYPE_REGISTRATION_TERMS,
            $request,
            ['source' => 'api_register']
        );

        app(ParticipationGiftService::class)->attachPendingGiftsToUser($user);

        $claimedByCode = 0;
        $linkCode = trim((string) $request->input('link_code', ''));
        if ($linkCode !== '') {
            try {
                $pending = app(PendingDigitalSaleService::class)->claimByLinkCode($user, $linkCode);
                $claimedByCode = (int) $pending->quantity;
            } catch (\InvalidArgumentException $e) {
                \Log::info('apiRegister: link_code no vinculado tras registro', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('apiRegister: error vinculando link_code', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'user_welcome',
                templateKey: null,
                mailClass: UserWelcomeMail::class,
                mailPayload: ['user_id' => $user->id],
                context: ['user_id' => $user->id, 'source' => 'api'],
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando bienvenida usuario: '.$e->getMessage());
        }

        $payload = ['user_id' => $user->id, 'exp' => now()->addDays(30)->timestamp];
        $token = Crypt::encrypt($payload);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'message' => $claimedByCode > 0
                ? 'Registro exitoso. Tus participaciones digitales ya están en tu cartera.'
                : 'Registro exitoso',
            'participations_claimed' => $claimedByCode,
        ], 201);
    }

    /**
     * API: Logout
     */
    public function apiLogout(Request $request)
    {
        // Token cifrado es stateless - no hay nada que revocar
        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * API: Refresh token (crear nuevo token).
     * Devuelve user, seller y manager según tablas (no users.role).
     */
    public function apiRefresh(Request $request)
    {
        $user = $request->user();

        $payload = ['user_id' => $user->id, 'exp' => now()->addDays(30)->timestamp];
        $token = Crypt::encrypt($payload);
        $response = [
            'success' => true,
            'token' => $token,
            'user' => $user
        ];

        $seller = Seller::where('user_id', $user->id)->where('status', Seller::STATUS_ACTIVE)->with('entities')->first();
        if ($seller) {
            $response['seller'] = $seller;
        }

        $manager = Manager::where('user_id', $user->id)->first();
        if ($manager) {
            $response['manager'] = $manager;
        }

        return response()->json($response);
    }
} 