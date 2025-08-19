@if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
        <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
             alt="{{ \App\Services\CompanySettingsService::getCompanyName() }}" 
             class="size-5 object-contain">
    </div>
@else
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
    </div>
@endif
<div class="ms-1 grid flex-1 text-start text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">
        {{ \App\Services\CompanySettingsService::getCompanyName() ?: 'Laravel Starter Kit' }}
    </span>
</div>
