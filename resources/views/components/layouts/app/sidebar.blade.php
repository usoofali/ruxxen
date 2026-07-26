<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-orange-500 selection:text-white relative overflow-x-hidden transition-colors duration-200">
        <!-- Ambient Radial Glows & Grid Mesh -->
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-orange-500/10 blur-3xl"></div>
            <div class="absolute top-1/3 -left-40 h-96 w-96 rounded-full bg-amber-500/10 blur-3xl"></div>
            <div class="absolute -bottom-40 right-1/3 h-96 w-96 rounded-full bg-orange-600/5 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#94a3b8_1px,transparent_1px)] dark:bg-[radial-gradient(#334155_1px,transparent_1px)] [background-size:24px_24px] opacity-20"></div>
        </div>

        <flux:sidebar sticky stashable class="border-e border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl shadow-lg dark:shadow-2xl">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <div class="px-2 py-3 mb-3 border-b border-slate-200/80 dark:border-slate-800/80">
                <a href="{{ route('dashboard') }}" class="flex items-center group transition-transform active:scale-[0.98]" wire:navigate>
                    <x-app-logo />
                </a>
            </div>

            <flux:navlist variant="outline" class="space-y-1">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                    
                    @if(auth()->user()->isCashier())
                        <flux:navlist.item icon="document-text" :href="route('sales.history')" :current="request()->routeIs('sales.history')" wire:navigate>{{ __('Sales History') }}</flux:navlist.item>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <flux:navlist.item icon="cube" :href="route('admin.inventory')" :current="request()->routeIs('admin.inventory')" wire:navigate>{{ __('Inventory') }}</flux:navlist.item>
                        <flux:navlist.item icon="currency-dollar" :href="route('admin.transactions')" :current="request()->routeIs('admin.transactions')" wire:navigate>{{ __('Transactions') }}</flux:navlist.item>
                        <flux:navlist.item icon="chart-bar" :href="route('admin.reports')" :current="request()->routeIs('admin.reports')" wire:navigate>{{ __('Reports') }}</flux:navlist.item>
                        <flux:navlist.item icon="hand-thumb-up" :href="route('admin.discounts')" :current="request()->routeIs('admin.discounts')" wire:navigate>{{ __('Discounts') }}</flux:navlist.item>
                        <flux:navlist.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    class="rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800/60 p-2 transition-colors border border-slate-200/80 dark:border-slate-800/50"
                />

                <flux:menu class="w-[240px] bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 backdrop-blur-xl shadow-2xl rounded-2xl p-2">
                    <flux:menu.radio.group>
                        <div class="p-1 text-sm font-normal">
                            <div class="flex items-center gap-3 px-2 py-2 text-start text-sm bg-slate-100 dark:bg-slate-800/40 rounded-xl border border-slate-200/80 dark:border-slate-800/50">
                                <span class="relative flex h-9 w-9 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-amber-600 font-bold text-white shadow-sm"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
                                    <span class="truncate text-[10px] font-semibold tracking-wide uppercase text-orange-600 dark:text-orange-400 mt-0.5">{{ ucfirst(auth()->user()->role) }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="my-1.5 border-slate-200 dark:border-slate-800" />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate class="hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-xl transition-colors">{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="my-1.5 border-slate-200 dark:border-slate-800" />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header class="lg:hidden border-b border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl px-4 py-3">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    class="rounded-lg p-1.5 border border-slate-200 dark:border-slate-800"
                />

                <flux:menu class="w-[220px] bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 backdrop-blur-xl shadow-2xl rounded-2xl p-2">
                    <flux:menu.radio.group>
                        <div class="p-1 text-sm font-normal">
                            <div class="flex items-center gap-2.5 px-2 py-2 text-start text-sm bg-slate-100 dark:bg-slate-800/40 rounded-xl">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-amber-600 font-bold text-white text-xs"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="my-1.5 border-slate-200 dark:border-slate-800" />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate class="hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-xl">{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator class="my-1.5 border-slate-200 dark:border-slate-800" />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-xl">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>


