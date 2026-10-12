@props([
    'tone' => 'neutral',
    'dot' => true,
    'outline' => false,
    'large' => false,
])

{{-- Status badge. Tones: neutral, success, warning, danger, info. --}}
<span {{ $attributes->class([
    'av-badge',
    'av-badge--'.$tone => $tone !== 'neutral',
    'av-badge--outline' => $outline,
    'av-badge--lg' => $large,
]) }}>
    @if ($dot)
        <span class="av-badge__dot"></span>
    @endif
    {{ $slot }}
</span>
