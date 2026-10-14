@php
    $throttle = $errors->first('throttle');
    $codeError = $errors->first('data.multiFactor.app.code');
    $submittedCode = (string) data_get($this->data, 'multiFactor.app.code', '');
@endphp

<div>
    @if ($this->signedInUrl)
        {{-- Connexion réussie, comme dans la maquette --}}
        <div class="av-auth-view av-auth-result is-entering" wire:key="success" x-data x-init="setTimeout(() => window.location.assign(@js($this->signedInUrl)), 1200)">
            <span class="av-auth-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg></span>
            <h1 class="av-auth-title">Connexion réussie</h1>
            <p class="av-auth-lead" role="status">Redirection vers votre tableau de bord…</p>
            <div class="av-auth-progress" aria-hidden="true"><span></span></div>
            <a class="av-auth-link" href="{{ $this->signedInUrl }}">Continuer maintenant</a>
        </div>
    @elseif (! $this->isVerifyingCode())
        {{-- Étape 1 : identifiants --}}
        <div class="av-auth-view is-entering" wire:key="credentials">
            <div class="av-auth-step"><span class="av-auth-step__bars" aria-hidden="true"><span class="is-active"></span><span></span></span>Étape 1 sur 2 · Identifiants</div>
            <h1 class="av-auth-title">Ravi de vous revoir</h1>
            <p class="av-auth-lead">Connectez-vous à votre espace d’administration Allo Vaho.</p>

            <form class="av-auth-form" wire:submit="authenticate" novalidate x-data="avRememberEmail()" x-on:submit="save()">
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de tentatives." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @elseif ($errors->has('credentials'))
                    <x-auth.message type="error" :title="$errors->first('credentials')" text="Vérifiez votre adresse e-mail et votre mot de passe, puis réessayez." />
                @endif

                <div class="av-auth-field">
                    <label class="av-auth-label" for="login-email">Adresse e-mail professionnelle</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('data.email')])>
                        <x-auth.icon name="mail" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="login-email" type="email" inputmode="email" autocomplete="username" placeholder="prenom.nom@allovaho.com" wire:model="data.email" aria-describedby="login-email-error" aria-invalid="{{ $errors->has('data.email') ? 'true' : 'false' }}" required autofocus>
                    </div>
                    <x-auth.field-error id="login-email-error" :message="$errors->first('data.email')" />
                </div>

                <div class="av-auth-field">
                    <div class="av-auth-field__row">
                        <label class="av-auth-label" for="login-password">Mot de passe</label>
                        @if (filament()->hasPasswordReset())
                            <a class="av-auth-link" href="{{ filament()->getRequestPasswordResetUrl() }}">Mot de passe oublié ?</a>
                        @endif
                    </div>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('data.password')])>
                        <x-auth.icon name="lock" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="login-password" type="password" autocomplete="current-password" placeholder="Votre mot de passe" wire:model="data.password" aria-describedby="login-password-error" aria-invalid="{{ $errors->has('data.password') ? 'true' : 'false' }}" required>
                        <button type="button" class="av-auth-control__action" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Afficher le mot de passe"><x-auth.icon name="eye" class="av-i-show" /><x-auth.icon name="eye-off" class="av-i-hide" /></button>
                    </div>
                    <x-auth.field-error id="login-password-error" :message="$errors->first('data.password')" />
                </div>

                {{-- Mémorise uniquement l'adresse e-mail dans ce navigateur : aucune session persistante. --}}
                <label class="av-auth-remember" title="Mémorise votre adresse e-mail sur cet appareil. Votre mot de passe et votre code restent demandés.">
                    <input type="checkbox" x-model="remember" data-remember-email>
                    Se souvenir de moi
                </label>

                <x-auth.submit target="authenticate" label="Se connecter" loading-label="Connexion…" arrow />
            </form>

            <div class="av-auth-foot">
                <p class="av-auth-secure"><x-auth.icon name="shield" />Connexion sécurisée · vérification en deux étapes</p>
                @if (config('admin.support_email'))
                    <p class="av-auth-help">Besoin d’aide ?<a class="av-auth-link" href="mailto:{{ config('admin.support_email') }}">Contacter le support</a></p>
                @endif
            </div>
        </div>
    @else
        {{-- Étape 2 : vérification du code TOTP --}}
        <div class="av-auth-view is-entering" wire:key="verification">
            <button type="button" class="av-auth-back" wire:click="backToCredentials"><x-auth.icon name="arrow-left" />Retour</button>
            <div class="av-auth-step"><span class="av-auth-step__bars" aria-hidden="true"><span class="is-active"></span><span class="is-active"></span></span>Étape 2 sur 2 · Vérification</div>
            <span class="av-auth-tile" aria-hidden="true"><x-auth.icon name="key" /></span>
            <h1 class="av-auth-title">Vérification en deux étapes</h1>
            <p class="av-auth-lead">Entrez le code à 6 chiffres affiché par votre application d'authentification.</p>

            <form class="av-auth-form" wire:submit="authenticate" novalidate>
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de tentatives." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @elseif ($errors->has('credentials'))
                    <x-auth.message type="error" :title="$errors->first('credentials')" text="Revenez à l’étape précédente et réessayez." />
                @elseif ($codeError && strlen($submittedCode) < 6)
                    <x-auth.message type="error" title="Entrez les 6 chiffres du code." />
                @elseif ($codeError)
                    @php($remaining = $this->remainingCodeAttempts())
                    <x-auth.message type="error" title="Ce code est incorrect." :text="match (true) {
                        $remaining === null => 'Vérifiez-le et réessayez.',
                        $remaining === 0 => 'Vérifiez-le et réessayez dans une minute.',
                        default => 'Vérifiez-le et réessayez. '.$remaining.' '.($remaining > 1 ? 'tentatives restantes' : 'tentative restante').'.',
                    }" />
                @endif

                <fieldset class="av-auth-field">
                    <legend class="av-auth-label" id="otp-label" style="margin-bottom:8px">Code de vérification</legend>
                    <x-auth.otp wire:key="otp-{{ $this->attempts }}" model="data.multiFactor.app.code" submit="authenticate" labelledby="otp-label" describedby="otp-hint" :invalid="filled($codeError)" />
                    <p class="av-auth-hint" id="otp-hint" style="margin-top:8px">Vous pouvez coller le code complet.</p>
                </fieldset>

                <x-auth.submit target="authenticate" label="Vérifier et continuer" loading-label="Vérification…" />

                <div class="av-auth-resend">
                    <span class="av-auth-resend__timer">Vous n'avez plus accès à votre application ? Contactez un Super Admin.</span>
                </div>
            </form>
        </div>
    @endif
</div>
