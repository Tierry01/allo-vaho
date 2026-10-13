@props([
    'model',
    'submit' => null,
    'labelledby',
    'describedby' => null,
    'invalid' => false,
])

{{-- Six-box verification code, as in the mockups. Writes the code to a Livewire property. --}}
<div
    {{ $attributes->class(['av-otp', 'is-error' => $invalid]) }}
    role="group"
    aria-labelledby="{{ $labelledby }}"
    @if ($describedby) aria-describedby="{{ $describedby }}" @endif
    x-data="avOtp({
        onChange: (code) => $wire.set('{{ $model }}', code, false),
        onComplete: (code) => { @if ($submit) $wire.set('{{ $model }}', code, false).then(() => $wire.{{ $submit }}()) @endif },
    })"
>
    @foreach (range(1, 6) as $position)
        @if ($position === 4)
            <span class="av-otp__sep" aria-hidden="true"></span>
        @endif
        <input
            class="av-otp__box"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="{{ $position === 1 ? 'one-time-code' : 'off' }}"
            aria-label="Chiffre {{ $position }} sur 6"
            aria-invalid="{{ $invalid ? 'true' : 'false' }}"
        >
    @endforeach
</div>
