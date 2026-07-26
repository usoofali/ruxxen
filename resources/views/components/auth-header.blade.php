@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center space-y-1.5 mb-2">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ $title }}</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400 font-normal leading-relaxed">{{ $description }}</p>
</div>


