<?php

use App\Domain\Admin\Models\Admin;
use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Auth\Pages\ResetPassword;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function resetPage(Admin $admin, ?string $token = null)
{
    $token ??= Password::broker('admins')->createToken($admin);

    return Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token]);
}

describe('reset request', function () {
    it('shows the custom forgotten password screen', function () {
        $this->get('/admin/password-reset/request')
            ->assertOk()
            ->assertSee('Mot de passe oublié ?')
            ->assertSee('Envoyer le lien')
            ->assertSee('av-auth-shell', false)
            ->assertDontSee('fi-simple-layout', false);
    });

    it('answers the same way whether or not an account matches', function (string $email) {
        Admin::factory()->create(['email' => 'connu@example.com']);

        Livewire::test(RequestPasswordReset::class)
            ->set('email', $email)
            ->call('request')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSee('Si un compte correspond à cette adresse');
    })->with(['known' => ['connu@example.com'], 'unknown' => ['inconnu@example.com']]);

    it('only e-mails a link to an active administrator', function () {
        $active = Admin::factory()->create();
        $suspended = Admin::factory()->suspended()->create();
        $mobile = User::factory()->create();

        foreach ([$active->email, $suspended->email, $mobile->email, 'inconnu@example.com'] as $email) {
            cache()->flush(); // each case starts with a fresh rate limiter

            Livewire::test(RequestPasswordReset::class)->set('email', $email)->call('request')->assertSet('sent', true);
        }

        Notification::assertSentTo($active, ResetPasswordNotification::class);
        Notification::assertNotSentTo($suspended, ResetPasswordNotification::class);
        Notification::assertNotSentTo($mobile, ResetPasswordNotification::class);
        Notification::assertCount(1);
    });

    it('stores only a hash of the reset token', function () {
        $admin = Admin::factory()->create();
        $token = Password::broker('admins')->createToken($admin);

        $stored = DB::table('admin_password_reset_tokens')->where('email', $admin->email)->value('token');

        expect($stored)->not->toBe($token)
            ->and(Hash::check($token, $stored))->toBeTrue()
            ->and(DB::table('password_reset_tokens')->count())->toBe(0);
    });

    it('validates the address and limits repeated requests', function () {
        Livewire::test(RequestPasswordReset::class)->call('request')->assertHasErrors('email')->assertSee('Saisissez votre adresse e-mail.');

        $page = Livewire::test(RequestPasswordReset::class)->set('email', 'inconnu@example.com');
        $page->call('request')->call('request')->call('request')->assertHasErrors('throttle');
    });
});

describe('new password', function () {
    it('changes the password, signs other sessions out and keeps two-factor enrolment', function () {
        $admin = Admin::factory()->create(['password' => 'ancien-mot-de-passe']);
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $before = $admin->fresh();

        resetPage($admin)
            ->set('password', 'nouvelle phrase')
            ->set('passwordConfirmation', 'nouvelle phrase')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertSet('done', true)
            ->assertSee('Mot de passe modifié');

        $after = $admin->fresh();

        expect(Hash::check('nouvelle phrase', $after->password))->toBeTrue()
            ->and($after->password)->not->toBe($before->password)
            ->and($after->remember_token)->not->toBe($before->remember_token)
            ->and($after->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
            ->and(auth('admin')->check())->toBeFalse()
            ->and(DB::table('admin_password_reset_tokens')->where('email', $admin->email)->exists())->toBeFalse();
    });

    it('accepts 8 characters without any composition rule', function (string $password) {
        resetPage(Admin::factory()->create())
            ->set('password', $password)
            ->set('passwordConfirmation', $password)
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertSet('done', true);
    })->with(['lowercase only' => ['abcdefgh'], 'digits only' => ['12345678'], 'passphrase' => ['le soleil se couche a douala']]);

    it('refuses a password that is too short, too long or not confirmed', function (string $password, string $confirmation, string $field, string $message) {
        $admin = Admin::factory()->create(['password' => 'ancien-mot-de-passe']);

        resetPage($admin)
            ->set('password', $password)
            ->set('passwordConfirmation', $confirmation)
            ->call('resetPassword')
            ->assertHasErrors($field)
            ->assertSee($message)
            ->assertSet('done', false);

        expect(Hash::check('ancien-mot-de-passe', $admin->fresh()->password))->toBeTrue();
    })->with([
        'too short' => ['1234567', '1234567', 'password', 'au moins 8 caractères'],
        'over 72 bytes' => [str_repeat('a', 73), str_repeat('a', 73), 'password', 'trop long'],
        'over 72 bytes with accents' => [str_repeat('é', 37), str_repeat('é', 37), 'password', 'trop long'],
        'not confirmed' => ['abcdefgh', 'abcdefgi', 'passwordConfirmation', 'ne correspondent pas'],
    ]);

    it('refuses an invalid, reused or foreign link', function () {
        $admin = Admin::factory()->create(['password' => 'ancien-mot-de-passe']);

        resetPage($admin, 'jeton-invalide')
            ->set('password', 'nouvelle phrase')
            ->set('passwordConfirmation', 'nouvelle phrase')
            ->call('resetPassword')
            ->assertHasErrors('link')
            ->assertSee('Lien expiré ou déjà utilisé');

        $token = Password::broker('admins')->createToken($admin);
        resetPage($admin, $token)->set('password', 'nouvelle phrase')->set('passwordConfirmation', 'nouvelle phrase')->call('resetPassword')->assertSet('done', true);

        cache()->flush(); // the reused link must be refused for itself, not by the rate limiter

        resetPage($admin, $token)->set('password', 'autre phrase !')->set('passwordConfirmation', 'autre phrase !')->call('resetPassword')->assertHasErrors('link');

        expect(Hash::check('nouvelle phrase', $admin->fresh()->password))->toBeTrue();
    });

    it('refuses a link that is more than 60 minutes old', function () {
        $admin = Admin::factory()->create(['password' => 'ancien-mot-de-passe']);
        $token = Password::broker('admins')->createToken($admin);

        $this->travel(61)->minutes();

        resetPage($admin, $token)->set('password', 'nouvelle phrase')->set('passwordConfirmation', 'nouvelle phrase')->call('resetPassword')->assertHasErrors('link');

        expect(Hash::check('ancien-mot-de-passe', $admin->fresh()->password))->toBeTrue();
    });

    it('does not reset the password of a suspended administrator', function () {
        $admin = Admin::factory()->create(['password' => 'ancien-mot-de-passe']);
        $token = Password::broker('admins')->createToken($admin);
        $admin->forceFill(['status' => 'suspended'])->save();

        resetPage($admin, $token)->set('password', 'nouvelle phrase')->set('passwordConfirmation', 'nouvelle phrase')->call('resetPassword')->assertHasErrors('link');

        expect(Hash::check('ancien-mot-de-passe', $admin->fresh()->password))->toBeTrue();
    });

    it('requires a signed link to open the new password screen', function () {
        $this->get('/admin/password-reset/reset?email=a@example.com&token=abc')->assertForbidden();
    });
});
