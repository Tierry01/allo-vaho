<?php

namespace App\Filament\Auth\Pages;

use App\Domain\Admin\Events\TwoFactorChallengeFailed;
use App\Domain\Admin\Models\Admin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

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

    /**
     * Set once the code is verified: the "Connexion réussie" state of the
     * mockup is shown, then the browser goes to this address.
     */
    #[Locked]
    public ?string $signedInUrl = null;

    public function authenticate(): ?LoginResponse
    {
        $this->attempts++;
        $this->resetErrorBag();

        // No persistent sign-in: whatever the browser sends, the session is never
        // remembered, so it always expires and always requires the second factor.
        unset($this->data['remember']);

        $admin = $this->isVerifyingCode() ? $this->getUserUndertakingMultiFactorAuthentication() : null;

        try {
            $response = parent::authenticate();

            // Fully signed in after the code: show the success state. An account that
            // still has to enrol is sent straight on, to the mandatory enrolment.
            if ($response && $admin instanceof Admin && Filament::auth()->check()) {
                $this->signedInUrl = session()->pull('url.intended', Filament::getUrl());
                $this->data['password'] = '';

                return null;
            }

            return $response;
        } catch (ValidationException $exception) {
            if ($admin instanceof Admin && array_key_exists('data.multiFactor.app.code', $exception->errors())) {
                event(new TwoFactorChallengeFailed($admin));
            }

            throw $exception;
        }
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

    /**
     * Code attempts left before the challenge is rate limited. Only shown at
     * the second step, once the password has been proved: it reveals nothing
     * about the account that the person does not already know.
     */
    public function remainingCodeAttempts(): ?int
    {
        $admin = $this->getUserUndertakingMultiFactorAuthentication();

        if (! $admin) {
            return null;
        }

        $challenge = $this->getMultiFactorChallenge();

        return max(0, RateLimiter::remaining('filament-multi-factor-challenge:'.Filament::getUserScopedAuthIdentifier($admin), $challenge->getMaxRateLimiterAttempts()));
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
