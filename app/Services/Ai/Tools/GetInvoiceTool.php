<?php

namespace App\Services\Ai\Tools;

use App\Models\Invoice;

/**
 * Fetch one invoice's full details by invoice_number, including items and taxes.
 */
class GetInvoiceTool extends AiTool
{
    public function name(): string
    {
        return 'get_invoice';
    }

    public function description(): string
    {
        return 'Fetch full details for a single invoice by its invoice_number, including line items, taxes, totals, customer info, and dates. Use this after search_invoices when the user wants more detail on a specific invoice.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'invoice_number' => [
                    'type' => 'string',
                    'description' => 'The invoice_number to look up (e.g. "INV-000001").',
                ],
            ],
            'required' => ['invoice_number'],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-invoice', Invoice::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->where('invoice_number', (string) ($arguments['invoice_number'] ?? ''))
            ->with(['customer:id,name,email,phone', 'items', 'taxes'])
            ->first();

        if (! $invoice) {
            return ['error' => 'invoice_not_found'];
        }

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'reference_number' => $invoice->reference_number,
                'status' => $invoice->status,
                'paid_status' => $invoice->paid_status,
                'invoice_date' => $this->asDate($invoice->invoice_date),
                'due_date' => $this->asDate($invoice->due_date),
                'sub_total' => $invoice->sub_total,
                'tax' => $invoice->tax,
                'discount' => $invoice->discount,
                'total' => $invoice->total,
                'due_amount' => $invoice->due_amount,
                'overdue' => (bool) $invoice->overdue,
                'notes' => $invoice->notes,
                'customer' => $invoice->customer ? [
                    'id' => $invoice->customer->id,
                    'name' => $invoice->customer->name,
                    'email' => $invoice->customer->email,
                    'phone' => $invoice->customer->phone,
                ] : null,
                'items' => $invoice->items->map(fn ($item): array => [
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ])->all(),
                'taxes' => $invoice->taxes->map(fn ($tax): array => [
                    'name' => $tax->name,
                    'percent' => $tax->percent,
                    'amount' => $tax->amount,
                ])->all(),
            ],
        ];
    }
}
