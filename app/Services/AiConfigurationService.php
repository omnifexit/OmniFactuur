<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Setting;
use App\Rules\PublicHttpUrl;
use App\Support\Ai\AiDriver;
use App\Support\Ai\AiDriverFactory;
use Illuminate\Support\Facades\Crypt;
use InvoiceShelf\Modules\Registry;

/**
 * Reads, writes, and resolves AI configuration at global and per-company scopes.
 *
 * Mirrors MailConfigurationService in shape: a global config lives in the `settings`
 * table under bare keys; a per-company override lives in `company_settings` under
 * `company_`-prefixed keys plus a `use_custom_ai_config` toggle. The resolution
 * order supports three layered kill-switches:
 *
 *   1. Global off  → no AI for anyone
 *   2. Per-company off  → no AI for this company (even if global is on)
 *   3. Role off (chat / text_generation) → role-level disable at either scope
 *
 * Deviation from mail: AI API keys are **encrypted** at the service layer via
 * Crypt::encryptString before persistence. OpenRouter bearer tokens have much
 * bigger blast radius than SMTP passwords; this is worth the pattern break.
 */
class AiConfigurationService
{
    public const ROLE_CHAT = 'chat';

    public const ROLE_TEXT_GENERATION = 'text_generation';

    public const ROLES = [self::ROLE_CHAT, self::ROLE_TEXT_GENERATION];

    private const GLOBAL_SCOPE = 'global';

    private const COMPANY_SCOPE = 'company';

    /**
     * Fields stored in the settings table (global scope, bare keys).
     *
     * Company-scope keys are these prefixed with `company_`.
     */
    private const FIELDS = [
        'ai_enabled',
        'ai_driver',
        'ai_api_key',
        'ai_base_url',
        'ai_chat_enabled',
        'ai_chat_model',
        'ai_text_generation_enabled',
        'ai_text_generation_model',
    ];

    /**
     * Fields whose stored values are encrypted at rest.
     */
    private const ENCRYPTED_FIELDS = [
        'ai_api_key',
    ];

    /**
     * Read the global AI config with decrypted secrets.
     *
     * @return array<string, mixed>
     */
    public function getGlobalConfig(): array
    {
        $raw = Setting::getSettings(self::FIELDS)->all();

        return $this->hydrateDefaults($this->decryptFields($raw));
    }

    /**
     * Read the per-company AI config with decrypted secrets.
     *
     * The response always includes the `use_custom_ai_config` toggle so the
     * frontend can render the override switch. Driver fields are present
     * regardless of the toggle — the client decides whether to show them.
     *
     * @return array<string, mixed>
     */
    public function getCompanyConfig(int|string $companyId): array
    {
        $companyKeys = array_merge(
            ['use_custom_ai_config'],
            $this->getCompanySettingKeys(),
        );

        $raw = CompanySetting::getSettings($companyKeys, $companyId)->all();

        return array_merge(
            ['use_custom_ai_config' => $raw['use_custom_ai_config'] ?? 'NO'],
            $this->hydrateDefaults($this->decryptFields($this->stripCompanyPrefix($raw))),
        );
    }

    /**
     * Persist the global AI config, encrypting sensitive fields.
     *
     * @param  array<string, mixed>  $payload
     */
    public function saveGlobalConfig(array $payload): void
    {
        Setting::setSettings($this->prepareSettingsForStorage($payload, self::GLOBAL_SCOPE));
    }

    /**
     * Persist the per-company AI config.
     *
     * When `use_custom_ai_config` is NOT 'YES', only the toggle is written —
     * driver fields in the payload are discarded. This mirrors the mail pattern
     * exactly and prevents stale per-company config from lingering after toggle-off.
     *
     * @param  array<string, mixed>  $payload
     */
    public function saveCompanyConfig(int|string $companyId, array $payload): void
    {
        if (($payload['use_custom_ai_config'] ?? 'YES') !== 'YES') {
            CompanySetting::setSettings([
                'use_custom_ai_config' => 'NO',
            ], $companyId);

            return;
        }

        $toStore = $this->prepareSettingsForStorage($payload, self::COMPANY_SCOPE);
        $toStore['use_custom_ai_config'] = 'YES';

        CompanySetting::setSettings($toStore, $companyId);
    }

    /**
     * Resolve the effective AI config for a company.
     *
     * Returns the decrypted config array, or `null` when AI is unavailable.
     * Resolution order:
     *
     *   1. Global `ai_enabled` must be YES. Otherwise AI is off for everyone.
     *   2. If the company has `use_custom_ai_config = YES`, return the company
     *      config. The company's own `ai_enabled` inside that override controls
     *      whether AI is on for this company (the company can opt out by
     *      setting `use_custom_ai_config = YES` and `ai_enabled = NO`).
     *   3. Otherwise, return the global config.
     *
     * @return array<string, mixed>|null
     */
    public function resolveForCompany(int|string $companyId): ?array
    {
        $global = $this->getGlobalConfig();

        // Global kill-switch — applies to all companies
        if (($global['ai_enabled'] ?? 'NO') !== 'YES') {
            return null;
        }

        $company = $this->getCompanyConfig($companyId);

        if (($company['use_custom_ai_config'] ?? 'NO') === 'YES') {
            // Company-specific config — can opt out via ai_enabled=NO
            if (($company['ai_enabled'] ?? 'NO') !== 'YES') {
                return null;
            }

            return $this->addBooleanFlags($company);
        }

        return $this->addBooleanFlags($global);
    }

    /**
     * Convenience: resolve config and instantiate a driver for the company.
     *
     * Returns `null` when AI is disabled for the company.
     */
    public function makeDriver(int|string $companyId): ?AiDriver
    {
        $config = $this->resolveForCompany($companyId);

        if ($config === null || empty($config['ai_api_key']) || empty($config['ai_driver'])) {
            return null;
        }

        return AiDriverFactory::make(
            $config['ai_driver'],
            $config['ai_api_key'],
            ['base_url' => $config['ai_base_url'] ?? null],
        );
    }

    /**
     * Build dynamic validation rules for a save request.
     *
     * @return array<string, mixed>
     */
    public function validationRules(bool $allowDisabledCustomConfig = false): array
    {
        $availableDrivers = AiDriverFactory::availableDrivers();

        return [
            'use_custom_ai_config' => $allowDisabledCustomConfig ? ['nullable', 'in:YES,NO'] : ['prohibited'],
            'ai_enabled' => ['nullable', 'in:YES,NO'],
            'ai_driver' => ['required_if:ai_enabled,YES', 'nullable', 'string', 'in:'.implode(',', $availableDrivers)],
            'ai_api_key' => ['required_if:ai_enabled,YES', 'nullable', 'string'],
            'ai_base_url' => ['nullable', 'string', 'url', new PublicHttpUrl],
            'ai_chat_enabled' => ['nullable', 'in:YES,NO'],
            'ai_chat_model' => ['nullable', 'string', 'max:200'],
            'ai_text_generation_enabled' => ['nullable', 'in:YES,NO'],
            'ai_text_generation_model' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Get driver metadata for the AI type — what the frontend needs to render forms.
     *
     * Shape matches the exchange rate driver list so the UI can reuse the same
     * data-driven rendering pattern.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDrivers(): array
    {
        return collect(Registry::allDrivers('ai'))
            ->map(fn (array $meta, string $name) => [
                'value' => $name,
                'label' => $meta['label'] ?? $name,
                'website' => $meta['website'] ?? '',
                'default_base_url' => $meta['default_base_url'] ?? '',
                'supported_roles' => $meta['supported_roles'] ?? [],
                'suggested_models' => $meta['suggested_models'] ?? [],
                'config_fields' => $meta['config_fields'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Company-scope keys: the FIELDS list with `company_` prefix.
     *
     * @return array<int, string>
     */
    protected function getCompanySettingKeys(): array
    {
        return array_map(fn (string $field) => 'company_'.$field, self::FIELDS);
    }

    /**
     * Strip the `company_` prefix from keys when reading company-scoped settings.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function stripCompanyPrefix(array $raw): array
    {
        $normalized = [];
        foreach ($raw as $key => $value) {
            if ($key === 'use_custom_ai_config') {
                continue;
            }
            if (str_starts_with($key, 'company_')) {
                $normalized[substr($key, strlen('company_'))] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Decrypt sensitive fields on read.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function decryptFields(array $settings): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                try {
                    $settings[$field] = Crypt::decryptString($settings[$field]);
                } catch (\Throwable) {
                    // Backward compat: if the value was stored before encryption
                    // was introduced, leave it as-is rather than wiping it.
                }
            }
        }

        return $settings;
    }

    /**
     * Fill in defaults for fields missing from storage so consumers get a complete array.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function hydrateDefaults(array $settings): array
    {
        return array_merge([
            'ai_enabled' => 'NO',
            'ai_driver' => 'openrouter',
            'ai_api_key' => '',
            'ai_base_url' => '',
            'ai_chat_enabled' => 'NO',
            'ai_chat_model' => 'anthropic/claude-sonnet-4.6',
            'ai_text_generation_enabled' => 'NO',
            'ai_text_generation_model' => 'anthropic/claude-haiku-4.5',
        ], $settings);
    }

    /**
     * Add derived boolean flags to a config array so consumers don't have to
     * compare against the 'YES'/'NO' strings everywhere.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function addBooleanFlags(array $config): array
    {
        $config['chat_enabled'] = ($config['ai_chat_enabled'] ?? 'NO') === 'YES';
        $config['text_generation_enabled'] = ($config['ai_text_generation_enabled'] ?? 'NO') === 'YES';

        return $config;
    }

    /**
     * Prepare a payload for storage: strip fields the caller doesn't own, encrypt
     * sensitive fields, prefix with `company_` for the company scope.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    protected function prepareSettingsForStorage(array $payload, string $scope): array
    {
        $prepared = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $value = $payload[$field];

            if (in_array($field, self::ENCRYPTED_FIELDS, true) && is_string($value) && $value !== '') {
                $value = Crypt::encryptString($value);
            }

            $storageKey = $scope === self::COMPANY_SCOPE ? 'company_'.$field : $field;
            $prepared[$storageKey] = $value;
        }

        return $prepared;
    }
}
