<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

class CompanySettingsService
{
    /**
     * Get the company logo URL
     */
    public static function getCompanyLogoUrl(): ?string
    {
        $settings = CompanySetting::getSettings();
        return $settings->logo_url;
    }

    /**
     * Get the company name
     */
    public static function getCompanyName(): ?string
    {
        $settings = CompanySetting::getSettings();
        return $settings->company_name;
    }

    /**
     * Get the company address
     */
    public static function getCompanyAddress(): ?string
    {
        $settings = CompanySetting::getSettings();
        return $settings->company_address;
    }

    /**
     * Get the company phone
     */
    public static function getCompanyPhone(): ?string
    {
        $settings = CompanySetting::getSettings();
        return $settings->company_phone;
    }

    /**
     * Get the company email
     */
    public static function getCompanyEmail(): ?string
    {
        $settings = CompanySetting::getSettings();
        return $settings->company_email;
    }

    /**
     * Get all company settings
     */
    public static function getAllSettings(): CompanySetting
    {
        return CompanySetting::getSettings();
    }

    /**
     * Clear any cached company settings
     */
    public static function clearCache(): void
    {
        // Clear any cached company settings
        Cache::forget('company_settings');
        
        // You can add more cache keys here if needed
        // Cache::forget('company_logo');
        // Cache::forget('company_info');
    }
}
