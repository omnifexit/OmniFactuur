<?php

namespace App\Providers;

use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Tools\GetCompanyStatsTool;
use App\Services\Ai\Tools\GetCustomerTool;
use App\Services\Ai\Tools\GetInvoiceTool;
use App\Services\Ai\Tools\ListExpenseCategoriesTool;
use App\Services\Ai\Tools\ListOverdueInvoicesTool;
use App\Services\Ai\Tools\ListRecentPaymentsTool;
use App\Services\Ai\Tools\RankExpenseCategoriesTool;
use App\Services\Ai\Tools\RankTopCustomersTool;
use App\Services\Ai\Tools\RankTopItemsTool;
use App\Services\Ai\Tools\SearchCustomersTool;
use App\Services\Ai\Tools\SearchInvoicesTool;
use App\Services\Ai\Tools\SearchItemsTool;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI tool registry as a singleton and registers every built-in
 * read-only tool the chat assistant can call.
 *
 * Modules that ship additional tools should extend the registry from their
 * own ServiceProvider::boot() by resolving AiToolRegistry from the container
 * and calling register() on it.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiToolRegistry::class, function (Application $app): AiToolRegistry {
            $registry = new AiToolRegistry;

            // Built-in read-only tools (order is presentation-only; the LLM picks).
            $registry->register(new SearchInvoicesTool);
            $registry->register(new GetInvoiceTool);
            $registry->register(new ListOverdueInvoicesTool);
            $registry->register(new SearchCustomersTool);
            $registry->register(new GetCustomerTool);
            $registry->register(new ListRecentPaymentsTool);
            $registry->register(new SearchItemsTool);
            $registry->register(new ListExpenseCategoriesTool);
            $registry->register(new GetCompanyStatsTool);

            // Ranking tools — group-by aggregates the individual-record
            // tools above can't express.
            $registry->register(new RankTopCustomersTool);
            $registry->register(new RankTopItemsTool);
            $registry->register(new RankExpenseCategoriesTool);

            return $registry;
        });
    }
}
