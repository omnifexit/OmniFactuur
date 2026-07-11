<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Rules\PublicHttpUrl;
use App\Services\AiConfigurationService;
use App\Support\Ai\AiDriverFactory;
use App\Support\Ai\AiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiConfigurationController extends Controller
{
    public function __construct(
        private readonly AiConfigurationService $aiConfigurationService,
    ) {}

    /**
     * Get the global AI configuration with decrypted API key masked for response.
     *
     * @throws AuthorizationException
     */
    public function getConfig(): JsonResponse
    {
        $this->authorize('manage ai config');

        $config = $this->aiConfigurationService->getGlobalConfig();

        return response()->json($this->maskApiKey($config));
    }

    /**
     * Persist the global AI configuration.
     *
     * If the submitted api_key is the masked placeholder, we retain the stored value —
     * otherwise the user would have to re-enter the key every time they save the form.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $this->authorize('manage ai config');

        $validated = $this->validate(
            $request,
            $this->aiConfigurationService->validationRules(allowDisabledCustomConfig: false),
        );

        // Preserve existing key when client submits the masked placeholder
        if (($validated['ai_api_key'] ?? null) === '********' || ($validated['ai_api_key'] ?? null) === '') {
            $existing = $this->aiConfigurationService->getGlobalConfig();
            $validated['ai_api_key'] = $existing['ai_api_key'] ?? '';
        }

        $this->aiConfigurationService->saveGlobalConfig($validated);

        return response()->json(['success' => 'ai_variables_save_successfully']);
    }

    /**
     * Return the AI driver list for the admin UI — same shape as the exchange rate endpoint.
     *
     * @throws AuthorizationException
     */
    public function getDrivers(): JsonResponse
    {
        $this->authorize('manage ai config');

        return response()->json([
            'ai_drivers' => $this->aiConfigurationService->listDrivers(),
        ]);
    }

    /**
     * Test the currently configured AI provider by instantiating its driver and calling validateConnection().
     *
     * @throws AuthorizationException
     */
    public function testConnection(Request $request): JsonResponse
    {
        $this->authorize('manage ai config');

        $this->validate($request, [
            'ai_driver' => 'required|string',
            'ai_api_key' => 'nullable|string',
            'ai_base_url' => ['nullable', 'string', 'url', new PublicHttpUrl],
        ]);

        // If the masked placeholder was submitted, fall back to the stored key
        $apiKey = $request->input('ai_api_key');
        if ($apiKey === '********' || $apiKey === null || $apiKey === '') {
            $existing = $this->aiConfigurationService->getGlobalConfig();
            $apiKey = $existing['ai_api_key'] ?? '';
        }

        if ($apiKey === '') {
            return response()->json(['error' => 'missing_api_key'], 422);
        }

        try {
            $driver = AiDriverFactory::make(
                $request->input('ai_driver'),
                $apiKey,
                ['base_url' => $request->input('ai_base_url')],
            );

            $result = $driver->validateConnection();
        } catch (AiException $e) {
            return response()->json(['error' => $e->errorKey, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'details' => $result]);
    }

    /**
     * Replace the stored API key with a masked placeholder so it's never returned to the client.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function maskApiKey(array $config): array
    {
        if (! empty($config['ai_api_key'])) {
            $config['ai_api_key'] = '********';
        }

        return $config;
    }
}
