<?php

namespace App\Services\Ai\Tools;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Ai\Tools\Concerns\ResolvesPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rank customers by a business metric (invoiced total, paid total,
 * invoice count, or outstanding balance) over a named time period.
 *
 * Answers questions the rest of the tool set can't touch: "who did the
 * most business with us", "top 5 customers by revenue this year", "who
 * owes us the most right now", "which customers sent us the most
 * invoices last quarter". All four metrics are groupBy aggregates on
 * either the invoices or payments table, scoped to the session's
 * company.
 */
class RankTopCustomersTool extends AiTool
{
    use ResolvesPeriod;

    private const METRICS = [
        'invoiced_total',
        'paid_total',
        'invoice_count',
        'outstanding_balance',
    ];

    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

    public function name(): string
    {
        return 'rank_top_customers';
    }

    public function description(): string
    {
        return "Rank customers by a business metric (invoiced_total, paid_total, invoice_count, or outstanding_balance). Use this when the user asks 'who are our top customers', 'who did the most business with us', 'who owes us the most', or similar ranking questions. outstanding_balance ignores the period — it's always the current snapshot.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'metric' => [
                    'type' => 'string',
                    'enum' => self::METRICS,
                    'description' => 'Which metric to rank by.',
                ],
                'period' => [
                    'type' => 'string',
                    'enum' => self::ALL_PERIODS,
                    'description' => 'Named time window. Use all_time for lifetime rankings. Ignored for outstanding_balance (always current).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max number of customers to return. Default 5.',
                ],
            ],
            'required' => ['metric'],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-customer', Customer::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $metric = (string) ($arguments['metric'] ?? 'invoiced_total');
        if (! in_array($metric, self::METRICS, true)) {
            return ['error' => 'invalid_metric', 'valid' => self::METRICS];
        }

        $period = (string) ($arguments['period'] ?? 'all_time');
        if (! in_array($period, self::ALL_PERIODS, true)) {
            return ['error' => 'invalid_period', 'valid' => self::ALL_PERIODS];
        }

        $limit = min(max((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);

        // outstanding_balance is a current-state snapshot; period is meaningless.
        $range = $metric === 'outstanding_balance' ? null : $this->rangeFor($period);

        $rows = match ($metric) {
            'invoiced_total' => $this->rankByInvoiceSum($companyId, $range, $limit, 'total'),
            'paid_total' => $this->rankByPaymentSum($companyId, $range, $limit),
            'invoice_count' => $this->rankByInvoiceCount($companyId, $range, $limit),
            'outstanding_balance' => $this->rankByOutstandingBalance($companyId, $limit),
        };

        // Batch-load the customers we're about to return so we can decorate
        // each ranking row with name fields. One query regardless of $limit.
        $customerIds = array_map(static fn ($row) => (int) $row->customer_id, $rows);
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->get()
            ->keyBy('id');

        $ranked = array_map(function ($row) use ($customers, $metric): array {
            $customer = $customers->get((int) $row->customer_id);

            return [
                'customer_id' => (int) $row->customer_id,
                'name' => $customer?->name,
                'display_name' => $customer?->display_name,
                'company_name' => $customer?->company_name,
                'metric_value' => $metric === 'invoice_count'
                    ? (int) $row->metric_value
                    : (float) $row->metric_value,
                'invoice_count' => isset($row->invoice_count) ? (int) $row->invoice_count : null,
            ];
        }, $rows);

        return [
            'metric' => $metric,
            'period' => $metric === 'outstanding_balance' ? 'current' : $period,
            'customers' => $ranked,
        ];
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $range
     * @return array<int, object>
     */
    private function rankByInvoiceSum(int $companyId, ?array $range, int $limit, string $sumColumn): array
    {
        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->whereNotNull('customer_id')
            ->select([
                'customer_id',
                DB::raw("SUM({$sumColumn}) as metric_value"),
                DB::raw('COUNT(*) as invoice_count'),
            ])
            ->groupBy('customer_id')
            ->orderByDesc('metric_value')
            ->limit($limit);

        if ($range !== null) {
            $query->whereBetween('invoice_date', [$range[0], $range[1]]);
        }

        return $query->get()->all();
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $range
     * @return array<int, object>
     */
    private function rankByPaymentSum(int $companyId, ?array $range, int $limit): array
    {
        $query = Payment::query()
            ->where('company_id', $companyId)
            ->whereNotNull('customer_id')
            ->select([
                'customer_id',
                DB::raw('SUM(amount) as metric_value'),
                DB::raw('COUNT(*) as invoice_count'),
            ])
            ->groupBy('customer_id')
            ->orderByDesc('metric_value')
            ->limit($limit);

        if ($range !== null) {
            $query->whereBetween('payment_date', [$range[0], $range[1]]);
        }

        // Note: `invoice_count` here is actually the payment count for this
        // customer within the window — semantically confusing, so drop it.
        return array_map(function ($row) {
            unset($row->invoice_count);

            return $row;
        }, $query->get()->all());
    }

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $range
     * @return array<int, object>
     */
    private function rankByInvoiceCount(int $companyId, ?array $range, int $limit): array
    {
        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->whereNotNull('customer_id')
            ->select([
                'customer_id',
                DB::raw('COUNT(*) as metric_value'),
                DB::raw('COUNT(*) as invoice_count'),
            ])
            ->groupBy('customer_id')
            ->orderByDesc('metric_value')
            ->limit($limit);

        if ($range !== null) {
            $query->whereBetween('invoice_date', [$range[0], $range[1]]);
        }

        return $query->get()->all();
    }

    /**
     * @return array<int, object>
     */
    private function rankByOutstandingBalance(int $companyId, int $limit): array
    {
        return Invoice::query()
            ->where('company_id', $companyId)
            ->whereNotNull('customer_id')
            ->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])
            ->select([
                'customer_id',
                DB::raw('SUM(due_amount) as metric_value'),
                DB::raw('COUNT(*) as invoice_count'),
            ])
            ->groupBy('customer_id')
            ->orderByDesc('metric_value')
            ->limit($limit)
            ->get()
            ->all();
    }
}
