<?php

namespace App\Filament\Auth\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LogicException;

/**
 * Mandatory enrolment of the authenticator app. Every protected page of the
 * panel redirects here until a first code has been verified; the secret is
 * only stored once that code is accepted, and is shown on this screen only.
 */
class SetUpTwoFactor extends SimplePage
{
    use WithRateLimiting;

    protected string $view = 'filament.auth.set-up-two-factor';

    protected static string $layout = 'layouts.admin-auth';

    /**
     * Secret being enrolled, encrypted: it never travels in clear in the
     * component state and is discarded if the page is left.
     */
    #[Locked]
    public string $pendingSecret = '';

    public string $code = '';

    public bool $activated = false;

    public int $attempts = 0;

    public function mount(): void
    {
        if ($this->provider()->isEnabled($this->admin())) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->pendingSecret = encrypt($this->provider()->generateSecret());
    }

    public function activate(): void
    {
        $this->attempts++;
        $this->resetErrorBag();

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', (string) $exception->secondsUntilAvailable);

            return;
        }

        $code = $this->code;
        $this->code = '';

        if (! preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::withMessages(['code' => 'incomplete']);
        }

        $secret = $this->secret();

        if (! $this->provider()->verifyCode($code, $secret, shouldPreventCodeReuse: true)) {
            throw ValidationException::withMessages(['code' => 'invalid']);
        }

        $this->provider()->saveSecret($this->admin(), $secret);
        $this->pendingSecret = '';
        $this->activated = true;

        session()->regenerate();
    }

    public function getTitle(): string
    {
        return 'Double authentification';
    }

    public function panelUrl(): string
    {
        return Filament::getUrl();
    }

    public function qrCodeDataUri(): string
    {
        return $this->provider()->generateQrCodeDataUri($this->secret());
    }

    /**
     * Setup key in groups of four characters, easier to type by hand.
     */
    public function formattedSecret(): string
    {
        return trim(chunk_split($this->secret(), 4, ' '));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLayoutData(): array
    {
        return ['title' => $this->getTitle(), 'cardLabel' => 'Configuration de la double authentification'];
    }

    protected function secret(): string
    {
        return decrypt($this->pendingSecret);
    }

    protected function provider(): AppAuthentication
    {
        $provider = Filament::getMultiFactorAuthenticationProviders()['app'] ?? null;

        if (! $provider instanceof AppAuthentication) {
            throw new LogicException('The admin panel must enable app authentication.');
        }

        return $provider;
    }

    protected function admin(): HasAppAuthentication
    {
        $admin = Filament::auth()->user();

        if (! $admin instanceof HasAppAuthentication) {
            throw new LogicException('The authenticated model must support app authentication.');
        }

        return $admin;
    }
}
