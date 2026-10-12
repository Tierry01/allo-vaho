# 0005 — Authentification administrateur : table `admins` et guard dédié

- **Statut :** orientation validée le 12/10/2026 (table distincte, guard propre). **Conception à valider — rien n'est implémenté.**
  L'implémentation appartient à la Phase 2 — Authentification.
- **Écart :** aucun. Le Dossier technique v1.3 ne décrit pas le stockage des comptes administrateurs ; cette décision comble un silence du dossier.

## Ce que dit le dossier

- § 13 : `users` porte `phone_e164`, `phone_hash`, `status`… et **aucun mot de passe**. Les utilisateurs mobiles
  se connectent par OTP ou connexion sociale, avec un jeton Sanctum par appareil (§ 11).
- § 13 : aucune table d'administrateurs n'est décrite. Le modèle prévoit `activity_log` (audit admin) et `settings`.
- § 4 : rôles admin avec `spatie/laravel-permission`, audit avec `spatie/laravel-activitylog`.
- § 17–18 : back-office avec 2FA obligatoire et rôles minimaux ; portail Camtel « espace séparé, comptes
  nominatifs Camtel avec double authentification ».

Les deux populations n'ont ni le même identifiant (téléphone / e-mail), ni le même facteur
(OTP SMS / mot de passe + TOTP), ni le même canal (jeton API / session web). Une table commune
obligerait `users` à porter un mot de passe et des secrets 2FA inutiles à 99 % des lignes, et ferait
dépendre l'accès au back-office d'un simple indicateur sur un compte mobile.

## Conception proposée

| Élément | Utilisateurs mobiles | Administrateurs |
|---|---|---|
| Table | `users` | `admins` |
| Modèle | domaine Identity | `App\Domain\Admin\Models\Admin` |
| Guard | `sanctum` (jetons) | `admin` (driver `session`) |
| Provider | `users` | `admins` (Eloquent) |
| Broker de mots de passe | aucun | `admins` |
| Facteurs | OTP SMS, Google, Apple | e-mail + mot de passe + TOTP obligatoire |
| Accès | `/api/v1` | panel Filament |

- **`config/auth.php`** : ajout du guard `admin`, du provider `admins` et du broker `admins`.
  Le guard `web` existant n'est pas utilisé par le back-office.
- **Filament** : `->authGuard('admin')` et `->authPasswordBroker('admins')` sur le panel ;
  le modèle `Admin` implémente `FilamentUser::canAccessPanel()`.
- **2FA** : fonction native de Filament 5 — `multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true)`.
  Le modèle implémente `HasAppAuthentication` et `HasAppAuthenticationRecovery`. Pas de plugin tiers.
  Le secret TOTP et les codes de secours sont stockés chiffrés.
- **Sessions** : la table `sessions` existante sert au guard `admin`. Les utilisateurs mobiles n'ont pas de
  session web. Un administrateur n'est jamais authentifié sur l'API mobile par sa session, et un jeton
  mobile ne donne jamais accès au panel : les deux guards n'ont aucun provider commun.
- **Rôles** : `spatie/laravel-permission` attaché au modèle `Admin` avec `guard_name = admin` (voir décision 0006).
- **Pas d'inscription libre** : les comptes sont créés par un super-admin. La maquette validée
  « Activer votre compte » indique un parcours d'activation par invitation.

### Colonnes minimales de `admins`

Imposées par Laravel et Filament : `id`, `name`, `email` (unique), `password`, `remember_token`,
`app_authentication_secret`, `app_authentication_recovery_codes`, `created_at`, `updated_at` (`timestamptz`).

## Conséquences

- La table `password_reset_tokens` existante pourra servir au broker `admins`.
- `users.password` et `users.remember_token` n'ont plus d'usage : leur suppression relève des
  migrations correctives de `users` (voir 0007), non décidées ici.
- Le portail Camtel constitue une troisième population (§ 17). Son rattachement — rôle dans `admins`
  avec un second panel, ou table et guard propres — n'est pas tranché.

## À valider avant la Phase 2

1. Colonnes complémentaires de `admins` : ULID public, statut actif/suspendu, date de dernière
   connexion, suppression logique, données d'invitation (jeton, expiration).
2. Parcours d'activation exact, à lire dans la maquette « Activation » une fois extraite.
3. Codes de secours 2FA : activés ou non, et procédure de réinitialisation de la 2FA d'un administrateur.
4. Durée de session du back-office et politique de mot de passe (non chiffrées dans le dossier).
5. Rattachement des comptes Camtel.
