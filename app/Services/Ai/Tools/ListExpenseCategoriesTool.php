<?php

namespace App\Services\Ai\Tools;

use App\Models\Expense;
use App\Models\ExpenseCategory;

class ListExpenseCategoriesTool extends AiTool
{
    public function name(): string
    {
        return 'list_expense_categories';
    }

    public function description(): string
    {
        return 'List all expense categories defined for the current company.';
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
        // Expense categories are gated by the expense ability (see ExpenseCategoryPolicy).
        return ['view-expense', Expense::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $categories = ExpenseCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return [
            'categories' => $categories->map(fn ($c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
            ])->all(),
        ];
    }
}
