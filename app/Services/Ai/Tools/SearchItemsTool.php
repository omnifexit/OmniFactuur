<?php

namespace App\Services\Ai\Tools;

use App\Models\Item;

class SearchItemsTool extends AiTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function name(): string
    {
        return 'search_items';
    }

    public function description(): string
    {
        return 'Search catalog items (products/services) for the current company by free-text query (matches name and description). Returns id, name, unit price, and description.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Free-text search against name and description.',
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
        return ['view-item', Item::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $limit = min((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $query = Item::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->limit($limit);

        if (! empty($arguments['query'])) {
            $q = $arguments['query'];
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get()->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $item->price,
            ])->all(),
        ];
    }
}
