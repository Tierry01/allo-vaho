<?php

namespace App\Domain\Admin\Support;

use App\Domain\Admin\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every open back-office session of an administrator.
 *
 * Sessions are stored in the "sessions" table; on back-office requests their
 * user_id is the administrator's id (mobile users have no web session). The
 * remember token is rotated as well, in case one was ever issued.
 */
class AdminSessions
{
    public function invalidate(Admin $admin): int
    {
        $admin->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $admin->getKey())->delete();
    }
}
