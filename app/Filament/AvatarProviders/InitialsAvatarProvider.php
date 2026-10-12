<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds avatars locally from the initials of a name, as an inline SVG, so the
 * back-office never calls an external avatar service.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = Str::of(Filament::getNameForDefaultAvatar($record))
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#D2ECF8"/>'
            .'<text x="32" y="33" text-anchor="middle" dominant-baseline="middle" '
            .'font-family="Noto Sans, system-ui, sans-serif" font-size="24" font-weight="600" fill="#005A8C">'
            .e($initials)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
