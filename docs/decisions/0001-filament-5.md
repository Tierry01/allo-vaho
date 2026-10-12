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
