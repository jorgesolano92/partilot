<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administration;
use App\Models\Manager;
use App\Models\User;
use App\Services\AdministrationContractService;
use App\Services\ManagerAccountService;
use App\Support\ContactEmailRegistry;

class ManagerController extends Controller
{
    /**
     * Show the form for editing the specified manager.
     */
    public function edit($id)
    {
        $manager = Manager::with('user')->findOrFail($id);
        if ($manager->user && $manager->user->isPanelAccount()) {
            return redirect()->back()
                ->with('error', 'La cuenta de acceso al panel no se edita como gestor; use la ficha de administración o entidad.');
        }

        return view('managers.edit', compact('manager'));
    }

    /**
     * Update the specified manager in storage.
     */
    public function update(Request $request, $id)
    {
        $manager = Manager::with('user')->findOrFail($id);

        if ($manager->user && $manager->user->isPanelAccount()) {
            return redirect()->back()
                ->with('error', 'La cuenta de acceso al panel no se edita como gestor; use la ficha de administración o entidad.');
        }

        $user = $manager->user;
        $userId = $user?->id;

        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['nullable', 'string', 'max:20', 'unique:users,nif_cif'.($userId ? ','.$userId : '')],
            'birthday' => ['nullable', 'date', new \App\Rules\MinimumAge(18)],
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
            'resend_saas_contract' => 'nullable|boolean',
        ]);

        $managerEmail = trim((string) $request->input('email'));
        $resolvedUser = $user;

        if ($resolvedUser && strcasecmp((string) $resolvedUser->email, $managerEmail) !== 0) {
            if (ContactEmailRegistry::isTaken($managerEmail, $resolvedUser->id)) {
                return back()->withErrors([
                    'email' => 'Este correo ya está en uso en otra cuenta.',
                ])->withInput();
            }
        } elseif (! $resolvedUser) {
            $resolvedUser = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [ContactEmailRegistry::normalize($managerEmail)])->first();
            if ($resolvedUser && $resolvedUser->isPanelAccount()) {
                return back()->withErrors([
                    'email' => 'Ese email corresponde a una cuenta de acceso al panel.',
                ])->withInput();
            }
        }

        $role = $manager->administration_id
            ? User::ROLE_ADMINISTRATION
            : User::ROLE_ENTITY;

        if (! $resolvedUser) {
            $resolvedUser = app(ManagerAccountService::class)->createUser([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'email' => $managerEmail,
                'role' => $role,
                'status' => true,
                'phone' => $request->phone ?: null,
                'nif_cif' => $request->nif_cif ?: null,
                'birthday' => $request->birthday ?: null,
                'comment' => $request->comment ?: null,
            ], $manager->administration_id ? 'administración de lotería' : 'gestor de entidad');
        } else {
            $resolvedUser->update([
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'email' => $managerEmail,
                'nif_cif' => $request->nif_cif,
                'birthday' => $request->birthday,
                'phone' => $request->phone,
                'comment' => $request->comment,
                'role' => $role,
            ]);
        }

        if ($request->hasFile('image')) {
            if ($resolvedUser->image && file_exists(public_path('manager/'.$resolvedUser->image))) {
                unlink(public_path('manager/'.$resolvedUser->image));
            }

            $file = $request->file('image');
            $filename = $file->hashName();
            $file->move(public_path('manager'), $filename);
            $resolvedUser->update(['image' => $filename]);
        }

        if ((int) $manager->user_id !== (int) $resolvedUser->id) {
            $manager->update(['user_id' => $resolvedUser->id]);
        }

        $successMessage = 'Gestor actualizado correctamente.';

        if ($request->boolean('resend_saas_contract')
            && $manager->administration_id
            && $request->user()?->isSuperAdmin()) {
            $administration = Administration::query()->find($manager->administration_id);
            if ($administration && ! $administration->hasSignedSaasContract()) {
                try {
                    app(AdministrationContractService::class)->sendContractInvitation(
                        $administration->fresh(['manager.user']),
                        (int) $request->user()->id
                    );
                    $successMessage .= ' Se ha reenviado el correo del contrato SaaS.';
                } catch (\Throwable $e) {
                    return redirect()->back()
                        ->with('success', $successMessage)
                        ->with('error', 'No se pudo reenviar el contrato: '.$e->getMessage());
                }
            }
        }

        if ($manager->administration_id) {
            return redirect()->route('administrations.edit-manager', $manager->administration_id)
                ->with('success', $successMessage);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * Remove the specified manager from storage.
     */
    public function destroy($id)
    {
        $manager = Manager::with('user')->findOrFail($id);
        if ($manager->user && $manager->user->isPanelAccount()) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la relación de la cuenta de acceso al panel.');
        }

        // Eliminar imagen del usuario si existe
        $user = $manager->user;
        if ($user && $user->image && file_exists(public_path('manager/' . $user->image))) {
            unlink(public_path('manager/' . $user->image));
        }

        // Eliminar la relación manager-entity
        $manager->delete();

        return redirect()->back()
            ->with('success', 'Gestor eliminado exitosamente.');
    }
} 