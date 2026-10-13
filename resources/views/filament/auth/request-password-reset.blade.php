@php
    $throttle = $errors->first('throttle');
@endphp

<div>
    <div class="av-auth-view is-entering">
        <a class="av-auth-back" href="{{ $this->loginUrl() }}"><x-auth.icon name="arrow-left" />Retour à la connexion</a>
        <span class="av-auth-tile" aria-hidden="true"><x-auth.icon name="lock" /></span>
        <h1 class="av-auth-title">Mot de passe oublié ?</h1>
        <p class="av-auth-lead">Indiquez l’adresse e-mail de votre compte administrateur. Nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

        @if (! $this->sent)
            <form class="av-auth-form" wire:submit="request" novalidate>
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de demandes." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @endif

                <div class="av-auth-field">
                    <label class="av-auth-label" for="forgot-email">Adresse e-mail professionnelle</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('email')])>
                        <x-auth.icon name="mail" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="forgot-email" type="email" inputmode="email" autocomplete="username" placeholder="prenom.nom@allovaho.com" wire:model="email" aria-describedby="forgot-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" required autofocus>
                    </div>
                    <x-auth.field-error id="forgot-email-error" :message="$errors->first('email')" />
                </div>

                <x-auth.submit target="request" label="Envoyer le lien" loading-label="Envoi…" />
            </form>
        @else
            <x-auth.message type="success" title="Vérifiez votre boîte de réception." text="Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé. Il est valable 60 minutes." style="margin-top:28px" />
            <div class="av-auth-actions"><a class="av-auth-btn av-auth-btn--secondary" href="{{ $this->loginUrl() }}"><span>Retour à la connexion</span></a></div>
        @endif
    </div>
</div>
