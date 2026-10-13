<?php

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\DesignSystem;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('redirects guests to the admin sign-in screen', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('shows the admin sign-in screen', function () {
    $this->get('/admin/login')->assertOk();
});

it('does not recognise a mobile user session in the admin panel', function () {
    $this->actingAs(User::factory()->make(['id' => 1]))
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

it('loads no resource from an external host on the sign-in screen', function () {
    $html = $this->get('/admin/login')->assertOk()->getContent();

    preg_match_all('#(?:src|href)=["\'](https?:)?//([^/"\']+)#i', $html, $matches);

    $externalHosts = array_values(array_diff(
        array_unique($matches[2]),
        [parse_url(config('app.url'), PHP_URL_HOST)],
    ));

    expect($externalHosts)->toBe([]);
});

it('builds avatars locally from initials', function () {
    $avatar = (new InitialsAvatarProvider)->get(new User(['name' => 'Christelle Mbarga']));

    expect($avatar)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($avatar, 26)))->toContain('>CM<');
});

it('keeps the design system reference page out of reach of guests', function () {
    $this->get('/admin/design-system')->assertRedirect('/admin/login');
});

it('only exposes the design system reference page in the local environment', function () {
    expect(DesignSystem::canAccess())->toBeFalse();

    $this->app->detectEnvironment(fn (): string => 'local');

    expect(DesignSystem::canAccess())->toBeTrue();
});
