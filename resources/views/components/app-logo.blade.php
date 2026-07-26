<div class="flex items-center gap-3">
    @if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 p-1.5 shadow-md shadow-slate-900/5 overflow-hidden">
            <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
                 alt="{{ \App\Services\CompanySettingsService::getCompanyName() }}" 
                 class="h-full w-full object-contain">
        </div>
    @else
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 via-amber-500 to-orange-600 text-white shadow-lg shadow-orange-500/25">
            <svg class="h-5.5 w-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9.879z" />
            </svg>
        </div>
    @endif
    <div class="grid flex-1 text-start leading-tight">
        <span class="truncate font-extrabold text-base tracking-tight text-slate-900 dark:text-white">
            {{ \App\Services\CompanySettingsService::getCompanyName() ?: 'Ruxxen POS' }}
        </span>
        <span class="truncate text-[10px] font-bold uppercase tracking-wider text-orange-600 dark:text-orange-400 flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-orange-500 animate-pulse"></span>
            LPG Management
        </span>
    </div>
</div>

