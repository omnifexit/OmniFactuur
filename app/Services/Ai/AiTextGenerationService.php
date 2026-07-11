<?php

namespace App\Services\Ai;

use App\Services\AiConfigurationService;
use App\Support\Ai\AiException;
use App\Support\Ai\PromptLoader;

/**
 * Stateless one-shot text generation for the WYSIWYG popup.
 *
 * Much simpler than the chat assistant — no conversation state, no tool calls,
 * no history. Takes a user-authored instruction plus optional surrounding
 * context (e.g. "here's the current editor content") and returns a single
 * generated text blob the frontend can insert into the editor.
 *
 * The text-generation role is distinct from chat in two places:
 *   - AiConfigurationService.text_generation_enabled gates availability
 *   - ai_text_generation_model picks which model to use
 *
 * That means an instance can use a cheap fast model for one-shot writing
 * (anthropic/claude-haiku-4.5) while pointing chat at a smarter model
 * (anthropic/claude-sonnet-4.6) without config gymnastics.
 */
class AiTextGenerationService
{
    public function __construct(
        private readonly AiConfigurationService $aiConfiguration,
    ) {}

    /**
     * Generate text from a user instruction, optionally grounded in a context blob.
     *
     * @param  int  $companyId  Current company — resolves config and model selection
     * @param  string  $prompt  User's instruction (e.g. "write a polite late-payment reminder")
     * @param  string|null  $context  Optional surrounding content — usually the editor's
     *                                current HTML/text, passed when the user wants the AI
     *                                to work from existing copy
     *
     * @throws AiException When AI is disabled, text generation is off, or the driver call fails
     */
    public function generate(int $companyId, string $prompt, ?string $context = null): string
    {
        $driver = $this->aiConfiguration->makeDriver($companyId);

        if ($driver === null) {
            throw new AiException('AI is not enabled for this company', 'ai_disabled');
        }

        $resolved = $this->aiConfiguration->resolveForCompany($companyId);
        if (empty($resolved['text_generation_enabled'])) {
            throw new AiException('Text generation is not enabled for this company', 'text_generation_disabled');
        }

        $model = (string) ($resolved['ai_text_generation_model'] ?? '');
        if ($model === '') {
            throw new AiException('No text generation model configured', 'missing_model');
        }

        $fullPrompt = $this->buildPrompt($prompt, $context);

        return trim($driver->textCompletion($fullPrompt, $model));
    }

    /**
     * Compose the final prompt sent to the model.
     *
     * Keep the framing terse — text-generation output should not be padded
     * with "Here is the text you requested:" preambles. The instruction is
     * always placed last so the model gives it the most weight.
     *
     * The static preamble lives at resources/ai/prompts/text-generation.md.
     * The conditional context + instruction appending stays here because it
     * has different structure depending on whether `context` is present.
     */
    protected function buildPrompt(string $prompt, ?string $context): string
    {
        $system = PromptLoader::load('text-generation');

        if ($context !== null && trim($context) !== '') {
            return $system."\n\n"
                ."Context (current content the user is working with):\n"
                .trim($context)
                ."\n\n"
                ."Instruction: {$prompt}";
        }

        return $system."\n\nInstruction: {$prompt}";
    }
}
