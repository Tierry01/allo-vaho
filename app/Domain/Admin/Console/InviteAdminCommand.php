<?php

namespace App\Domain\Admin\Console;

use App\Domain\Admin\Actions\InviteAdmin;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Support\AuthAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Invites an administrator from the server, until the back-office screens of
 * Phase 11 exist. The link is shown once in the terminal; no e-mail is relied on.
 */
class InviteAdminCommand extends Command
{
    protected $signature = 'admin:invite
        {email : Adresse e-mail professionnelle de la personne invitée}
        {role : Rôle attribué (super-admin, support, marketing ou finance)}';

    protected $description = 'Invite un administrateur du back-office (lien personnel, usage unique, 7 jours)';

    public function handle(InviteAdmin $inviteAdmin): int
    {
        app(AuthAudit::class)->fromConsole();

        $email = trim((string) $this->argument('email'));
        $role = AdminRole::tryFrom((string) $this->argument('role'));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:strict', 'max:255']])->fails()) {
            $this->error('Cette adresse e-mail n’est pas valide.');

            return self::INVALID;
        }

        if (! $role) {
            $this->error('Rôle inconnu. Rôles autorisés : '.implode(', ', array_column(AdminRole::cases(), 'value')).'.');

            return self::INVALID;
        }

        $replaces = AdminInvitation::query()->where('email', mb_strtolower($email))->usable()->exists();

        try {
            ['invitation' => $invitation, 'url' => $url] = $inviteAdmin->handle($email, $role);
        } catch (AdminAlreadyExists) {
            $this->error('Un compte administrateur utilise déjà cette adresse.');

            return self::FAILURE;
        }

        $expiresAt = $invitation->expires_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i');

        $this->info('Invitation créée pour '.$invitation->email.' (rôle '.$role->label().').');

        if ($replaces) {
            $this->warn('L’invitation précédente pour cette adresse a été révoquée.');
        }

        $this->line('Valable '.AdminInvitation::VALIDITY_DAYS.' jours, jusqu’au '.$expiresAt.' (heure de Douala). Usage unique.');
        $this->newLine();
        $this->line('Lien d’activation, à transmettre à cette personne uniquement :');
        $this->line($url);
        $this->newLine();
        $this->warn('Ce lien ne sera plus affiché. Relancer la commande révoque celui-ci et en crée un nouveau.');

        return self::SUCCESS;
    }
}
