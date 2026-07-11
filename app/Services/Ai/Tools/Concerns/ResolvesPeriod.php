<?php

namespace App\Services\Ai\Tools\Concerns;

use Carbon\Carbon;

/**
 * Shared period-name → [start, end] resolution for AI tools.
 *
 * Several tools (stats, rankings) accept a named time window and need the
 * same match logic. Rather than duplicate the Carbon juggling in every
 * tool, they `use ResolvesPeriod` and call `rangeFor()` consistently.
 *
 * `all_time` is included in the superset of period names so ranking tools
 * can offer an unbounded window — in that case `rangeFor()` returns null
 * and the caller skips any `whereBetween` filter. `GetCompanyStatsTool`
 * deliberately does NOT expose `all_time` in its own enum (stats over all
 * time drops every record into one giant bucket and is rarely useful).
 */
trait ResolvesPeriod
{
    /** Superset of period names supported by the trait. */
    protected const ALL_PERIODS = [
        'all_time',
        'today',
        'this_week',
        'this_month',
        'last_month',
        'this_quarter',
        'this_year',
        'last_year',
    ];

    /**
     * Resolve a named period to a start/end Carbon pair.
     *
     * Returns null for `all_time` (meaning: "no date filter — use every
     * record regardless of date"). Callers are expected to branch on the
     * null and skip any `whereBetween` clause.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function rangeFor(string $period): ?array
    {
        $now = Carbon::now();

        return match ($period) {
            'all_time' => null,
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [
                $now->copy()->subYearNoOverflow()->startOfYear(),
                $now->copy()->subYearNoOverflow()->endOfYear(),
            ],
            default => null,
        };
    }
}
