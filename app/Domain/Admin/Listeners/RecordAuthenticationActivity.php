<?php

namespace App\Domain\Admin\Listeners;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Events\AdminInvitationAccepted;
use App\Domain\Admin\Events\AdminInvitationRevoked;
use App\Domain\Admin\Events\AdminInvited;
use App\Domain\Admin\Events\SuperAdminRecovered;
use App\Domain\Admin\Events\TwoFactorChallengeFailed;
use App\Domain\Admin\Events\TwoFactorEnrolled;
use App\Domain\Admin\Events\TwoFactorReset;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Support\AuthAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Events\Dispatcher;

/**
 * Audit trail of back-office authentication and invitations (decision 0009).
 * Events of other guards (mobile users) are ignored.
 */
class RecordAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        if (! $this->isAdminGuard($event->guard) || ! $event->user instanceof Admin) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit()->record('auth.login', 'Connexion réussie', $event->user, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        if (! $this->isAdminGuard($event->guard)) {
            return;
        }

        $admin = $event->user instanceof Admin ? $event->user : null;

        $this->audit()->record('auth.login_failed', 'Échec de connexion', null, $admin, [
            'reason' => match (true) {
                $admin === null => 'unknown_account',
                ! $admin->isActive() => 'inactive_account',
                default => 'invalid_credentials',
            },
            'email_fingerprint' => $admin ? null : AuthAudit::emailFingerprint($event->credentials['email'] ?? null),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($this->isAdminGuard($event->guard) && $event->user instanceof Admin) {
            $this->audit()->record('auth.logout', 'Déconnexion', $event->user, $event->user);
        }
    }

    public function handleTwoFactorChallengeFailed(TwoFactorChallengeFailed $event): void
    {
        $this->audit()->record('auth.two_factor_failed', 'Code de vérification refusé', null, $event->admin);
    }

    public function handleTwoFactorEnrolled(TwoFactorEnrolled $event): void
    {
        $this->audit()->record('auth.two_factor_enrolled', 'Double authentification activée', $event->admin, $event->admin);
    }

    public function handleTwoFactorReset(TwoFactorReset $event): void
    {
        $this->audit()->record('auth.two_factor_reset', 'Double authentification réinitialisée par un Super Admin', $event->resetBy, $event->admin, [
            'reason' => $event->reason,
        ]);
    }

    public function handleSuperAdminRecovered(SuperAdminRecovered $event): void
    {
        $this->audit()->record('auth.super_admin_recovered', 'Récupération exceptionnelle du dernier Super Admin', null, $event->admin, [
            'operator' => $event->operator,
            'reason' => $event->reason,
            'password_reset_issued' => $event->passwordResetIssued,
        ]);
    }

    public function handlePasswordResetLinkSent(PasswordResetLinkSent $event): void
    {
        if ($event->user instanceof Admin) {
            $this->audit()->record('auth.password_reset_requested', 'Lien de réinitialisation du mot de passe envoyé', null, $event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof Admin) {
            $this->audit()->record('auth.password_reset', 'Mot de passe réinitialisé', $event->user, $event->user);
        }
    }

    public function handleAdminInvited(AdminInvited $event): void
    {
        $this->audit()->record('invitation.issued', 'Invitation émise', $event->invitedBy, $event->invitation, [
            'email' => $event->invitation->email,
            'role' => $event->invitation->role->value,
            'expires_at' => $event->invitation->expires_at->toIso8601ZuluString(),
        ]);
    }

    public function handleAdminInvitationRevoked(AdminInvitationRevoked $event): void
    {
        $this->audit()->record('invitation.revoked', 'Invitation révoquée', $event->revokedBy, $event->invitation, [
            'email' => $event->invitation->email,
            'role' => $event->invitation->role->value,
        ]);
    }

    public function handleAdminInvitationAccepted(AdminInvitationAccepted $event): void
    {
        $this->audit()->record('invitation.accepted', 'Invitation acceptée, compte créé', $event->admin, $event->invitation, [
            'admin_id' => $event->admin->getKey(),
            'role' => $event->invitation->role->value,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            TwoFactorChallengeFailed::class => 'handleTwoFactorChallengeFailed',
            TwoFactorEnrolled::class => 'handleTwoFactorEnrolled',
            TwoFactorReset::class => 'handleTwoFactorReset',
            SuperAdminRecovered::class => 'handleSuperAdminRecovered',
            PasswordResetLinkSent::class => 'handlePasswordResetLinkSent',
            PasswordReset::class => 'handlePasswordReset',
            AdminInvited::class => 'handleAdminInvited',
            AdminInvitationRevoked::class => 'handleAdminInvitationRevoked',
            AdminInvitationAccepted::class => 'handleAdminInvitationAccepted',
        ];
    }

    protected function audit(): AuthAudit
    {
        return app(AuthAudit::class);
    }

    protected function isAdminGuard(?string $guard): bool
    {
        return $guard === AdminRole::GUARD;
    }
}
