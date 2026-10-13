@props([
    'type' => 'error',
    'title',
    'text' => null,
])

@php
    $icon = ['error' => 'alert', 'warning' => 'clock', 'success' => 'check-circle', 'info' => 'info'][$type] ?? 'info';
@endphp

{{-- Message block of the auth card. Types: error, warning, success, info. --}}
<div {{ $attributes->class(['av-auth-message', 'av-auth-message--'.$type])->merge(['role' => in_array($type, ['error', 'warning']) ? 'alert' : 'status']) }}>
    <x-auth.icon :name="$icon" class="av-auth-message__icon" />
    <div class="av-auth-message__body">
        <p class="av-auth-message__title">{{ $title }}</p>
        @if ($text)
            <p class="av-auth-message__text">{{ $text }}</p>
        @endif
    </div>
</div>
