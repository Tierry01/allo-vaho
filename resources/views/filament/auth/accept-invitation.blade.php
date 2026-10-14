@php
    use App\Domain\Admin\Enums\InvitationState;
    use App\Domain\Admin\Support\AdminPassword;

    $throttle = $errors->first('throttle');
    $invitation = $this->invitation();
    $state = $this->state();
    $min = AdminPassword::MIN_LENGTH;
@endphp

<div>
    @if ($this->activated)
        {{-- Activation réussie --}}
        <div class="av-auth-view av-auth-result is-entering" wire:key="success">
            <span class="av-auth-check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg></span>
            <h1 class="av-auth-title">Votre compte est prêt.</h1>
            <p class="av-auth-lead">Votre accès administrateur Allo Vaho a été activé.</p>
            <section class="av-invite-box" aria-label="Récapitulatif du compte">
                <dl class="av-invite-box__list">
                    <div><dt>Nom</dt><dd>{{ $this->activated['name'] }}</dd></div>
                    <div><dt>Adresse e-mail</dt><dd>{{ $this->activated['email'] }}</dd></div>
                    <div><dt>Rôle</dt><dd><span class="av-auth-role">{{ $this->activated['role'] }}</span></dd></div>
                </dl>
            </section>
            <div class="av-auth-actions">
                <a class="av-auth-btn av-auth-btn--primary" href="{{ $this->loginUrl() }}"><span>Se connecter</span><x-auth.icon name="arrow-right" class="av-auth-btn__arrow" /></a>
            </div>
            <p class="av-auth-hint" style="margin-top:14px">À votre première connexion, vous configurerez la vérification en deux étapes.</p>
        </div>
    @elseif ($state === InvitationState::Usable)
        {{-- Invitation valide : formulaire --}}
        <div class="av-auth-view is-entering" wire:key="form">
            <span class="av-auth-kicker"><x-auth.icon name="send" />Invitation personnelle</span>
            <h1 class="av-auth-title">Activez votre compte</h1>
            <p class="av-auth-lead">Votre accès administrateur Allo Vaho vous a été attribué. Il ne vous reste qu’à choisir votre mot de passe.</p>

            <form class="av-auth-form" wire:submit="activate" novalidate x-data="avStrength({ min: {{ $min }} })">
                @if ($throttle)
                    <x-auth.message type="warning" title="Trop de tentatives." :text="'Réessayez dans '.$throttle.' secondes.'" />
                @endif

                <section class="av-invite-box" aria-labelledby="invite-box-title">
                    <div class="av-invite-box__head"><h2 class="av-invite-box__title" id="invite-box-title">Défini par votre invitation</h2><span class="av-invite-box__tag"><x-auth.icon name="lock" />Non modifiable</span></div>
                    <dl class="av-invite-box__list">
                        <div><dt>Adresse e-mail</dt><dd>{{ $invitation->email }}</dd></div>
                        <div><dt>Rôle</dt><dd><span class="av-auth-role">{{ $invitation->role->label() }}</span><span class="av-invite-box__meta">Attribué par un administrateur</span></dd></div>
                    </dl>
                </section>

                <div class="av-auth-field">
                    <label class="av-auth-label" for="activate-name">Nom complet</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('name')])>
                        <x-auth.icon name="user" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="activate-name" type="text" autocomplete="name" placeholder="Prénom Nom" wire:model="name" aria-describedby="activate-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" required autofocus>
                    </div>
                    <x-auth.field-error id="activate-name-error" :message="$errors->first('name')" />
                </div>

                <div class="av-auth-field">
                    <label class="av-auth-label" for="activate-password">Mot de passe</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('password')])>
                        <x-auth.icon name="lock" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="activate-password" type="password" autocomplete="new-password" placeholder="Choisissez un mot de passe" wire:model="password" x-model="password" aria-describedby="activate-password-error pwd-strength" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" required>
                        <button type="button" class="av-auth-control__action" data-password-toggle aria-controls="activate-password" aria-pressed="false" aria-label="Afficher le mot de passe"><x-auth.icon name="eye" class="av-i-show" /><x-auth.icon name="eye-off" class="av-i-hide" /></button>
                    </div>
                    <x-auth.field-error id="activate-password-error" :message="$errors->first('password')" />

                    <div class="av-strength" id="pwd-strength" :data-level="level" data-level="0" wire:ignore.self>
                        <div class="av-strength__head"><span>Sécurité du mot de passe</span><strong x-text="labels[level]" aria-live="polite">—</strong></div>
                        <div class="av-strength__bar" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        <ul class="av-rules" aria-label="Règle du mot de passe" style="grid-template-columns: minmax(0, 1fr)">
                            <li class="av-rule" :class="{ 'is-met': longEnough }"><span class="av-rule__mark"><x-auth.icon name="check" /></span><span>Au moins {{ $min }} caractères</span></li>
                        </ul>
                        <p class="av-strength__note">Seule la longueur est exigée. Une phrase longue est plus sûre qu’un mot court et compliqué.</p>
                    </div>
                </div>

                <div class="av-auth-field">
                    <label class="av-auth-label" for="activate-confirm">Confirmer le mot de passe</label>
                    <div @class(['av-auth-control', 'is-invalid' => $errors->has('passwordConfirmation')])>
                        <x-auth.icon name="lock" class="av-auth-control__icon" />
                        <input class="av-auth-input" id="activate-confirm" type="password" autocomplete="new-password" placeholder="Saisissez-le à nouveau" wire:model="passwordConfirmation" x-model="confirmation" aria-describedby="activate-confirm-error confirm-ok" aria-invalid="{{ $errors->has('passwordConfirmation') ? 'true' : 'false' }}" required>
                        <button type="button" class="av-auth-control__action" data-password-toggle aria-controls="activate-confirm" aria-pressed="false" aria-label="Afficher le mot de passe"><x-auth.icon name="eye" class="av-i-show" /><x-auth.icon name="eye-off" class="av-i-hide" /></button>
                    </div>
                    <x-auth.field-error id="activate-confirm-error" :message="$errors->first('passwordConfirmation')" />
                    <p class="av-auth-ok" id="confirm-ok" x-show="matches" x-cloak><x-auth.icon name="check-circle" /><span>Les mots de passe correspondent.</span></p>
                </div>

                <x-auth.submit target="activate" label="Activer mon compte" loading-label="Activation…" arrow />
            </form>

            <div class="av-auth-foot">
                <p class="av-auth-secure"><x-auth.icon name="shield" />La vérification en deux étapes sera demandée à chaque connexion.</p>
            </div>
        </div>
    @elseif ($state === InvitationState::Expired)
        {{-- Invitation expirée --}}
        <div class="av-auth-view av-auth-result is-entering" wire:key="expired">
            <span class="av-auth-tile av-auth-tile--warning" aria-hidden="true"><x-auth.icon name="clock" /></span>
            <h1 class="av-auth-title">Invitation expirée</h1>
            <p class="av-auth-lead">Ce lien d’invitation n’est plus valide. Il a expiré le {{ $invitation->expires_at->timezone(config('app.display_timezone'))->locale('fr')->translatedFormat('j M Y') }}.</p>

            @if ($throttle)
                <x-auth.message type="warning" title="Trop de demandes." :text="'Réessayez dans '.$throttle.' secondes.'" />
            @elseif ($this->renewalRequested)
                <x-auth.message type="success" title="Votre demande a été transmise." text="Si l’administrateur qui vous a invité peut être joint, il recevra votre demande de nouvelle invitation." />
            @endif

            <div class="av-auth-actions">
                @unless ($this->renewalRequested)
                    <button type="button" class="av-auth-btn av-auth-btn--primary" wire:click="requestNewInvitation" wire:loading.attr="disabled"><span>Demander une nouvelle invitation</span></button>
                @endunless
                @if (config('admin.support_email'))
                    <a class="av-auth-btn av-auth-btn--secondary" href="mailto:{{ config('admin.support_email') }}"><span>Contacter le support</span></a>
                @endif
            </div>
        </div>
    @elseif ($state === InvitationState::Accepted)
        {{-- Invitation déjà utilisée --}}
        <div class="av-auth-view av-auth-result is-entering" wire:key="used">
            <span class="av-auth-tile av-auth-tile--neutral" aria-hidden="true"><x-auth.icon name="check-circle" /></span>
            <h1 class="av-auth-title">Cette invitation a déjà été utilisée.</h1>
            <p class="av-auth-lead">Le compte associé est déjà actif. Connectez-vous avec votre adresse e-mail et votre mot de passe.</p>
            <div class="av-auth-actions">
                <a class="av-auth-btn av-auth-btn--primary" href="{{ $this->loginUrl() }}"><span>Se connecter</span><x-auth.icon name="arrow-right" class="av-auth-btn__arrow" /></a>
            </div>
        </div>
    @else
        {{-- Lien invalide ou invitation révoquée : même écran, sans rien révéler --}}
        <div class="av-auth-view av-auth-result is-entering" wire:key="invalid">
            <span class="av-auth-tile av-auth-tile--danger" aria-hidden="true"><x-auth.icon name="unlink" /></span>
            <h1 class="av-auth-title">Lien d’invitation invalide</h1>
            <p class="av-auth-lead">Ce lien est incomplet ou a été modifié. Ouvrez-le directement depuis l’e-mail d’invitation, ou demandez un nouveau lien à l’administrateur qui vous a invité.</p>
            <div class="av-auth-actions">
                @if (config('admin.support_email'))
                    <a class="av-auth-btn av-auth-btn--secondary" href="mailto:{{ config('admin.support_email') }}"><span>Contacter le support</span></a>
                @endif
                <a class="av-auth-link" href="{{ $this->loginUrl() }}">J’ai déjà un compte · Se connecter</a>
            </div>
        </div>
    @endif
</div>
