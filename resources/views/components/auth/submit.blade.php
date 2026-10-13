@props([
    'target',
    'label',
    'loadingLabel',
    'arrow' => false,
])

{{-- Primary action with its loading state: fixed width, explicit loading label, as in the mockups. --}}
<button
    type="submit"
    {{ $attributes->class('av-auth-btn av-auth-btn--primary') }}
    wire:loading.class="is-loading"
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
>
    <svg class="av-auth-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true" wire:loading wire:target="{{ $target }}"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
    <span wire:loading.remove wire:target="{{ $target }}">{{ $label }}</span>
    <span wire:loading wire:target="{{ $target }}">{{ $loadingLabel }}</span>
    @if ($arrow)
        <x-auth.icon name="arrow-right" class="av-auth-btn__arrow" />
    @endif
</button>
