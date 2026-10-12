@props([
    'kind' => 'empty',
    'title',
    'text' => null,
    'icon' => null,
    'code' => null,
])

@php
    $icon ??= ['empty' => 'inbox', 'error' => 'cloud-off', 'restricted' => 'lock', 'offline' => 'wifi-off'][$kind] ?? 'inbox';
    $isError = in_array($kind, ['error', 'offline']);
@endphp

{{-- Feedback block for a zone with no content. Kinds: empty, error, restricted, offline. --}}
<div {{ $attributes->class([
    'av-state',
    'av-state--error' => $isError,
    'av-state--restricted' => $kind === 'restricted',
])->merge($isError ? ['role' => 'alert'] : []) }}>
    <span class="av-state__icon"><x-admin.icon :name="$icon" /></span>

    <p class="av-state__title">{{ $title }}</p>

    @if ($text)
        <p class="av-state__text">{{ $text }}</p>
    @endif

    @if ($code)
        <span class="av-state__code">{{ $code }}</span>
    @endif

    @isset($actions)
        <div class="av-state__actions">{{ $actions }}</div>
    @endisset
</div>
