<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Entity;
use App\Models\Administration;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Auth;
use App\Services\FirebaseService;
use App\Services\FirebaseServiceModern;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    protected $firebaseService;
    protected $firebaseServiceModern;

    public function __construct(FirebaseService $firebaseService, FirebaseServiceModern $firebaseServiceModern)
    {
        $this->firebaseService = $firebaseService;
        $this->firebaseServiceModern = $firebaseServiceModern;

        $this->middleware(function ($request, $next) {
            $action = $request->route()?->getActionMethod();
            $tokenExcept = ['registerToken', 'unregisterToken', 'getFirebaseConfig'];
            $panelInboxActions = ['panelInboxFeed', 'panelInboxMarkRead', 'panelInboxMarkAllRead'];

            if (in_array($action, $tokenExcept, true)) {
                return $next($request);
            }

            if (in_array($action, $panelInboxActions, true)) {
                $user = auth()->user();
                if (! $user || (! $user->isSuperAdmin() && ! $user->isAdministrationPanelAccount() && ! $user->isEntityPanelAccount())) {
                    abort(403, 'No autorizado.');
                }

                return $next($request);
            }

            $this->ensureSuperAdmin();

            return $next($request);
        });
    }

    private function ensureSuperAdmin(): void
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403, 'Solo el superadministrador puede gestionar notificaciones push del panel.');
        }
    }

    private function forgetPushWizardSession(Request $request): void
    {
        $request->session()->forget([
            'notification_type',
            'selected_entity',
            'selected_entities',
            'selected_entities_data',
            'selected_administration',
            'push_scope',
            'push_recipient_mode',
            'push_manager_ids',
        ]);
    }
    /**
     * Display a listing of notifications
     */
    public function index()
    {
        $notifications = Notification::with(['sender', 'entity.manager.user', 'administration'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Show the form for creating a new notification - Step 1: Type selection
     */
    public function create()
    {
        return view('notifications.create');
    }

    /**
     * Store notification type selection and redirect to appropriate step
     */
    public function storeType(Request $request)
    {
        $request->validate([
            'notification_type' => 'required|in:administration,entity,user',
        ]);

        $request->session()->put('notification_type', $request->notification_type);

        if ($request->notification_type === 'entity') {
            return redirect()->route('notifications.select-entity');
        }

        if ($request->notification_type === 'user') {
            return redirect()->route('notifications.push-to-user');
        }

        return redirect()->route('notifications.select-administration');
    }

    /**
     * Formulario: push FCM a un usuario concreto (gestores/vendedores de entidades a las que tienes acceso).
     */
    public function pushToUserForm()
    {
        $auth = auth()->user();
        $users = $auth->isSuperAdmin()
            ? User::query()->with('fcmTokens')->orderBy('name')->get()
                ->reject(fn (User $u) => $u->shouldExcludeFromOperationalPushRecipients())
                ->values()
            : $this->collectUsersLinkedToEntities($auth->accessibleEntityIds());

        return view('notifications.push-to-user', [
            'users' => $users,
        ]);
    }

    /**
     * Envía push FCM al usuario seleccionado (sin crear filas por entidad en `notifications`).
     */
    public function storeUserPush(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $auth = auth()->user();
        $target = User::query()->with('fcmTokens')->findOrFail((int) $request->user_id);

        if ($target->shouldExcludeFromOperationalPushRecipients()) {
            abort(403, 'No se envían pushes a administradores del sistema ni a cuentas de acceso directo del panel (administración o entidad).');
        }

        if (! $this->authUserMaySendDirectPushTo($auth, $target)) {
            abort(403, 'No puedes enviar notificaciones a este usuario.');
        }

        if ($target->fcmTokens->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['user_id' => 'Este usuario no tiene dispositivos con token FCM registrados.']);
        }

        $sent = 0;
        $failed = 0;
        $notification = null;

        DB::beginTransaction();
        try {
            $notification = Notification::create([
                'title' => $request->title,
                'message' => $request->message,
                'sender_id' => $auth->id,
                'recipient_user_id' => $target->id,
                'entity_id' => null,
                'administration_id' => null,
                'kind' => 'push_directo_panel',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            foreach ($target->fcmTokens as $device) {
                try {
                    $ok = $this->firebaseServiceModern->sendToDevice(
                        $device->token,
                        $request->title,
                        $request->message,
                        [
                            'type' => 'inbox_notification',
                            'notification_id' => (string) $notification->id,
                            'kind' => 'push_directo_panel',
                            'sender_id' => (string) $auth->id,
                            'sender_name' => (string) $auth->name,
                            'target_user_id' => (string) $target->id,
                            'platform' => (string) $device->platform,
                        ]
                    );
                    if ($ok) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    \Log::error('storeUserPush FCM: '.$e->getMessage());
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($sent === 0 && isset($notification)) {
            $notification->delete();

            return back()
                ->withInput()
                ->withErrors(['user_id' => 'No se pudo entregar el push. Revisa credenciales Firebase y los logs.']);
        }

        return redirect()
            ->route('notifications.push-to-user')
            ->with('success', "Push enviado: {$sent} dispositivo(s)".($failed > 0 ? "; {$failed} fallido(s)." : '.'));
    }

    /**
     * Show entity selection form - Step 2a: Entity selection
     */
    public function selectEntity(Request $request)
    {
        if (!session('notification_type')) {
            return redirect()->route('notifications.create');
        }

        if ($entity = \App\Support\PanelSelectionResolver::resolveEntity($request->user())) {
            $request->session()->put('selected_entity', $entity);
            $request->session()->put('selected_entities', [$entity->id]);
            $request->session()->put('push_scope', 'entities');

            return redirect()->route('notifications.select-recipients');
        }

        $entities = Entity::with(['administration'])
            ->forUser(auth()->user())
            ->where('status', 1)
            ->get();

        return view('notifications.select-entity', compact('entities'));
    }

    /**
     * Store selected entity and redirect to message form
     */
    public function storeEntity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id'
        ]);

        $entity = Entity::with('administration')
            ->forUser(auth()->user())
            ->findOrFail($request->entity_id);
        $request->session()->put('selected_entity', $entity);
        $request->session()->put('selected_entities', [$entity->id]);
        $request->session()->put('push_scope', 'entities');
        $request->session()->forget(['push_recipient_mode', 'push_manager_ids']);

        return redirect()->route('notifications.select-recipients');
    }

    /**
     * Show administration selection form - Step 2b: Administration selection
     */
    public function selectAdministration(Request $request)
    {
        if (!session('notification_type') || session('notification_type') !== 'administration') {
            return redirect()->route('notifications.create');
        }

        if ($redirect = $this->redirectIfImplicitAdministration($request, 'notifications.select-administration-target')) {
            return $redirect;
        }

        $administrations = Administration::forUser(auth()->user())
            ->where('status', 1)
            ->get();

        return view('notifications.select-administration', compact('administrations'));
    }

    /**
     * Store selected administration and redirect to entities selection
     */
    public function storeAdministration(Request $request)
    {
        $request->validate([
            'administration_id' => 'required|exists:administrations,id'
        ]);

        $administration = Administration::forUser(auth()->user())->findOrFail($request->administration_id);
        $request->session()->put('selected_administration', $administration);
        $request->session()->forget([
            'selected_entities',
            'selected_entities_data',
            'selected_entity',
            'push_scope',
            'push_recipient_mode',
            'push_manager_ids',
        ]);

        return redirect()->route('notifications.select-administration-target');
    }

    /**
     * Tras elegir administración: enviar a la administración o a sus entidades.
     */
    public function selectAdministrationTarget()
    {
        if (! session('selected_administration')) {
            return redirect()->route('notifications.create');
        }

        $administration = session('selected_administration');
        if (! $administration || ! auth()->user()->canAccessAdministration($administration->id)) {
            return redirect()->route('notifications.create')
                ->with('error', 'Permisos insuficientes para la administración seleccionada.');
        }

        return view('notifications.select-administration-target', compact('administration'));
    }

    public function storeAdministrationTarget(Request $request)
    {
        $request->validate([
            'admin_target' => 'required|in:administration,entities',
        ]);

        $administration = session('selected_administration');
        if (! $administration || ! auth()->user()->canAccessAdministration($administration->id)) {
            return redirect()->route('notifications.create')
                ->with('error', 'Permisos insuficientes para la administración seleccionada.');
        }

        if ($request->admin_target === 'administration') {
            $request->session()->put('push_scope', 'administration');
            $request->session()->forget([
                'selected_entities',
                'selected_entities_data',
                'selected_entity',
                'push_recipient_mode',
                'push_manager_ids',
            ]);

            return redirect()->route('notifications.message');
        }

        $request->session()->put('push_scope', 'entities');

        return redirect()->route('notifications.select-administration-entities');
    }

    /**
     * Show entities of selected administration - Step 3: Administration entities selection
     */
    public function selectAdministrationEntities()
    {
        if (!session('selected_administration')) {
            return redirect()->route('notifications.create');
        }

        $administration = session('selected_administration');

        if (!$administration || !auth()->user()->canAccessAdministration($administration->id)) {
            return redirect()->route('notifications.create')
                ->with('error', 'Permisos insuficientes para la administración seleccionada.');
        }

        $entities = Entity::forUser(auth()->user())
            ->where('administration_id', $administration->id)
            ->where('status', 1)
            ->get();

        return view('notifications.select-administration-entities', compact('entities', 'administration'));
    }

    /**
     * Store selected entities and redirect to message form
     */
    public function storeAdministrationEntities(Request $request)
    {
        $request->validate([
            'entity_ids' => 'required_without:send_to_all|array|min:1',
            'entity_ids.*' => 'exists:entities,id',
            'send_to_all' => 'nullable|boolean',
        ]);

        if ($request->boolean('send_to_all')) {
            $administration = session('selected_administration');

            if (!$administration || !auth()->user()->canAccessAdministration($administration->id)) {
                return redirect()->route('notifications.create')
                    ->with('error', 'Permisos insuficientes para la administración seleccionada.');
            }

            $entityIds = Entity::forUser(auth()->user())
                ->where('administration_id', $administration->id)
                ->where('status', 1)
                ->pluck('id')
                ->toArray();
        } else {
            $entityIds = collect($request->entity_ids)
                ->filter(fn ($id) => auth()->user()->canAccessEntity((int) $id))
                ->values()
                ->all();

            if (empty($entityIds)) {
                return back()->withErrors(['entity_ids' => 'Debes seleccionar al menos una entidad válida.']);
            }
        }

        $entities = Entity::forUser(auth()->user())
            ->whereIn('id', $entityIds)
            ->get();
        $request->session()->put('selected_entities', $entityIds);
        $request->session()->put('selected_entities_data', $entities);
        $request->session()->put('push_scope', 'entities');
        $request->session()->forget(['push_recipient_mode', 'push_manager_ids']);

        return redirect()->route('notifications.select-recipients');
    }

    /**
     * Destinatarios del push a entidad(es): cuenta panel, gestores o todos los involucrados.
     */
    public function selectRecipients()
    {
        if (session('push_scope') === 'administration') {
            return redirect()->route('notifications.message');
        }

        $selectedEntityIds = collect(session('selected_entities', []))
            ->filter(fn ($id) => auth()->user()->canAccessEntity((int) $id))
            ->values()
            ->all();

        if ($selectedEntityIds === []) {
            return redirect()->route('notifications.create');
        }

        $selectedEntities = Entity::with('administration')
            ->forUser(auth()->user())
            ->whereIn('id', $selectedEntityIds)
            ->get();

        $managers = \App\Models\Manager::query()
            ->whereIn('entity_id', $selectedEntityIds)
            ->whereNotNull('user_id')
            ->with(['user', 'entity'])
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->values();

        return view('notifications.select-recipients', compact('selectedEntities', 'managers'));
    }

    public function storeRecipients(Request $request)
    {
        $request->validate([
            'recipient_mode' => 'required|in:entity,managers,all_involved',
            'manager_ids' => 'nullable|array',
            'manager_ids.*' => 'integer|exists:managers,id',
        ]);

        $selectedEntityIds = collect(session('selected_entities', []))
            ->filter(fn ($id) => auth()->user()->canAccessEntity((int) $id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($selectedEntityIds === []) {
            return redirect()->route('notifications.create')
                ->with('error', 'No se han seleccionado entidades válidas.');
        }

        $mode = $request->recipient_mode;
        $managerIds = [];

        if ($mode === 'managers') {
            $allowed = \App\Models\Manager::query()
                ->whereIn('entity_id', $selectedEntityIds)
                ->whereNotNull('user_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $managerIds = collect($request->input('manager_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $allowed, true))
                ->values()
                ->all();

            if ($managerIds === []) {
                return back()
                    ->withInput()
                    ->withErrors(['manager_ids' => 'Selecciona al menos un gestor o responsable.']);
            }
        }

        $request->session()->put('push_scope', 'entities');
        $request->session()->put('push_recipient_mode', $mode);
        $request->session()->put('push_manager_ids', $managerIds);

        return redirect()->route('notifications.message');
    }

    /**
     * Show message composition form - Step 4: Message form
     */
    public function message()
    {
        $pushScope = session('push_scope', 'entities');
        $administration = session('selected_administration');
        $recipientMode = session('push_recipient_mode');
        $selectedEntities = collect();

        if ($pushScope === 'administration') {
            if (! $administration || ! auth()->user()->canAccessAdministration($administration->id)) {
                return redirect()->route('notifications.create');
            }
        } else {
            $selectedEntityIds = collect(session('selected_entities', []))
                ->filter(fn ($id) => auth()->user()->canAccessEntity((int) $id))
                ->values()
                ->all();

            if ($selectedEntityIds === [] || ! $recipientMode) {
                return redirect()->route('notifications.create');
            }

            $selectedEntities = Entity::forUser(auth()->user())
                ->whereIn('id', $selectedEntityIds)
                ->get();
        }

        $recipientSummary = $this->describePushRecipients($pushScope, $recipientMode, $selectedEntities, $administration);

        return view('notifications.message', compact(
            'selectedEntities',
            'administration',
            'pushScope',
            'recipientMode',
            'recipientSummary'
        ));
    }

    /**
     * Store and send notification
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $pushScope = session('push_scope', 'entities');
        $administration = session('selected_administration');
        $recipientMode = session('push_recipient_mode');
        $managerIds = collect(session('push_manager_ids', []))->map(fn ($id) => (int) $id)->all();

        $selectedEntities = collect();
        $selectedEntityIds = [];

        if ($pushScope === 'administration') {
            if (! $administration || ! auth()->user()->canAccessAdministration($administration->id)) {
                return redirect()->route('notifications.create')
                    ->with('error', 'Administración no válida para la notificación.');
            }
        } else {
            $selectedEntityIds = collect(session('selected_entities', []))
                ->filter(fn ($id) => auth()->user()->canAccessEntity((int) $id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($selectedEntityIds === [] || ! $recipientMode) {
                return redirect()->route('notifications.create')
                    ->with('error', 'No se han seleccionado entidades o destinatarios válidos.');
            }

            $selectedEntities = Entity::forUser(auth()->user())
                ->whereIn('id', $selectedEntityIds)
                ->get();
        }

        $notification = null;
        $successCount = 0;
        $firebaseSuccess = false;
        $firebaseTokensCount = 0;

        DB::beginTransaction();
        try {
            $relevantUsers = $this->resolvePushRecipients(
                $pushScope,
                $administration,
                $selectedEntityIds,
                $recipientMode,
                $managerIds
            )->values();

            $kind = $pushScope === 'administration' ? 'manual_administracion' : 'manual_entidad';

            \Log::info('=== ENVIANDO NOTIFICACIÓN FIREBASE (panel push) ===', [
                'push_scope' => $pushScope,
                'recipient_mode' => $recipientMode,
                'users' => $relevantUsers->count(),
            ]);

            $firebaseSuccessCount = 0;
            $firebaseFailCount = 0;

            foreach ($relevantUsers as $user) {
                $entityIdForPush = $selectedEntityIds !== []
                    ? $this->primarySelectedEntityIdForUser($user, $selectedEntityIds)
                    : null;
                if ($entityIdForPush === null && $user->isEntityPanelAccount() && in_array((int) $user->panel_account_id, $selectedEntityIds, true)) {
                    $entityIdForPush = (int) $user->panel_account_id;
                }

                $administrationIdForRow = null;
                if ($pushScope === 'administration' && $administration) {
                    $administrationIdForRow = (int) $administration->id;
                } elseif ($entityIdForPush) {
                    $administrationIdForRow = Entity::query()->where('id', $entityIdForPush)->value('administration_id');
                } elseif ($user->isAdministrationPanelAccount()) {
                    $administrationIdForRow = (int) $user->panel_account_id;
                }

                $notification = Notification::create([
                    'title' => $request->title,
                    'message' => $request->message,
                    'sender_id' => Auth::id(),
                    'recipient_user_id' => $user->id,
                    'entity_id' => $entityIdForPush,
                    'administration_id' => $administrationIdForRow,
                    'kind' => $kind,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $successCount++;

                $user->loadMissing('fcmTokens');
                foreach ($user->fcmTokens as $device) {
                    $firebaseTokensCount++;
                    try {
                        $sent = $this->firebaseServiceModern->sendToDevice(
                            $device->token,
                            $request->title,
                            $request->message,
                            [
                                'type' => 'inbox_notification',
                                'notification_id' => (string) $notification->id,
                                'kind' => $kind,
                                'sender_name' => (string) Auth::user()->name,
                                'sender_id' => (string) Auth::id(),
                                'user_id' => (string) $user->id,
                                'user_role' => (string) $user->role,
                                'entity_id' => $entityIdForPush !== null ? (string) $entityIdForPush : '',
                                'entity_ids' => implode(',', $selectedEntityIds),
                                'platform' => (string) $device->platform,
                            ]
                        );
                        if ($sent) {
                            $firebaseSuccessCount++;
                        } else {
                            $firebaseFailCount++;
                        }
                    } catch (\Exception $e) {
                        $firebaseFailCount++;
                        \Log::error("Error enviando a usuario {$user->id}: ".$e->getMessage());
                    }
                }
            }

            $firebaseSuccess = $firebaseSuccessCount > 0;
            \Log::info("Resultado FCM: {$firebaseSuccessCount} ok, {$firebaseFailCount} fallidas; bandeja: {$successCount}");

            if ($successCount === 0) {
                DB::rollBack();

                return back()->withErrors(['error' => 'No hay destinatarios para este envío.']);
            }

            DB::commit();

            $this->forgetPushWizardSession($request);

            return redirect()->route('notifications.success')
                ->with('success_count', $successCount)
                ->with('notification', $notification)
                ->with('firebase_success', $firebaseSuccess)
                ->with('firebase_tokens_count', $firebaseTokensCount);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error al procesar notificación: '.$e->getMessage());

            return back()->withErrors(['error' => 'Error al enviar la notificación: '.$e->getMessage()]);
        }
    }

    /**
     * Show success message
     */
    public function success()
    {
        $successCount = session('success_count', 0);
        $notification = session('notification');

        return view('notifications.success', compact('successCount', 'notification'));
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return redirect()->route('notifications.index')
            ->with('success', 'Notificación eliminada correctamente');
    }

    /**
     * Get entities by administration (AJAX)
     */
    public function getEntitiesByAdministration(Request $request)
    {
        $administrationId = $request->administration_id;

        if (!auth()->user()->canAccessAdministration((int) $administrationId)) {
            return response()->json([]);
        }

        $entities = Entity::forUser(auth()->user())
            ->where('administration_id', $administrationId)
            ->where('status', 1)
            ->get();

        return response()->json($entities);
    }

    /**
     * Get Firebase configuration for frontend
     */
    public function getFirebaseConfig()
    {
        return response()->json($this->firebaseServiceModern->getConfig());
    }

    /**
     * Register FCM token for user (app móvil envía `fcm_token`; el panel puede enviar `token`).
     */
    public function registerToken(Request $request)
    {
        $token = $request->input('fcm_token') ?? $request->input('token');
        if (! is_string($token) || $token === '') {
            return response()->json([
                'success' => false,
                'message' => 'Se requiere el token de dispositivo (campos aceptados: fcm_token o token).',
            ], 422);
        }

        $platform = strtolower((string) $request->input('platform', 'android'));
        if (! in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        $user = Auth::user();
        UserFcmToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform]
        );

        \Log::info('FCM Token registered for user ' . Auth::id() . ' (' . $platform . '): ' . substr($token, 0, 48) . '...');

        return response()->json(['success' => true]);
    }

    /**
     * Elimina el token FCM de este dispositivo para el usuario autenticado (evita notificaciones tras cerrar sesión).
     */
    public function unregisterToken(Request $request)
    {
        $token = $request->input('fcm_token') ?? $request->input('token');
        if (! is_string($token) || $token === '') {
            return response()->json(['success' => true]);
        }

        $deleted = UserFcmToken::query()
            ->where('user_id', Auth::id())
            ->where('token', $token)
            ->delete();

        \Log::info('FCM Token unregistered for user ' . Auth::id() . ', rows deleted: ' . $deleted);

        return response()->json(['success' => true]);
    }

    /**
     * Show Firebase dashboard
     */
    public function dashboard()
    {
        $usersWithTokens = User::query()->has('fcmTokens')->get()
            ->reject(fn (User $u) => $u->shouldExcludeFromOperationalPushRecipients())
            ->count();
        $users = User::query()->withCount('fcmTokens')->orderBy('name')->get()
            ->reject(fn (User $u) => $u->shouldExcludeFromOperationalPushRecipients())
            ->values();
        $totalNotifications = Notification::count();
        $notificationsToday = Notification::whereDate('created_at', today())->count();
        $recentNotifications = Notification::with(['sender', 'entity'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Verificar configuración
        $config = [
            'credentials' => file_exists(storage_path('firebase-credentials.json')),
            'api_key' => !empty(config('firebase.api_key')),
            'project_id' => config('firebase.project_id'),
            'server_key' => !empty(config('firebase.server_key')),
            'service_worker' => file_exists(public_path('firebase-messaging-sw.js')),
        ];
        $config['all_configured'] = $config['credentials'] && $config['api_key'] && $config['project_id'];

        return view('notifications.dashboard', compact(
            'usersWithTokens',
            'users',
            'totalNotifications',
            'notificationsToday',
            'recentNotifications',
            'config'
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Entity>  $selectedEntities
     */
    private function describePushRecipients(
        string $pushScope,
        ?string $recipientMode,
        $selectedEntities,
        $administration
    ): string {
        if ($pushScope === 'administration') {
            $name = $administration->name ?? 'Administración';

            return "Cuenta panel de la administración «{$name}»";
        }

        return match ($recipientMode) {
            'entity' => 'Cuentas panel de la(s) entidad(es) seleccionada(s)',
            'managers' => 'Gestores/responsables seleccionados',
            'all_involved' => 'Administración, entidad y gestores/responsables involucrados',
            default => 'Destinatarios del push',
        };
    }

    /**
     * Destinatarios del wizard (incluye cuentas panel cuando el modo lo pide; sin vendedores).
     *
     * @param  array<int>  $entityIds
     * @param  array<int>  $managerIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolvePushRecipients(
        string $pushScope,
        $administration,
        array $entityIds,
        ?string $recipientMode,
        array $managerIds
    ): \Illuminate\Support\Collection {
        $byId = [];

        $addUser = function (?User $user, string $roleLabel) use (&$byId): void {
            if (! $user || $user->isSuperAdmin()) {
                return;
            }
            if (! isset($byId[$user->id])) {
                $user->loadMissing('fcmTokens');
                $user->role = $roleLabel;
                $byId[$user->id] = $user;
            }
        };

        if ($pushScope === 'administration' && $administration) {
            $panelUsers = User::query()
                ->where('panel_account_type', 'administration')
                ->where('panel_account_id', $administration->id)
                ->with('fcmTokens')
                ->get();
            foreach ($panelUsers as $u) {
                $addUser($u, 'administration_panel');
            }

            return collect($byId)->sortBy('name')->values();
        }

        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));

        if ($recipientMode === 'entity' || $recipientMode === 'all_involved') {
            $panelUsers = User::query()
                ->where('panel_account_type', 'entity')
                ->whereIn('panel_account_id', $entityIds)
                ->with('fcmTokens')
                ->get();
            foreach ($panelUsers as $u) {
                $addUser($u, 'entity_panel');
            }
        }

        if ($recipientMode === 'all_involved') {
            $adminIds = Entity::query()->whereIn('id', $entityIds)->pluck('administration_id')->filter()->unique()->all();
            if ($adminIds !== []) {
                $adminPanels = User::query()
                    ->where('panel_account_type', 'administration')
                    ->whereIn('panel_account_id', $adminIds)
                    ->with('fcmTokens')
                    ->get();
                foreach ($adminPanels as $u) {
                    $addUser($u, 'administration_panel');
                }
            }
        }

        if ($recipientMode === 'managers') {
            $managers = \App\Models\Manager::query()
                ->whereIn('id', $managerIds)
                ->whereIn('entity_id', $entityIds)
                ->with('user.fcmTokens')
                ->get();
            foreach ($managers as $manager) {
                $addUser($manager->user, $manager->is_primary ? 'responsable' : 'gestor');
            }
        }

        if ($recipientMode === 'all_involved') {
            $managers = \App\Models\Manager::query()
                ->whereIn('entity_id', $entityIds)
                ->whereNotNull('user_id')
                ->with('user.fcmTokens')
                ->get();
            foreach ($managers as $manager) {
                $addUser($manager->user, $manager->is_primary ? 'responsable' : 'gestor');
            }
        }

        return collect($byId)->sortBy('name')->values();
    }

    /**
     * Gestores y vendedores vinculados a las entidades indicadas (un usuario aparece una vez; prioridad rol gestor).
     *
     * @param  array<int|string>  $entityIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function collectUsersLinkedToEntities(array $entityIds): \Illuminate\Support\Collection
    {
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if ($entityIds === []) {
            return collect();
        }

        $byId = [];

        $managers = \App\Models\Manager::query()
            ->whereIn('entity_id', $entityIds)
            ->with('user.fcmTokens')
            ->get();

        foreach ($managers as $manager) {
            if ($manager->user) {
                $u = $manager->user;
                $u->role = 'manager';
                $byId[$u->id] = $u;
            }
        }

        $sellers = \App\Models\Seller::query()
            ->whereHas('entities', fn ($q) => $q->whereIn('entities.id', $entityIds))
            ->where('seller_type', '!=', 'externo')
            ->whereNotNull('user_id')
            ->where('user_id', '>', 0)
            ->with('user.fcmTokens')
            ->get();

        foreach ($sellers as $seller) {
            if ($seller->user && ! isset($byId[$seller->user->id])) {
                $u = $seller->user;
                $u->role = 'seller';
                $byId[$seller->user->id] = $u;
            }
        }

        return collect($byId)
            ->reject(fn (User $u) => $u->shouldExcludeFromOperationalPushRecipients())
            ->sortBy('name')
            ->values();
    }

    private function authUserMaySendDirectPushTo(User $auth, User $target): bool
    {
        if ($auth->isSuperAdmin()) {
            return true;
        }

        return $this->collectUsersLinkedToEntities($auth->accessibleEntityIds())
            ->contains('id', $target->id);
    }

    /**
     * Obtener usuarios relevantes de las entidades seleccionadas (solo con token FCM).
     */
    private function getUsersFromEntities($entityIds)
    {
        return $this->collectUsersLinkedToEntities(is_array($entityIds) ? $entityIds : [])
            ->filter(fn (User $u) => $u->fcmTokens->isNotEmpty())
            ->values();
    }

    /**
     * Primera entidad seleccionada a la que el usuario está vinculado como gestor (prioridad) o vendedor.
     * Sirve para el notification_id del push cuando hay varias entidades en el mismo envío.
     *
     * @param  array<int>  $selectedEntityIds
     */
    private function primarySelectedEntityIdForUser(User $user, array $selectedEntityIds): ?int
    {
        $selectedEntityIds = array_values(array_unique(array_map('intval', $selectedEntityIds)));
        sort($selectedEntityIds);

        foreach ($selectedEntityIds as $eid) {
            if (\App\Models\Manager::query()->where('entity_id', $eid)->where('user_id', $user->id)->exists()) {
                return $eid;
            }
        }

        foreach ($selectedEntityIds as $eid) {
            if (\App\Models\Seller::query()->where('user_id', $user->id)
                ->whereHas('entities', fn ($q) => $q->where('entities.id', $eid))
                ->exists()) {
                return $eid;
            }
        }

        return null;
    }

    /**
     * Send test notification to all users
     */
    public function sendTest(Request $request)
    {
        try {
            $devices = UserFcmToken::query()
                ->with('user')
                ->get()
                ->filter(fn ($d) => $d->user && ! $d->user->shouldExcludeFromOperationalPushRecipients())
                ->values();

            if ($devices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay tokens FCM en usuarios elegibles (se excluyen administración del sistema, superadmin y cuentas panel).',
                ], 400);
            }

            $deviceCount = $devices->count();
            \Log::info('📤 Iniciando envío de notificación de prueba a '.$deviceCount.' dispositivo(s)');

            $successCount = 0;
            $failCount = 0;

            foreach ($devices as $device) {
                $user = $device->user;
                $label = $user ? $user->name : ('user#' . $device->user_id);
                try {
                    \Log::info('  Enviando a: ' . $label . ' [' . $device->platform . '] (' . substr($device->token, 0, 50) . '...)');

                    $sent = $this->firebaseServiceModern->sendToDevice(
                        $device->token,
                        '🔥 Notificación de Prueba',
                        'Firebase está funcionando correctamente. ¡Las notificaciones push están activas!',
                        [
                            'type' => 'test',
                            'timestamp' => now()->toIso8601String(),
                            'user_id' => (string) $device->user_id,
                            'platform' => $device->platform,
                        ]
                    );

                    if ($sent) {
                        $successCount++;
                        \Log::info('  ✅ Enviado exitosamente a ' . $label);
                    } else {
                        $failCount++;
                        \Log::warning('  ⚠️ Falló el envío a ' . $label);
                    }
                } catch (\Exception $e) {
                    $failCount++;
                    \Log::error('  ❌ Error enviando a ' . $label . ': ' . $e->getMessage());
                }
            }

            \Log::info('📊 Resultado: ' . $successCount . ' exitosos, ' . $failCount . ' fallidos');

            if ($successCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Notificación enviada a {$successCount} de {$deviceCount} dispositivo(s)",
                    'success_count' => $successCount,
                    'fail_count' => $failCount
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo enviar ninguna notificación. Revisa los logs.'
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Error al enviar notificación de prueba: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Bandeja panel (campana administración / entidad)
    // -------------------------------------------------------------------------

    public function panelInboxFeed(Request $request)
    {
        $user = Auth::user();
        $limit = min(20, max(1, (int) $request->input('limit', 10)));

        $items = $this->panelInboxQuery($user)
            ->with(['sender:id,name', 'entity:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => \Illuminate\Support\Str::limit((string) $n->message, 120),
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
                'created_at_human' => $n->created_at?->diffForHumans(),
                'sender' => $n->sender?->name,
            ]);

        $unread = $this->panelInboxQuery($user)->whereNull('read_at')->count();

        return response()->json([
            'success' => true,
            'unread' => $unread,
            'notifications' => $items,
        ]);
    }

    public function panelInboxMarkRead(Request $request, int $id)
    {
        $user = Auth::user();
        $n = $this->panelInboxQuery($user)->where('id', $id)->firstOrFail();
        if ($n->read_at === null) {
            $n->markAsRead();
            if ($n->status !== 'read') {
                $n->update(['status' => 'read']);
            }
        }

        return response()->json([
            'success' => true,
            'unread' => $this->panelInboxQuery($user)->whereNull('read_at')->count(),
        ]);
    }

    public function panelInboxMarkAllRead()
    {
        $user = Auth::user();
        $this->panelInboxQuery($user)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => 'read',
            ]);

        return response()->json(['success' => true, 'unread' => 0]);
    }

    private function panelInboxQuery(User $user)
    {
        return Notification::query()->where(function ($q) use ($user) {
            $q->where('recipient_user_id', $user->id);

            if ($user->isEntityPanelAccount() && $user->panel_account_id) {
                $q->orWhere(function ($inner) use ($user) {
                    $inner->whereNull('recipient_user_id')
                        ->where('entity_id', (int) $user->panel_account_id);
                });
            }

            if ($user->isAdministrationPanelAccount() && $user->panel_account_id) {
                $q->orWhere(function ($inner) use ($user) {
                    $inner->whereNull('recipient_user_id')
                        ->where('administration_id', (int) $user->panel_account_id);
                });
            }
        });
    }

    // -------------------------------------------------------------------------
    // API app móvil (Bearer), bandeja de notificaciones
    // -------------------------------------------------------------------------

    public function apiIndex(Request $request)
    {
        $user = Auth::user();
        $entityIds = $user->accessibleEntityIds();

        $query = Notification::query()
            ->with(['entity:id,name,image', 'sender:id,name'])
            ->where(function ($q) use ($user, $entityIds) {
                $q->where('recipient_user_id', $user->id);
                if (! empty($entityIds)) {
                    $q->orWhereIn('entity_id', $entityIds);
                }
            })
            ->orderByDesc('created_at')
            ->limit(400);

        $notifications = $query->get()->map(fn ($n) => $this->formatNotificationForApp($n));

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
        ]);
    }

    public function apiShow(Request $request, int $id)
    {
        $n = Notification::with(['entity', 'sender'])->findOrFail($id);
        $this->authorizeNotificationAccess($n, Auth::user());

        return response()->json([
            'success' => true,
            'notification' => $this->formatNotificationForApp($n),
        ]);
    }

    public function apiMarkAsRead(Request $request, int $id)
    {
        $n = Notification::query()->findOrFail($id);
        $this->authorizeNotificationAccess($n, Auth::user());
        if ($n->read_at === null) {
            $n->markAsRead();
            if ($n->status !== 'read') {
                $n->update(['status' => 'read']);
            }
        }

        return response()->json(['success' => true]);
    }

    public function apiMarkAllAsRead(Request $request)
    {
        $user = Auth::user();
        $entityIds = $user->accessibleEntityIds();

        $q = Notification::query()
            ->whereNull('read_at')
            ->where(function ($query) use ($user, $entityIds) {
                $query->where('recipient_user_id', $user->id);
                if (! empty($entityIds)) {
                    $query->orWhereIn('entity_id', $entityIds);
                }
            });

        $q->update([
            'read_at' => now(),
            'status' => 'read',
        ]);

        return response()->json(['success' => true]);
    }

    public function apiUnreadCount(Request $request)
    {
        $user = Auth::user();
        $entityIds = $user->accessibleEntityIds();

        $count = Notification::query()
            ->whereNull('read_at')
            ->where(function ($query) use ($user, $entityIds) {
                $query->where('recipient_user_id', $user->id);
                if (! empty($entityIds)) {
                    $query->orWhereIn('entity_id', $entityIds);
                }
            })
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    public function apiDestroy(Request $request, int $id)
    {
        $n = Notification::query()->findOrFail($id);
        $this->authorizeNotificationAccess($n, Auth::user());
        $n->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeNotificationAccess(Notification $n, User $user): void
    {
        if ($n->recipient_user_id && (int) $n->recipient_user_id === (int) $user->id) {
            return;
        }
        $entityIds = array_map('intval', $user->accessibleEntityIds());
        if ($n->entity_id && in_array((int) $n->entity_id, $entityIds, true)) {
            return;
        }
        abort(403, 'No puedes acceder a esta notificación.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotificationForApp(Notification $n): array
    {
        $meta = is_array($n->meta) ? $n->meta : [];
        $kind = $n->kind ?? 'manual';
        $rol = $meta['rol_context'] ?? (
            in_array($kind, ['asignacion_participaciones', 'invitacion_vendedor'], true) ? 'vendedor' : 'usuario'
        );

        return [
            'id' => $n->id,
            'tipo' => $kind,
            'titulo' => $n->title,
            'mensaje' => $n->message,
            'fecha' => $n->created_at?->toIso8601String(),
            'leida' => $n->read_at !== null,
            'detalle' => $meta['detalle'] ?? null,
            'rolContext' => $rol,
            'entidadNombre' => $n->entity?->name ?? ($meta['entidad_nombre'] ?? $n->title),
            'invitadorTexto' => $meta['invitador_texto'] ?? null,
            'entity_image' => $n->entity?->image ?? null,
        ];
    }
}
