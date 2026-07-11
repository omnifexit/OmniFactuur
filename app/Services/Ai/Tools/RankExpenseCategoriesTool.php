<?php

namespace App\Services\Ai\Tools;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Ai\Tools\Concerns\ResolvesPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Rank expense categories by total spend over a named time period.
 *
 * Answers "what are we spending the most on" / "top expense categories"
 * questions. There's only one sensible aggregation (sum of amount per
 * category), so no `metric` parameter — just period + limit.
 */
class RankExpenseCategoriesTool extends AiTool
{
    use ResolvesPeriod;

    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 20;

    public function name(): string
    {
        return 'rank_expense_categories';
    }

    public function description(): string
    {
        return "Rank expense categories by total spend over a named time period. Use this when the user asks 'what are we spending the most on', 'top expense categories', or 'where is the money going'.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => self::ALL_PERIODS,
                    'description' => 'Named time window. Use all_time for lifetime totals.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max number of categories to return. Default 10.',
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-expense', Expense::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $period = (string) ($arguments['period'] ?? 'all_time');
        if (! in_array($period, self::ALL_PERIODS, true)) {
            return ['error' => 'invalid_period', 'valid' => self::ALL_PERIODS];
        }

        $limit = min(max((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $range = $this->rangeFor($period);

        $query = Expense::query()
            ->where('company_id', $companyId)
            ->whereNotNull('expense_category_id')
            ->select([
                'expense_category_id',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as expense_count'),
            ])
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->limit($limit);

        if ($range !== null) {
            $query->whereBetween('expense_date', [$range[0], $range[1]]);
        }

        $rows = $query->get()->all();

        // Batch-load category names in one query.
        $categoryIds = array_map(static fn ($row) => (int) $row->expense_category_id, $rows);
        $categories = ExpenseCategory::query()
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        $ranked = array_map(function ($row) use ($categories): array {
            $category = $categories->get((int) $row->expense_category_id);

            return [
                'expense_category_id' => (int) $row->expense_category_id,
                'name' => $category?->name,
                'total_amount' => (float) $row->total_amount,
                'expense_count' => (int) $row->expense_count,
            ];
        }, $rows);

        return [
            'period' => $period,
            'categories' => $ranked,
        ];
    }
}
