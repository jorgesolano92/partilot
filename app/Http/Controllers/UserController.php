<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\ValidCalendarDate;
use App\Models\Seller;
use App\Models\Manager;
use App\Http\Controllers\ParticipationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Solo super_admin puede acceder al módulo de usuarios.
     */
    private function authorizeUsersModule(): void
    {
        $auth = auth()->user();
        if (! $auth || ! $auth->isSuperAdmin()) {
            abort(403, 'No autorizado.');
        }
    }

    /**
     * Perfil administración (no superadmin): solo usuarios de su red (gestores/vendedores).
     * @deprecated La administración ya no accede al módulo de usuarios.
     */
    private function authorizeUserVisibleToAdministration(User $user): void
    {
        $this->authorizeUsersModule();

        $auth = auth()->user();
        if ($auth && $auth->isAdministration() && ! $auth->isSuperAdmin() && ! $user->isAccessibleToAdministrationViewer($auth)) {
            abort(403, 'No autorizado.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeUsersModule();

        $query = User::query()
            ->whereNull('panel_account_type')
            ->excludingAdministrationContactRecords()
            ->orderBy('name');
        $auth = auth()->user();
        if ($auth && $auth->isAdministration() && ! $auth->isSuperAdmin()) {
            $query->forAdministrationScopedViewer($auth);
        }
        $users = $query->get();

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorizeUsersModule();
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeUsersModule();

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument, 'unique:users'],
            'birthday' => ValidCalendarDate::birthday(),
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Procesar imagen si se subió
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('users', 'public');
            }

            // Crear usuario con rol 'client' por defecto
            // Si hay vendedores pendientes con este email, el UserObserver cambiará el rol a 'seller'
            $user = User::create([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'nif_cif' => $request->nif_cif,
                'birthday' => $request->birthday,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'image' => $imagePath,
                'status' => true,
                'role' => User::ROLE_CLIENT, // Rol por defecto: cliente
            ]);

            // El UserObserver se encargará de vincular vendedores automáticamente
            Log::info("Usuario creado: {$user->id} - {$user->email}");

            DB::commit();

            return redirect()->route('users.show', $user->id)
                ->with('success', 'Usuario creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear usuario: " . $e->getMessage());
            
            return back()->withInput()
                ->with('error', 'Error al crear el usuario. Por favor, inténtalo de nuevo.');
        }
    }

    /**
     * Display the specified resource.
     * Acepta ?tab=cartera|historial para abrir directamente en esa pestaña.
     */
    public function show(Request $request, User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        $tab = $request->query('tab');
        return view('users.show', compact('user', 'tab'));
    }

    /**
     * Cartera del usuario: participaciones en cartera (propias + recibidas como regalo).
     */
    public function wallet(User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        $participationController = app(ParticipationController::class);
        $items = $participationController->getWalletDataForUser($user);
        return view('users.wallet', compact('user', 'items'));
    }

    /**
     * Historial del usuario: digitalizaciones, regalos, cobros, donaciones.
     */
    public function history(User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        $participationController = app(ParticipationController::class);
        $historial = $participationController->getHistorialDataForUser($user);
        return view('users.history', compact('user', 'historial'));
    }

    /**
     * Cambiar estado (Activo/Bloqueado) del usuario vía AJAX.
     */
    public function toggleStatus(Request $request, User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        $user->update(['status' => !$user->status]);
        return response()->json([
            'success' => true,
            'status' => (bool) $user->fresh()->status,
            'status_text' => $user->status ? 'Activo' : 'Bloqueado',
            'status_class' => $user->status ? 'success' : 'danger',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['required', 'string', 'max:20', new \App\Rules\SpanishDocument, 'unique:users,nif_cif,' . $user->id],
            'birthday' => ValidCalendarDate::birthday(),
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20',
            'password' => 'nullable|string|min:8',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Procesar imagen si se subió
            $imagePath = $user->image;
            if ($request->hasFile('image')) {
                // Eliminar imagen anterior si existe
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                $imagePath = $request->file('image')->store('users', 'public');
            }

            // Preparar datos de actualización
            $updateData = [
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'nif_cif' => $request->nif_cif,
                'birthday' => $request->birthday,
                'email' => $request->email,
                'phone' => $request->phone,
                'image' => $imagePath,
            ];

            // Actualizar contraseña solo si se proporcionó
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // Actualizar vendedores vinculados si el email cambió
            if ($request->email !== $user->getOriginal('email')) {
                $this->updateLinkedSellers($user);
            }

            DB::commit();

            return redirect()->route('users.show', $user->id)
                ->with('success', 'Usuario actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar usuario: " . $e->getMessage());
            
            return back()->withInput()
                ->with('error', 'Error al actualizar el usuario. Por favor, inténtalo de nuevo.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorizeUserVisibleToAdministration($user);
        if ($user->isPanelAccount()) {
            return back()->with('error', 'No se puede eliminar una cuenta de acceso al panel (administración o entidad).');
        }

        try {
            DB::beginTransaction();

            // Eliminar imagen si existe
            if ($user->image && Storage::disk('public')->exists($user->image)) {
                Storage::disk('public')->delete($user->image);
            }

            // Eliminar vendedores relacionados
            $sellers = Seller::where('user_id', $user->id)->get();
            foreach ($sellers as $seller) {
                // Eliminar relaciones many-to-many antes de eliminar el seller
                $seller->entities()->detach();
                $seller->groups()->detach();
                $seller->delete();
            }

            // Eliminar gestores relacionados
            Manager::where('user_id', $user->id)->delete();

            // Eliminar el usuario
            $user->delete();

            DB::commit();

            return redirect()->route('users.index')
                ->with('success', 'Usuario eliminado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar usuario: " . $e->getMessage());
            
            return back()->with('error', 'Error al eliminar el usuario. Por favor, inténtalo de nuevo.');
        }
    }

    /**
     * Obtener datos para DataTable
     */
    public function data(Request $request)
    {
        $this->authorizeUsersModule();

        $query = User::query()
            ->whereNull('panel_account_type')
            ->excludingAdministrationContactRecords();
        $auth = auth()->user();
        if ($auth && $auth->isAdministration() && ! $auth->isSuperAdmin()) {
            $query->forAdministrationScopedViewer($auth);
        }

        // Aplicar filtros
        if ($request->filled('entity')) {
            // Filtrar por entidad a través de vendedores vinculados
            $query->whereHas('sellers', function($q) use ($request) {
                $q->where('entity_id', $request->entity);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return datatables($query)
            ->addColumn('pending_amount', function($user) {
                // Calcular importe pendiente (implementar lógica según necesidades)
                return 0.00;
            })
            ->addColumn('actions', function($user) {
                return view('users.actions', compact('user'))->render();
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * API: Verificar si existe un usuario por email (para venta digital).
     * Solo vendedores. Devuelve exists y user_id si existe.
     */
    public function apiCheckUserExists(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        return response()->json([
            'exists' => $user !== null,
            'user_id' => $user?->id,
            'can_offer_registration' => $user === null,
        ]);
    }

    /**
     * Verificar si el email ya está en uso en usuarios (para validación AJAX)
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'exclude_id' => 'nullable|integer'
        ]);

        $query = User::where('email', $request->email);
        
        // Excluir el ID actual si se está editando
        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Este email ya está en uso por otro usuario' : null
        ]);
    }

    /**
     * Actualizar vendedores vinculados cuando cambia el email del usuario
     */
    private function updateLinkedSellers(User $user)
    {
        $linkedSellers = Seller::where('user_id', $user->id)->get();
        
        foreach ($linkedSellers as $seller) {
            $seller->update([
                'email' => $user->email,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'last_name2' => $user->last_name2,
                'nif_cif' => $user->nif_cif,
                'birthday' => $user->birthday,
                'phone' => $user->phone,
                'image' => $user->image,
                'status' => $user->status,
            ]);
        }
        
        Log::info("Vendedores vinculados actualizados para usuario: {$user->id}");
    }
}
