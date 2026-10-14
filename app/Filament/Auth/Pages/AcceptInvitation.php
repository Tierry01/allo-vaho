<?php

namespace App\Filament\Auth\Pages;

use App\Domain\Admin\Actions\AcceptAdminInvitation;
use App\Domain\Admin\Enums\InvitationState;
use App\Domain\Admin\Exceptions\AdminAlreadyExists;
use App\Domain\Admin\Exceptions\InvitationNotUsable;
use App\Domain\Admin\Models\AdminInvitation;
use App\Domain\Admin\Notifications\AdminInvitationRenewalRequested;
use App\Domain\Admin\Support\AdminPassword;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Activation of an invited administrator account, reproducing the validated
 * "Activation" mockup. It creates the account but never signs it in: the
 * first sign-in goes through the mandatory two-factor enrolment.
 */
class AcceptInvitation extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.auth.accept-invitation';

    protected static string $layout = 'layouts.admin-auth';

    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /**
     * Summary shown once the account is created.
     *
     * @var array{name: string, email: string, role: string}|null
     */
    #[Locked]
    public ?array $activated = null;

    public bool $renewalRequested = false;

    public function mount(#[SensitiveParameter] string $token): void
    {
        $this->token = $token;
    }

    public function activate(AcceptAdminInvitation $accept): void
    {
        $this->resetErrorBag();

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', (string) $exception->secondsUntilAvailable);

            return;
        }

        $this->validate(
            [
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'password' => AdminPassword::rules(),
                'passwordConfirmation' => ['required', 'same:password'],
            ],
            [
                'name.required' => 'Indiquez votre nom complet.',
                'name.min' => 'Indiquez votre nom complet.',
                'name.max' => 'Ce nom est trop long.',
                'password.required' => AdminPassword::messages()['required'],
                'password.min' => AdminPassword::messages()['min'],
                'passwordConfirmation.required' => 'Confirmez votre mot de passe.',
                'passwordConfirmation.same' => 'Les mots de passe ne correspondent pas.',
            ],
        );

        try {
            $admin = $accept->handle($this->token, $this->name, $this->password);
        } catch (InvitationNotUsable|AdminAlreadyExists) {
            // The view re-reads the invitation and shows its current state.
            $this->password = '';
            $this->passwordConfirmation = '';

            return;
        }

        $this->activated = [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $this->invitation()->role->label(),
        ];

        $this->reset('name', 'password', 'passwordConfirmation');
    }

    /**
     * Ask the administrator who issued an expired invitation for a new one.
     * The answer is the same whether or not a message could be sent.
     */
    public function requestNewInvitation(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', (string) $exception->secondsUntilAvailable);

            return;
        }

        $invitation = $this->invitation();

        if ($this->state() === InvitationState::Expired && $invitation?->invitedBy?->isActive()) {
            $invitation->invitedBy->notifyNow(new AdminInvitationRenewalRequested($invitation));
        }

        $this->renewalRequested = true;
    }

    public function invitation(): ?AdminInvitation
    {
        return AdminInvitation::findByToken($this->token);
    }

    public function state(): InvitationState
    {
        return AdminInvitation::stateOf($this->invitation());
    }

    public function getTitle(): string
    {
        return 'Activer votre compte';
    }

    public function loginUrl(): string
    {
        return Filament::getLoginUrl();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        $invitation = $this->invitation();
        $usable = $this->activated === null && $this->state() === InvitationState::Usable;
        $ready = $usable || $this->activated !== null;
        $inviter = $invitation?->invitedBy;

        return [
            'title' => $this->getTitle(),
            'cardLabel' => 'Activation du compte',
            'brand' => [
                'variant' => 'invite',
                'label' => 'Invitation au back-office',
                'eyebrow' => 'Invitation · Back-office',
                'title' => $ready ? ['Votre accès', 'est prêt.'] : ['Accès sur', 'invitation.'],
                'text' => $ready
                    ? 'Choisissez votre mot de passe pour rejoindre l’espace d’administration Allo Vaho.'
                    : 'Les comptes administrateurs sont créés uniquement sur invitation d’un Super Admin.',
                'card' => $usable ? [
                    'initials' => $inviter ? Str::upper(Str::substr($inviter->name, 0, 1).Str::substr(Str::after($inviter->name, ' '), 0, 1)) : 'AV',
                    'name' => $inviter?->name ?? 'Allo Vaho',
                    'sub' => ($inviter?->getRoleNames()->first() ? Str::headline($inviter->getRoleNames()->first()) : 'Administration').' · vous a invité',
                    'role' => $invitation->role->label(),
                    'validUntil' => $invitation->expires_at->timezone(config('app.display_timezone'))->locale('fr')->translatedFormat('j M Y · H:i'),
                ] : null,
            ],
        ];
    }
}
