# 0006 — Permissions et journal d'activité du back-office

- **Statut :** retenue pendant la Phase 2 (décision 0009). `spatie/laravel-permission` 8.3 porte les quatre rôles,
  sans aucune permission ; `spatie/laravel-activitylog` 4.12 porte le journal `auth`. Ni Shield ni plugin d'écran.
  La matrice des permissions, les écrans et la durée de conservation du journal restent à décider (Phase 11).
- **Écart :** aucun. Les deux paquets retenus sont ceux que le Dossier technique v1.3 nomme (§ 4).

## Besoin

- Rôles `super-admin`, `support`, `marketing`, `finance` ; moindre privilège ; permissions explicites.
- Audit de toute action sensible (§ 18), dans la table `activity_log` prévue au § 13.
- Rôles et permissions gérables depuis le back-office (« Rôles & permissions », « Journal d'activité »).

## Options étudiées

| Option | Apport | Limite |
|---|---|---|
| Policies et Gates Laravel seules | Natif, aucune dépendance | Rôles figés dans le code : pas d'édition depuis le back-office |
| Policies + `spatie/laravel-permission` 8.3.0 | Rôles et permissions en base, cache intégré, prévu par le dossier | Une dépendance (PHP ^8.3, Laravel ^12 \| ^13 : compatible) |
| + `bezhansalleh/filament-shield` 4.3.1 | Génère permissions et écran des rôles | Génère une permission par action de chaque ressource : contraire à « permissions explicites » ; conventions imposées |
| Journal maison | Aucune dépendance | Réécrit ce que fait le paquet prévu |
| `spatie/laravel-activitylog` 4.12.3 | Table `activity_log`, auteur, sujet, propriétés ; prévu par le dossier | Reste en 4.x : la 5.x exige PHP 8.4 |
| + `rmsramos/activitylog` 4.1.0 | Écran Filament prêt à l'emploi | Dépendance tierce pour un écran en lecture seule |

## Recommandation

1. **Autorisation** : une Policy Laravel par ressource. Filament 5 applique nativement les Policies
   aux ressources ; aucune couche supplémentaire n'est nécessaire.
2. **Rôles et permissions** : `spatie/laravel-permission`, guard `admin`. Les permissions sont
   déclarées dans le code (une liste nommée par domaine, par exemple `refunds.approve`) et créées par
   un seeder idempotent. Aucune permission n'est accordée par défaut.
3. **Super-admin** : traité par `Gate::before`, limité au guard `admin`.
4. **Audit** : `spatie/laravel-activitylog`. Les actions sensibles sont journalisées explicitement
   (qui, quoi, sur quoi, avant/après), sans jamais enregistrer de secret, mot de passe ou OTP.
5. **Écrans** : ressources Filament écrites dans le projet (rôles en Phase 11, journal en lecture seule).
6. **Non retenus** : Filament Shield et `rmsramos/activitylog`. Leur intérêt se limite à des écrans que
   Filament permet d'écrire simplement, au prix de conventions qui s'écartent des permissions explicites.

## Points à valider

- la recommandation ci-dessus (deux paquets Spatie, pas de plugin Filament tiers) ;
- la matrice rôles × permissions : le dossier nomme les rôles et cite « remboursements : rôle finance »,
  mais ne fournit pas la matrice complète ;
- la durée de conservation du journal d'activité (non précisée).
