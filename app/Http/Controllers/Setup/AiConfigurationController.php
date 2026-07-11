<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Rules\PublicHttpUrl;
use App\Services\AiConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * Installer wizard step: optional "Enable AI" configuration.
 *
 * Runs without an authenticated user — the installer middleware allows public
 * access until `profile_complete` is marked COMPLETED. Persists a minimal global
 * AI config so the first super-admin doesn't have to revisit the admin panel
 * just to turn on chat/text-generation after install.
 *
 * Skipping the step (posting ai_enabled=NO) is the default path — users can
 * always configure AI later from Admin → Settings → AI Configuration.
 */
class AiConfigurationController extends Controller
{
    public function __construct(
        private readonly AiConfigurationService $aiConfigurationService,
    ) {}

    /**
     * Return the current AI config defaults plus the driver list for the wizard form.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'config' => $this->aiConfigurationService->getGlobalConfig(),
            'drivers' => $this->aiConfigurationService->listDrivers(),
        ]);
    }

    /**
     * Persist the installer's AI config choice and advance the wizard step.
     *
     * @throws ValidationException
     */
    public function save(Request $request): JsonResponse
    {
        Artisan::call('optimize:clear');

        $validated = $this->validate($request, [
            'ai_enabled' => 'required|in:YES,NO',
            'ai_driver' => 'required_if:ai_enabled,YES|nullable|string',
            'ai_api_key' => 'required_if:ai_enabled,YES|nullable|string',
            'ai_base_url' => ['nullable', 'string', 'url', new PublicHttpUrl],
            'ai_chat_enabled' => 'nullable|in:YES,NO',
            'ai_chat_model' => 'nullable|string|max:200',
            'ai_text_generation_enabled' => 'nullable|in:YES,NO',
            'ai_text_generation_model' => 'nullable|string|max:200',
        ]);

        $this->aiConfigurationService->saveGlobalConfig($validated);

        // Advance the installer's profile_complete marker if we're the first to touch it.
        // Mail uses `4`; we'll use the next sentinel but leave actual completion to the
        // final Preferences step (which sets 'COMPLETED'). The sentinel value is ignored
        // once COMPLETED is written — it only matters for step-tracking during install.
        $profileComplete = Setting::getSetting('profile_complete');
        if ($profileComplete !== 'COMPLETED' && (int) $profileComplete < 5) {
            Setting::setSetting('profile_complete', 5);
        }

        return response()->json(['success' => true]);
    }
}
