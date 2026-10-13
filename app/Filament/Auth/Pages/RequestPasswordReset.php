<?php

namespace App\Filament\Auth\Pages;

use App\Domain\Admin\Models\Admin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Timebox;
use SensitiveParameter;

/**
 * "Forgot password" request. The answer is identical whether or not an
 * account matches the address, and takes the same time to arrive.
 */
class RequestPasswordReset extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.auth.request-password-reset';

    protected static string $layout = 'layouts.admin-auth';

    public string $email = '';

    public bool $sent = false;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function request(): void
    {
        $this->resetErrorBag();

        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', (string) $exception->secondsUntilAvailable);

            return;
        }

        $this->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Saisissez votre adresse e-mail.', 'email.email' => 'Cette adresse e-mail n’est pas valide.'],
        );

        app(Timebox::class)->call(function (): void {
            Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                ['email' => $this->email],
                function (CanResetPassword $admin, #[SensitiveParameter] string $token): void {
                    if (! $admin instanceof Admin || ! $admin->isActive()) {
                        return;
                    }

                    $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                    $notification->url = Filament::getResetPasswordUrl($token, $admin);

                    $admin->notify($notification);

                    event(new PasswordResetLinkSent($admin));
                },
            );
        }, 400_000);

        $this->sent = true;
    }

    public function getTitle(): string
    {
        return 'Mot de passe oublié';
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
        return ['title' => $this->getTitle(), 'cardLabel' => 'Mot de passe oublié'];
    }
}
