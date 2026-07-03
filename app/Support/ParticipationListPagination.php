<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Paginación y filtro por fechas para listados de participaciones (cartera, historial, ventas).
 */
class ParticipationListPagination
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * @return array{
     *     enabled: bool,
     *     dateFrom: Carbon,
     *     dateTo: Carbon,
     *     perPage: int,
     *     page: int,
     *     includeExpired: bool,
     *     defaultMonths: int
     * }
     */
    public static function parseFromRequest(Request $request): array
    {
        $defaultMonths = max(1, (int) config('digital_sale.wallet_validity_months_after_draw', 3));
        $enabled = $request->has('page')
            || $request->has('per_page')
            || $request->has('date_from')
            || $request->has('date_to')
            || $request->boolean('paginate');

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subMonths($defaultMonths)->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->input('per_page', self::DEFAULT_PER_PAGE))
        );
        $page = max(1, (int) $request->input('page', 1));
        $includeExpired = filter_var($request->input('include_expired', false), FILTER_VALIDATE_BOOLEAN);

        return [
            'enabled' => $enabled,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'perPage' => $perPage,
            'page' => $page,
            'includeExpired' => $includeExpired,
            'defaultMonths' => $defaultMonths,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function filterByDate(array $items, Carbon $from, Carbon $to, string $dateKey = 'fecha'): array
    {
        return array_values(array_filter($items, function (array $item) use ($from, $to, $dateKey) {
            $raw = $item[$dateKey] ?? null;
            if ($raw === null || $raw === '') {
                return false;
            }

            try {
                return Carbon::parse($raw)->betweenIncluded($from, $to);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function paginateArray(array $items, int $page, int $perPage, Carbon $dateFrom, Carbon $dateTo): array
    {
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($items, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>|null}
     */
    public static function apply(array $items, array $options, string $dateKey = 'fecha'): array
    {
        if (! ($options['enabled'] ?? false)) {
            return ['data' => $items, 'meta' => null];
        }

        $filtered = self::filterByDate($items, $options['dateFrom'], $options['dateTo'], $dateKey);

        return self::paginateArray(
            $filtered,
            $options['page'],
            $options['perPage'],
            $options['dateFrom'],
            $options['dateTo']
        );
    }
}
