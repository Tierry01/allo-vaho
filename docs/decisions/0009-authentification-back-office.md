# 0009 — Phase 2 : authentification du back-office

- **Statut :** plan approuvé le 13/10/2026. Réalisation par étapes, chacune validée avant la suivante.
- **Périmètre :** authentification des administrateurs du back-office uniquement. L'authentification
  mobile (OTP, Google, Apple) est hors périmètre.
- **Écart au Dossier technique v1.3 :** aucun. Complète la décision [0005](0005-authentification-administrateur.md).
- **Contrat OpenAPI :** inchangé. L'authentification du back-office passe par des pages web en session.

## Décisions

| Sujet | Décision |
|---|---|
| Stockage | Table `admins`, distincte de `users` |
| Laravel | Guard `admin` (session), provider `admins`, broker de mots de passe `admins` |
| Création de compte | Sur invitation uniquement ; aucune inscription libre |
| Invitation | Personnelle, à usage unique, révocable, valable 7 jours ; seule l'empreinte du jeton est stockée |
| Premier super-administrateur | Commande Artisan qui émet une invitation ; aucun mot de passe prédéfini, affiché ou journalisé |
| Rôles | `super-admin`, `support`, `marketing`, `finance` ; matrice de permissions et écrans en Phase 11 |
| Double authentification | TOTP obligatoire pour tous ; aucun accès au panneau avant enrôlement et vérification |
| Mot de passe | **8 caractères minimum**, sans règle de composition imposée |
| Récupération du mot de passe | Lien par e-mail valable 60 minutes ; réponse identique que le compte existe ou non |
| Verrouillage | Limitation des tentatives ; aucun verrouillage définitif d'un compte |
| Sessions | Expiration après 120 minutes d'inactivité ; cookie sécurisé hors environnement local |
| E-mails | Pilote `log` ; aucun envoi réel avant le choix explicite d'un prestataire SMTP |
| Écrans | Maquettes validées Connexion et Activation ; sans compte à rebours de renvoi ni changement de mode |

## Mot de passe et hachage

Constaté dans le projet : hachage **bcrypt**, 12 tours (`BCRYPT_ROUNDS`), revérification de l'algorithme
activée, re-hachage à la connexion activé. `config/hashing.php` n'est pas publié : ce sont les valeurs de Laravel.

bcrypt ne prend en compte que les **72 premiers octets**. Sans réglage, Laravel hache la valeur sans prévenir
et le reste est ignoré. Deux protections :

- `BCRYPT_LIMIT=72` : le hachage refuse (exception) toute valeur plus longue au lieu de la tronquer ;
- la validation des formulaires refuse un mot de passe de plus de 72 octets, avec un message clair.

La longueur se compte en octets : un caractère accentué en occupe deux.

Avec un minimum de 8 caractères, la protection contre les essais répétés repose sur la limitation des
tentatives, le coût du hachage et la double authentification obligatoire.

## TOTP : fenêtre de validation

Bibliothèque utilisée par Filament 5.10 : `pragmarx/google2fa` 9.1 (codes à 6 chiffres, pas de 30 secondes, SHA-1).

La « fenêtre » est le nombre de pas acceptés **de part et d'autre** de l'instant présent. Mesuré avec la
bibliothèque installée :

| Fenêtre | Codes valides en même temps | Durée d'acceptation |
|---|---|---|
| 8 (valeur par défaut de Filament) | 17 | 8 min 30 |
| 2 | 5 | 2 min 30 |
| **1 (retenue)** | 3 | 1 min 30, soit ±30 secondes |
| 0 | 1 | 30 secondes |

La fenêtre **1** est la plus stricte qui reste utilisable : elle tolère le temps de saisie et un léger écart
d'horloge. La fenêtre 0 rejetterait un code saisi en fin de période.

Anti-rejeu, mesuré : après acceptation d'un code, ce même code et tout code antérieur sont refusés ;
seul un code plus récent est accepté. Filament applique cette règle à la connexion et conserve le dernier
pas accepté dans le cache.

**Horloge :** la validation dépend de l'heure du serveur. Le serveur doit être synchronisé (NTP). Un écart
de plus de 30 secondes fait refuser les codes de tous les administrateurs.

**Secret :** chiffré en base avec `APP_KEY`, masqué à la sérialisation, affiché uniquement pendant
l'enrôlement, enregistré seulement après un premier code valide, jamais journalisé. Perdre ou changer
`APP_KEY` sans procédure impose de ré-enrôler tous les administrateurs.

## Dépendances

| Paquet | Version | Justification | Installation |
|---|---|---|---|
| `spatie/laravel-permission` | 8.3 | Prévu par le dossier (§ 4). Une simple colonne de rôle suffirait en Phase 2 mais serait à défaire en Phase 11. Usage limité aux quatre rôles : aucune permission, aucun écran | Étape 1 |
| `spatie/laravel-activitylog` | 4.12 | Prévu par le dossier (§ 4, table `activity_log` au § 13). Évite une table d'audit maison qui ferait doublon. La 5.x exige PHP 8.4 | Étape d'audit |

Compatibilité vérifiée avec PHP 8.3.14 et Laravel 13.35 : installation sans mise à jour d'aucun paquet existant.

## Journalisation

Événements à tracer : connexion réussie et échouée, déconnexion, invitation émise, acceptée et révoquée,
changement et réinitialisation de mot de passe, enrôlement et réinitialisation de la 2FA, récupération exceptionnelle.

Ne sont jamais journalisés : mot de passe, code TOTP, secret TOTP, jeton d'invitation brut, lien de
réinitialisation complet.

## Récupération et réinitialisation de la 2FA

- **Réinitialisation par un super-administrateur :** jamais sur son propre compte ; confirmation par son mot de
  passe et un code TOTP valide ; motif obligatoire ; la cible doit se ré-enrôler ; action tracée.
- **Récupération du dernier super-administrateur :** commande serveur dédiée. Elle exige l'identification de
  l'opérateur et un motif, n'agit que sur le compte visé, laisse une trace et ne désactive jamais la 2FA
  de façon générale.
- Le dernier super-administrateur actif ne peut être ni suspendu, ni supprimé, ni rétrogradé.

## Tests

Les tests qui écrivent en base ciblent uniquement `allo_vaho_test`. `tests/TestCase.php` refuse de démarrer
sur toute autre base. Chaque test s'exécute dans une transaction annulée ; `migrate:fresh` n'est pas utilisé.

## Étapes de réalisation

1. Socle : tables, modèles, guard, provider, broker, rôles, panneau branché sur le guard `admin`.
2. Invitation et activation.
3. Connexion et déconnexion.
4. TOTP obligatoire.
5. Mot de passe oublié et réinitialisation.
6. Audit et durcissement.
7. Vérification visuelle et documentation.
