<?php

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->withoutVite();
});

describe('configuration', function () {
    it('gives administrators their own guard, provider and password broker', function () {
        expect(config('auth.guards.admin'))->toBe(['driver' => 'session', 'provider' => 'admins'])
            ->and(config('auth.providers.admins.model'))->toBe(Admin::class)
            ->and(config('auth.passwords.admins.provider'))->toBe('admins')
            ->and(config('auth.passwords.admins.table'))->toBe('admin_password_reset_tokens')
            ->and(config('auth.passwords.admins.expire'))->toBe(60);
    });

    it('shares no provider between mobile users and administrators', function () {
        expect(config('auth.guards.web.provider'))->toBe('users')
            ->and(config('auth.providers.users.model'))->toBe(User::class)
            ->and(config('sanctum.guard'))->not->toContain('admin');
    });

    it('stores administrators apart from users, with timezone-aware dates', function () {
        $types = collect(Schema::getColumns('admins'))->pluck('type_name', 'name');

        expect(Schema::hasTable('admins'))->toBeTrue()
            ->and($types['created_at'])->toBe('timestamptz')
            ->and($types['last_login_at'])->toBe('timestamptz')
            ->and($types['deleted_at'])->toBe('timestamptz')
            ->and(Schema::hasColumn('users', 'app_authentication_secret'))->toBeFalse();
    });
});

describe('administrator model', function () {
    it('creates an active administrator with a public ULID and a hashed password', function () {
        $admin = Admin::factory()->create(['email' => '  Christelle.Mbarga@Example.COM ', 'password' => 'motdepasse']);

        expect($admin->ulid)->toBeString()->toHaveLength(26)
            ->and($admin->email)->toBe('christelle.mbarga@example.com')
            ->and($admin->status)->toBe(AdminStatus::Active)
            ->and($admin->password)->not->toBe('motdepasse')
            ->and(Hash::check('motdepasse', $admin->password))->toBeTrue();
    });

    it('never exposes the password or the two-factor secret when serialized', function () {
        $admin = Admin::factory()->create();
        $admin->app_authentication_secret = 'JBSWY3DPEHPK3PXP';
        $admin->save();

        $stored = DB::table('admins')->where('id', $admin->id)->value('app_authentication_secret');

        expect($admin->toArray())->not->toHaveKeys(['password', 'remember_token', 'app_authentication_secret'])
            ->and($stored)->not->toContain('JBSWY3DPEHPK3PXP')
            ->and($admin->fresh()->app_authentication_secret)->toBe('JBSWY3DPEHPK3PXP');
    });

    it('refuses two administrators with the same e-mail address', function () {
        Admin::factory()->create(['email' => 'double@example.com']);

        expect(fn () => Admin::factory()->create(['email' => 'DOUBLE@example.com']))
            ->toThrow(QueryException::class);
    });
});

describe('panel access', function () {
    it('lets an active administrator into the panel', function () {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin')
            ->assertOk();
    });

    it('refuses a suspended administrator', function () {
        $this->actingAs(Admin::factory()->suspended()->create(), 'admin')
            ->get('/admin')
            ->assertForbidden();
    });

    it('does not authenticate a deleted administrator', function () {
        $admin = Admin::factory()->create(['password' => 'motdepasse']);
        $admin->delete();

        expect(Auth::guard('admin')->attempt(['email' => $admin->email, 'password' => 'motdepasse']))->toBeFalse();
    });

    it('keeps mobile users out of the panel in every environment', function (string $environment) {
        $this->app->detectEnvironment(fn (): string => $environment);
        config(['app.env' => $environment]);

        $this->actingAs(User::factory()->create(), 'web')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    })->with(['local', 'testing', 'production']);

    it('does not accept the credentials of a mobile user on the admin guard', function () {
        $user = User::factory()->create(['email' => 'mobile@example.com', 'password' => 'motdepasse']);

        expect(Auth::guard('admin')->attempt(['email' => $user->email, 'password' => 'motdepasse']))->toBeFalse()
            ->and(Auth::guard('admin')->check())->toBeFalse();
    });

    it('does not authenticate an administrator on the guards used by mobile users', function () {
        $admin = Admin::factory()->create(['email' => 'admin@example.com', 'password' => 'motdepasse']);

        $this->actingAs($admin, 'admin');

        expect(Auth::guard('web')->check())->toBeFalse()
            ->and(Auth::guard('web')->attempt(['email' => $admin->email, 'password' => 'motdepasse']))->toBeFalse();
    });
});

describe('roles', function () {
    it('provides exactly the four back-office roles on the admin guard', function () {
        $roles = Role::where('guard_name', 'admin')->pluck('name')->sort()->values()->all();

        expect($roles)->toBe(['finance', 'marketing', 'super-admin', 'support'])
            ->and(Role::where('guard_name', '!=', 'admin')->count())->toBe(0);
    });

    it('defines no permission before Phase 11', function () {
        expect(Permission::count())->toBe(0);
    });

    it('assigns a role to an administrator', function () {
        $admin = Admin::factory()->create();
        $admin->assignRole(AdminRole::Support->value);

        expect($admin->hasRole(AdminRole::Support->value))->toBeTrue()
            ->and($admin->hasRole(AdminRole::SuperAdmin->value))->toBeFalse();
    });
});

describe('invitations', function () {
    it('keeps only the hash of an invitation token and hides it', function () {
        $invitation = AdminInvitation::create([
            'email' => 'invite@example.com',
            'role' => AdminRole::Support,
            'token_hash' => hash('sha256', 'jeton-brut'),
            'expires_at' => now()->addDays(AdminInvitation::VALIDITY_DAYS),
        ]);

        expect($invitation->isUsable())->toBeTrue()
            ->and($invitation->role)->toBe(AdminRole::Support)
            ->and($invitation->toArray())->not->toHaveKey('token_hash')
            ->and(Schema::hasColumn('admin_invitations', 'token'))->toBeFalse();
    });

    it('treats expired, revoked and accepted invitations as unusable', function (array $state) {
        $invitation = AdminInvitation::create([
            'email' => 'invite@example.com',
            'role' => AdminRole::Finance,
            'token_hash' => hash('sha256', 'jeton-brut'),
            'expires_at' => now()->addDays(7),
        ]);
        $invitation->forceFill($state)->save();

        expect($invitation->fresh()->isUsable())->toBeFalse()
            ->and(AdminInvitation::usable()->count())->toBe(0);
    })->with([
        'expired' => [['expires_at' => '2020-01-01 00:00:00+00']],
        'revoked' => [['revoked_at' => '2026-01-01 00:00:00+00']],
        'accepted' => [['accepted_at' => '2026-01-01 00:00:00+00']],
    ]);

    it('allows a single open invitation per e-mail address', function () {
        $attributes = ['email' => 'invite@example.com', 'role' => AdminRole::Support, 'expires_at' => now()->addDays(7)];

        AdminInvitation::create([...$attributes, 'token_hash' => hash('sha256', 'premier')]);

        expect(fn () => AdminInvitation::create([...$attributes, 'token_hash' => hash('sha256', 'second')]))
            ->toThrow(QueryException::class);
    });
});

describe('password hashing', function () {
    it('hashes passwords with bcrypt', function () {
        expect(config('hashing.driver'))->toBe('bcrypt')
            ->and(Hash::info(Hash::make('motdepasse'))['algoName'])->toBe('bcrypt');
    });

    it('refuses to hash a value that bcrypt would silently truncate', function () {
        expect(config('hashing.bcrypt.limit'))->toBe(72)
            ->and(Hash::make(str_repeat('a', 72)))->toBeString()
            ->and(fn () => Hash::make(str_repeat('a', 73)))->toThrow(InvalidArgumentException::class);
    });
});
