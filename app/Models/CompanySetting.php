<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'logo_path',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    protected $casts = [
        'smtp_port' => 'integer',
    ];

    /**
     * Get the SMTP password attribute (decrypted)
     */
    protected function smtpPassword(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Get the logo URL
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo_path) {
            return asset('storage/' . $this->logo_path);
        }
        return null;
    }

    /**
     * Get or create the company settings instance
     */
    public static function getSettings()
    {
        return static::firstOrCreate([], [
            'company_name' => 'Ruxxen LPG Gas Plant',
            'company_address' => '123 Gas Plant Street, City, State',
            'company_phone' => '+234 123 456 7890',
            'company_email' => 'info@ruxxenlpg.com',
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
        ]);
    }
}
