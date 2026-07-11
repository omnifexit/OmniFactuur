<?php

namespace App\Providers;

use App\Support\Ai\OpenRouterDriver;
use App\Support\ExchangeRate\CurrencyConverterDriver;
use App\Support\ExchangeRate\CurrencyFreakDriver;
use App\Support\ExchangeRate\CurrencyLayerDriver;
use App\Support\ExchangeRate\OpenExchangeRateDriver;
use Illuminate\Support\ServiceProvider;
use InvoiceShelf\Modules\Registry;

class DriverRegistryProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerExchangeRateDrivers();
        $this->registerAiDrivers();
    }

    protected function registerExchangeRateDrivers(): void
    {
        Registry::registerExchangeRateDriver('currency_converter', [
            'class' => CurrencyConverterDriver::class,
            'label' => 'settings.exchange_rate.currency_converter',
            'website' => 'https://www.currencyconverterapi.com',
            'config_fields' => [
                [
                    'key' => 'type',
                    'type' => 'select',
                    'label' => 'settings.exchange_rate.server',
                    'options' => [
                        ['label' => 'settings.preferences.premium', 'value' => 'PREMIUM'],
                        ['label' => 'settings.preferences.prepaid', 'value' => 'PREPAID'],
                        ['label' => 'settings.preferences.free', 'value' => 'FREE'],
                        ['label' => 'settings.preferences.dedicated', 'value' => 'DEDICATED'],
                    ],
                    'default' => 'FREE',
                ],
                [
                    'key' => 'url',
                    'type' => 'text',
                    'label' => 'settings.exchange_rate.url',
                    'visible_when' => ['type' => 'DEDICATED'],
                ],
            ],
        ]);

        Registry::registerExchangeRateDriver('currency_freak', [
            'class' => CurrencyFreakDriver::class,
            'label' => 'settings.exchange_rate.currency_freak',
            'website' => 'https://currencyfreaks.com',
        ]);

        Registry::registerExchangeRateDriver('currency_layer', [
            'class' => CurrencyLayerDriver::class,
            'label' => 'settings.exchange_rate.currency_layer',
            'website' => 'https://currencylayer.com',
        ]);

        Registry::registerExchangeRateDriver('open_exchange_rate', [
            'class' => OpenExchangeRateDriver::class,
            'label' => 'settings.exchange_rate.open_exchange_rate',
            'website' => 'https://openexchangerates.org',
        ]);
    }

    protected function registerAiDrivers(): void
    {
        Registry::registerAiDriver('openrouter', [
            'class' => OpenRouterDriver::class,
            'label' => 'settings.ai.openrouter',
            'website' => 'https://openrouter.ai',
            'default_base_url' => 'https://openrouter.ai/api/v1',
            'supported_roles' => ['chat', 'text_generation'],
            'suggested_models' => [
                ['value' => 'anthropic/claude-sonnet-4.6', 'label' => 'Anthropic Claude Sonnet 4.6'],
                ['value' => 'anthropic/claude-haiku-4.5', 'label' => 'Anthropic Claude Haiku 4.5'],
                ['value' => 'anthropic/claude-opus-4.6', 'label' => 'Anthropic Claude Opus 4.6'],
                ['value' => 'openai/gpt-5.4', 'label' => 'OpenAI GPT-5.4'],
                ['value' => 'openai/gpt-5.4-mini', 'label' => 'OpenAI GPT-5.4 mini'],
                ['value' => 'google/gemini-3.1-pro-preview', 'label' => 'Google Gemini 3.1 Pro (preview)'],
                ['value' => 'google/gemini-3.1-flash-lite-preview', 'label' => 'Google Gemini 3.1 Flash Lite (preview)'],
                ['value' => 'z-ai/glm-5.1', 'label' => 'Z.AI GLM 5.1'],
                ['value' => 'z-ai/glm-4.7-flash', 'label' => 'Z.AI GLM 4.7 Flash'],
            ],
            'config_fields' => [
                [
                    'key' => 'base_url',
                    'type' => 'text',
                    'label' => 'settings.ai.base_url',
                    'default' => 'https://openrouter.ai/api/v1',
                ],
            ],
        ]);
    }
}
