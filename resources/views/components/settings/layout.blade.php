<div class="flex flex-col md:flex-row items-start gap-6 lg:gap-8 w-full">
    <!-- Settings Navigation Sidebar -->
    <div class="w-full md:w-64 flex-shrink-0">
        <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-4 backdrop-blur-xl shadow-xl overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>
            
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-3 pt-2 pb-3">Settings Menu</p>
            <flux:navlist>
                <flux:navlist.item :href="route('settings.profile')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
                <flux:navlist.item :href="route('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
                <flux:navlist.item :href="route('settings.appearance')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
                @auth
                    @if(auth()->user()->isAdmin())
                        <flux:navlist.item :href="route('settings.company')" wire:navigate>{{ __('Company Settings') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('settings.data-manager')" wire:navigate>{{ __('Data Manager') }}</flux:navlist.item>
                    @endif
                @endauth
            </flux:navlist>
        </div>
    </div>

    <!-- Main Content Area Card -->
    <div class="flex-1 self-stretch w-full min-w-0">
        <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 sm:p-8 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
            <div class="mb-6">
                @if(isset($heading) && $heading)
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $heading }}</h2>
                @endif
                @if(isset($subheading) && $subheading)
                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $subheading }}</p>
                @endif
            </div>

            <div class="w-full max-w-2xl">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

