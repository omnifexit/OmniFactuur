<?php

namespace App\Support\Ai;

use InvalidArgumentException;
use InvoiceShelf\Modules\Registry;

/**
 * Instantiates AiDriver implementations by name.
 *
 * Mirrors the shape of ExchangeRateDriverFactory: a static $drivers fallback
 * map for built-ins registered directly against the factory, plus a fallback
 * to the module Registry so module-contributed drivers (via
 * Registry::registerAiDriver()) are also resolvable. Canonical registration
 * path is the Registry — the local fallback map exists so the factory keeps
 * working even in tests or contexts where the Registry happens to be flushed.
 */
class AiDriverFactory
{
    /**
     * @var array<string, class-string<AiDriver>>
     */
    protected static array $drivers = [
        'openrouter' => OpenRouterDriver::class,
    ];

    /**
     * Register a custom AI driver directly with the factory.
     *
     * Modules should prefer Registry::registerAiDriver() which carries
     * the metadata (label, website, supported_roles, suggested_models,
     * config_fields) that the frontend UI needs to render a configuration
     * form. This method exists for tests and programmatic registration.
     *
     * @param  class-string<AiDriver>  $driverClass
     */
    public static function register(string $name, string $driverClass): void
    {
        static::$drivers[$name] = $driverClass;
    }

    /**
     * Instantiate a driver by name.
     *
     * @param  array<string, mixed>  $config  Driver-specific config (base_url, timeouts, etc.)
     *
     * @throws InvalidArgumentException When the driver name isn't known.
     */
    public static function make(string $driver, string $apiKey, array $config = []): AiDriver
    {
        $class = static::resolveDriverClass($driver);

        if (! $class) {
            throw new InvalidArgumentException("Unknown AI driver: {$driver}");
        }

        return new $class($apiKey, $config);
    }

    /**
     * Get all known driver names — both factory-registered built-ins and Registry-contributed.
     *
     * @return array<int, string>
     */
    public static function availableDrivers(): array
    {
        $local = array_keys(static::$drivers);
        $registry = array_keys(Registry::allDrivers('ai'));

        return array_values(array_unique(array_merge($local, $registry)));
    }

    /**
     * Resolve a driver name to its concrete class via the local map then the Registry.
     */
    protected static function resolveDriverClass(string $driver): ?string
    {
        if (isset(static::$drivers[$driver])) {
            return static::$drivers[$driver];
        }

        $meta = Registry::driverMeta('ai', $driver);

        return $meta['class'] ?? null;
    }
}
