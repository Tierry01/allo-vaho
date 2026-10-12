@props([
    'name',
    'size' => null,
])

{{-- Lucide icon, sized like the mockups (18px, or "sm" 15px / "xs" 13px). --}}
{{ svg('lucide-'.$name, $attributes->class(['av-icon', 'av-icon--'.$size => $size])->merge(['aria-hidden' => 'true'])->getAttributes()) }}
