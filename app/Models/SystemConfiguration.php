<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get configuration value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $config = static::where('key', $key)->first();
        
        if (!$config) {
            return $default;
        }

        return match ($config->type) {
            'boolean' => (bool) $config->value,
            'integer' => (int) $config->value,
            'json' => is_string($config->value) ? json_decode($config->value, true) : $config->value,
            default => $config->value,
        };
    }

    /**
     * Set configuration value by key
     */
    public static function setValue(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
            ]
        );
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
     * Get setup progress
     */
    public static function getSetupProgress(): array
    {
        return [
            'database_migrated' => static::getValue('database_migrated', false),
            'admin_created' => static::getValue('admin_created', false),
            'company_configured' => static::getValue('company_configured', false),
            'system_configured' => static::getValue('system_configured', false),
        ];
    }

    /**
     * Mark setup step as completed
     */
    public static function markStepCompleted(string $step): void
    {
        static::setValue($step, true, 'boolean', "Setup step: {$step}");
    }

    /**
     * Get all configuration as array
     */
    public static function getAllConfigurations(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
