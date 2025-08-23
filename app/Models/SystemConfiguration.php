<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfiguration extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get a configuration value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_config_{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $config = static::where('key', $key)->first();
            
            if (!$config) {
                return $default;
            }

            return match ($config->type) {
                'boolean' => filter_var($config->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $config->value,
                'json' => json_decode($config->value, true),
                default => $config->value,
            };
        });
    }

    /**
     * Set a configuration value
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $config = static::firstOrNew(['key' => $key]);
        
        $config->value = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
        
        $config->type = $type;
        $config->description = $description;
        $config->save();

        // Clear cache
        Cache::forget("system_config_{$key}");
    }

    /**
     * Check if system is configured
     */
    public static function isConfigured(): bool
    {
        return static::getValue('system_configured', false);
    }

    /**
     * Mark system as configured
     */
    public static function markAsConfigured(): void
    {
        static::setValue('system_configured', true, 'boolean', 'Indicates if the system has been fully configured');
    }

    /**
     * Get setup completion status
     */
    public static function getSetupStatus(): array
    {
        return [
            'database_migrated' => static::getValue('database_migrated', false),
            'admin_created' => static::getValue('admin_created', false),
            'company_configured' => static::getValue('company_configured', false),
            'system_configured' => static::getValue('system_configured', false),
        ];
    }

    /**
     * Mark a setup step as completed
     */
    public static function markSetupStepCompleted(string $step): void
    {
        static::setValue($step, true, 'boolean', "Setup step: {$step}");
    }

    /**
     * Clear all configuration cache
     */
    public static function clearCache(): void
    {
        $configs = static::all();
        foreach ($configs as $config) {
            Cache::forget("system_config_{$config->key}");
        }
    }
}
