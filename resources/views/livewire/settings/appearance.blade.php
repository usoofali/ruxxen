<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Customize the interface theme mode for your session')">
        <div class="my-4 space-y-4" x-data="{ currentTheme: $flux.appearance }">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Interface Color Scheme
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Light Mode Card -->
                <button 
                    type="button"
                    x-on:click="$flux.appearance = 'light'; currentTheme = 'light'"
                    :class="($flux.appearance === 'light') ? 'border-orange-500/80 bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-2 ring-orange-500/40 shadow-lg shadow-orange-500/10' : 'border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-950/40 text-slate-700 dark:text-slate-300 hover:border-orange-500/40'"
                    class="relative rounded-2xl border p-5 flex flex-col items-center justify-center gap-3 transition-all duration-200 cursor-pointer group text-center"
                >
                    <div :class="($flux.appearance === 'light') ? 'bg-gradient-to-br from-orange-500 via-amber-500 to-orange-600 text-white shadow-md shadow-orange-500/30' : 'bg-slate-200/60 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 group-hover:text-orange-500'" class="h-12 w-12 rounded-2xl flex items-center justify-center transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Light Mode</div>
                        <div class="text-[11px] opacity-70">Clean light styling</div>
                    </div>
                    <template x-if="$flux.appearance === 'light'">
                        <span class="absolute top-3 right-3 h-2.5 w-2.5 rounded-full bg-orange-500 shadow-sm shadow-orange-500 animate-pulse"></span>
                    </template>
                </button>

                <!-- Dark Mode Card -->
                <button 
                    type="button"
                    x-on:click="$flux.appearance = 'dark'; currentTheme = 'dark'"
                    :class="($flux.appearance === 'dark') ? 'border-orange-500/80 bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-2 ring-orange-500/40 shadow-lg shadow-orange-500/10' : 'border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-950/40 text-slate-700 dark:text-slate-300 hover:border-orange-500/40'"
                    class="relative rounded-2xl border p-5 flex flex-col items-center justify-center gap-3 transition-all duration-200 cursor-pointer group text-center"
                >
                    <div :class="($flux.appearance === 'dark') ? 'bg-gradient-to-br from-orange-500 via-amber-500 to-orange-600 text-white shadow-md shadow-orange-500/30' : 'bg-slate-200/60 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 group-hover:text-orange-500'" class="h-12 w-12 rounded-2xl flex items-center justify-center transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">Dark Mode</div>
                        <div class="text-[11px] opacity-70">Sleek dark theme</div>
                    </div>
                    <template x-if="$flux.appearance === 'dark'">
                        <span class="absolute top-3 right-3 h-2.5 w-2.5 rounded-full bg-orange-500 shadow-sm shadow-orange-500 animate-pulse"></span>
                    </template>
                </button>

                <!-- System Card -->
                <button 
                    type="button"
                    x-on:click="$flux.appearance = 'system'; currentTheme = 'system'"
                    :class="($flux.appearance === 'system') ? 'border-orange-500/80 bg-orange-500/10 text-orange-600 dark:text-orange-400 ring-2 ring-orange-500/40 shadow-lg shadow-orange-500/10' : 'border-slate-200/80 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-950/40 text-slate-700 dark:text-slate-300 hover:border-orange-500/40'"
                    class="relative rounded-2xl border p-5 flex flex-col items-center justify-center gap-3 transition-all duration-200 cursor-pointer group text-center"
                >
                    <div :class="($flux.appearance === 'system') ? 'bg-gradient-to-br from-orange-500 via-amber-500 to-orange-600 text-white shadow-md shadow-orange-500/30' : 'bg-slate-200/60 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 group-hover:text-orange-500'" class="h-12 w-12 rounded-2xl flex items-center justify-center transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold">System Preference</div>
                        <div class="text-[11px] opacity-70">Syncs with OS mode</div>
                    </div>
                    <template x-if="$flux.appearance === 'system'">
                        <span class="absolute top-3 right-3 h-2.5 w-2.5 rounded-full bg-orange-500 shadow-sm shadow-orange-500 animate-pulse"></span>
                    </template>
                </button>
            </div>
        </div>
    </x-settings.layout>
</section>


