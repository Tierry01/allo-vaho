<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Enums\InvitationState;
use App\Domain\Admin\Events\AdminInvitationAccepted;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Exceptions\InvitationNotUsable;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Creates the administrator account an invitation was issued for. The e-mail
 * address and the role come from the invitation, never from the request.
 *
 * The account is created without any two-factor secret and is not signed in:
 * the first sign-in goes through the mandatory enrolment.
 */
class AcceptAdminInvitation
{
    /**
     * @throws InvitationNotUsable
     * @throws AdminAlreadyExists
     */
    public function handle(#[SensitiveParameter] string $token, string $name, #[SensitiveParameter] string $password): Admin
    {
        return DB::transaction(function () use ($token, $name, $password): Admin {
            // The row lock makes two simultaneous uses of the same link wait for each
            // other: the second one then sees an invitation that is already accepted.
            $invitation = AdminInvitation::findByToken($token, lock: true);

            $state = AdminInvitation::stateOf($invitation);

            if ($state !== InvitationState::Usable) {
                throw new InvitationNotUsable($state);
            }

            if (Admin::withTrashed()->where('email', $invitation->email)->exists()) {
                throw new AdminAlreadyExists;
            }

            $admin = new Admin;
            $admin->forceFill([
                'name' => trim($name),
                'email' => $invitation->email,
                'password' => $password,
            ])->save();

            $admin->assignRole($invitation->role->value);

            $invitation->forceFill(['accepted_at' => now()])->save();

            event(new AdminInvitationAccepted($invitation, $admin));

            return $admin;
        });
    }
}
