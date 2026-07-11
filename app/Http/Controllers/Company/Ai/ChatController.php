<?php

namespace App\Http\Controllers\Company\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantService;
use App\Support\Ai\AiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $assistant,
    ) {}

    /**
     * Send a message into a conversation and get the assistant's reply.
     *
     * If `conversation_id` is omitted (or belongs to a conversation the user
     * doesn't own), we start a fresh conversation scoped to the current
     * (company_id, user_id). This matches the "new chat" UX where the user
     * opens the drawer and starts typing immediately.
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('use ai');

        $validated = $this->validate($request, [
            'conversation_id' => 'nullable|integer',
            'message' => 'required|string|max:10000',
        ]);

        $companyId = (int) $request->header('company');
        $userId = (int) $request->user()->id;

        $conversation = $this->resolveConversation(
            $validated['conversation_id'] ?? null,
            $companyId,
            $userId,
            $validated['message'],
        );

        try {
            $assistantMessage = $this->assistant->chat($conversation, $validated['message']);
        } catch (AiException $e) {
            return response()->json([
                'error' => $e->errorKey,
                'message' => $e->getMessage(),
            ], 422);
        }

        $conversation->refresh();

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
                'updated_at' => $conversation->updated_at,
            ],
            'message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role,
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at,
            ],
        ]);
    }

    /**
     * Pick an existing conversation the user owns, or create a new one.
     */
    protected function resolveConversation(
        ?int $conversationId,
        int $companyId,
        int $userId,
        string $firstMessage,
    ): AiConversation {
        if ($conversationId !== null) {
            $existing = AiConversation::query()
                ->where('id', $conversationId)
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return $this->assistant->startConversation($companyId, $userId, $firstMessage);
    }
}
