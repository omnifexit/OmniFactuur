<?php

namespace App\Http\Controllers\Company\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * List the current user's conversations for the current company.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('use ai');

        $conversations = AiConversation::query()
            ->where('company_id', $request->header('company'))
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'model', 'updated_at', 'created_at']);

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Show a single conversation with its full message history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorize('use ai');

        $conversation = AiConversation::query()
            ->where('id', $id)
            ->where('company_id', $request->header('company'))
            ->firstOrFail();

        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->get(['id', 'role', 'content', 'created_at']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Rename a conversation.
     *
     * @throws ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('use ai');

        $conversation = AiConversation::query()
            ->where('id', $id)
            ->where('company_id', $request->header('company'))
            ->firstOrFail();

        $this->authorize('update', $conversation);

        $validated = $this->validate($request, [
            'title' => 'required|string|max:255',
        ]);

        $conversation->update(['title' => $validated['title']]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a conversation (cascades to messages via DB foreign key).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorize('use ai');

        $conversation = AiConversation::query()
            ->where('id', $id)
            ->where('company_id', $request->header('company'))
            ->firstOrFail();

        $this->authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(['success' => true]);
    }
}
