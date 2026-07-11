<?php

namespace App\Services\Ai\Tools;

use App\Models\Customer;

class SearchCustomersTool extends AiTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function name(): string
    {
        return 'search_customers';
    }

    public function description(): string
    {
        return 'Search customers for the current company by free-text query (matches name, display_name, email, company_name, contact_name). Returns a compact list with ids, names, and contact info.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Free-text search against name, email, and related fields.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-customer', Customer::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $limit = min((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $query = Customer::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->limit($limit);

        if (! empty($arguments['query'])) {
            $q = $arguments['query'];
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%");
            });
        }

        return [
            'customers' => $query->get()->map(fn (Customer $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'display_name' => $c->display_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'company_name' => $c->company_name,
            ])->all(),
        ];
    }
}
