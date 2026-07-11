<?php

namespace App\Services\Ai\Tools;

use App\Models\Invoice;

/**
 * Search invoices by free text, status, and/or customer, scoped to the current company.
 *
 * Returns a compact list — no line items, no taxes. For full details the LLM
 * should follow up with GetInvoiceTool using the returned invoice_number.
 */
class SearchInvoicesTool extends AiTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function name(): string
    {
        return 'search_invoices';
    }

    public function description(): string
    {
        return 'Search invoices for the current company. Filter by free-text query (matches invoice number and reference), status, or customer_id. Returns a compact list with invoice numbers, customers, dates, totals, and statuses.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional free-text search against invoice_number and reference_number.',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['DRAFT', 'SENT', 'VIEWED', 'COMPLETED', 'UNPAID', 'PARTIALLY_PAID', 'PAID', 'OVERDUE'],
                    'description' => 'Optional status filter.',
                ],
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'Optional customer ID to restrict to a specific customer.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max rows to return (default 10, max 50).',
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-invoice', Invoice::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $limit = min((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->with('customer:id,name')
            ->latest('invoice_date')
            ->limit($limit);

        if (! empty($arguments['query'])) {
            $q = $arguments['query'];
            $query->where(function ($qb) use ($q) {
                $qb->where('invoice_number', 'like', "%{$q}%")
                    ->orWhere('reference_number', 'like', "%{$q}%");
            });
        }

        if (! empty($arguments['status'])) {
            $status = strtoupper((string) $arguments['status']);
            // 'PAID' / 'UNPAID' / 'PARTIALLY_PAID' live on paid_status; the rest on status.
            if (in_array($status, ['PAID', 'UNPAID', 'PARTIALLY_PAID'], true)) {
                $query->where('paid_status', $status);
            } elseif ($status === 'OVERDUE') {
                $query->where('overdue', true);
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($arguments['customer_id'])) {
            $query->where('customer_id', (int) $arguments['customer_id']);
        }

        return [
            'invoices' => $query->get()->map(fn (Invoice $inv): array => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer?->name,
                'invoice_date' => $this->asDate($inv->invoice_date),
                'due_date' => $this->asDate($inv->due_date),
                'status' => $inv->status,
                'paid_status' => $inv->paid_status,
                'total' => $inv->total,
                'due_amount' => $inv->due_amount,
                'overdue' => (bool) $inv->overdue,
            ])->all(),
        ];
    }
}
