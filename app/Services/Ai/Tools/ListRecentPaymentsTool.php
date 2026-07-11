<?php

namespace App\Services\Ai\Tools;

use App\Models\Payment;
use Carbon\Carbon;

class ListRecentPaymentsTool extends AiTool
{
    private const DEFAULT_DAYS = 30;

    private const MAX_DAYS = 365;

    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function name(): string
    {
        return 'list_recent_payments';
    }

    public function description(): string
    {
        return 'List payments received in the last N days for the current company, sorted most recent first. Returns payment number, customer, amount, payment date, and payment method.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_DAYS,
                    'description' => 'How many days back to look (default 30, max 365).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max rows to return (default 20, max 100).',
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-payment', Payment::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $days = min((int) ($arguments['days'] ?? self::DEFAULT_DAYS), self::MAX_DAYS);
        $limit = min((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $since = Carbon::now()->subDays($days)->startOfDay();

        $payments = Payment::query()
            ->where('company_id', $companyId)
            ->where('payment_date', '>=', $since)
            ->with(['customer:id,name', 'paymentMethod:id,name'])
            ->latest('payment_date')
            ->limit($limit)
            ->get();

        return [
            'since' => $since->toDateString(),
            'payments' => $payments->map(fn (Payment $p): array => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'payment_date' => $this->asDate($p->payment_date),
                'amount' => $p->amount,
                'customer_id' => $p->customer_id,
                'customer_name' => $p->customer?->name,
                'invoice_id' => $p->invoice_id,
                'payment_method' => $p->paymentMethod?->name,
            ])->all(),
        ];
    }
}
