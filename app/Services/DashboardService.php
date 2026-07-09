<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\Participation;
use App\Models\Seller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    public function build(User $user): array
    {
        $showUsers = $user->isSuperAdmin() || $user->isAdministration();
        $showAdministrations = $user->isSuperAdmin();
        $showSellersPanel = ! $showUsers && $user->isEntity();

        return [
            'metrics' => [
                'users' => $showUsers ? $this->metricFor($this->usersBaseQuery($user)) : null,
                'entities' => $this->metricFor(Entity::query()->forUser($user)),
                'sellers' => $this->metricFor(Seller::query()->forUser($user)),
                'participations' => $this->metricFor(Participation::query()->forUser($user)),
            ],
            'recent_entities' => $this->recentEntities($user),
            'recent_users' => $showUsers ? $this->recentUsers($user) : collect(),
            'recent_sellers' => $showSellersPanel ? $this->recentSellers($user) : collect(),
            'recent_administrations' => $showAdministrations ? $this->recentAdministrations() : collect(),
            'show_users_metric' => $showUsers,
            'show_users_panel' => $showUsers,
            'show_sellers_panel' => $showSellersPanel,
            'show_administrations_panel' => $showAdministrations,
        ];
    }

    private function usersBaseQuery(User $user): Builder
    {
        $query = User::query()
            ->whereNull('panel_account_type')
            ->excludingAdministrationContactRecords();

        if ($user->isAdministration() && ! $user->isSuperAdmin()) {
            return $query->forAdministrationScopedViewer($user);
        }

        if ($user->isEntity() && ! $user->isSuperAdmin() && ! $user->isAdministration()) {
            $entityIds = $user->accessibleEntityIds();
            if ($entityIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $q) use ($entityIds) {
                $q->whereHas('sellers', function (Builder $s) use ($entityIds) {
                    $s->whereHas('entities', function (Builder $e) use ($entityIds) {
                        $e->whereIn('entities.id', $entityIds);
                    });
                })->orWhereHas('managers', function (Builder $m) use ($entityIds) {
                    $m->whereIn('entity_id', $entityIds);
                });
            });
        }

        return $query;
    }

    /**
     * @return array{total: int, formatted: string, change_percent: ?float, change_label: string, change_positive: ?bool}
     */
    private function metricFor(Builder $query): array
    {
        $total = (clone $query)->count();
        $change = $this->monthOverMonthChange($query);

        return [
            'total' => $total,
            'formatted' => $this->formatCount($total),
            'change_percent' => $change,
            'change_label' => $this->formatChangeLabel($change),
            'change_positive' => $change === null ? null : $change >= 0,
        ];
    }

    private function monthOverMonthChange(Builder $baseQuery): ?float
    {
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $thisMonth = (clone $baseQuery)->where('created_at', '>=', $thisMonthStart)->count();
        $lastMonth = (clone $baseQuery)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        if ($lastMonth === 0) {
            return $thisMonth > 0 ? 100.0 : null;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    private function formatCount(int $count): string
    {
        if ($count >= 1_000_000) {
            return number_format($count / 1_000_000, 2, '.', '') . 'M';
        }

        if ($count >= 10_000) {
            return number_format($count / 1_000, 1, '.', '') . 'K';
        }

        if ($count >= 1_000) {
            return number_format($count, 0, '', '.');
        }

        return (string) $count;
    }

    private function formatChangeLabel(?float $change): string
    {
        if ($change === null) {
            return 'Sin datos del mes anterior';
        }

        $sign = $change > 0 ? '+' : '';

        return $sign . number_format($change, 2, ',', '.') . '% desde el mes pasado';
    }

    private function recentEntities(User $user): Collection
    {
        return Entity::query()
            ->forUser($user)
            ->with(['manager.user', 'administration'])
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();
    }

    private function recentUsers(User $user): Collection
    {
        return $this->usersBaseQuery($user)
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();
    }

    private function recentSellers(User $user): Collection
    {
        return Seller::query()
            ->forUser($user)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();
    }

    private function recentAdministrations(): Collection
    {
        return Administration::query()
            ->with('manager')
            ->orderByDesc('created_at')
            ->limit(7)
            ->get();
    }
}
