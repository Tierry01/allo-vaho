<?php

namespace App\Filament\Auth\Pages;

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Support\AdminPassword;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Choice of a new password from an e-mailed link. It never bypasses the
 * second factor: the next sign-in still asks for the authenticator code.
 */
class ResetPassword extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.auth.reset-password';

    protected static string $layout = 'layouts.admin-auth';

    #[Locked]
    public ?string $email = null;

    #[Locked]
    public ?string $token = null;

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $done = false;

    public function mount(?string $email = null, #[SensitiveParameter] ?string $token = null): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->email = $email ?? request()->query('email');
        $this->token = $token ?? request()->query('token');
    }

    public function resetPassword(): void
    {
        $this->resetErrorBag();

        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', (string) $exception->secondsUntilAvailable);

            return;
        }

        $this->validate(
            [
                'password' => AdminPassword::rules(),
                'passwordConfirmation' => ['required', 'same:password'],
            ],
            [
                'password.required' => AdminPassword::messages()['required'],
                'password.min' => AdminPassword::messages()['min'],
                'passwordConfirmation.required' => 'Confirmez votre mot de passe.',
                'passwordConfirmation.same' => 'Les mots de passe ne correspondent pas.',
            ],
        );

        $changed = false;

        $status = Password::broker(Filament::getAuthPasswordBroker())->reset(
            [
                'email' => (string) $this->email,
                'token' => (string) $this->token,
                'password' => $this->password,
            ],
            function (CanResetPassword $admin, #[SensitiveParameter] string $password) use (&$changed): void {
                if (! $admin instanceof Admin || ! $admin->isActive()) {
                    return;
                }

                $changed = true;

                // Changing the password and the remember token signs every other session out.
                $admin->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            },
        );

        $this->password = '';
        $this->passwordConfirmation = '';

        if ($status !== Password::PASSWORD_RESET || ! $changed) {
            $this->addError('link', 'invalid');

            return;
        }

        $this->done = true;
    }

    public function getTitle(): string
    {
        return 'Nouveau mot de passe';
    }

    public function loginUrl(): string
    {
        return Filament::getLoginUrl();
    }

    public function requestUrl(): string
    {
        return Filament::getRequestPasswordResetUrl();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return ['title' => $this->getTitle(), 'cardLabel' => 'Nouveau mot de passe'];
    }
}
