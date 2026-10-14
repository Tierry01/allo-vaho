<?php

use App\Domain\Admin\Actions\AcceptAdminInvitation;
use App\Domain\Admin\Actions\InviteAdmin;
use App\Domain\Admin\Actions\RevokeAdminInvitation;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\InvitationState;
use App\Domain\Admin\Events\AdminInvitationAccepted;
use App\Domain\Admin\Events\AdminInvitationRevoked;
use App\Domain\Admin\Events\AdminInvited;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Exceptions\InvitationNotUsable;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Notifications\AdminInvitationNotification;
use App\Domain\Admin\Notifications\AdminInvitationRenewalRequested;
use App\Filament\Auth\Pages\AcceptInvitation;
use App\Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

/**
 * Issue an invitation and return it with the raw token carried by its link.
 *
 * @return array{0: AdminInvitation, 1: string}
 */
function invite(string $email = 'invite@example.com', AdminRole $role = AdminRole::Support, ?Admin $by = null): array
{
    ['invitation' => $invitation, 'url' => $url] = app(InviteAdmin::class)->handle($email, $role, $by);

    return [$invitation, Str::afterLast($url, '/')];
}

function activationPage(string $token)
{
    return Livewire::test(AcceptInvitation::class, ['token' => $token]);
}

describe('issuing an invitation', function () {
    it('stores only the SHA-256 hash of a random token, valid for 7 days', function () {
        $this->freezeTime();

        [$invitation, $token] = invite();
        $row = (array) DB::table('admin_invitations')->where('id', $invitation->id)->first();

        expect($token)->toMatch('/^[0-9a-f]{64}$/')
            ->and($row['token_hash'])->toBe(hash('sha256', $token))
            ->and(implode('|', array_map('strval', $row)))->not->toContain($token)
            ->and($invitation->fresh()->expires_at->timestamp)->toBe(now()->addDays(7)->timestamp)
            ->and($invitation->role)->toBe(AdminRole::Support)
            ->and($invitation->email)->toBe('invite@example.com');
    });

    it('never gives two invitations the same token', function () {
        [, $first] = invite('a@example.com');
        [, $second] = invite('b@example.com');

        expect($first)->not->toBe($second);
    });

    it('e-mails the personal link without queueing it', function () {
        [, $token] = invite('  Invite@Example.com ');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            AdminInvitationNotification::class,
            fn (AdminInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'invite@example.com'
                && str_ends_with($notification->url, '/admin/activation/'.$token)
                && ! $notification instanceof ShouldQueue,
        );

        expect(DB::table('jobs')->count())->toBe(0);
    });

    it('revokes the previous open invitation when a new one is issued', function () {
        Event::fake([AdminInvitationRevoked::class, AdminInvited::class]);

        [$first, $firstToken] = invite();
        [$second] = invite();

        expect($first->fresh()->isRevoked())->toBeTrue()
            ->and($second->fresh()->isUsable())->toBeTrue()
            ->and(AdminInvitation::stateOf(AdminInvitation::findByToken($firstToken)))->toBe(InvitationState::Revoked);

        Event::assertDispatched(AdminInvitationRevoked::class, 1);
        Event::assertDispatched(AdminInvited::class, 2);
    });

    it('refuses to invite an address that already has an account', function () {
        Admin::factory()->create(['email' => 'deja@example.com']);

        expect(fn () => invite('Deja@example.com'))->toThrow(AdminAlreadyExists::class);
        expect(AdminInvitation::count())->toBe(0);
    });

    it('can be revoked, once', function () {
        [$invitation] = invite();

        expect(app(RevokeAdminInvitation::class)->handle($invitation))->toBeTrue()
            ->and(app(RevokeAdminInvitation::class)->handle($invitation))->toBeFalse()
            ->and($invitation->fresh()->isUsable())->toBeFalse();
    });
});

describe('activation screen', function () {
    it('shows the custom activation form with the address and role set by the invitation', function () {
        $inviter = Admin::factory()->create(['name' => 'Estelle Fouda']);
        $inviter->assignRole(AdminRole::SuperAdmin->value);
        [, $token] = invite('jean.dupont@example.com', AdminRole::Support, $inviter);

        $this->get('/admin/activation/'.$token)
            ->assertOk()
            ->assertSee('Activez votre compte')
            ->assertSee('Défini par votre invitation')
            ->assertSee('jean.dupont@example.com')
            ->assertSee('Support')
            ->assertSee('Estelle Fouda')
            ->assertSee('Votre accès')
            ->assertSee('Au moins 8 caractères')
            ->assertDontSee('Une lettre majuscule')
            ->assertDontSee('Un chiffre')
            ->assertSee('av-auth-brand--invite', false)
            ->assertDontSee('fi-simple-layout', false);
    });

    it('offers no open registration', function () {
        $this->get('/admin/register')->assertNotFound();
        $this->get('/admin/activation')->assertNotFound();

        expect(Filament::hasRegistration())->toBeFalse();
    });

    it('shows a dedicated screen for an unusable link', function (Closure $prepare, string $expected) {
        [$invitation, $token] = invite();
        $token = $prepare($invitation, $token) ?? $token;

        $this->get('/admin/activation/'.$token)
            ->assertOk()
            ->assertSee($expected)
            ->assertDontSee('Activer mon compte')
            ->assertDontSee('Votre accès');
    })->with([
        'expired' => [fn ($invitation) => $invitation->forceFill(['expires_at' => now()->subMinute()])->save() ? null : null, 'Invitation expirée'],
        'revoked' => [fn ($invitation) => app(RevokeAdminInvitation::class)->handle($invitation) ? null : null, 'Lien d’invitation invalide'],
        'already used' => [fn ($invitation, $token) => app(AcceptAdminInvitation::class)->handle($token, 'Jean Dupont', 'phrase-de-passe') ? null : null, 'Cette invitation a déjà été utilisée.'],
        'unknown token' => [fn () => str_repeat('a', 64), 'Lien d’invitation invalide'],
        'tampered token' => [fn ($invitation, $token) => strrev($token), 'Lien d’invitation invalide'],
    ]);

    it('does not reveal whether a revoked link ever existed', function () {
        [$invitation, $token] = invite('secret@example.com');
        app(RevokeAdminInvitation::class)->handle($invitation);

        $this->get('/admin/activation/'.$token)->assertDontSee('secret@example.com');
    });
});

describe('activating an account', function () {
    it('creates the account with the invited address and role, and uses the invitation', function () {
        Event::fake([AdminInvitationAccepted::class]);
        [$invitation, $token] = invite('jean.dupont@example.com', AdminRole::Finance);

        activationPage($token)
            ->set('name', '  Jean Dupont ')
            ->set('password', 'phrase-de-passe')
            ->set('passwordConfirmation', 'phrase-de-passe')
            ->call('activate')
            ->assertHasNoErrors()
            ->assertSee('Votre compte est prêt.')
            ->assertSee('Jean Dupont')
            ->assertSet('password', '');

        $admin = Admin::where('email', 'jean.dupont@example.com')->firstOrFail();

        expect($admin->name)->toBe('Jean Dupont')
            ->and(Hash::check('phrase-de-passe', $admin->password))->toBeTrue()
            ->and($admin->hasRole(AdminRole::Finance->value))->toBeTrue()
            ->and($admin->getRoleNames()->all())->toBe(['finance'])
            ->and($admin->isActive())->toBeTrue()
            ->and($invitation->fresh()->isAccepted())->toBeTrue();

        Event::assertDispatched(AdminInvitationAccepted::class, 1);
    });

    it('does not sign the new administrator in and still requires two-factor enrolment', function () {
        [, $token] = invite('jean.dupont@example.com');

        activationPage($token)->set('name', 'Jean Dupont')->set('password', 'phrase-de-passe')->set('passwordConfirmation', 'phrase-de-passe')->call('activate')->assertHasNoErrors();

        $admin = Admin::where('email', 'jean.dupont@example.com')->firstOrFail();

        expect(Auth::guard('admin')->check())->toBeFalse()
            ->and($admin->getAppAuthenticationSecret())->toBeNull();

        $this->get('/admin')->assertRedirect('/admin/login');

        Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', 'phrase-de-passe')->call('authenticate')->assertHasNoErrors();

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    });

    it('takes the address and the role from the invitation, never from the request', function () {
        [, $token] = invite('jean.dupont@example.com', AdminRole::Support);

        activationPage($token)
            ->set('name', 'Jean Dupont')
            ->set('password', 'phrase-de-passe')
            ->set('passwordConfirmation', 'phrase-de-passe')
            ->set('email', 'autre@example.com')
            ->set('role', 'super-admin')
            ->call('activate');
    })->throws(Exception::class);

    it('refuses to change the token of the page', function () {
        [, $token] = invite();
        [, $other] = invite('autre@example.com');

        activationPage($token)->set('token', $other);
    })->throws(Exception::class);

    it('validates the name and the password policy', function (array $input, string $field, string $message) {
        [$invitation, $token] = invite();

        activationPage($token)
            ->set('name', $input['name'] ?? 'Jean Dupont')
            ->set('password', $input['password'] ?? 'phrase-de-passe')
            ->set('passwordConfirmation', $input['confirmation'] ?? $input['password'] ?? 'phrase-de-passe')
            ->call('activate')
            ->assertHasErrors($field)
            ->assertSee($message);

        expect(Admin::count())->toBe(0)
            ->and($invitation->fresh()->isUsable())->toBeTrue();
    })->with([
        'missing name' => [['name' => ''], 'name', 'Indiquez votre nom complet.'],
        'short password' => [['password' => '1234567'], 'password', 'au moins 8 caractères'],
        'password over 72 bytes' => [['password' => str_repeat('a', 73)], 'password', 'trop long'],
        'unconfirmed password' => [['confirmation' => 'autre-phrase'], 'passwordConfirmation', 'ne correspondent pas'],
    ]);

    it('accepts an 8-character password without composition rules', function () {
        [, $token] = invite();

        activationPage($token)->set('name', 'Jean Dupont')->set('password', 'abcdefgh')->set('passwordConfirmation', 'abcdefgh')->call('activate')->assertHasNoErrors()->assertSee('Votre compte est prêt.');
    });

    it('keeps the invitation usable when activation is interrupted', function () {
        [$invitation, $token] = invite();

        activationPage($token)->set('name', 'Jean Dupont')->set('password', 'court')->set('passwordConfirmation', 'court')->call('activate')->assertHasErrors('password');
        activationPage($token)->set('name', 'Jean Dupont');

        expect($invitation->fresh()->isUsable())->toBeTrue()->and(Admin::count())->toBe(0);

        activationPage($token)->set('name', 'Jean Dupont')->set('password', 'phrase-de-passe')->set('passwordConfirmation', 'phrase-de-passe')->call('activate')->assertSee('Votre compte est prêt.');

        expect(Admin::count())->toBe(1);
    });

    it('lets a link be used only once, even from two open pages', function () {
        [, $token] = invite('jean.dupont@example.com');

        $first = activationPage($token)->set('name', 'Jean Dupont')->set('password', 'phrase-de-passe')->set('passwordConfirmation', 'phrase-de-passe');
        $second = activationPage($token)->set('name', 'Intrus')->set('password', 'autre-phrase-de-passe')->set('passwordConfirmation', 'autre-phrase-de-passe');

        $first->call('activate')->assertSee('Votre compte est prêt.');
        $second->call('activate')->assertSee('Cette invitation a déjà été utilisée.')->assertDontSee('Votre compte est prêt.');

        $admin = Admin::where('email', 'jean.dupont@example.com')->firstOrFail();

        expect(Admin::count())->toBe(1)
            ->and($admin->name)->toBe('Jean Dupont')
            ->and(Hash::check('phrase-de-passe', $admin->password))->toBeTrue();
    });

    it('locks the invitation row while it is being accepted', function () {
        [, $token] = invite();
        DB::enableQueryLog();

        app(AcceptAdminInvitation::class)->handle($token, 'Jean Dupont', 'phrase-de-passe');

        $lookup = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql): bool => str_contains($sql, 'admin_invitations') && str_contains($sql, 'token_hash'));

        expect($lookup)->toContain('for update');
    });

    it('refuses every unusable invitation at the domain level', function (Closure $prepare, InvitationState $state) {
        [$invitation, $token] = invite();
        $token = $prepare($invitation, $token) ?? $token;

        try {
            app(AcceptAdminInvitation::class)->handle($token, 'Jean Dupont', 'phrase-de-passe');
            $this->fail('The invitation should not be usable.');
        } catch (InvitationNotUsable $exception) {
            expect($exception->state)->toBe($state);
        }
    })->with([
        'expired' => [fn ($invitation) => $invitation->forceFill(['expires_at' => now()->subSecond()])->save() ? null : null, InvitationState::Expired],
        'revoked' => [fn ($invitation) => app(RevokeAdminInvitation::class)->handle($invitation) ? null : null, InvitationState::Revoked],
        'unknown' => [fn () => str_repeat('b', 64), InvitationState::Invalid],
    ]);

    it('expires exactly after 7 days', function () {
        [, $token] = invite();

        $this->travel(7)->days();
        $this->travel(1)->seconds();

        activationPage($token)->assertSee('Invitation expirée');
    });

    it('limits activation attempts', function () {
        [, $token] = invite();
        $page = activationPage($token)->set('name', 'Jean Dupont')->set('password', 'court')->set('passwordConfirmation', 'court');

        foreach (range(1, 5) as $attempt) {
            $page->call('activate')->assertHasErrors('password');
        }

        $page->call('activate')->assertHasErrors('throttle');
    });
});

describe('expired invitation', function () {
    it('lets the invitee ask the inviter for a new invitation, without creating one', function () {
        $inviter = Admin::factory()->create();
        [$invitation, $token] = invite('jean.dupont@example.com', AdminRole::Support, $inviter);
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        activationPage($token)->call('requestNewInvitation')->assertSet('renewalRequested', true)->assertSee('Votre demande a été transmise.');

        Notification::assertSentTo($inviter, AdminInvitationRenewalRequested::class);

        expect(AdminInvitation::count())->toBe(1);
    });

    it('gives the same answer when nobody can be notified', function () {
        [$invitation, $token] = invite();
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        activationPage($token)->call('requestNewInvitation')->assertSet('renewalRequested', true)->assertSee('Votre demande a été transmise.');

        Notification::assertNotSentTo(new AnonymousNotifiable, AdminInvitationRenewalRequested::class);
    });

    it('does not send a renewal request for a link that is not expired', function () {
        $inviter = Admin::factory()->create();
        [, $token] = invite('jean.dupont@example.com', AdminRole::Support, $inviter);

        activationPage($token)->call('requestNewInvitation');

        Notification::assertNotSentTo($inviter, AdminInvitationRenewalRequested::class);
    });
});

describe('first super admin command', function () {
    it('invites the first super admin without setting or printing any password', function () {
        $this->artisan('admin:invite-first-super-admin', ['email' => 'Premier@Example.com'])
            ->expectsOutputToContain('Invitation Super Admin créée pour premier@example.com.')
            ->expectsOutputToContain('/admin/activation/')
            ->doesntExpectOutputToContain('mot de passe :')
            ->assertSuccessful();

        $invitation = AdminInvitation::firstOrFail();

        expect($invitation->role)->toBe(AdminRole::SuperAdmin)
            ->and($invitation->invited_by_id)->toBeNull()
            ->and($invitation->isUsable())->toBeTrue()
            ->and(Admin::count())->toBe(0);
    });

    it('leads to a super admin account through the normal activation', function () {
        ['url' => $url] = app(InviteAdmin::class)->handle('premier@example.com', AdminRole::SuperAdmin);

        activationPage(Str::afterLast($url, '/'))->set('name', 'Première Admin')->set('password', 'phrase-de-passe')->set('passwordConfirmation', 'phrase-de-passe')->call('activate')->assertHasNoErrors();

        expect(Admin::firstOrFail()->hasRole(AdminRole::SuperAdmin->value))->toBeTrue();
    });

    it('refuses to run once a super admin exists', function () {
        Admin::factory()->create()->assignRole(AdminRole::SuperAdmin->value);

        $this->artisan('admin:invite-first-super-admin', ['email' => 'autre@example.com'])
            ->expectsOutputToContain('Un Super Admin existe déjà.')
            ->assertFailed();

        expect(AdminInvitation::count())->toBe(0);
    });

    it('rejects an invalid e-mail address', function () {
        $this->artisan('admin:invite-first-super-admin', ['email' => 'pas-une-adresse'])->assertExitCode(2);

        expect(AdminInvitation::count())->toBe(0);
    });

    it('replaces the previous link when run again before activation', function () {
        $this->artisan('admin:invite-first-super-admin', ['email' => 'premier@example.com'])->assertSuccessful();
        $this->artisan('admin:invite-first-super-admin', ['email' => 'premier@example.com'])->assertSuccessful();

        expect(AdminInvitation::count())->toBe(2)
            ->and(AdminInvitation::usable()->count())->toBe(1);
    });
});
