<?php

namespace App\Services\Ai\Tools;

/**
 * Abstract base class for read-only AI tools invokable by the chat assistant.
 *
 * Tools are what the LLM "calls" to fetch data when answering a user question.
 * Every tool is a small, well-typed PHP function:
 *
 *   - `name()` — snake_case identifier passed to the LLM
 *   - `description()` — one-sentence description; this is how the LLM decides
 *     when to invoke the tool. Write it like documentation, not marketing.
 *   - `parameterSchema()` — JSON schema for the parameters the LLM can pass.
 *     Must NEVER include a `company_id` field — scoping is injected at execute
 *     time from the caller's session. This is the v1 prompt-injection defense.
 *   - `execute()` — actually runs the query. Receives the resolved `$companyId`
 *     and `$userId` from the session, plus the arguments the LLM chose.
 *   - `requiredAbility()` — the Bouncer ability the caller must hold for this
 *     tool to be offered to the LLM and executed. Enforces per-user permissions
 *     on top of company scoping, so a restricted role can't read data via the
 *     assistant that it couldn't read through the normal API.
 *
 * Tools are **read-only** by contract. There is intentionally no mutation
 * surface in v1 — the chat assistant cannot create, update, or delete
 * anything, no matter what the LLM is told. Any attempt to add a mutation
 * tool should go through a separate design review.
 */
abstract class AiTool
{
    /** Stable identifier sent to the LLM. Use snake_case. */
    abstract public function name(): string;

    /**
     * Natural-language description the LLM uses to decide when to call this tool.
     *
     * Keep it one sentence, written in imperative mood, describing what you get
     * back. Good examples:
     *
     *   - "Search invoices by customer, status, or free-text query."
     *   - "Fetch full details for a customer by ID, including their address and totals."
     *
     * Avoid marketing language and hedging. The LLM pays close attention to
     * this string.
     */
    abstract public function description(): string;

    /**
     * JSON schema for the arguments the LLM may pass.
     *
     * Shape matches OpenAI's function-calling parameters schema — an object
     * with a `properties` map and a `required` array. Tools that take no
     * arguments should return a bare `['type' => 'object', 'properties' => (object) []]`.
     *
     * Critical: do NOT include `company_id` or `user_id` in the schema.
     * Scoping is the host's responsibility and is injected at execute time.
     *
     * @return array<string, mixed>
     */
    abstract public function parameterSchema(): array;

    /**
     * Run the tool with the given arguments, scoped to the caller's session.
     *
     * Implementations MUST query within the given `$companyId` and never trust
     * any company/user identifier the LLM tries to sneak into `$arguments`.
     *
     * @param  array<string, mixed>  $arguments  Parsed from the LLM's tool_call JSON
     * @param  int  $companyId  Injected from the current session — authoritative
     * @param  int  $userId  Injected from the current session
     * @return mixed Anything JSON-encodable; will be serialized and sent back to the LLM
     */
    abstract public function execute(array $arguments, int $companyId, int $userId): mixed;

    /**
     * The Bouncer ability a caller must hold to use this tool, as a
     * `[ability, modelClass]` pair — or null if no ability beyond `use ai`.
     *
     * The registry checks this against the session user before exposing the tool
     * to the LLM and again before executing it, so the assistant honours the same
     * per-user permissions as the rest of the app. The model element may be null
     * for gate-style abilities that take no model (e.g. `dashboard`).
     *
     * @return array{0: string, 1: class-string|null}|null
     */
    abstract public function requiredAbility(): ?array;

    /**
     * Convert this tool into an OpenAI-style tools array entry.
     *
     * @return array<string, mixed>
     */
    public function toOpenAiToolSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameterSchema(),
            ],
        ];
    }

    /**
     * Normalize a model date field (which may be a string OR a Carbon instance
     * depending on model casts) to a YYYY-MM-DD string for tool output.
     *
     * Many InvoiceShelf models store dates as raw strings; others cast to Carbon.
     * Centralizing this avoids cast-guessing in every tool.
     */
    protected function asDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toDateString')) {
            return $value->toDateString();
        }

        // String like '2026-04-11' or '2026-04-11 00:00:00' — keep the date part only.
        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        return null;
    }
}
