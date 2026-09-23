<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Invalida sesiones de panel asociadas a una administración (INC-006).
 */
class AdministrationSessionInvalidationService
{
    public function invalidateForAdministration(Administration $administration): void
    {
        $userIds = User::query()
            ->where(function ($q) use ($administration) {
                $q->where(function ($q2) use ($administration) {
                    $q2->where('panel_account_type', 'administration')
                        ->where('panel_account_id', $administration->id);
                })->orWhereIn('id', Manager::query()
                    ->where('administration_id', $administration->id)
                    ->whereNotNull('user_id')
                    ->pluck('user_id'));
            })
            ->pluck('id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($userIds === []) {
            return;
        }

        $now = now()->timestamp;

        foreach ($userIds as $userId) {
            Cache::forever($this->cacheKey((int) $userId), $now);
            User::query()->whereKey($userId)->update([
                'remember_token' => Str::random(60),
            ]);
        }

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }
    }

    public function markSessionValidated(int $userId): void
    {
        session(['partilot_auth_validated_at' => now()->timestamp]);
        // Si no había revocación previa, no bloquear.
        if (! Cache::has($this->cacheKey($userId))) {
            Cache::forever($this->cacheKey($userId), 0);
        }
    }

    public function shouldForceLogout(int $userId): bool
    {
        $revokedAt = (int) Cache::get($this->cacheKey($userId), 0);
        if ($revokedAt <= 0) {
            return false;
        }

        $validatedAt = (int) session('partilot_auth_validated_at', 0);

        return $validatedAt < $revokedAt;
    }

    private function cacheKey(int $userId): string
    {
        return 'partilot_panel_access_revoked_at:'.$userId;
    }
}
