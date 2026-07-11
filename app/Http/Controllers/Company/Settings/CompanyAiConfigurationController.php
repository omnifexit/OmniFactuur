<?php

namespace App\Http\Controllers\Company\Settings;

use App\Http\Controllers\Controller;
use App\Rules\PublicHttpUrl;
use App\Services\AiConfigurationService;
use App\Support\Ai\AiDriverFactory;
use App\Support\Ai\AiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompanyAiConfigurationController extends Controller
{
    public function __construct(
        private readonly AiConfigurationService $aiConfigurationService,
    ) {}

    /**
     * Get the per-company AI config with decrypted API key masked for response.
     */
    public function getConfig(Request $request): JsonResponse
    {
        $config = $this->aiConfigurationService->getCompanyConfig($request->header('company'));

        return response()->json($this->maskApiKey($config));
    }

    /**
     * Persist the per-company AI config.
     *
     * Respects the `use_custom_ai_config` toggle — when OFF, only the toggle is written
     * and the driver fields are discarded (same pattern as the mail company override).
     *
     * @throws ValidationException
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $this->authorize('owner only');

        $validated = $this->validate(
            $request,
            $this->aiConfigurationService->validationRules(allowDisabledCustomConfig: true),
        );

        // Preserve existing key when masked placeholder is submitted
        if (($validated['ai_api_key'] ?? null) === '********' || ($validated['ai_api_key'] ?? null) === '') {
            $existing = $this->aiConfigurationService->getCompanyConfig($request->header('company'));
            $validated['ai_api_key'] = $existing['ai_api_key'] ?? '';
        }

        $this->aiConfigurationService->saveCompanyConfig(
            $request->header('company'),
            $validated,
        );

        return response()->json(['success' => true]);
    }

    /**
     * Test a company-level AI configuration without persisting it.
     *
     * @throws ValidationException
     */
    public function testConnection(Request $request): JsonResponse
    {
        $this->authorize('owner only');

        $this->validate($request, [
            'ai_driver' => 'required|string',
            'ai_api_key' => 'nullable|string',
            'ai_base_url' => ['nullable', 'string', 'url', new PublicHttpUrl],
        ]);

        $apiKey = $request->input('ai_api_key');
        if ($apiKey === '********' || $apiKey === null || $apiKey === '') {
            $existing = $this->aiConfigurationService->getCompanyConfig($request->header('company'));
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
