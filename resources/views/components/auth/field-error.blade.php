@props(['id', 'message' => null])

{{-- Error line under a field, as in the mockups. --}}
<p class="av-auth-error" id="{{ $id }}" @if (blank($message)) hidden @endif>
    <x-auth.icon name="alert" /><span>{{ $message }}</span>
</p>
