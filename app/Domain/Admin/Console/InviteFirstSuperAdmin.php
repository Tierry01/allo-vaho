<?php

namespace App\Domain\Admin\Console;

use App\Domain\Admin\Actions\InviteAdmin;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Support\AuthAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Bootstraps the back-office: invites the first Super Admin, who then
 * activates the account like anyone else. No password is ever set here.
 */
class InviteFirstSuperAdmin extends Command
{
    protected $signature = 'admin:invite-first-super-admin {email : Adresse e-mail professionnelle du premier Super Admin}';

    protected $description = 'Invite le premier Super Admin du back-office (aucun mot de passe n’est défini)';

    public function handle(InviteAdmin $inviteAdmin): int
    {
        app(AuthAudit::class)->fromConsole();

        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Cette adresse e-mail n’est pas valide.');

            return self::INVALID;
        }

        if (Admin::withTrashed()->role(AdminRole::SuperAdmin->value, AdminRole::GUARD)->exists()) {
            $this->error('Un Super Admin existe déjà. Cette commande ne sert qu’à créer le premier.');

            return self::FAILURE;
        }

        try {
            ['invitation' => $invitation, 'url' => $url] = $inviteAdmin->handle($email, AdminRole::SuperAdmin);
        } catch (AdminAlreadyExists) {
            $this->error('Un compte administrateur utilise déjà cette adresse.');

            return self::FAILURE;
        }

        $expiresAt = $invitation->expires_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i');

        $this->info('Invitation Super Admin créée pour '.$invitation->email.'.');
        $this->line('Valable '.AdminInvitation::VALIDITY_DAYS.' jours, jusqu’au '.$expiresAt.' (heure de Douala). Usage unique.');
        $this->newLine();
        $this->line('Lien d’activation, à transmettre à cette personne uniquement :');
        $this->line($url);
        $this->newLine();
        $this->warn('Ce lien ne sera plus affiché. Relancer la commande révoque celui-ci et en crée un nouveau.');

        return self::SUCCESS;
    }
}
