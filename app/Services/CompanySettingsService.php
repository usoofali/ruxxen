<?php

namespace App\Services;

use App\Models\CompanySetting;

class CompanySettingsService
{
    private static $settings = null;

    /**
     * Get company settings (cached for performance)
     */
    public static function getSettings(): CompanySetting
    {
        if (self::$settings === null) {
            self::$settings = CompanySetting::getSettings();
        }

        return self::$settings;
    }

    /**
     * Get company name
     */
    public static function getCompanyName(): string
    {
        return self::getSettings()->company_name;
    }

    /**
     * Get company address
     */
    public static function getCompanyAddress(): ?string
    {
        return self::getSettings()->company_address;
    }

    /**
     * Get company phone
     */
    public static function getCompanyPhone(): ?string
    {
        return self::getSettings()->company_phone;
    }

    /**
     * Get company email
     */
    public static function getCompanyEmail(): ?string
    {
        return self::getSettings()->company_email;
    }

    /**
     * Get company logo URL
     */
    public static function getCompanyLogoUrl(): ?string
    {
        return self::getSettings()->logo_url;
    }

    /**
     * Get SMTP configuration
     */
    public static function getSmtpConfig(): array
    {
        $settings = self::getSettings();
        
        return [
            'host' => $settings->smtp_host,
            'port' => $settings->smtp_port,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'encryption' => $settings->smtp_encryption,
        ];
    }

    /**
     * Check if SMTP is configured
     */
    public static function isSmtpConfigured(): bool
    {
        $settings = self::getSettings();
        
        return !empty($settings->smtp_host) && 
               !empty($settings->smtp_port) && 
               !empty($settings->smtp_username) && 
               !empty($settings->smtp_password);
    }

    /**
     * Clear cached settings (useful after updates)
     */
    public static function clearCache(): void
    {
        self::$settings = null;
    }
}
