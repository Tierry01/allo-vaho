<?php

use App\Domain\Admin\Actions\AcceptAdminInvitation;
use App\Domain\Admin\Actions\InviteAdmin;
use App\Domain\Admin\Actions\ResetAdminTwoFactor;
use App\Domain\Admin\Actions\RevokeAdminInvitation;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Exceptions\LastSuperAdminProtected;
use App\Domain\Admin\Exceptions\TwoFactorResetRefused;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Notifications\TwoFactorResetNotification;
use App\Domain\Admin\Support\AuthAudit;
use App\Filament\Auth\Pages\Login;
use App\Filament\Auth\Pages\RequestPasswordReset;
use App\Filament\Auth\Pages\ResetPassword;
use App\Filament\Auth\Pages\SetUpTwoFactor;
use App\Models\User;
use App\Support\SessionCookie;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

uses(DatabaseTransactions::class);

const SECURITY_PASSWORD = 'phrase-de-passe-securite';

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function securityAdmin(array $attributes = [], ?AdminRole $role = null, bool $enrolled = true): Admin
{
    $admin = Admin::factory()->create(['password' => SECURITY_PASSWORD, ...$attributes]);

    if ($role) {
        $admin->assignRole($role->value);
    }

    if ($enrolled) {
        $admin->saveAppAuthenticationSecret(app(Google2FA::class)->generateSecretKey(16));
    }

    return $admin->fresh();
}

function securityCode(Admin $admin, int $stepOffset = 0): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($admin->getAppAuthenticationSecret(), $google2fa->getTimestamp() + $stepOffset);
}

/**
 * @return array<int, string>
 */
function auditEvents(): array
{
    return Activity::query()->where('log_name', AuthAudit::LOG_NAME)->orderBy('id')->pluck('event')->all();
}

function auditDump(): string
{
    return Activity::query()->get()->map(fn (Activity $activity): string => json_encode([$activity->description, $activity->properties]))->implode("\n");
}

describe('audit trail', function () {
    it('records successful and failed sign-ins without any credential', function () {
        $admin = securityAdmin();
        $suspended = securityAdmin(['status' => AdminStatus::Suspended]);

        Livewire::test(Login::class)->set('data.email', 'inconnu@example.com')->set('data.password', 'mot-de-passe-faux')->call('authenticate');
        Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', 'mot-de-passe-faux')->call('authenticate');
        cache()->flush(); // the sign-in rate limit is not what this test is about
        Livewire::test(Login::class)->set('data.email', $suspended->email)->set('data.password', SECURITY_PASSWORD)->call('authenticate');
        Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', SECURITY_PASSWORD)
            ->call('authenticate')->set('data.multiFactor.app.code', '000000')->call('authenticate')
            ->set('data.multiFactor.app.code', securityCode($admin))->call('authenticate')->assertHasNoErrors();

        $reasons = Activity::query()->where('event', 'auth.login_failed')->orderBy('id')->get()->map(fn (Activity $a) => $a->properties['reason'])->all();

        expect(auditEvents())->toContain('auth.login_failed', 'auth.two_factor_failed', 'auth.login')
            ->and($reasons)->toBe(['unknown_account', 'invalid_credentials', 'inactive_account'])
            ->and(Activity::query()->where('event', 'auth.login')->first()->causer_id)->toBe($admin->id)
            ->and(Activity::query()->where('event', 'auth.login_failed')->first()->properties['email_fingerprint'])->toHaveLength(64)
            ->and($admin->fresh()->last_login_at)->not->toBeNull()
            ->and(auditDump())->not->toContain(SECURITY_PASSWORD)->not->toContain('mot-de-passe-faux')->not->toContain('inconnu@example.com')
            ->not->toContain($admin->getAppAuthenticationSecret())->not->toContain('000000');
    });

    it('records the request context of a sign-in', function () {
        $admin = securityAdmin();

        Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', 'mot-de-passe-faux')->call('authenticate');

        $properties = Activity::query()->where('event', 'auth.login_failed')->firstOrFail()->properties;

        expect($properties['channel'])->toBe('web')->and($properties)->toHaveKeys(['ip', 'user_agent']);
    });

    it('records sign-out', function () {
        $admin = securityAdmin();

        $this->actingAs($admin, 'admin')->post('/admin/logout');

        expect(auditEvents())->toContain('auth.logout');
    });

    it('records the two-factor enrolment without the secret', function () {
        $admin = securityAdmin(enrolled: false);
        $page = Livewire::actingAs($admin, 'admin')->test(SetUpTwoFactor::class);
        $secret = decrypt($page->get('pendingSecret'));
        $google2fa = app(Google2FA::class);

        $page->set('code', $google2fa->oathTotp($secret, $google2fa->getTimestamp()))->call('activate')->assertHasNoErrors();

        expect(auditEvents())->toContain('auth.two_factor_enrolled')
            ->and(auditDump())->not->toContain($secret);
    });

    it('records password reset requests and resets without the link', function () {
        $admin = securityAdmin();

        Livewire::test(RequestPasswordReset::class)->set('email', $admin->email)->call('request');

        $token = Password::broker('admins')->createToken($admin);
        Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
            ->set('password', 'nouvelle phrase')->set('passwordConfirmation', 'nouvelle phrase')->call('resetPassword')->assertSet('done', true);

        expect(auditEvents())->toContain('auth.password_reset_requested', 'auth.password_reset')
            ->and(auditDump())->not->toContain($token)->not->toContain('nouvelle phrase')->not->toContain('password-reset/reset');
    });

    it('records invitations issued, revoked and accepted without the token or the link', function () {
        $super = securityAdmin(role: AdminRole::SuperAdmin);

        ['invitation' => $first, 'url' => $firstUrl] = app(InviteAdmin::class)->handle('invite@example.com', AdminRole::Support, $super);
        app(RevokeAdminInvitation::class)->handle($first, $super);
        ['url' => $url] = app(InviteAdmin::class)->handle('invite@example.com', AdminRole::Support, $super);
        $token = Str::afterLast($url, '/');
        app(AcceptAdminInvitation::class)->handle($token, 'Jean Dupont', SECURITY_PASSWORD);

        $issued = Activity::query()->where('event', 'invitation.issued')->firstOrFail();

        expect(auditEvents())->toBe(['invitation.issued', 'invitation.revoked', 'invitation.issued', 'invitation.accepted'])
            ->and($issued->causer_id)->toBe($super->id)
            ->and($issued->properties['role'])->toBe('support')
            ->and(auditDump())->not->toContain($token)->not->toContain(Str::afterLast($firstUrl, '/'))->not->toContain('/admin/activation/')->not->toContain(SECURITY_PASSWORD);
    });

    it('ignores the sign-in events of mobile users', function () {
        $user = User::factory()->create(['password' => SECURITY_PASSWORD]);

        Auth::guard('web')->attempt(['email' => $user->email, 'password' => SECURITY_PASSWORD]);
        Auth::guard('web')->attempt(['email' => $user->email, 'password' => 'mot-de-passe-faux']);

        expect(auditEvents())->toBe([]);
    });

    it('stores the activity log in timezone-aware columns with JSONB properties', function () {
        $types = collect(DB::select("select column_name, data_type from information_schema.columns where table_name = 'activity_log'"))->pluck('data_type', 'column_name');

        expect($types['created_at'])->toBe('timestamp with time zone')->and($types['properties'])->toBe('jsonb');
    });
});

describe('session cookie', function () {
    it('is HTTPS-only by default outside local development and tests', function (?string $environment, bool $secure) {
        expect(SessionCookie::secureByDefault($environment))->toBe($secure);
    })->with([
        'production' => ['production', true],
        'recette' => ['staging', true],
        'unknown environment' => [null, true],
        'local' => ['local', false],
        'testing' => ['testing', false],
    ]);

    it('is HTTP-only, same-site and expires after 120 minutes of inactivity', function () {
        expect(config('session.http_only'))->toBeTrue()
            ->and(config('session.same_site'))->toBe('lax')
            ->and(config('session.lifetime'))->toBe(120)
            ->and(config('session.expire_on_close'))->toBeFalse();
    });
});

describe('last super admin', function () {
    it('cannot be suspended or deleted', function () {
        $only = securityAdmin(role: AdminRole::SuperAdmin);

        expect(fn () => $only->forceFill(['status' => AdminStatus::Suspended])->save())->toThrow(LastSuperAdminProtected::class);
        expect(fn () => $only->delete())->toThrow(LastSuperAdminProtected::class);
        expect($only->fresh()->isActive())->toBeTrue();
    });

    it('can be suspended when another active super admin remains', function () {
        $first = securityAdmin(role: AdminRole::SuperAdmin);
        securityAdmin(role: AdminRole::SuperAdmin);

        $first->forceFill(['status' => AdminStatus::Suspended])->save();

        expect($first->fresh()->status)->toBe(AdminStatus::Suspended);
    });
});

describe('two-factor reset by a super admin', function () {
    it('resets the target, closes their sessions and notifies them', function () {
        $super = securityAdmin(role: AdminRole::SuperAdmin);
        $target = securityAdmin(role: AdminRole::Support);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'session-cible', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $oldToken = $target->remember_token;

        app(ResetAdminTwoFactor::class)->handle($target, $super, SECURITY_PASSWORD, securityCode($super), 'Téléphone perdu, demande vérifiée par appel');

        $target->refresh();
        $activity = Activity::query()->where('event', 'auth.two_factor_reset')->firstOrFail();

        expect($target->getAppAuthenticationSecret())->toBeNull()
            ->and($target->remember_token)->not->toBe($oldToken)
            ->and(DB::table('sessions')->where('id', 'session-cible')->exists())->toBeFalse()
            ->and($activity->causer_id)->toBe($super->id)
            ->and($activity->subject_id)->toBe($target->id)
            ->and($activity->properties['reason'])->toBe('Téléphone perdu, demande vérifiée par appel');

        Notification::assertSentTo($target, TwoFactorResetNotification::class);

        $this->actingAs($target, 'admin')->get('/admin')->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    });

    it('refuses without the required authorisation, confirmation and reason', function (Closure $scenario) {
        $super = securityAdmin(role: AdminRole::SuperAdmin);
        $target = securityAdmin(role: AdminRole::Support);
        [$actor, $subject, $password, $code, $reason] = $scenario($super, $target);

        expect(fn () => app(ResetAdminTwoFactor::class)->handle($subject, $actor, $password, $code, $reason))->toThrow(TwoFactorResetRefused::class);
        expect($target->fresh()->getAppAuthenticationSecret())->not->toBeNull()
            ->and(auditEvents())->not->toContain('auth.two_factor_reset');
    })->with([
        'not a super admin' => [fn ($super, $target) => [$s = securityAdmin(role: AdminRole::Finance), $target, SECURITY_PASSWORD, securityCode($s), 'Motif suffisamment long']],
        'own account' => [fn ($super) => [$super, $super, SECURITY_PASSWORD, securityCode($super), 'Motif suffisamment long']],
        'wrong password' => [fn ($super, $target) => [$super, $target, 'mauvais', securityCode($super), 'Motif suffisamment long']],
        'wrong code' => [fn ($super, $target) => [$super, $target, SECURITY_PASSWORD, '000000', 'Motif suffisamment long']],
        'reason too short' => [fn ($super, $target) => [$super, $target, SECURITY_PASSWORD, securityCode($super), 'court']],
        'suspended super admin' => [fn ($super, $target) => [tap(securityAdmin(role: AdminRole::SuperAdmin), fn ($s) => $s->forceFill(['status' => AdminStatus::Suspended])->save()), $target, SECURITY_PASSWORD, securityCode($super), 'Motif suffisamment long']],
    ]);
});

describe('admin:invite command', function () {
    it('invites an administrator and shows the link once, without any password', function () {
        $this->artisan('admin:invite', ['email' => 'Marketing@Example.com', 'role' => 'marketing'])
            ->expectsOutputToContain('Invitation créée pour marketing@example.com (rôle Marketing).')
            ->expectsOutputToContain('/admin/activation/')
            ->assertSuccessful();

        $invitation = AdminInvitation::firstOrFail();

        expect($invitation->role)->toBe(AdminRole::Marketing)
            ->and($invitation->isUsable())->toBeTrue()
            ->and(Admin::count())->toBe(0)
            ->and(auditEvents())->toBe(['invitation.issued'])
            ->and(Activity::query()->firstOrFail()->properties['channel'])->toBe('console')
            ->and(auditDump())->not->toContain('/admin/activation/');
    });

    it('revokes the previous invitation for the same address', function () {
        $this->artisan('admin:invite', ['email' => 'finance@example.com', 'role' => 'finance'])->assertSuccessful();
        $this->artisan('admin:invite', ['email' => 'finance@example.com', 'role' => 'finance'])
            ->expectsOutputToContain('L’invitation précédente pour cette adresse a été révoquée.')
            ->assertSuccessful();

        expect(AdminInvitation::count())->toBe(2)
            ->and(AdminInvitation::usable()->count())->toBe(1)
            ->and(auditEvents())->toBe(['invitation.issued', 'invitation.revoked', 'invitation.issued']);
    });

    it('rejects an invalid address or an unknown role', function (string $email, string $role) {
        $this->artisan('admin:invite', ['email' => $email, 'role' => $role])->assertExitCode(2);

        expect(AdminInvitation::count())->toBe(0);
    })->with([
        'invalid address' => ['pas-une-adresse', 'support'],
        'unknown role' => ['ok@example.com', 'administrateur'],
        'role with another case' => ['ok@example.com', 'Support'],
    ]);

    it('refuses an address that already has an account', function () {
        securityAdmin(['email' => 'existe@example.com']);

        $this->artisan('admin:invite', ['email' => 'existe@example.com', 'role' => 'support'])->assertFailed();

        expect(AdminInvitation::count())->toBe(0);
    });
});

describe('admin:recover-super-admin command', function () {
    it('resets the two-factor authentication of the last super admin, with operator, reason and audit', function () {
        $super = securityAdmin(role: AdminRole::SuperAdmin);
        $password = $super->password;

        $this->artisan('admin:recover-super-admin', ['email' => $super->email, '--operator' => 'Responsable technique', '--reason' => 'Téléphone du seul Super Admin perdu'])
            ->expectsQuestion('Pour confirmer, saisissez l’adresse e-mail du compte', $super->email)
            ->expectsOutputToContain('Récupération effectuée et journalisée.')
            ->assertSuccessful();

        $super->refresh();
        $activity = Activity::query()->where('event', 'auth.super_admin_recovered')->firstOrFail();

        expect($super->getAppAuthenticationSecret())->toBeNull()
            ->and($super->password)->toBe($password)
            ->and($super->isActive())->toBeTrue()
            ->and($activity->properties['operator'])->toBe('Responsable technique')
            ->and($activity->properties['reason'])->toBe('Téléphone du seul Super Admin perdu')
            ->and($activity->properties['channel'])->toBe('console')
            ->and($activity->properties['password_reset_issued'])->toBeFalse();

        Notification::assertSentTo($super, TwoFactorResetNotification::class);

        // Two-factor authentication stays mandatory: the account must enrol again.
        $this->actingAs($super, 'admin')->get('/admin')->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        expect(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
    });

    it('can also issue a password reset link, shown once and not logged', function () {
        $super = securityAdmin(role: AdminRole::SuperAdmin);

        $this->artisan('admin:recover-super-admin', ['email' => $super->email, '--operator' => 'Responsable technique', '--reason' => 'Accès entièrement perdu', '--reset-password' => true])
            ->expectsQuestion('Pour confirmer, saisissez l’adresse e-mail du compte', $super->email)
            ->expectsOutputToContain('/admin/password-reset/reset')
            ->assertSuccessful();

        expect(Activity::query()->where('event', 'auth.super_admin_recovered')->firstOrFail()->properties['password_reset_issued'])->toBeTrue()
            ->and(auditDump())->not->toContain('password-reset/reset');
    });

    it('refuses when another active super admin can reset the account', function () {
        $super = securityAdmin(role: AdminRole::SuperAdmin);
        securityAdmin(role: AdminRole::SuperAdmin);

        $this->artisan('admin:recover-super-admin', ['email' => $super->email, '--operator' => 'Responsable technique', '--reason' => 'Motif suffisamment long'])
            ->expectsOutputToContain('Un autre Super Admin actif peut réinitialiser')
            ->assertFailed();

        expect($super->fresh()->getAppAuthenticationSecret())->not->toBeNull();
    });

    it('requires an operator, a reason, a super admin account and an exact confirmation', function (Closure $arguments, ?string $answer, int $exitCode) {
        $super = securityAdmin(role: AdminRole::SuperAdmin);
        $support = securityAdmin(role: AdminRole::Support);
        $command = $this->artisan('admin:recover-super-admin', $arguments($super, $support));

        if ($answer !== null) {
            $command->expectsQuestion('Pour confirmer, saisissez l’adresse e-mail du compte', $answer);
        }

        $command->assertExitCode($exitCode)->run();

        expect($super->fresh()->getAppAuthenticationSecret())->not->toBeNull()
            ->and($support->fresh()->getAppAuthenticationSecret())->not->toBeNull()
            ->and(auditEvents())->not->toContain('auth.super_admin_recovered');
    })->with([
        'no operator' => [fn ($super) => ['email' => $super->email, '--reason' => 'Motif suffisamment long'], null, 2],
        'reason too short' => [fn ($super) => ['email' => $super->email, '--operator' => 'X', '--reason' => 'court'], null, 2],
        'not a super admin' => [fn ($super, $support) => ['email' => $support->email, '--operator' => 'X', '--reason' => 'Motif suffisamment long'], null, 1],
        'unknown address' => [fn () => ['email' => 'inconnu@example.com', '--operator' => 'X', '--reason' => 'Motif suffisamment long'], null, 1],
        'wrong confirmation' => [fn ($super) => ['email' => $super->email, '--operator' => 'X', '--reason' => 'Motif suffisamment long'], 'autre@example.com', 1],
    ]);

    it('does not reactivate a suspended super admin', function () {
        $suspended = securityAdmin(role: AdminRole::SuperAdmin);
        securityAdmin(role: AdminRole::SuperAdmin, enrolled: false);
        $suspended->forceFill(['status' => AdminStatus::Suspended])->save();

        $this->artisan('admin:recover-super-admin', ['email' => $suspended->email, '--operator' => 'X', '--reason' => 'Motif suffisamment long'])->assertFailed();

        expect($suspended->fresh()->status)->toBe(AdminStatus::Suspended);
    });
});
