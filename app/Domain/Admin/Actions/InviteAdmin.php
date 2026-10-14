<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Events\AdminInvitationRevoked;
use App\Domain\Admin\Events\AdminInvited;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Notifications\AdminInvitationNotification;
use App\Domain\Admin\Support\InvitationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Issues a personal, single-use invitation valid for 7 days. A previous open
 * invitation for the same address is revoked: only the latest link works.
 */
class InviteAdmin
{
    /**
     * @return array{invitation: AdminInvitation, url: string}
     *
     * @throws AdminAlreadyExists
     */
    public function handle(string $email, AdminRole $role, ?Admin $invitedBy = null): array
    {
        $email = Str::lower(trim($email));

        if (Admin::withTrashed()->where('email', $email)->exists()) {
            throw new AdminAlreadyExists;
        }

        $token = InvitationToken::generate();

        $invitation = DB::transaction(function () use ($email, $role, $invitedBy, $token): AdminInvitation {
            AdminInvitation::query()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(function (AdminInvitation $previous) use ($invitedBy): void {
                    $previous->forceFill(['revoked_at' => now()])->save();

                    event(new AdminInvitationRevoked($previous, $invitedBy));
                });

            return AdminInvitation::create([
                'email' => $email,
                'role' => $role,
                'token_hash' => InvitationToken::hash($token),
                'invited_by_id' => $invitedBy?->getKey(),
                'expires_at' => now()->addDays(AdminInvitation::VALIDITY_DAYS),
            ]);
        });

        $url = route('filament.admin.auth.activation', ['token' => $token]);

        // Sent immediately, never queued: a queued job would store the raw token.
        Notification::route('mail', $email)->notifyNow(new AdminInvitationNotification($invitation, $url));

        event(new AdminInvited($invitation, $invitedBy));

        return ['invitation' => $invitation, 'url' => $url];
    }
}
