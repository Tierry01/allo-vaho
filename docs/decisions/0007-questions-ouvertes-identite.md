# 0007 — Questions ouvertes : identité et table `users`

- **Statut :** **questions en attente de décision métier. Rien n'est implémenté ni tranché.**
- Ces points bloquent les migrations correctives de `users` et la Phase 2 — Authentification.

## 1. Algorithme et partage de `phone_hash`

**Contexte.** § 13 : `users.phone_hash`, `user_contacts.phone_hash`, `invitations.phone_hash`.
§ 14 : `POST /contacts/sync` — « envoyer les empreintes (delta) ». Le mobile calcule donc les empreintes
et le serveur doit produire les mêmes : l'algorithme fait partie du contrat d'API.

**Dépend de :** développeur Flutter, contrat OpenAPI, exigence « aucune donnée de contacts ne quitte les
serveurs camerounais » (§ 18).

| Option | Avantage | Limite |
|---|---|---|
| SHA-256 du numéro E.164 | Simple, calculable hors ligne | Réversible par force brute : l'espace des numéros camerounais est petit |
| SHA-256 avec sel applicatif partagé | Empêche les tables précalculées génériques | Le sel est extractible de l'application |
| HMAC avec clé serveur | Non réversible sans la clé | Le mobile ne peut pas calculer l'empreinte : il faudrait envoyer les numéros en clair |

## 2. Valeurs de `users.status`

**Contexte.** § 13 cite la colonne sans ses valeurs. § 17 mentionne la « suspension » d'un utilisateur ;
§ 11 la suppression de compte ; la suppression logique est déjà prévue par `deleted_at`.

**Dépend de :** règles de modération, parcours d'inscription (numéro vérifié ou non).

**Options.** Liste minimale `active` / `suspended` ; ou liste étendue incluant un état avant vérification
du numéro. À fixer avec les transitions autorisées et qui peut les déclencher.

## 3. Unicité de l'e-mail chiffré

**Contexte.** § 13 : `users.email`. § 18 : chiffrement des colonnes sensibles, dont l'e-mail.
Le schéma actuel impose `email NOT NULL UNIQUE`. Un chiffrement non déterministe rend l'index unique inopérant.

| Option | Avantage | Limite |
|---|---|---|
| Pas d'unicité (le téléphone est l'identifiant pivot) | Le plus simple | Un même e-mail sur plusieurs comptes |
| Colonne `email_hash` (HMAC clé serveur) avec index unique | Unicité et recherche exacte | Une colonne et une clé de plus à gérer |
| Chiffrement déterministe | Index direct | Protection plus faible |

**Question préalable :** l'e-mail doit-il être unique, sachant qu'il est facultatif et sert aux campagnes ?

## 4. Limitation des OTP

**Contexte.** § 11 fixe : 6 chiffres, 5 minutes, 3 essais par code, 5 envois par numéro et par heure.
Il annonce des « limites par IP et par appareil » sans les chiffrer.

**À fixer :** seuils par IP et par appareil, durée de blocage après dépassement, comportement après
3 essais échoués (nouveau code immédiat ou délai), identifiant d'appareil utilisé.
Voir la décision 0003 pour le stockage.

## 5. Schéma `users` et mot de passe

**Contexte.** Écarts constatés entre le schéma actuel (migration Laravel par défaut) et le § 13 :
colonnes absentes (`ulid`, `phone_e164`, `phone_hash`, `avatar_path`, `number_hidden`, `has_ever_paid`,
`city`, `status`, `referred_by`, `deleted_at`), dates en `timestamp` au lieu de `timestamptz`,
`name`, `email` et `password` obligatoires.

**Stratégie retenue :** migrations correctives, sans modifier la migration d'origine. La table est vide.

**À décider :**
- suppression de `password` et `remember_token` (cohérente avec la décision 0005), ou simple passage en nullable ;
- `name` nullable tant que le profil n'est pas renseigné ;
- sort de `sessions.user_id`, qui référencera des administrateurs ;
- ces migrations dépendent des points 1 à 3 ci-dessus.
