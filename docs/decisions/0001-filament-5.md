# 0001 — Back-office sur Filament 5

- **Statut :** validée le 12/10/2026. Installation non réalisée (choix de plugins et de modèle d'administrateur à valider).
- **Écart :** le Dossier technique v1.3 (§ 4, p. 4) prévoit « Filament 3 ».

## Contexte

Le projet démarre sur Laravel 13.23 / PHP 8.3.14. Filament 3.3 accepte encore
Laravel 13, mais se trouve deux versions majeures derrière la version courante.
Démarrer dessus impliquerait une migration v3 → v4 → v5 pendant la vie du projet.

## Décision

Utiliser Filament 5 pour le back-office (et, le moment venu, le portail Camtel).

## Compatibilité vérifiée (Packagist, 12/10/2026)

| Paquet | Version | Contraintes | Résultat |
|---|---|---|---|
| filament/filament | 5.10.1 | PHP ^8.2, ext-intl, Laravel ^11.28 \| ^12 \| ^13 | Compatible |
| livewire/livewire | ^4.4.7 (requis par Filament 5) | PHP ^8.1, Laravel ^10 à ^13 | Compatible |
| spatie/laravel-permission | 8.3.0 | PHP ^8.3, Laravel ^12 \| ^13 | Compatible |
| spatie/laravel-activitylog | 4.12.3 | PHP ^8.1, Laravel ^8 à ^13 | Compatible |
| spatie/laravel-activitylog | 5.1.1 | **PHP ^8.4** | Incompatible avec PHP 8.3 |

`composer require filament/filament:^5.10 spatie/laravel-permission:^8.3 spatie/laravel-activitylog:^4.12 --dry-run`
résout sans conflit : 37 installations, aucune mise à jour des paquets existants.

## Conséquences

- Livewire 4 et Tailwind CSS 4 (déjà déclaré dans `package.json`) font partie du socle.
- Filament 5 embarque la double authentification TOTP (`pragmarx/google2fa` est une
  dépendance directe) : pas de plugin tiers pour la 2FA obligatoire du back-office.
- Les plugins et exemples écrits pour Filament 3 ne sont pas réutilisables tels quels.
- `spatie/laravel-activitylog` reste en 4.x tant que le projet est en PHP 8.3.

## Vérifications complémentaires (documentation Filament 5.x, 12/10/2026)

- Prérequis : PHP 8.2+, Laravel 11.28+, Tailwind CSS 4.1+.
- Guard : `->authGuard()` et `->authPasswordBroker()` se règlent par panel (voir décision 0005).
- 2FA : `multiFactorAuthentication([...], isRequired: true)` avec `AppAuthentication` (TOTP) et codes de secours.
- Thème : `php artisan make:filament-theme` crée une feuille de style Tailwind 4 compilée par Vite et
  enregistrée par `->viteTheme()` ; couleurs, police, logo et mode sombre se règlent sur le panel.
- Police : Filament charge par défaut ses polices depuis le CDN Bunny Fonts. À traiter en Phase 1
  (hébergement local des polices du Brief).

## Stratégie d'installation

À exécuter une fois les décisions 0005 et 0006 validées :

1. `npm install` (jamais exécuté dans ce projet), puis vérifier `npm run build` avec Vite 8.
2. `composer require filament/filament:"^5.10"` — simulation : aucune mise à jour des paquets existants.
3. `php artisan filament:install --panels` (crée `app/Providers/Filament/AdminPanelProvider.php`).
4. Phase 1 — Design system : thème personnalisé et composants, d'après le Brief et les maquettes validées.
5. Phase 2 — Authentification : guard `admin`, table `admins`, 2FA obligatoire, écrans Connexion et Activation.

Ne pas utiliser `filament:install --scaffold` : cette commande écrase des fichiers existants.

## Préparation de la Phase 1 — constats du 12/10/2026

Filament n'est pas encore installé. Vérifié dans l'environnement réel :

| Élément | Version installée | Constat |
|---|---|---|
| Laravel | 13.35.0 | Filament 5 demande 11.28+ |
| PHP | 8.3.14, `ext-intl` présente | Filament 5 demande 8.2+ |
| Vite | 8.3.4 | `npm run build` réussit |
| Tailwind CSS | 4.3.3 | Filament 5 demande 4.1+ |
| laravel-vite-plugin | 3.2.0 | Télécharge les polices à la compilation et les sert depuis `public/build` |
| Node | 24.13.0 | Suffisant pour Vite 8 et Tailwind 4 |

### Stratégie du panneau

- **Panneau distinct du parcours mobile :** un panel Filament `admin`, sans lien avec `/api/v1`.
  Son chemin d'accès est à valider (voir ci-dessous).
- **Authentification séparée :** guard `admin` et table `admins` (décision 0005, Phase 2).
  En Phase 1 le panel est installé sans écran d'authentification métier.
- **Fidélité aux maquettes :** les variables `--av-*` des maquettes extraites
  (`docs/references/extracted/`) deviennent la source des couleurs, rayons et ombres du thème.
  Les écrans Connexion et Activation des maquettes ont une mise en page propre : ils seront
  réalisés comme pages personnalisées, pas comme l'écran de connexion par défaut de Filament.
- **Thèmes clair et sombre :** les maquettes définissent les deux par `data-theme` et mémorisent le
  choix sous la clé `av.theme`. Filament gère son propre mode sombre (classe `dark`) : le thème
  personnalisé doit faire correspondre les deux jeux de variables.
- **Compilation locale :** thème créé par `php artisan make:filament-theme`, compilé par Vite,
  enregistré par `->viteTheme()`.
- **Polices et ressources externes :** aucune ressource chargée depuis un CDN à l'exécution.
  Nunito et Noto Sans (Brief Design) sont servies localement ; Filament est configuré avec
  `LocalFontProvider` au lieu de Bunny Fonts. `vite.config.js` déclare encore la police par défaut
  de Laravel (Instrument Sans), à remplacer.
- **Icônes :** les maquettes utilisent Lucide ; Filament fournit Heroicons par défaut. Le choix du jeu
  d'icônes du back-office est à valider.
- **Rôles, permissions, audit, 2FA :** rien n'est installé en Phase 1. Le panel est structuré pour les
  recevoir (décisions 0005 et 0006) : Policies par ressource, `multiFactorAuthentication(..., isRequired: true)`.

### Points à valider avant l'installation

1. Chemin du panneau (`/admin` par défaut dans Filament ; le dossier ne le fixe pas).
2. Jeu d'icônes : Lucide comme dans les maquettes (dépendance supplémentaire) ou Heroicons.
3. Origine des fichiers de polices : ceux des maquettes extraites, ou téléchargement à la compilation.
