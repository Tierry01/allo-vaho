@props([
    'width' => '100%',
    'height' => 12,
])

{{-- Loading placeholder line. --}}
<span {{ $attributes->class('av-skeleton')->merge(['aria-hidden' => 'true']) }} style="width: {{ $width }}; height: {{ $height }}px"></span>
