<?php

namespace App\Services\Ai\Tools;

use App\Models\Invoice;

class ListOverdueInvoicesTool extends AiTool
{
    public function name(): string
    {
        return 'list_overdue_invoices';
    }

    public function description(): string
    {
        return 'List all invoices for the current company that are currently overdue (past their due date and unpaid or partially paid). Sorted by oldest-due-first.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-invoice', Invoice::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->where('overdue', true)
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->limit(100)
            ->get();

        $totalOutstanding = (float) $invoices->sum('due_amount');

        return [
            'count' => $invoices->count(),
            'total_outstanding' => $totalOutstanding,
            'invoices' => $invoices->map(fn (Invoice $inv): array => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_id' => $inv->customer_id,
                'customer_name' => $inv->customer?->name,
                'due_date' => $this->asDate($inv->due_date),
                'due_amount' => $inv->due_amount,
                'total' => $inv->total,
            ])->all(),
        ];
    }
}
