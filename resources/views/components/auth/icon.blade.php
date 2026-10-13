@props(['name'])

{{-- Icon from the auth sprite (see x-auth.sprite). --}}
<svg {{ $attributes->class('av-i')->merge(['aria-hidden' => 'true']) }}><use href="#i-{{ $name }}"></use></svg>
