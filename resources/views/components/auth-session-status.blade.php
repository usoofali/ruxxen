@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-3.5 text-center shadow-sm backdrop-blur-sm']) }}>
        {{ $status }}
    </div>
@endif

