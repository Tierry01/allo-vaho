<?php

namespace App\Domain\Admin\Models;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use Database\Factories\AdminFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * A back-office administrator. Mobile users are a separate model, table,
 * guard and provider: nothing here authenticates against the API.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret'])]
class Admin extends Authenticatable implements FilamentUser, HasAppAuthentication
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, HasRoles, HasUlids, InteractsWithAppAuthentication, Notifiable, SoftDeletes;

    /**
     * Guard the roles of an administrator belong to.
     */
    protected string $guard_name = AdminRole::GUARD;

    /**
     * Model defaults, mirroring the column defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AdminStatus::Active->value,
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Only an active administrator may enter the panel, in every environment.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive();
    }

    public function isActive(): bool
    {
        return $this->status === AdminStatus::Active && ! $this->trashed();
    }

    /**
     * E-mail addresses are compared case-insensitively: store them lowercased.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }
}
