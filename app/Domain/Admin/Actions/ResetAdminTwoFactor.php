<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Events\TwoFactorReset;
use App\Domain\Admin\Exceptions\TwoFactorResetRefused;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Notifications\TwoFactorResetNotification;
use App\Domain\Admin\Support\AdminSessions;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * A Super Admin resets another administrator's two-factor authentication
 * (decision 0009). The Super Admin confirms with their own password and a
 * current code, and gives a reason. The target must enrol again at the next
 * sign-in; their sessions are closed. The screen belongs to Phase 11.
 */
class ResetAdminTwoFactor
{
    public const MIN_REASON_LENGTH = 10;

    public function __construct(protected AdminSessions $sessions) {}

    /**
     * @throws TwoFactorResetRefused
     */
    public function handle(Admin $target, Admin $actor, #[SensitiveParameter] string $actorPassword, #[SensitiveParameter] string $actorCode, string $reason): void
    {
        $reason = trim($reason);

        if (! $actor->isActive() || ! $actor->hasRole(AdminRole::SuperAdmin->value)) {
            throw new TwoFactorResetRefused('Only an active Super Admin can reset two-factor authentication.');
        }

        if ($actor->is($target)) {
            throw new TwoFactorResetRefused('A Super Admin cannot reset their own two-factor authentication.');
        }

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new TwoFactorResetRefused('A reason of at least '.self::MIN_REASON_LENGTH.' characters is required.');
        }

        if (! Hash::check($actorPassword, $actor->password)) {
            throw new TwoFactorResetRefused('The Super Admin password is incorrect.');
        }

        $secret = $actor->getAppAuthenticationSecret();

        if (blank($secret) || ! $this->provider()->verifyCode($actorCode, $secret, shouldPreventCodeReuse: true)) {
            throw new TwoFactorResetRefused('The Super Admin verification code is incorrect.');
        }

        DB::transaction(function () use ($target): void {
            $target->saveAppAuthenticationSecret(null);
            $this->sessions->invalidate($target);
        });

        event(new TwoFactorReset($target, $actor, $reason));

        $target->notifyNow(new TwoFactorResetNotification('par un Super Admin'));
    }

    protected function provider(): AppAuthentication
    {
        $provider = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders()['app'] ?? null;

        return $provider instanceof AppAuthentication ? $provider : AppAuthentication::make()->codeWindow(1);
    }
}
