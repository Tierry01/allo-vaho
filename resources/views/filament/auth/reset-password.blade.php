@php
    use App\Domain\Admin\Support\AdminPassword;

    $throttle = $errors->first('throttle');
    $min = AdminPassword::MIN_LENGTH;
@endphp

<div>
    @if ($this->done)
        <div class="av-auth-view av-auth-result is-entering" wire:key="done">
            <span class="av-auth-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg></span>
            <h1 class="av-auth-title">Mot de passe modifié</h1>
            <p class="av-auth-lead" role="status">Connectez-vous avec votre nouveau mot de passe. Votre code de vérification vous sera demandé.</p>
            <div class="av-auth-actions"><a class="av-auth-btn av-auth-btn--primary" href="{{ $this->loginUrl() }}"><span>Se connecter</span><x-auth.icon name="arrow-right" class="av-auth-btn__arrow" /></a></div>
        </div>
    @elseif ($errors->has('link'))
        <div class="av-auth-view av-auth-result is-entering" wire:key="invalid">
            <span class="av-auth-tile av-auth-tile--warning" aria-hidden="true"><x-auth.icon name="clock" /></span>
            <h1 class="av-auth-title">Lien expiré ou déjà utilisé</h1>
            <p class="av-auth-lead" role="alert">Ce lien de réinitialisation n’est plus valide. Demandez-en un nouveau : il sera valable 60 minutes.</p>
            <div class="av-auth-actions"><a class="av-auth-btn av-auth-btn--primary" href="{{ $this->requestUrl() }}"><span>Demander un nouveau lien</span></a></div>
        </div>
    @else
        {{-- Formulaire dérivé de la maquette Activation : mêmes champs et mêmes composants --}}
        <div class="av-auth-view is-entering" wire:key="form">
            <span class="av-auth-tile" aria-hidden="true"><x-auth.icon name="lock" /></span>
            <h1 class="av-auth-title">Choisissez un nouveau mot de passe</h1>
            <p class="av-auth-lead">Pour le compte <strong>{{ $this->email }}</strong>. Votre code de vérification restera demandé à la connexion.</p>

            <form class="av-auth-form" wire:submit="resetPassword" novalidate x-data="avStrength({ min: {{ $min }} })">
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de tentatives." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @endif

                <div class="av-auth-field">
                    <label class="av-auth-label" for="reset-password">Mot de passe</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('password')])>
                        <x-auth.icon name="lock" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="reset-password" type="password" autocomplete="new-password" placeholder="Choisissez un mot de passe" wire:model="password" x-model="password" aria-describedby="reset-password-error pwd-strength" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" required autofocus>
                        <button type="button" class="av-auth-control__action" data-password-toggle aria-controls="reset-password" aria-pressed="false" aria-label="Afficher le mot de passe"><x-auth.icon name="eye" class="av-i-show" /><x-auth.icon name="eye-off" class="av-i-hide" /></button>
                    </div>
                    <x-auth.field-error id="reset-password-error" :message="$errors->first('password')" />

                    <div class="av-strength" id="pwd-strength" :data-level="level" data-level="0" wire:ignore.self>
                        <div class="av-strength__head"><span>Sécurité du mot de passe</span><strong x-text="labels[level]">—</strong></div>
                        <div class="av-strength__bar" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        <ul class="av-rules" style="grid-template-columns: minmax(0, 1fr)">
                            <li class="av-rule" :class="{ 'is-met': longEnough }"><span class="av-rule__mark"><x-auth.icon name="check" /></span><span>Au moins {{ $min }} caractères</span></li>
                        </ul>
                        <p class="av-strength__note">Seule la longueur est exigée. Une phrase longue est plus sûre qu’un mot court et compliqué.</p>
                    </div>
                </div>

                <div class="av-auth-field">
                    <label class="av-auth-label" for="reset-confirm">Confirmer le mot de passe</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('passwordConfirmation')])>
                        <x-auth.icon name="lock" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="reset-confirm" type="password" autocomplete="new-password" placeholder="Saisissez-le à nouveau" wire:model="passwordConfirmation" aria-describedby="reset-confirm-error" aria-invalid="{{ $errors->has('passwordConfirmation') ? 'true' : 'false' }}" required>
                        <button type="button" class="av-auth-control__action" data-password-toggle aria-controls="reset-confirm" aria-pressed="false" aria-label="Afficher le mot de passe"><x-auth.icon name="eye" class="av-i-show" /><x-auth.icon name="eye-off" class="av-i-hide" /></button>
                    </div>
                    <x-auth.field-error id="reset-confirm-error" :message="$errors->first('passwordConfirmation')" />
                </div>

                <x-auth.submit target="resetPassword" label="Enregistrer le mot de passe" loading-label="Enregistrement…" />
            </form>
        </div>
    @endif
</div>
