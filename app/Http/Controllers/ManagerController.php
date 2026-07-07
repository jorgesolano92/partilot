<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Manager;
use App\Models\User;
use App\Models\Administration;
use App\Services\ManagerAccountService;
use App\Services\AdministrationManagerContactService;
use App\Support\ContactEmailRegistry;
use App\Support\FormRedirectNotify;
use App\Rules\ValidCalendarDate;
use Illuminate\Support\Facades\Validator;

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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => [
                'nullable',
                'string',
                'max:20',
                new \App\Rules\SpanishDocument,
                new \App\Rules\ManagerContactNif(
                    $manager->administration_id ? (int) $manager->administration_id : null,
                    $manager->entity_id ? (int) $manager->entity_id : null,
                    $userId,
                    $manager->administration_id ? (int) $manager->id : null,
                ),
            ],
            'birthday' => ValidCalendarDate::birthday(false),
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
        ]);

        $validator->after(function ($v) use ($manager, $request, $userId) {
            $managerEmail = ContactEmailRegistry::normalize((string) $request->input('email'));
            if ($managerEmail === '') {
                return;
            }

            if ($manager->administration_id) {
                $administration = Administration::query()->find($manager->administration_id);
                if ($administration) {
                    $error = app(AdministrationManagerContactService::class)
                        ->contactEmailValidationError($managerEmail, $administration);
                    if ($error) {
                        $v->errors()->add('email', $error);
                    }
                }

                return;
            }

            if (ContactEmailRegistry::isTaken(
                $managerEmail,
                $userId,
                null,
                $manager->entity_id ? (int) $manager->entity_id : null,
            )) {
                $v->errors()->add('email', 'Este correo ya está en uso en otra cuenta.');
            }
        });

        if ($validator->fails()) {
            return FormRedirectNotify::withErrors(
                $this->managerFormRedirect($manager),
                $validator
            );
        }

        $managerEmail = trim((string) $request->input('email'));

        if ($manager->administration_id) {
            $administration = Administration::query()->findOrFail($manager->administration_id);
            $contactService = app(AdministrationManagerContactService::class);

            $contactService->persistPrimaryContact($administration, [
                'name' => $request->name,
                'last_name' => $request->last_name,
                'last_name2' => $request->last_name2,
                'email' => $managerEmail,
                'nif_cif' => $request->nif_cif ?: null,
                'birthday' => $request->birthday ?: null,
                'phone' => $request->phone ?: null,
                'comment' => $request->comment ?: null,
            ], $manager);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = $file->hashName();
                $file->move(public_path('manager'), $filename);
                $contactService->updateContactImage($manager->fresh(), $filename);
            }

            return redirect()
                ->route('administrations.show', $manager->administration_id)
                ->withFragment('datos_contacto')
                ->with('success', 'Gestor actualizado correctamente.');
        }

        $resolvedUser = $user;

        if (! $resolvedUser) {
            $resolvedUser = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [ContactEmailRegistry::normalize($managerEmail)])->first();
            if ($resolvedUser && $resolvedUser->isPanelAccount()) {
                return FormRedirectNotify::withErrors($this->managerFormRedirect($manager), [
                    'email' => 'Ese email corresponde a una cuenta de acceso al panel.',
                ]);
            }
        }

        $role = User::ROLE_ENTITY;

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
            ], 'gestor de entidad');
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

        return redirect()->back()->with('success', 'Gestor actualizado correctamente.');
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

    private function managerFormRedirect(Manager $manager)
    {
        if ($manager->administration_id) {
            return redirect()->route('administrations.edit-manager', $manager->administration_id);
        }

        if ($manager->entity_id) {
            return redirect()->route('entities.edit-manager', $manager->entity_id);
        }

        return redirect()->back();
    }
} 