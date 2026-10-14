<?php

use App\Domain\Admin\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Every GET route of the admin panel except the public sign-in screens.
 *
 * @return array<int, string>
 */
function protectedPanelUris(): array
{
    $public = ['filament.admin.auth.login', 'filament.admin.auth.password-reset.request', 'filament.admin.auth.password-reset.reset', 'filament.admin.auth.activation', 'filament.admin.auth.multi-factor-authentication.set-up-required'];

    return collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'filament.admin.') && in_array('GET', $route->methods(), true))
        ->reject(fn (Route $route): bool => in_array($route->getName(), $public, true))
        ->map(fn (Route $route): string => '/'.$route->uri())
        ->values()
        ->all();
}

it('finds the protected pages of the panel', function () {
    expect(protectedPanelUris())->toContain('/admin', '/admin/design-system');
});

it('keeps every protected page closed to guests', function () {
    foreach (protectedPanelUris() as $uri) {
        $this->get($uri)->assertRedirect('/admin/login');
    }
});

it('keeps every protected page closed to an administrator who has only given a password', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    foreach (protectedPanelUris() as $uri) {
        $this->get($uri)->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
});

it('keeps every protected page closed to a suspended administrator', function () {
    $admin = Admin::factory()->suspended()->create();
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($admin->fresh(), 'admin');

    foreach (protectedPanelUris() as $uri) {
        $this->get($uri)->assertForbidden();
    }
});

it('exposes no registration and no Filament profile or two-factor management page', function () {
    $names = collect(Router::getRoutes()->getRoutes())->map(fn (Route $route): ?string => $route->getName())->filter()->all();

    expect($names)->not->toContain('filament.admin.auth.register')
        ->not->toContain('filament.admin.auth.profile')
        ->and(Filament::getPanel('admin')->hasRegistration())->toBeFalse()
        ->and(Filament::getPanel('admin')->hasProfile())->toBeFalse()
        ->and(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
});
