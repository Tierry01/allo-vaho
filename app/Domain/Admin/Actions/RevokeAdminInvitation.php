<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Events\AdminInvitationRevoked;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use Illuminate\Support\Facades\DB;

/**
 * Revokes an invitation that has not been accepted: its link stops working.
 */
class RevokeAdminInvitation
{
    /**
     * @return bool Whether the invitation was open and is now revoked.
     */
    public function handle(AdminInvitation $invitation, ?Admin $revokedBy = null): bool
    {
        return DB::transaction(function () use ($invitation, $revokedBy): bool {
            $locked = AdminInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->isAccepted() || $locked->isRevoked()) {
                return false;
            }

            $locked->forceFill(['revoked_at' => now()])->save();

            event(new AdminInvitationRevoked($locked, $revokedBy));

            return true;
        });
    }
}
