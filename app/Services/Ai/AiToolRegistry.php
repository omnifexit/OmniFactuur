<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Tools\AiTool;
use InvalidArgumentException;

/**
 * In-memory registry of AiTool instances.
 *
 * Register tools from a service provider (see `App\Providers\AiServiceProvider`)
 * at app boot; the AiAssistantService reads `schemas()` to populate the LLM's
 * tool-calling payload and calls `execute()` when the model returns a tool_call.
 *
 * The registry is intentionally a singleton: tools themselves are stateless
 * dispatchers, so one instance shared across the request is safe. Modules can
 * register their own tools by resolving this service and calling `register()`
 * from their own ServiceProvider::boot().
 *
 *     $this->app->resolving(AiToolRegistry::class, function (AiToolRegistry $registry) {
 *         $registry->register(new MyCustomTool);
 *     });
 */
class AiToolRegistry
{
    /**
     * @var array<string, AiTool>
     */
    protected array $tools = [];

    /**
     * Per-user memo so a single turn doesn't re-query the user for every tool.
     *
     * @var array<int, User|null>
     */
    protected array $userCache = [];

    public function register(AiTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /**
     * @return array<string, AiTool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    public function get(string $name): ?AiTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Export the tools the given user is authorized to use as the `tools` array
     * for an OpenAI-style chat request.
     *
     * Tools the user lacks the required ability for are omitted entirely, so the
     * LLM is never even told they exist. `execute()` re-checks as a backstop.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemas(int $userId): array
    {
        $authorized = array_filter(
            $this->tools,
            fn (AiTool $tool): bool => $this->userCan($tool, $userId),
        );

        return array_values(array_map(
            fn (AiTool $tool): array => $tool->toOpenAiToolSchema(),
            $authorized,
        ));
    }

    /**
     * Execute a tool by name, injecting company + user scope from the caller's session.
     *
     * The AiAssistantService is the only place this should be called from — that's
     * how we guarantee the `$companyId` and `$userId` arguments are session-authoritative
     * and never influenced by LLM output.
     *
     * Authorization backstop: even though `schemas()` already hides tools the user
     * can't use, we re-check the required ability here so a model that hallucinates
     * an unauthorized tool name gets a structured error instead of data.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws InvalidArgumentException When the tool name is not registered.
     */
    public function execute(string $name, array $arguments, int $companyId, int $userId): mixed
    {
        $tool = $this->get($name);

        if ($tool === null) {
            throw new InvalidArgumentException("Unknown AI tool: {$name}");
        }

        if (! $this->userCan($tool, $userId)) {
            return [
                'error' => 'unauthorized',
                'message' => 'You do not have permission to access this data.',
            ];
        }

        return $tool->execute($arguments, $companyId, $userId);
    }

    /**
     * Whether the user holds the Bouncer ability a tool requires.
     *
     * The ability is evaluated under the ambient Bouncer scope, which the
     * `company` + `bouncer` (ScopeBouncer) middleware set to the active company
     * on every AI request — the same way the app's policies check abilities. The
     * resolved user is always the conversation owner (ChatController binds it to
     * the request user), so this never trusts an identifier from LLM output.
     */
    protected function userCan(AiTool $tool, int $userId): bool
    {
        $required = $tool->requiredAbility();

        if ($required === null) {
            return true;
        }

        [$ability, $model] = $required;

        $user = $this->userCache[$userId] ??= User::find($userId);

        if ($user === null) {
            return false;
        }

        return $model === null
            ? $user->can($ability)
            : $user->can($ability, $model);
    }

    /**
     * Test-only: reset the registry between tests that exercise different tool sets.
     */
    public function flush(): void
    {
        $this->tools = [];
        $this->userCache = [];
    }
}
