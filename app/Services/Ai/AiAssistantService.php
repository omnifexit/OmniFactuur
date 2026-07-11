<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Company;
use App\Models\User;
use App\Services\AiConfigurationService;
use App\Support\Ai\AiChatResponse;
use App\Support\Ai\AiException;
use App\Support\Ai\PromptLoader;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Orchestrates a single turn of the chat assistant.
 *
 * The public surface is `chat(conversation, userMessage)` — it persists the
 * user's message, runs the LLM → tool-calls → LLM loop until the model emits
 * a plain-text reply (or we hit the hard cap), persists everything along the
 * way, and returns the final assistant AiMessage.
 *
 * Per-phase-2 plan this is NON-STREAMING: the caller waits for the final
 * response. Streaming is a future polish refactor — the storage shape and
 * return type will be the same.
 */
class AiAssistantService
{
    /** Hard cap on tool-call iterations per turn. Prevents runaway loops. */
    public const MAX_TOOL_ITERATIONS = 5;

    /** Cap on messages loaded from history to fit the model context window. */
    private const HISTORY_WINDOW = 40;

    public function __construct(
        private readonly AiConfigurationService $aiConfiguration,
        private readonly AiToolRegistry $toolRegistry,
    ) {}

    /**
     * Start a new conversation for the given user in the given company.
     */
    public function startConversation(int $companyId, int $userId, ?string $firstMessage = null): AiConversation
    {
        return AiConversation::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $firstMessage !== null ? $this->titleFromMessage($firstMessage) : null,
        ]);
    }

    /**
     * Process one user message within an existing conversation.
     *
     * Returns the final assistant AiMessage that should be shown to the user.
     *
     * @throws AiException When AI is disabled or the driver call fails unrecoverably.
     */
    public function chat(AiConversation $conversation, string $userMessage): AiMessage
    {
        $driver = $this->aiConfiguration->makeDriver($conversation->company_id);

        if ($driver === null) {
            throw new AiException('AI is not enabled for this company', 'ai_disabled');
        }

        $resolved = $this->aiConfiguration->resolveForCompany($conversation->company_id);
        if (empty($resolved['chat_enabled'])) {
            throw new AiException('Chat is not enabled for this company', 'chat_disabled');
        }

        $model = (string) ($resolved['ai_chat_model'] ?? '');
        if ($model === '') {
            throw new AiException('No chat model configured', 'missing_model');
        }

        // Auto-title on first message.
        if ($conversation->title === null) {
            $conversation->title = $this->titleFromMessage($userMessage);
            $conversation->model = $model;
        }

        // Persist the user's message first so it shows even if the LLM call fails.
        $userRow = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        $conversation->touch();  // bump updated_at for "recent" ordering

        $messages = $this->buildMessagesPayload($conversation);
        $tools = $this->toolRegistry->schemas($conversation->user_id);

        for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
            try {
                $response = $driver->chatCompletion($messages, $model, $tools);
            } catch (AiException $e) {
                // Persist the error as an assistant message so the UI can render it.
                return AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessage::ROLE_ASSISTANT,
                    'content' => "Error: {$e->getMessage()}",
                    'model' => $model,
                ]);
            }

            // Tool calls requested → execute each, append their results, loop.
            if ($response->hasToolCalls()) {
                $this->persistAssistantToolCallTurn($conversation, $response, $model);
                $messages[] = $this->assistantToolCallMessage($response);

                foreach ($response->toolCalls as $call) {
                    $toolResult = $this->safelyExecuteTool(
                        name: $call['name'],
                        arguments: $call['arguments'] ?? [],
                        companyId: $conversation->company_id,
                        userId: $conversation->user_id,
                    );

                    $resultJson = json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    AiMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => AiMessage::ROLE_TOOL,
                        'content' => $resultJson,
                        'tool_call_id' => $call['id'] ?? null,
                    ]);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? '',
                        'content' => $resultJson,
                    ];
                }

                // Loop — the LLM will receive the tool results on the next iteration.
                continue;
            }

            // Plain text response — persist and return.
            return AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessage::ROLE_ASSISTANT,
                'content' => $response->message ?? '',
                'model' => $model,
                'tokens_in' => $response->usage['tokens_in'] ?? null,
                'tokens_out' => $response->usage['tokens_out'] ?? null,
            ]);
        }

        // Exceeded the loop cap.
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => 'The assistant could not complete this request within the tool-call budget. Please try rephrasing.',
            'model' => $model,
        ]);
    }

    /**
     * Build the OpenAI-format messages payload for the next LLM call.
     *
     * Starts with a fresh system prompt, then the recent message history
     * trimmed to HISTORY_WINDOW items, then the message(s) that will drive
     * the upcoming round-trip (already persisted by chat()).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildMessagesPayload(AiConversation $conversation): array
    {
        $payload = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($conversation),
            ],
        ];

        $history = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->values();

        foreach ($history as $msg) {
            $payload[] = $this->formatMessageForDriver($msg);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatMessageForDriver(AiMessage $msg): array
    {
        $base = ['role' => $msg->role];

        if ($msg->role === AiMessage::ROLE_TOOL) {
            $base['tool_call_id'] = $msg->tool_call_id ?? '';
            $base['content'] = $msg->content ?? '';

            return $base;
        }

        if ($msg->role === AiMessage::ROLE_ASSISTANT && $msg->tool_calls) {
            $base['content'] = $msg->content;
            $base['tool_calls'] = array_map(function (array $call): array {
                return [
                    'id' => $call['id'] ?? '',
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'] ?? '',
                        'arguments' => json_encode($call['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }, $msg->tool_calls);

            return $base;
        }

        $base['content'] = $msg->content ?? '';

        return $base;
    }

    /**
     * Compose the system prompt with company context.
     *
     * The template itself lives at resources/ai/prompts/chat-system.md so
     * it can be edited without touching this class. See PromptLoader for
     * the substitution rules.
     */
    protected function buildSystemPrompt(AiConversation $conversation): string
    {
        $company = Company::find($conversation->company_id);
        $user = User::find($conversation->user_id);

        return PromptLoader::load('chat-system', [
            'user_name' => $user?->name ?? 'the user',
            'company_name' => $company?->name ?? 'this company',
            'today' => Carbon::now()->toDateString(),
        ]);
    }

    /**
     * Persist the assistant's tool_calls-turn message (the one that requested tools).
     */
    protected function persistAssistantToolCallTurn(
        AiConversation $conversation,
        AiChatResponse $response,
        string $model,
    ): void {
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => $response->message,  // usually null on a tool_calls turn
            'tool_calls' => $response->toolCalls,
            'model' => $model,
            'tokens_in' => $response->usage['tokens_in'] ?? null,
            'tokens_out' => $response->usage['tokens_out'] ?? null,
        ]);
    }

    /**
     * Format the assistant tool-call turn for the NEXT driver call, in OpenAI format.
     *
     * @return array<string, mixed>
     */
    protected function assistantToolCallMessage(AiChatResponse $response): array
    {
        return [
            'role' => 'assistant',
            'content' => $response->message,
            'tool_calls' => array_map(function (array $call): array {
                return [
                    'id' => $call['id'] ?? '',
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'] ?? '',
                        'arguments' => json_encode($call['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
                    ],
                ];
            }, $response->toolCalls),
        ];
    }

    /**
     * Call a tool and trap any exception — we want tool failures to be visible
     * to the LLM as structured errors, not to crash the whole turn.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function safelyExecuteTool(
        string $name,
        array $arguments,
        int $companyId,
        int $userId,
    ): mixed {
        try {
            return $this->toolRegistry->execute($name, $arguments, $companyId, $userId);
        } catch (InvalidArgumentException $e) {
            return ['error' => 'unknown_tool', 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['error' => 'tool_execution_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Derive a short conversation title from the first user message.
     */
    protected function titleFromMessage(string $message): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if (mb_strlen($trimmed) <= 60) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, 57)).'...';
    }
}
