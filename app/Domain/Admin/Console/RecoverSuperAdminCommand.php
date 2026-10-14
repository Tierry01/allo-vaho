<?php

namespace App\Domain\Admin\Console;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Events\SuperAdminRecovered;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Notifications\TwoFactorResetNotification;
use App\Domain\Admin\Support\AdminSessions;
use App\Domain\Admin\Support\AuthAudit;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * Exceptional recovery of the last Super Admin who lost access to their
 * authenticator (decision 0009). Run on the server only, it names the
 * operator, states a reason, acts on that single account and is audited.
 *
 * It never disables two-factor authentication: it removes this account's
 * secret, so the next sign-in requires the password and a new enrolment.
 */
class RecoverSuperAdminCommand extends Command
{
    public const MIN_REASON_LENGTH = 10;

    protected $signature = 'admin:recover-super-admin
        {email : Adresse e-mail du Super Admin à récupérer}
        {--operator= : Nom de la personne qui exécute la récupération (obligatoire)}
        {--reason= : Motif de la récupération, au moins 10 caractères (obligatoire)}
        {--reset-password : Émettre aussi un lien de réinitialisation du mot de passe, affiché une fois}';

    protected $description = 'Procédure exceptionnelle : récupère l’accès du dernier Super Admin (réinitialise sa double authentification)';

    public function handle(AdminSessions $sessions): int
    {
        app(AuthAudit::class)->fromConsole();

        $operator = trim((string) $this->option('operator'));
        $reason = trim((string) $this->option('reason'));

        if ($operator === '' || mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            $this->error('Indiquez l’opérateur (--operator) et un motif d’au moins '.self::MIN_REASON_LENGTH.' caractères (--reason).');

            return self::INVALID;
        }

        $admin = Admin::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $admin || ! $admin->isSuperAdmin()) {
            $this->error('Aucun Super Admin ne correspond à cette adresse.');

            return self::FAILURE;
        }

        if ($admin->status !== AdminStatus::Active) {
            $this->error('Ce compte est suspendu. La récupération ne réactive pas un compte suspendu.');

            return self::FAILURE;
        }

        $others = Admin::query()
            ->role(AdminRole::SuperAdmin->value, AdminRole::GUARD)
            ->where('status', AdminStatus::Active->value)
            ->whereNotNull('app_authentication_secret')
            ->whereKeyNot($admin->getKey())
            ->count();

        if ($others > 0) {
            $this->error('Un autre Super Admin actif peut réinitialiser cette double authentification depuis le back-office.');
            $this->line('Cette procédure exceptionnelle est réservée au cas où aucun autre Super Admin n’est disponible.');

            return self::FAILURE;
        }

        $this->warn('Procédure exceptionnelle de récupération du dernier Super Admin.');
        $this->line('Compte : '.$admin->name.' <'.$admin->email.'>');
        $this->line('Effets : double authentification de ce compte réinitialisée, toutes ses sessions fermées.');
        $this->line('La double authentification n’est pas désactivée : elle devra être configurée à la prochaine connexion.');

        if ($this->ask('Pour confirmer, saisissez l’adresse e-mail du compte') !== $admin->email) {
            $this->error('Confirmation incorrecte. Aucune modification.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($admin, $sessions): void {
            $admin->saveAppAuthenticationSecret(null);
            $sessions->invalidate($admin);
        });

        $resetUrl = null;

        if ($this->option('reset-password')) {
            URL::forceRootUrl(config('app.url'));
            $resetUrl = Filament::getPanel('admin')->getResetPasswordUrl(Password::broker('admins')->createToken($admin), $admin);
        }

        event(new SuperAdminRecovered($admin, $operator, $reason, $resetUrl !== null));

        $admin->notifyNow(new TwoFactorResetNotification('par une procédure de récupération exécutée sur le serveur'));

        $this->info('Récupération effectuée et journalisée.');
        $this->line('À la prochaine connexion, ce Super Admin configurera à nouveau Google Authenticator.');

        if ($resetUrl) {
            $this->newLine();
            $this->line('Lien de réinitialisation du mot de passe, valable 60 minutes, à transmettre à cette personne uniquement :');
            $this->line($resetUrl);
            $this->warn('Ce lien ne sera plus affiché.');
        }

        return self::SUCCESS;
    }
}
