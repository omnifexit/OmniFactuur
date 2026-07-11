<?php

namespace App\Services\Ai\Tools;

use App\Models\InvoiceItem;
use App\Models\Item;
use App\Services\Ai\Tools\Concerns\ResolvesPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Rank catalog items by quantity sold or revenue over a named time period.
 *
 * Joins invoice_items → invoices for company scoping + date filtering.
 * Ad-hoc line items (where `item_id` is null — the user typed a name
 * directly without picking from the catalog) are excluded, because
 * ranking them by id isn't meaningful.
 */
class RankTopItemsTool extends AiTool
{
    use ResolvesPeriod;

    private const METRICS = ['quantity_sold', 'revenue'];

    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

    public function name(): string
    {
        return 'rank_top_items';
    }

    public function description(): string
    {
        return "Rank catalog items by quantity_sold or revenue over a named time period. Use this when the user asks 'what's our best-selling item', 'most popular products', 'which items brought in the most money', or similar.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'metric' => [
                    'type' => 'string',
                    'enum' => self::METRICS,
                    'description' => 'Which dimension to rank by.',
                ],
                'period' => [
                    'type' => 'string',
                    'enum' => self::ALL_PERIODS,
                    'description' => 'Named time window. Use all_time for lifetime rankings.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max number of items to return. Default 5.',
                ],
            ],
            'required' => ['metric'],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-item', Item::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $metric = (string) ($arguments['metric'] ?? 'revenue');
        if (! in_array($metric, self::METRICS, true)) {
            return ['error' => 'invalid_metric', 'valid' => self::METRICS];
        }

        $period = (string) ($arguments['period'] ?? 'all_time');
        if (! in_array($period, self::ALL_PERIODS, true)) {
            return ['error' => 'invalid_period', 'valid' => self::ALL_PERIODS];
        }

        $limit = min(max((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
        $range = $this->rangeFor($period);
        $orderColumn = $metric === 'revenue' ? 'total_revenue' : 'total_quantity';

        $query = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->whereNotNull('invoice_items.item_id')
            ->select([
                'invoice_items.item_id',
                DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.total) as total_revenue'),
            ])
            ->groupBy('invoice_items.item_id')
            ->orderByDesc($orderColumn)
            ->limit($limit);

        if ($range !== null) {
            $query->whereBetween('invoices.invoice_date', [$range[0], $range[1]]);
        }

        $rows = $query->get()->all();

        // Batch-load item names in one query.
        $itemIds = array_map(static fn ($row) => (int) $row->item_id, $rows);
        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $ranked = array_map(function ($row) use ($items): array {
            $item = $items->get((int) $row->item_id);

            return [
                'item_id' => (int) $row->item_id,
                'name' => $item?->name,
                'quantity_sold' => (float) $row->total_quantity,
                'revenue' => (float) $row->total_revenue,
            ];
        }, $rows);

        return [
            'metric' => $metric,
            'period' => $period,
            'items' => $ranked,
        ];
    }
}
