<?php

namespace App\Http\Controllers\Company\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiTextGenerationService;
use App\Support\Ai\AiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GenerationController extends Controller
{
    public function __construct(
        private readonly AiTextGenerationService $generator,
    ) {}

    /**
     * One-shot text generation for the WYSIWYG popup.
     *
     * Stateless — nothing is persisted. Each call is fully self-contained.
     * Rate-limited via the shared 'ai' limiter so a stuck client can't
     * hammer the provider.
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('use ai');

        $validated = $this->validate($request, [
            'prompt' => 'required|string|max:4000',
            'context' => 'nullable|string|max:20000',
        ]);

        try {
            $text = $this->generator->generate(
                (int) $request->header('company'),
                $validated['prompt'],
                $validated['context'] ?? null,
            );
        } catch (AiException $e) {
            return response()->json([
                'error' => $e->errorKey,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'text' => $text,
        ]);
    }
}
