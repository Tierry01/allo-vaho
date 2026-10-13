# Décisions techniques — Allo Vaho

Ce dossier consigne les décisions prises pendant le développement, en particulier
les **écarts au Dossier technique v1.3**. Le dossier technique reste la référence
fonctionnelle ; seules les dérogations listées ici sont autorisées.

| N° | Décision | Statut | Écart au dossier v1.3 |
|---|---|---|---|
| [0001](0001-filament-5.md) | Back-office sur Filament 5 | Validée le 12/10/2026 — installation en attente | Oui (v1.3 : Filament 3) |
| [0002](0002-environnement-local-et-tests.md) | Environnement local sans Docker/Redis, tests sur PostgreSQL | Validée le 12/10/2026 — en place | Oui (v1.3 : Docker Compose, Redis) |
| [0003](0003-otp-provisoire-postgresql.md) | OTP provisoire sur cache PostgreSQL | Validée dans son principe — non implémentée | Oui (v1.3 : Redis) |
| [0004](0004-contrat-openapi.md) | Contrat OpenAPI défini en amont | En place — serveur de mock reporté | Partiel (v1.3 : doc générée par Scribe) |
| [0005](0005-authentification-administrateur.md) | Table `admins` et guard dédié | Orientation validée — conception à valider | Non (silence du dossier) |
| [0006](0006-permissions-et-audit.md) | Permissions et journal d'activité | **Recommandation — en attente de validation** | Non |
| [0007](0007-questions-ouvertes-identite.md) | Questions ouvertes : identité et table `users` | **Questions en attente de décision métier** | — |
| [0008](0008-design-system.md) | Design system : polices, icônes, ressources externes | Validée le 12/10/2026 | Non |
| [0009](0009-authentification-back-office.md) | Phase 2 : authentification du back-office | Plan approuvé le 13/10/2026 — socle, connexion et TOTP réalisés | Non |
