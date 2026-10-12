@props([
    'tone' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $icon ??= [
        'danger' => 'triangle-alert',
        'warning' => 'triangle-alert',
        'success' => 'circle-check',
        'info' => 'info',
    ][$tone] ?? 'info';
@endphp

{{-- Banner. Tones: info, success, warning, danger. Critical tones are announced to screen readers. --}}
<div {{ $attributes->class(['av-alert', 'av-alert--'.$tone])->merge(['role' => in_array($tone, ['danger', 'warning']) ? 'alert' : 'status']) }}>
    <x-admin.icon :name="$icon" class="av-alert__icon" />

    <div class="av-alert__body">
        @if ($title)
            <span class="av-alert__title">{{ $title }}</span>
        @endif
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="av-alert__actions">{{ $actions }}</div>
    @endisset
</div>
