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

Ordre revu le 13/10/2026 : la connexion et le TOTP obligatoire sont réalisés ensemble, avant l'invitation,
pour qu'aucun compte réel ne puisse exister tant que la double authentification n'est pas imposée.

## Connexion et TOTP — réalisé le 13/10/2026

### Parcours

1. Identifiants. Aucune session n'est ouverte à ce stade.
2. Compte déjà enrôlé : le code TOTP est exigé ; la session n'est ouverte qu'après un code valide.
3. Compte non enrôlé : la session est ouverte, mais toute page protégée renvoie vers l'enrôlement.
   Le secret n'est enregistré qu'après un premier code valide.

Le mot de passe seul ne donne donc jamais accès au panneau.

### Écrans

Les cinq écrans (identifiants, vérification, enrôlement, mot de passe oublié, nouveau mot de passe) n'utilisent
pas l'interface de Filament. Ils reprennent le balisage, les icônes et la feuille « Authentification » des
maquettes validées (`resources/css/admin/auth.css`, copie conforme). La logique reste celle de Filament et de Laravel.

| Élément | Fichier |
|---|---|
| Mise en page commune | `resources/views/layouts/admin-auth.blade.php` |
| Pages | `app/Filament/Auth/Pages/` et `resources/views/filament/auth/` |
| Composants | `resources/views/components/auth/` |
| Compléments hors maquette | `resources/css/admin/auth-extras.css` |
| Comportements (thème, mot de passe, code) | `resources/js/admin/auth.js` |
| Liens de support, de confidentialité et version | `config/admin.php` — masqués tant qu'ils ne sont pas configurés |

### Adaptations de la maquette

- Étape 2 : texte adapté au TOTP ; compte à rebours et changement de mode retirés ; aide « Contactez un Super Admin ».
- Enrôlement : écran sans maquette, dérivé de la carte de l'étape 2.
- Nouveau mot de passe : écran sans maquette, dérivé du formulaire d'Activation ; un seul critère affiché (8 caractères).
- « Se souvenir de moi » : ajouté, absent de la maquette.
- Bouton « Démo » : non reproduit.
- Écran « Connexion réussie » : non reproduit, la redirection est immédiate.
- « Tentatives restantes » : non affiché ; la limitation s'applique sans décompte visible.

### Limitation des tentatives

| Action | Limite |
|---|---|
| Identifiants | 5 par minute |
| Code TOTP à la connexion | 5, puis attente |
| Code TOTP à l'enrôlement | 5 par minute |
| Demande de réinitialisation | 2 par minute, et un lien par adresse et par minute |
| Nouveau mot de passe | 2 par minute |

Aucune de ces limites ne verrouille un compte.

### Points d'attention

- **« Se souvenir de moi »** (décision du 13/10/2026) ne mémorise que l'adresse e-mail, dans le navigateur
  (`localStorage`, clé `av.admin.email`). Ce n'est pas un champ du formulaire d'authentification : le serveur
  ne demande jamais de connexion persistante, n'émet aucun cookie de rappel et ne renseigne pas `remember_token`.
  La session expire donc toujours après inactivité, et le mot de passe comme le code restent demandés.
  Vérifié dans Chrome : seuls les cookies de session et anti-CSRF sont émis.
- **Pilote d'e-mail `log` :** en développement, le contenu des e-mails, lien de réinitialisation compris, est écrit
  dans le journal par ce pilote. C'est son fonctionnement ; ce ne sera plus le cas avec un SMTP.
- Le mot de passe saisi reste dans l'état de la page pendant l'étape 2, comme dans Filament : la connexion
  revalide les identifiants après le code.

## Invitation et activation — réalisé le 13/10/2026

### Règles

- Le jeton fait 256 bits aléatoires (64 caractères hexadécimaux). Seule son empreinte SHA-256 est stockée.
- Une invitation est valable 7 jours, utilisable une fois, et révocable tant qu'elle n'est pas acceptée.
- Émettre une nouvelle invitation pour une adresse révoque la précédente : un seul lien fonctionne.
- L'adresse e-mail et le rôle viennent de l'invitation, jamais de la requête.
- Une adresse qui a déjà un compte, même supprimé, ne peut pas être invitée.
- L'activation crée le compte sans le connecter et sans secret TOTP : la première connexion passe par l'enrôlement.
- Il n'existe aucune route d'inscription libre.

### Code

| Élément | Fichier |
|---|---|
| Émettre, révoquer, accepter | `app/Domain/Admin/Actions/` |
| Événements (sans jeton) | `app/Domain/Admin/Events/` |
| E-mail d'invitation, demande de renouvellement | `app/Domain/Admin/Notifications/` |
| Premier Super Admin | `php artisan admin:invite-first-super-admin <adresse>` |
| Page d'activation | `app/Filament/Auth/Pages/AcceptInvitation.php`, route `/admin/activation/{jeton}` |

### Usage concurrent

L'acceptation verrouille la ligne de l'invitation (`SELECT … FOR UPDATE`) dans une transaction : deux
utilisations simultanées du même lien s'attendent, et la seconde trouve l'invitation déjà acceptée.
L'unicité de l'adresse dans `admins` est une seconde barrière.

### Écrans

La page reproduit la maquette « Activation » : panneau de marque en variante invitation, carte de
l'invitant, encadré « Défini par votre invitation », formulaire, puis les états réussie, expirée, invalide
et déjà utilisée. Une invitation révoquée affiche l'écran « lien invalide », sans rien révéler.

Adaptations : un seul critère de mot de passe affiché (8 caractères) ; la phrase de l'écran de réussite
annonce la configuration de la vérification en deux étapes à la première connexion ; les liens de
support sont masqués tant qu'ils ne sont pas configurés ; pas d'écran intermédiaire « Vérification de
votre invitation… », le serveur répond directement.

### E-mails

L'e-mail d'invitation n'est jamais mis en file d'attente : un travail en file stockerait le jeton en clair.
La commande du premier Super Admin affiche le lien une fois dans le terminal de l'opérateur, puisqu'aucun
e-mail réel n'est envoyé.

### Invitations depuis le serveur

`php artisan admin:invite <adresse> <rôle>` (décision du 14/10/2026) invite un administrateur avant les écrans de
la Phase 11. Rôles acceptés, en minuscules : `super-admin`, `support`, `marketing`, `finance`. Mêmes règles que
l'invitation : usage unique, 7 jours, empreinte seule, invitation précédente révoquée. Le lien est affiché une
fois dans le terminal ; l'émission et la révocation sont journalisées sans jeton ni lien.

## Audit, sessions et récupération — réalisé le 14/10/2026

### Journal d'audit

`spatie/laravel-activitylog` 4.12, table `activity_log` (`timestamptz`, propriétés en JSONB), journal `auth`.

| Événement | Quand |
|---|---|
| `auth.login`, `auth.logout` | connexion complète (code vérifié), déconnexion |
| `auth.login_failed` | identifiants refusés ; motif `unknown_account`, `invalid_credentials` ou `inactive_account` |
| `auth.two_factor_failed` | code refusé à la connexion |
| `auth.two_factor_enrolled` | enrôlement terminé |
| `auth.two_factor_reset` | réinitialisation par un Super Admin, avec son motif |
| `auth.super_admin_recovered` | récupération exceptionnelle, avec opérateur et motif |
| `auth.password_reset_requested`, `auth.password_reset` | lien envoyé, mot de passe changé |
| `invitation.issued`, `invitation.revoked`, `invitation.accepted` | cycle de vie des invitations |

Contexte enregistré : canal (`web` ou `console`), adresse IP et navigateur pour le web, utilisateur système
pour la console. Pour une adresse inconnue, seule une empreinte HMAC est conservée. Jamais de mot de passe,
de code, de secret TOTP, de jeton ni de lien. Les événements des utilisateurs mobiles sont ignorés.
La connexion met aussi à jour `admins.last_login_at`.

La durée de conservation reste à décider (décision 0006) ; aucune purge n'est planifiée.

### Cookie de session

HTTPS uniquement par défaut hors `local` et `testing` (`App\Support\SessionCookie`), sauf si
`SESSION_SECURE_COOKIE` le précise. `http_only`, `same_site=lax`, 120 minutes d'inactivité.

### Dernier Super Admin

Le dernier Super Admin actif ne peut être ni suspendu ni supprimé (règle du modèle `Admin`). La rétrogradation
de rôle sera contrôlée avec les écrans de la Phase 11.

### Réinitialisation du TOTP par un Super Admin

Action `ResetAdminTwoFactor` (l'écran viendra en Phase 11) : Super Admin actif uniquement, jamais sur son propre
compte, confirmation par son mot de passe et un code valide, motif d'au moins 10 caractères. Effets : secret de
la cible effacé, sessions de la cible fermées, e-mail d'information, entrée d'audit. La cible se ré-enrôle à sa
prochaine connexion.

### Récupération exceptionnelle du dernier Super Admin

`php artisan admin:recover-super-admin <adresse> --operator="…" --reason="…" [--reset-password]`

- Refusée si un autre Super Admin actif et enrôlé peut faire la réinitialisation normale.
- Refusée pour un compte suspendu (elle ne réactive rien).
- Demande de retaper l'adresse du compte pour confirmer.
- Efface le secret TOTP de ce seul compte et ferme ses sessions. Le TOTP reste obligatoire pour tous :
  la prochaine connexion exige le mot de passe puis un nouvel enrôlement.
- `--reset-password` émet en plus un lien de réinitialisation, affiché une fois.
- Journalisée avec l'opérateur, le motif et l'utilisateur système.

### Fermeture des sessions

`AdminSessions` supprime les lignes de `sessions` de l'administrateur (pilote `database`) et renouvelle son
`remember_token`. Sur les requêtes du back-office, `sessions.user_id` contient l'identifiant de l'administrateur ;
les utilisateurs mobiles n'ont pas de session web.
