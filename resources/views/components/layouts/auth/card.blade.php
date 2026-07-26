<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-orange-500 selection:text-white relative overflow-hidden flex items-center justify-center p-4 sm:p-6 md:p-10 transition-colors duration-200">
        <!-- Ambient Radial Glows & Grid Mesh -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-orange-500/10 dark:bg-orange-500/15 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 h-96 w-96 rounded-full bg-amber-500/10 blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full bg-orange-600/5 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#94a3b8_1px,transparent_1px)] dark:bg-[radial-gradient(#334155_1px,transparent_1px)] [background-size:24px_24px] opacity-25"></div>
        </div>

        <div class="flex w-full max-w-md flex-col gap-6 relative z-10">
            <!-- Brand Logo Header -->
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 group transition-transform duration-300 hover:scale-105" wire:navigate>
                @if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-white/80 dark:bg-slate-900/80 p-2 border border-slate-200/80 dark:border-orange-500/30 shadow-lg shadow-orange-500/10 ring-1 ring-orange-500/20 backdrop-blur-md">
                        <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
                             alt="{{ \App\Services\CompanySettingsService::getCompanyName() }}" 
                             class="h-10 w-10 object-contain">
                    </div>
                    @if(\App\Services\CompanySettingsService::getCompanyName())
                        <span class="text-base font-semibold tracking-wide text-slate-900 dark:text-white group-hover:text-orange-500 dark:group-hover:text-orange-400 transition-colors">
                            {{ \App\Services\CompanySettingsService::getCompanyName() }}
                        </span>
                    @endif
                @else
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 p-3 shadow-lg shadow-orange-500/25 ring-2 ring-orange-400/40">
                        <x-app-logo-icon class="size-8 fill-current text-white" />
                    </div>
                    <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white group-hover:text-orange-500 dark:group-hover:text-orange-400 transition-colors">
                        {{ config('app.name', 'Ruxxen Energy') }}
                    </span>
                @endif
            </a>

            <!-- Glassmorphic Form Card Container -->
            <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-8 sm:p-10 backdrop-blur-xl shadow-xl shadow-slate-200/50 dark:shadow-2xl dark:shadow-black/60 overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

                {{ $slot }}
            </div>

            <!-- Footer Copy -->
            <div class="text-center text-xs text-slate-400 dark:text-slate-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Ruxxen') }}. All rights reserved.
            </div>
        </div>
        @fluxScripts
    </body>
</html>


