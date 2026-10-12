<x-filament-panels::page>
    @php
        $swatches = [
            'Marque' => ['--av-blue', '--av-blue-dark', '--av-blue-darker', '--av-ink', '--av-sand', '--av-clay', '--av-sun'],
            'Surfaces' => ['--av-bg', '--av-surface', '--av-surface-2', '--av-surface-3', '--av-border', '--av-border-strong', '--av-sb-bg'],
            'Statuts' => ['--av-success', '--av-success-bg', '--av-warning', '--av-warning-bg', '--av-danger', '--av-danger-bg', '--av-info', '--av-info-bg'],
        ];
    @endphp

    <x-admin.alert tone="info" title="Page de référence.">
        Valeurs d'exemple uniquement. Visible en environnement local.
    </x-admin.alert>

    <x-filament::section heading="Couleurs" description="Tokens --av-* des maquettes validées.">
        <div class="grid gap-6">
            @foreach ($swatches as $group => $tokens)
                <div>
                    <p class="av-caption av-strong">{{ $group }}</p>
                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
                        @foreach ($tokens as $token)
                            <div>
                                <div class="h-12 rounded-lg" style="background: var({{ $token }}); box-shadow: inset 0 0 0 1px var(--av-border)"></div>
                                <p class="av-caption av-mono mt-1">{{ $token }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Typographie" description="Nunito pour les titres, Noto Sans pour le texte.">
        <div class="grid gap-3">
            <p class="av-h1">Titre de page — Nunito 800, 26 px</p>
            <p class="av-h2">Titre de section — Nunito 800, 18 px</p>
            <p class="av-h3">Titre de carte — Nunito 700, 15 px</p>
            <p>Texte courant — Noto Sans 400, 14 px. Votre forfait se termine dans 3 jours.</p>
            <p class="av-strong">Texte fort — Noto Sans 600.</p>
            <p class="av-caption">Légende — 12 px, texte atténué.</p>
            <p class="av-num">Chiffres tabulaires : 1 250 000 FCFA · +237 6 99 00 11 22</p>
            <p class="av-mono">Code technique : AV-EXEMPLE-0000</p>
        </div>
    </x-filament::section>

    <x-filament::section heading="Boutons">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button>Principal</x-filament::button>
            <x-filament::button color="gray">Secondaire</x-filament::button>
            <x-filament::button color="danger">Danger</x-filament::button>
            <x-filament::button outlined>Contour</x-filament::button>
            <x-filament::button icon="lucide-plus">Avec icône</x-filament::button>
            <x-filament::button size="sm" color="gray">Petit</x-filament::button>
            <x-filament::button disabled>Désactivé</x-filament::button>
            <x-filament::icon-button icon="lucide-refresh-cw" label="Actualiser" color="gray" />
            <x-filament::link href="#">Lien</x-filament::link>
        </div>
    </x-filament::section>

    <x-filament::section heading="Badges">
        <div class="grid gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.badge>Neutre</x-admin.badge>
                <x-admin.badge tone="success">Succès</x-admin.badge>
                <x-admin.badge tone="warning">Attention</x-admin.badge>
                <x-admin.badge tone="danger">Erreur</x-admin.badge>
                <x-admin.badge tone="info">Information</x-admin.badge>
                <x-admin.badge outline :dot="false">Contour</x-admin.badge>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge color="gray">Neutre</x-filament::badge>
                <x-filament::badge color="success">Succès</x-filament::badge>
                <x-filament::badge color="warning">Attention</x-filament::badge>
                <x-filament::badge color="danger">Erreur</x-filament::badge>
                <x-filament::badge color="info">Information</x-filament::badge>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Champs">
        <div class="grid gap-4 md:grid-cols-3">
            <label class="av-field">
                <span class="av-label">Champ texte</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" placeholder="Saisir une valeur" />
                </x-filament::input.wrapper>
                <span class="av-help">Texte d'aide.</span>
            </label>

            <label class="av-field">
                <span class="av-label">Liste</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select>
                        <option>Option A</option>
                        <option>Option B</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label class="av-field">
                <span class="av-label">Champ en erreur</span>
                <x-filament::input.wrapper :valid="false">
                    <x-filament::input type="text" value="Valeur refusée" aria-invalid="true" />
                </x-filament::input.wrapper>
                <span class="av-help" style="color: var(--av-danger)">Message d'erreur.</span>
            </label>

            <label class="av-field">
                <span class="av-label">Champ désactivé</span>
                <x-filament::input.wrapper disabled>
                    <x-filament::input type="text" value="Non modifiable" disabled />
                </x-filament::input.wrapper>
            </label>

            <label class="av-check">
                <x-filament::input.checkbox checked />
                Case à cocher
            </label>
        </div>
    </x-filament::section>

    <x-filament::section heading="Alertes">
        <div class="grid gap-3">
            <x-admin.alert tone="success" title="Succès.">L'opération est terminée.</x-admin.alert>
            <x-admin.alert tone="warning" title="Attention.">Une vérification est nécessaire.</x-admin.alert>
            <x-admin.alert tone="danger" title="Erreur.">
                L'opération n'a pas abouti.
                <x-slot:actions>
                    <x-filament::button size="sm" color="gray">Réessayer</x-filament::button>
                </x-slot:actions>
            </x-admin.alert>
        </div>
    </x-filament::section>

    <x-filament::section heading="Tableau">
        {{ $this->table }}
    </x-filament::section>

    <x-filament::section heading="États" description="Vide, erreur, accès restreint, hors ligne, chargement.">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="av-card">
                <x-admin.state kind="empty" title="Rien à afficher" text="Les données apparaîtront ici dès la première activité." />
            </div>
            <div class="av-card">
                <x-admin.state kind="error" title="Ces données n'ont pas pu être chargées" text="Le service n'a pas répondu à temps." code="AV-EXEMPLE-0000">
                    <x-slot:actions>
                        <x-filament::button size="sm" color="gray" icon="lucide-refresh-cw">Réessayer</x-filament::button>
                    </x-slot:actions>
                </x-admin.state>
            </div>
            <div class="av-card">
                <x-admin.state kind="restricted" title="Accès restreint" text="Votre rôle ne permet pas de consulter ces données." />
            </div>
            <div class="av-card">
                <x-admin.state kind="offline" title="Vous êtes hors ligne" text="Vérifiez votre connexion, puis réessayez." />
            </div>
        </div>

        <div class="av-card mt-4">
            <div class="av-card__body av-skeleton-stack" aria-busy="true" aria-label="Chargement">
                <x-admin.skeleton width="40%" :height="16" />
                <x-admin.skeleton width="90%" />
                <x-admin.skeleton width="75%" />
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
