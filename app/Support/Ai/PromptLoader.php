<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * Loads LLM prompt templates from disk and performs lightweight
 * placeholder substitution.
 *
 * Prompts live as plain markdown files under `resources/ai/prompts/`
 * so they can be edited without touching PHP service classes, get
 * syntax-highlighted in editors, and produce clean PR diffs. Keeping
 * the templates outside PHP also sidesteps heredoc indentation
 * rules and interpolation quirks (dollar signs, curly braces).
 *
 * Placeholders use the `{{name}}` double-brace form — a common
 * template convention that doesn't collide with markdown syntax and
 * is trivial to substitute via strtr(). We deliberately do NOT use
 * Blade for this: Blade's `{{ $var }}` HTML-escapes ampersands and
 * quotes, which would corrupt prompts that reference names like
 * "Smith & Co" (the model would see "Smith &amp; Co").
 */
class PromptLoader
{
    /**
     * Load a prompt template and substitute any placeholders.
     *
     * @param  string  $name  Template filename without extension (e.g. 'chat-system').
     * @param  array<string, scalar|\Stringable>  $vars  Placeholder => value map.
     *
     * @throws RuntimeException when the template file does not exist.
     */
    public static function load(string $name, array $vars = []): string
    {
        $path = resource_path("ai/prompts/{$name}.md");

        if (! is_file($path)) {
            throw new RuntimeException("Missing AI prompt template: {$name} (expected at {$path})");
        }

        $template = (string) file_get_contents($path);

        if ($vars === []) {
            return trim($template);
        }

        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) $value;
        }

        return trim(strtr($template, $replacements));
    }
}
