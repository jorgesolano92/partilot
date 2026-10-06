<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use App\Support\ContactEmailRegistry;

/**
 * Gestor principal de administración: solo datos de contacto en managers (sin fila users).
 */
class AdministrationManagerContactService
{
    public function contactEmailValidationError(string $contactEmail, Administration $administration): ?string
    {
        $norm = ContactEmailRegistry::normalize($contactEmail);
        if ($norm === '') {
            return null;
        }

        $panelUser = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$norm])
            ->whereNotNull('panel_account_type')
            ->where('panel_account_type', '!=', '')
            ->whereNotNull('panel_account_id')
            ->first();

        if (! $panelUser) {
            return null;
        }

        if ($administration->id
            && $panelUser->panel_account_type === 'administration'
            && (int) $panelUser->panel_account_id === (int) $administration->id) {
            return null;
        }

        return 'Ese email corresponde a una cuenta de acceso al panel de otra administración o entidad.';
    }

    /**
     * @param  array<string, mixed>  $profile  name, last_name, last_name2, email, nif_cif, birthday, phone, comment
     */
    public function persistPrimaryContact(Administration $administration, array $profile, ?Manager $manager = null): Manager
    {
        $manager ??= Manager::query()
            ->where('administration_id', $administration->id)
            ->where('is_primary', true)
            ->first();

        $oldUserId = $manager?->user_id;

        if (! $manager) {
            $manager = new Manager([
                'administration_id' => $administration->id,
                'entity_id' => null,
                'is_primary' => true,
                'permission_sellers' => true,
                'permission_design' => true,
                'permission_statistics' => true,
                'permission_payments' => true,
                'status' => 1,
            ]);
        }

        Manager::query()
            ->where('administration_id', $administration->id)
            ->where('id', '!=', $manager->id ?? 0)
            ->update(['is_primary' => false]);

        $manager->fill([
            'user_id' => null,
            'is_primary' => true,
            'contact_email' => ContactEmailRegistry::normalize((string) ($profile['email'] ?? '')),
            'contact_name' => $profile['name'] ?? null,
            'contact_last_name' => $profile['last_name'] ?? null,
            'contact_last_name2' => $profile['last_name2'] ?? null,
            'contact_nif_cif' => $profile['nif_cif'] ?? null,
            'contact_birthday' => $profile['birthday'] ?? null,
            'contact_phone' => $profile['phone'] ?? null,
            'contact_comment' => $profile['comment'] ?? null,
        ]);
        $manager->save();

        if ($oldUserId) {
            $this->cleanupOrphanContactUser((int) $oldUserId, (int) $manager->id);
        }

        return $manager->fresh();
    }

    public function updateContactImage(Manager $manager, string $filename): void
    {
        if ($manager->contact_image && is_file(public_path('manager/'.$manager->contact_image))) {
            unlink(public_path('manager/'.$manager->contact_image));
        }

        $manager->update(['contact_image' => $filename]);
    }

    private function cleanupOrphanContactUser(int $userId, int $exceptManagerId): void
    {
        $user = User::query()->find($userId);
        if (! $user || $user->isPanelAccount()) {
            return;
        }

        if (Manager::query()->where('user_id', $userId)->where('id', '!=', $exceptManagerId)->exists()) {
            return;
        }

        if (Seller::query()->where('user_id', $userId)->exists()) {
            return;
        }

        if ($user->image && is_file(public_path('manager/'.$user->image))) {
            unlink(public_path('manager/'.$user->image));
        }

        $user->delete();
    }
}
