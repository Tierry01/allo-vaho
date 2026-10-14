<?php

namespace App\Domain\Admin\Models;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\InvitationState;
use App\Domain\Admin\Support\InvitationToken;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use SensitiveParameter;

/**
 * A personal, single-use invitation to create an administrator account.
 * Only the hash of its token is kept.
 */
#[Fillable(['email', 'role', 'token_hash', 'invited_by_id', 'expires_at'])]
#[Hidden(['token_hash'])]
class AdminInvitation extends Model
{
    /**
     * Number of days an invitation stays valid.
     */
    public const VALIDITY_DAYS = 7;

    /**
     * Find the invitation a link points to, from the token it carries.
     */
    public static function findByToken(#[SensitiveParameter] string $token, bool $lock = false): ?self
    {
        return static::query()
            ->where('token_hash', InvitationToken::hash($token))
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    public static function stateOf(?self $invitation): InvitationState
    {
        return match (true) {
            $invitation === null => InvitationState::Invalid,
            $invitation->isAccepted() => InvitationState::Accepted,
            $invitation->isRevoked() => InvitationState::Revoked,
            $invitation->isExpired() => InvitationState::Expired,
            default => InvitationState::Usable,
        };
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'invited_by_id')->withTrashed();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * An invitation can be used once, before it expires and unless revoked.
     */
    public function isUsable(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
