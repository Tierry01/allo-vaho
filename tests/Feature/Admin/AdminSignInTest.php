<?php

use App\Domain\Admin\Models\Admin;
use App\Filament\Auth\Pages\Login;
use App\Filament\Auth\Pages\SetUpTwoFactor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

uses(DatabaseTransactions::class);

const SIGN_IN_PASSWORD = 'phrase-de-passe';

beforeEach(function () {
    $this->withoutVite();
});

/**
 * An administrator who has completed the authenticator enrolment.
 */
function enrolledAdmin(array $attributes = []): Admin
{
    $admin = Admin::factory()->create(['password' => SIGN_IN_PASSWORD, ...$attributes]);
    $admin->saveAppAuthenticationSecret(app(Google2FA::class)->generateSecretKey(16));

    return $admin->fresh();
}

/**
 * The code an authenticator app shows now, or a number of 30-second steps away.
 */
function totpCode(Admin|string $secret, int $stepOffset = 0): string
{
    $secret = $secret instanceof Admin ? $secret->getAppAuthenticationSecret() : $secret;
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $stepOffset);
}

function submitCredentials(string $email, string $password = SIGN_IN_PASSWORD)
{
    return Livewire::test(Login::class)
        ->set('data.email', $email)
        ->set('data.password', $password)
        ->call('authenticate');
}

describe('sign-in screen', function () {
    it('shows the custom sign-in screen instead of the Filament one', function () {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Ravi de vous revoir')
            ->assertSee('Étape 1 sur 2 · Identifiants')
            ->assertSee('Tout Allo Vaho,')
            ->assertSee('Se souvenir de moi')
            ->assertSee('av-auth-shell', false)
            ->assertDontSee('fi-simple-layout', false)
            ->assertDontSee('Démo');
    });

    it('hides the support, privacy and version links when they are not configured', function () {
        config(['admin.support_email' => null, 'admin.privacy_url' => null, 'admin.version' => null]);

        $this->get('/admin/login')
            ->assertDontSee('Contacter le support')
            ->assertDontSee('Confidentialité')
            ->assertDontSee('mailto:', false);
    });

    it('shows the configured support, privacy and version values', function () {
        config(['admin.support_email' => 'support@example.test', 'admin.privacy_url' => 'https://example.test/confidentialite', 'admin.version' => 'v9.9.9']);

        $this->get('/admin/login')
            ->assertSee('mailto:support@example.test', false)
            ->assertSee('https://example.test/confidentialite', false)
            ->assertSee('v9.9.9');
    });

    it('explains missing or malformed credentials in French', function () {
        Livewire::test(Login::class)
            ->call('authenticate')
            ->assertHasErrors(['data.email', 'data.password'])
            ->assertSee('Saisissez votre adresse e-mail.')
            ->assertSee('Saisissez votre mot de passe.');

        Livewire::test(Login::class)
            ->set('data.email', 'pas-une-adresse')
            ->set('data.password', SIGN_IN_PASSWORD)
            ->call('authenticate')
            ->assertSee('Cette adresse e-mail n’est pas valide.');
    });
});

describe('credentials', function () {
    it('gives the same answer for a wrong password, an unknown address and a suspended account', function () {
        $admin = enrolledAdmin();
        $suspended = enrolledAdmin(['status' => 'suspended']);

        foreach ([
            [$admin->email, 'mauvais-mot-de-passe'],
            ['inconnu@example.com', SIGN_IN_PASSWORD],
            [$suspended->email, SIGN_IN_PASSWORD],
        ] as [$email, $password]) {
            submitCredentials($email, $password)
                ->assertHasErrors('credentials')
                ->assertSee('Les informations saisies sont incorrectes.')
                ->assertDontSee('Étape 2 sur 2');
        }

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    it('never opens a session with the password alone', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee('Étape 2 sur 2 · Vérification')
            ->assertSee('Entrez le code à 6 chiffres affiché par votre application d\'authentification.', false)
            ->assertSee('Contactez un Super Admin.')
            ->assertDontSee('Renvoyer')
            ->assertDontSee('Modifier le mode de vérification');

        expect(Auth::guard('admin')->check())->toBeFalse();

        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('limits repeated sign-in attempts without locking the account', function () {
        $admin = enrolledAdmin();
        $page = Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', 'mauvais-mot-de-passe');

        foreach (range(1, 5) as $attempt) {
            $page->call('authenticate')->assertHasErrors('credentials');
        }

        $page->call('authenticate')->assertHasErrors('throttle')->assertSee('Trop de tentatives.');

        expect($admin->fresh()->isActive())->toBeTrue();
    });
});

describe('verification code', function () {
    it('signs the administrator in once the current code is verified', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->set('data.multiFactor.app.code', totpCode($admin))
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(Filament::getUrl());

        expect(Auth::guard('admin')->id())->toBe($admin->id);
    });

    it('refuses a wrong, incomplete or missing code', function (string $code, string $message) {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->set('data.multiFactor.app.code', $code)
            ->call('authenticate')
            ->assertHasErrors('data.multiFactor.app.code')
            ->assertSee($message)
            ->assertNoRedirect();

        expect(Auth::guard('admin')->check())->toBeFalse();
    })->with([
        'wrong' => ['000000', 'Ce code est incorrect.'],
        'incomplete' => ['123', 'Entrez les 6 chiffres du code.'],
        'missing' => ['', 'Entrez les 6 chiffres du code.'],
    ]);

    it('accepts a code within one step and refuses an older one', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->set('data.multiFactor.app.code', totpCode($admin, -2))
            ->call('authenticate')
            ->assertHasErrors('data.multiFactor.app.code');

        expect(Auth::guard('admin')->check())->toBeFalse();

        submitCredentials($admin->email)
            ->set('data.multiFactor.app.code', totpCode($admin, -1))
            ->call('authenticate')
            ->assertHasNoErrors();

        expect(Auth::guard('admin')->check())->toBeTrue();
    });

    it('refuses a code that has already been used', function () {
        $admin = enrolledAdmin();
        $code = totpCode($admin);

        submitCredentials($admin->email)->set('data.multiFactor.app.code', $code)->call('authenticate')->assertHasNoErrors();

        Auth::guard('admin')->logout();

        submitCredentials($admin->email)
            ->set('data.multiFactor.app.code', $code)
            ->call('authenticate')
            ->assertHasErrors('data.multiFactor.app.code');

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    it('limits repeated code attempts', function () {
        $admin = enrolledAdmin();
        $page = submitCredentials($admin->email);

        foreach (range(1, 4) as $attempt) {
            $page->set('data.multiFactor.app.code', '000000')->call('authenticate')->assertHasErrors('data.multiFactor.app.code');
        }

        $page->set('data.multiFactor.app.code', totpCode($admin))->call('authenticate')->assertHasErrors('throttle');

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    it('goes back to the credentials without keeping the password', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->call('backToCredentials')
            ->assertSee('Étape 1 sur 2 · Identifiants')
            ->assertSet('data.password', '');
    });
});

describe('mandatory enrolment', function () {
    it('keeps every protected page out of reach until enrolment is complete', function () {
        $admin = Admin::factory()->create(['password' => SIGN_IN_PASSWORD]);

        submitCredentials($admin->email)->assertHasNoErrors();

        $setUpUrl = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();

        $this->actingAs($admin, 'admin');

        foreach (['/admin', '/admin/design-system'] as $url) {
            $this->get($url)->assertRedirect($setUpUrl);
        }
    });

    it('shows the enrolment screen with a QR code, a setup key and three instructions', function () {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(Filament::getSetUpRequiredMultiFactorAuthenticationUrl())
            ->assertOk()
            ->assertSee('Étape 2 sur 2 · Configuration')
            ->assertSee('Google Authenticator')
            ->assertSee('Clé de configuration')
            ->assertSee('Activer la double authentification')
            ->assertSee('data:image/svg+xml', false)
            ->assertSee('av-auth-shell', false);
    });

    it('requires a guest to sign in before enrolling', function () {
        $this->get(Filament::getSetUpRequiredMultiFactorAuthenticationUrl())->assertRedirect('/admin/login');
    });

    it('does not store the secret until a first code is verified', function (string $code) {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(SetUpTwoFactor::class)
            ->set('code', $code)
            ->call('activate')
            ->assertHasErrors('code')
            ->assertSet('activated', false);

        expect($admin->fresh()->getAppAuthenticationSecret())->toBeNull();

        $this->get('/admin')->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    })->with(['wrong' => ['000000'], 'incomplete' => ['12']]);

    it('activates two-factor authentication with a valid first code and opens the panel', function () {
        $admin = Admin::factory()->create();

        $page = Livewire::actingAs($admin, 'admin')->test(SetUpTwoFactor::class);
        $secret = decrypt($page->get('pendingSecret'));

        $page->set('code', totpCode($secret))
            ->call('activate')
            ->assertHasNoErrors()
            ->assertSet('activated', true)
            ->assertSet('pendingSecret', '')
            ->assertSee('Double authentification activée');

        $stored = DB::table('admins')->where('id', $admin->id)->value('app_authentication_secret');

        expect($admin->fresh()->getAppAuthenticationSecret())->toBe($secret)
            ->and($stored)->not->toContain($secret);

        $this->actingAs($admin->fresh(), 'admin')->get('/admin')->assertOk();
    });

    it('does not show the setup key again once enrolment is done', function () {
        $admin = enrolledAdmin();

        $this->actingAs($admin, 'admin')
            ->get(Filament::getSetUpRequiredMultiFactorAuthenticationUrl())
            ->assertRedirect()
            ->assertDontSee($admin->getAppAuthenticationSecret());
    });

    it('limits enrolment attempts', function () {
        $page = Livewire::actingAs(Admin::factory()->create(), 'admin')->test(SetUpTwoFactor::class);

        foreach (range(1, 5) as $attempt) {
            $page->set('code', '000000')->call('activate')->assertHasErrors('code');
        }

        $page->set('code', '000000')->call('activate')->assertHasErrors('throttle');
    });
});

describe('sign-out and revoked access', function () {
    it('signs the administrator out', function () {
        $this->actingAs(enrolledAdmin(), 'admin')
            ->post('/admin/logout')
            ->assertRedirect();

        expect(Auth::guard('admin')->check())->toBeFalse();

        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('refuses an administrator suspended or deleted during a session', function () {
        $suspended = enrolledAdmin();
        $this->actingAs($suspended, 'admin')->get('/admin')->assertOk();

        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($suspended->fresh(), 'admin')->get('/admin')->assertForbidden();

        $deleted = enrolledAdmin();
        $deleted->delete();

        expect(Auth::guard('admin')->getProvider()->retrieveById($deleted->id))->toBeNull();
    });
});

describe('secrets', function () {
    it('writes no password, code or secret to the application log', function () {
        $log = storage_path('framework/testing/auth-'.uniqid().'.log');
        config(['logging.default' => 'single', 'logging.channels.single.path' => $log, 'logging.channels.single.level' => 'debug']);

        $admin = enrolledAdmin();
        $secret = $admin->getAppAuthenticationSecret();
        $code = totpCode($admin);

        submitCredentials($admin->email, 'mauvais-mot-de-passe');
        submitCredentials($admin->email)->set('data.multiFactor.app.code', '000000')->call('authenticate');
        submitCredentials($admin->email)->set('data.multiFactor.app.code', $code)->call('authenticate')->assertHasNoErrors();

        $pending = Livewire::actingAs(Admin::factory()->create(), 'admin')->test(SetUpTwoFactor::class);
        $newSecret = decrypt($pending->get('pendingSecret'));
        $pending->set('code', totpCode($newSecret))->call('activate');

        logger()->info('fin du parcours');

        $content = file_get_contents($log);
        @unlink($log);

        expect($content)->toContain('fin du parcours')
            ->not->toContain(SIGN_IN_PASSWORD)
            ->not->toContain('mauvais-mot-de-passe')
            ->not->toContain($secret)
            ->not->toContain($newSecret)
            ->not->toContain($code);
    });
});

describe('remember me and session', function () {
    it('offers a remember option that is not wired to the authentication form', function () {
        $this->get('/admin/login')
            ->assertSee('Se souvenir de moi')
            ->assertSee('data-remember-email', false)
            ->assertDontSee('data.remember', false);
    });

    it('never creates a persistent sign-in, even when the browser asks for one', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)
            ->set('data.remember', true)
            ->set('data.multiFactor.app.code', totpCode($admin))
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(Filament::getUrl());

        $recallerCookies = collect(Cookie::getQueuedCookies())
            ->filter(fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_'));

        expect(Auth::guard('admin')->check())->toBeTrue()
            ->and(Auth::guard('admin')->viaRemember())->toBeFalse()
            ->and($admin->fresh()->remember_token)->toBeNull()
            ->and($recallerCookies)->toBeEmpty();
    });

    it('still asks for the code on the next sign-in', function () {
        $admin = enrolledAdmin();

        submitCredentials($admin->email)->set('data.remember', true)->set('data.multiFactor.app.code', totpCode($admin))->call('authenticate');

        Auth::guard('admin')->logout();

        submitCredentials($admin->email)->set('data.remember', true)->assertSee('Étape 2 sur 2 · Vérification');

        expect(Auth::guard('admin')->check())->toBeFalse();
    });

    it('starts a new session identifier when the administrator signs in', function () {
        $admin = enrolledAdmin();
        $page = submitCredentials($admin->email);
        $before = session()->getId();

        $page->set('data.multiFactor.app.code', totpCode($admin))->call('authenticate')->assertHasNoErrors();

        expect(session()->getId())->not->toBe($before);
    });

    it('expires sessions after 120 minutes of inactivity', function () {
        expect(config('session.lifetime'))->toBe(120)
            ->and(config('session.expire_on_close'))->toBeFalse();
    });
});
