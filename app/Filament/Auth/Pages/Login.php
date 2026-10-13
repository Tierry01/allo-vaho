<?php

namespace App\Filament\Auth\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Back-office sign-in. The authentication logic is Filament's (rate limiting,
 * timing protection, second-factor challenge before any session is opened);
 * only the interface is replaced, to match the validated mockups.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    protected static string $layout = 'layouts.admin-auth';

    /**
     * Incremented on each submission, so the code boxes start empty again.
     */
    public int $attempts = 0;

    public function authenticate(): ?LoginResponse
    {
        $this->attempts++;
        $this->resetErrorBag();

        // No persistent sign-in: whatever the browser sends, the session is never
        // remembered, so it always expires and always requires the second factor.
        unset($this->data['remember']);

        return parent::authenticate();
    }

    /**
     * "Se souvenir de moi" only keeps the e-mail address in the browser; it is
     * not a field of this form and never reaches the authentication guard.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }

    /**
     * Leave the second step and start again from the credentials.
     */
    public function backToCredentials(): void
    {
        $this->userUndertakingMultiFactorAuthentication = null;
        $this->data['password'] = '';
        $this->data['multiFactor'] = [];
        $this->resetErrorBag();
    }

    public function isVerifyingCode(): bool
    {
        return filled($this->userUndertakingMultiFactorAuthentication);
    }

    public function getTitle(): string
    {
        return 'Connexion';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return ['title' => $this->getTitle(), 'cardLabel' => 'Connexion'];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->email()
            ->required()
            ->validationMessages([
                'required' => 'Saisissez votre adresse e-mail.',
                'email' => 'Cette adresse e-mail n’est pas valide.',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->password()
            ->required()
            ->validationMessages([
                'required' => 'Saisissez votre mot de passe.',
            ]);
    }

    /**
     * The same answer whatever the reason: unknown address, wrong password,
     * suspended or deleted account.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'credentials' => 'Les informations saisies sont incorrectes.',
        ]);
    }

    /**
     * Too many attempts are reported inside the card, not as a toast.
     */
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $this->addError('throttle', (string) $exception->secondsUntilAvailable);

        return null;
    }
}
