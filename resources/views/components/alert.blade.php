@props([
    'variant' => 'success', // success, error, warning, info
    'dismissible' => true,
    'timeout' => 5000, // milliseconds before auto-dismiss (set to 0 to disable)
])

@php
    $baseClasses = 'flex items-center p-4 mb-4 text-sm rounded-lg shadow-md border transition-opacity duration-300';
    $variants = [
        'success' => 'text-green-700 bg-green-100 border-green-300',
        'error' => 'text-red-700 bg-red-100 border-red-300',
        'warning' => 'text-yellow-700 bg-yellow-100 border-yellow-300',
        'info' => 'text-blue-700 bg-blue-100 border-blue-300',
    ];

    $iconPaths = [
        'success' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
        'error' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2h-2v-2zm0-6h2v4h-2V7z',
        'warning' => 'M8.257 3.099c.764-1.36 2.722-1.36 3.486 0l6.518 11.614c.75 1.338-.213 3.038-1.743 3.038H3.482c-1.53 0-2.493-1.7-1.743-3.038L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-2a1 1 0 01-1-1V7a1 1 0 112 0v3a1 1 0 01-1 1z',
        'info' => 'M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 9h2v6H9V9zm0-4h2v2H9V5z',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['success']);
@endphp

<div
    x-data="{ show: true }"
    x-init="@if($timeout > 0) setTimeout(() => show = false, {{ $timeout }}); @endif"
    x-show="show"
    x-transition.opacity
    {{ $attributes->merge(['class' => $classes, 'role' => 'alert']) }}
>
    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="{{ $iconPaths[$variant] ?? $iconPaths['success'] }}" clip-rule="evenodd" />
    </svg>
    <span class="flex-1">{{ $slot }}</span>

    @if ($dismissible)
        <button type="button"
                class="ml-auto -mx-1.5 -my-1.5 text-inherit rounded-lg focus:ring-2 p-1 hover:bg-opacity-20 inline-flex h-6 w-6"
                @click="show = false">
            <span class="sr-only">Dismiss</span>
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                      clip-rule="evenodd"/>
            </svg>
        </button>
    @endif
</div>
