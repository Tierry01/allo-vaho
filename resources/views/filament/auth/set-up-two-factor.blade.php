@php
    $throttle = $errors->first('throttle');
    $codeError = $errors->first('code');
@endphp

<div>
    @if (! $this->activated)
        {{-- Enrôlement : aucune maquette n'existe, écran dérivé de l'étape de vérification --}}
        <div class="av-auth-view is-entering" wire:key="set-up">
            <div class="av-auth-step"><span class="av-auth-step__bars" aria-hidden="true"><span class="is-active"></span><span class="is-active"></span></span>Étape 2 sur 2 · Configuration</div>
            <h1 class="av-auth-title">Activez la double authentification</h1>
            <p class="av-auth-lead">Elle protège votre accès. Un code vous sera demandé à chaque connexion.</p>

            <form class="av-auth-form" wire:submit="activate" novalidate>
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de tentatives." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @elseif ($codeError === 'incomplete')
                    <x-auth.message type="error" title="Entrez les 6 chiffres du code." />
                @elseif ($codeError)
                    <x-auth.message type="error" title="Ce code est incorrect." text="Vérifiez l'heure de votre téléphone, puis saisissez le code affiché actuellement." />
                @endif

                <div class="av-auth-setup">
                    <ol class="av-auth-setup__steps">
                        <li><span>Ouvrez <strong>Google Authenticator</strong> sur votre téléphone.</span></li>
                        <li><span>Scannez le <strong>QR code</strong> ci-dessous, ou saisissez la clé.</span></li>
                        <li><span>Entrez le <strong>code à 6 chiffres</strong> affiché par l'application.</span></li>
                    </ol>

                    <div class="av-auth-setup__box">
                        <div class="av-auth-setup__qr"><img src="{{ $this->qrCodeDataUri() }}" alt="QR code de configuration de la double authentification" width="132" height="132"></div>
                        <div class="av-auth-setup__manual">
                            <span class="av-auth-setup__manual-label">Clé de configuration</span>
                            <span class="av-auth-setup__key" id="setup-key">{{ $this->formattedSecret() }}</span>
                            <span class="av-auth-setup__manual-hint">À saisir si vous ne pouvez pas scanner le code. Elle ne sera plus affichée ensuite.</span>
                        </div>
                    </div>
                </div>

                <fieldset class="av-auth-field">
                    <legend class="av-auth-label" id="setup-code-label" style="margin-bottom:8px">Code de vérification</legend>
                    <x-auth.otp wire:key="setup-otp-{{ $this->attempts }}" model="code" labelledby="setup-code-label" describedby="setup-code-hint" :invalid="filled($codeError)" />
                    <p class="av-auth-hint" id="setup-code-hint" style="margin-top:8px">Vous pouvez coller le code complet.</p>
                </fieldset>

                <x-auth.submit target="activate" label="Activer la double authentification" loading-label="Activation…" />
            </form>
        </div>
    @else
        <div class="av-auth-view av-auth-result is-entering" wire:key="activated" x-data x-init="setTimeout(() => window.location.assign(@js($this->panelUrl())), 2200)">
            <span class="av-auth-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg></span>
            <h1 class="av-auth-title">Double authentification activée</h1>
            <p class="av-auth-lead" role="status">Redirection vers votre tableau de bord…</p>
            <div class="av-auth-progress" aria-hidden="true"><span></span></div>
            <a class="av-auth-link" href="{{ $this->panelUrl() }}">Continuer maintenant</a>
        </div>
    @endif
</div>
