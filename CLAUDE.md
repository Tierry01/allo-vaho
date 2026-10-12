# Allo Vaho — Instructions de développement

## 1. Projet

Allo Vaho est une plateforme numérique camerounaise composée de :

- une application mobile Flutter Android/iOS ;
- un backend Laravel exposant une API REST versionnée ;
- un back-office administrateur ;
- un portail partenaire Camtel ;
- un espace web Entreprise ;
- plusieurs intégrations externes.

Ce repository concerne le projet web/backend Allo Vaho.

Le back-office administrateur est une partie du même projet Laravel.

---

# 2. Sources de vérité

Avant toute implémentation fonctionnelle, consulter les documents présents dans :

- `docs/technical/`
- `docs/design/`
- `docs/references/`

## Documentation technique

Le document :

`docs/technical/Allo_Vaho_Dossier_technique_v1.3.pdf`

est la source de vérité pour :

- architecture ;
- modèle de données ;
- flux métier ;
- sécurité ;
- API ;
- paiements ;
- abonnements ;
- Camtel ;
- rôles ;
- back-office ;
- infrastructure ;
- décisions validées.

## Design

Les documents dans `docs/design/` sont les références visuelles et rédactionnelles.

Le Brief Design Allo Vaho v1.0 fait foi pour l'identité visuelle, les textes et les principes d'interface.

## Références visuelles validées

Les fichiers dans :

`docs/references/`

sont des maquettes HTML validées par la hiérarchie.

Elles constituent la référence visuelle pour leur implémentation Laravel.

Ne pas réinventer leur design sans demande explicite.

---

# 3. Stack technique

## Backend

- Laravel 13
- PHP 8.3+
- API REST versionnée
- Laravel Sanctum
- PostgreSQL 16
- PostGIS

## Back-office

- Laravel
- Filament
- composants Blade lorsque nécessaire
- architecture compatible avec le design Allo Vaho validé

## Base de données

- PostgreSQL 16
- PostGIS
- clés primaires bigint
- identifiants publics ULID pour l'API
- timestamps en timestamptz
- données JSON en JSONB lorsque prévu par la documentation
- soft delete pour les utilisateurs lorsque prévu

## Cache / files

Redis et Laravel Horizon sont prévus par l'architecture cible.

IMPORTANT :
Redis n'est pas encore configuré dans l'environnement local actuel.

Ne pas considérer Redis comme disponible tant qu'il n'a pas été explicitement configuré.

## Infrastructure locale

L'architecture cible prévoit Docker Compose.

Pour le développement actuel, Docker Desktop est volontairement reporté.

L'environnement local utilise actuellement :

- PHP installé localement ;
- PostgreSQL 16 installé localement ;
- PostGIS installé localement.

Ne pas introduire Docker ou Redis sans demande explicite.

---

# 4. Architecture

Le projet doit respecter une architecture Laravel modulaire.

Les domaines métier doivent être isolés.

Exemples :

- `app/Domain/Identity`
- `app/Domain/Packs`
- `app/Domain/Subscriptions`
- `app/Domain/Payments`
- `app/Domain/Camtel`
- `app/Domain/Directory`
- `app/Domain/Visibility`
- `app/Domain/Companies`
- `app/Domain/Invitations`
- `app/Domain/Notifications`
- `app/Domain/Admin`
- `app/Domain/Campaigns`

Chaque domaine peut contenir notamment :

- Models
- Services
- Actions
- Events
- Listeners
- Policies
- DTOs
- Enums
- Jobs
- Repositories si réellement nécessaires
- Tests

Ne pas créer des abstractions inutiles.

Privilégier un code simple, testable et maintenable.

---

# 5. Principes d'architecture

## API-first

L'API REST versionnée est une partie centrale du projet.

L'application mobile Flutter consomme cette API.

Préserver une séparation claire entre :

- logique métier ;
- API ;
- back-office ;
- intégrations externes.

Le contrat API doit être documenté avec OpenAPI.

Le contrat OpenAPI doit être défini et stabilisé avant l'implémentation
des fonctionnalités consommées par l'application mobile lorsque cela est
possible.

Le backend et l'application mobile doivent travailler à partir du même
contrat API.

Ne pas inventer des formats de requête ou de réponse différents entre
backend et mobile.

## Événements

Les modules doivent pouvoir communiquer par événements métier.

Exemples :

- `PaymentSucceeded`
- `SubscriptionActivated`

Ne pas créer des dépendances directes inutiles entre domaines.

## Asynchronisme

Tout ce qui dépend d'un prestataire externe doit être conçu pour être
asynchrone lorsque le dossier technique le prévoit.

Exemples :

- paiement ;
- Camtel ;
- SMS ;
- push ;
- e-mails.

Une panne d'un prestataire externe ne doit pas bloquer inutilement l'application.

## Idempotence

Les webhooks et jobs doivent être idempotents.

Un callback reçu plusieurs fois ne doit jamais provoquer :

- deux paiements ;
- deux activations ;
- deux remboursements ;
- deux notifications critiques.

---

# 6. API REST & Developer Area

## API versionnée

La plateforme expose une API REST versionnée.

Base actuelle :

`/api/v1`

L'API est principalement consommée par l'application mobile Flutter.

Le dossier technique prévoit notamment :

- Laravel Sanctum ;
- rate limiting ;
- OpenAPI ;
- API REST versionnée.

Ne jamais créer une route API sans vérifier son besoin fonctionnel,
ses règles d'accès et son contrat.

## Contrat OpenAPI

OpenAPI constitue la référence du contrat entre le backend et les clients
de l'API.

Pour chaque endpoint important, documenter :

- méthode HTTP ;
- chemin ;
- description ;
- authentification requise ;
- permissions requises lorsque nécessaire ;
- headers ;
- paramètres de route ;
- paramètres de requête ;
- body de la requête ;
- validation ;
- exemple de requête ;
- réponse de succès ;
- codes HTTP ;
- réponses d'erreur ;
- pagination lorsque nécessaire.

Lorsqu'un contrat API change :

1. mettre à jour OpenAPI ;
2. mettre à jour l'implémentation Laravel ;
3. mettre à jour les tests ;
4. vérifier l'impact sur l'application mobile ;
5. documenter les changements importants.

Éviter les changements incompatibles lorsque cela est possible.

## Domaines API

L'API doit être organisée autour des domaines métier du projet.

Les domaines prévus comprennent notamment :

- authentification ;
- utilisateur courant ;
- packs ;
- abonnements ;
- paiements ;
- entreprises ;
- plans Entreprise ;
- annuaire ;
- visibilité ;
- invitations ;
- notifications ;
- numéros utiles ;
- campagnes lorsque nécessaire.

Ne pas inventer une liste complète d'endpoints.

Avant de créer un endpoint, vérifier :

1. le besoin dans la documentation ;
2. la règle métier ;
3. les droits d'accès ;
4. le contrat de données ;
5. la nécessité pour un client ;
6. les tests nécessaires.

## Developer Area du back-office

Le back-office doit comporter une section destinée aux développeurs et à
l'exploitation technique de l'API.

Structure cible :

Administration
├── Administrateurs
├── Rôles & permissions
├── Journal d'activité
├── Paramètres
└── Développeur
    ├── API
    │   ├── Documentation
    │   ├── Endpoints
    │   └── Tester
    ├── Clés & Credentials
    ├── Webhooks
    └── Health Check

Cette section doit être réservée aux administrateurs disposant des
permissions appropriées.

## Documentation API

La page Documentation doit permettre de consulter le contrat API de façon
lisible.

Pour chaque endpoint, afficher si applicable :

- méthode ;
- URL ;
- description ;
- authentification ;
- paramètres ;
- body ;
- exemples ;
- réponses ;
- erreurs ;
- permissions.

La documentation doit idéalement être générée à partir de la définition
OpenAPI afin d'éviter une double maintenance.

## Endpoint Explorer

La page Endpoints peut permettre aux utilisateurs autorisés de parcourir
les endpoints disponibles.

Elle peut notamment permettre de :

1. sélectionner un endpoint ;
2. consulter sa documentation ;
3. renseigner les paramètres ;
4. renseigner le body ;
5. envoyer une requête de test ;
6. consulter le code HTTP ;
7. consulter la réponse ;
8. consulter les erreurs éventuelles.

Les endpoints destructifs doivent demander une confirmation explicite.

Ne jamais afficher ou exposer de secrets dans cette interface.

Ne pas permettre à un administrateur disposant uniquement de droits
métier de contourner les permissions API.

## Clés & Credentials

Cette section doit permettre, lorsque nécessaire, de gérer les credentials
techniques prévus par le projet.

Exemples :

- clés API ;
- credentials d'intégration ;
- secrets webhook ;
- credentials de prestataires.

Les secrets doivent être stockés de manière sécurisée.

Un secret ne doit pas être réaffiché en clair après sa création.

Toute opération sensible sur un credential doit être auditée.

## Webhooks

La section Webhooks doit permettre de surveiller les intégrations
entrantes.

Afficher lorsque disponible :

- intégration ;
- endpoint ;
- état ;
- dernier événement ;
- date du dernier événement ;
- dernier code HTTP ;
- nombre d'échecs ;
- dernière erreur ;
- historique des événements ;
- clé d'idempotence ;
- statut de traitement.

Les webhooks doivent respecter les règles de signature, d'idempotence et
de protection contre le rejeu prévues par l'architecture.

## Health Check

Le Health Check doit permettre de vérifier l'état des composants critiques.

Prévoir notamment :

- API Laravel ;
- PostgreSQL ;
- PostGIS ;
- stockage ;
- cache ;
- système de files ;
- intégrations externes ;
- Redis lorsqu'il sera configuré.

États possibles :

- opérationnel ;
- dégradé ;
- indisponible ;
- non configuré.

Redis ne doit pas être présenté comme opérationnel tant qu'il n'est pas
configuré dans l'environnement.

## Sécurité de la Developer Area

Ne jamais exposer dans le back-office :

- mots de passe ;
- OTP ;
- secrets API ;
- secrets webhook ;
- credentials internes ;
- tokens privés ;
- secrets d'infrastructure.

Les opérations sensibles doivent :

- respecter les permissions ;
- être protégées par l'authentification administrateur ;
- être enregistrées dans le journal d'activité lorsque nécessaire.

## Relation avec l'application mobile

Le développeur Flutter doit pouvoir utiliser la documentation API pour
connaître précisément :

- l'endpoint ;
- la méthode HTTP ;
- l'authentification ;
- les paramètres ;
- le body ;
- les réponses ;
- les erreurs ;
- les règles de pagination.

Le back-office ne remplace pas l'API.

Il fournit une interface permettant de documenter et, pour les utilisateurs
autorisés, d'inspecter et tester l'API.

---

# 7. Base de données

La base officielle du projet est :

PostgreSQL 16 + PostGIS.

Ne jamais remplacer PostgreSQL par MySQL ou SQLite pour le projet.

SQLite peut éventuellement être utilisé uniquement pour un besoin de test explicitement validé.

La géolocalisation doit utiliser PostGIS conformément au dossier technique.

Les recherches géographiques doivent utiliser les capacités spatiales PostgreSQL/PostGIS.

---

# 8. Règles de sécurité

La sécurité est une exigence fonctionnelle.

Respecter notamment :

- authentification sécurisée ;
- Sanctum pour les tokens API ;
- rate limiting ;
- policies Laravel ;
- validation stricte ;
- protection contre l'énumération des numéros ;
- webhooks signés ;
- protection anti-rejeu ;
- idempotence ;
- chiffrement des données sensibles lorsque prévu ;
- audit des actions sensibles du back-office.

Le back-office administrateur nécessite une authentification à deux facteurs.

Les rôles administrateurs doivent respecter le principe du moindre privilège.

---

# 9. Rôles administrateurs

Les rôles métier définis sont notamment :

- super-admin
- support
- marketing
- finance

Ne jamais donner automatiquement toutes les permissions à tous les administrateurs.

Les permissions doivent être explicites.

---

# 10. Back-office

Le back-office doit permettre notamment :

- gestion des utilisateurs ;
- abonnements ;
- packs ;
- groupes ;
- entreprises ;
- modération ;
- catégories ;
- numéros utiles ;
- campagnes ;
- notifications ;
- paiements ;
- remboursements ;
- réconciliation ;
- statistiques ;
- paramètres ;
- audit ;
- administration.

Le tableau de bord doit notamment permettre de suivre :

- inscriptions ;
- conversions ;
- chiffre d'affaires par moyen de paiement ;
- demandes Camtel en attente.

---

# 11. Camtel

Le flux Camtel est critique.

Respecter :

- transmission de la demande ;
- suivi du statut ;
- SLA ;
- alerte à 12 heures ;
- escalade à 24 heures ;
- activation ;
- rejet ;
- motif de rejet ;
- référence Camtel ;
- remboursement soumis à validation administrative.

Aucun remboursement Camtel rejeté ne doit être automatique.

Les transitions doivent être traçables.

---

# 12. Paiements

Les paiements doivent pouvoir gérer :

- Nokash ;
- Orange Money ;
- MTN Mobile Money ;
- Visa ;
- Apple IAP ;
- Google Play.

Les paiements doivent conserver les références nécessaires et les données techniques prévues par le modèle.

Les clés d'idempotence doivent être uniques.

Les payloads externes doivent être conservés lorsqu'ils sont prévus par la documentation.

---

# 13. Design

Le design Allo Vaho validé doit être respecté.

Couleurs principales :

- `#009FE3`
- `#0077B6`
- `#1F1C18`
- `#FAF7F2`
- `#6E665A`
- `#F2A93B`

Le back-office doit rester cohérent avec l'identité Allo Vaho tout en étant plus dense et opérationnel que l'application mobile.

Le design validé comprend notamment :

- interface premium ;
- responsive ;
- dark mode ;
- sidebar ;
- topbar ;
- tableaux ;
- filtres ;
- cartes ;
- badges ;
- modales ;
- états loading ;
- états empty ;
- états error ;
- états offline lorsque pertinent.

Les maquettes HTML validées doivent servir de référence.

---

# 14. Accessibilité

Respecter :

- contraste AA ;
- navigation clavier ;
- labels explicites ;
- focus visible ;
- focus de 2 px avec la couleur `#009FE3` sur les interfaces web ;
- textes compréhensibles ;
- boutons avec états visibles.

---

# 15. États des interfaces

Les interfaces importantes doivent prévoir :

- état normal ;
- chargement ;
- vide ;
- erreur ;
- hors ligne lorsque pertinent ;
- aucun résultat après filtrage ;
- action en cours ;
- succès.

Ne pas construire uniquement l'état "happy path".

---

# 16. Règles de développement

Avant de modifier une fonctionnalité :

1. Lire la documentation concernée.
2. Examiner le code existant.
3. Identifier les dépendances.
4. Proposer l'approche lorsque la décision est structurante.
5. Implémenter le minimum nécessaire.
6. Tester.
7. Vérifier les régressions.
8. Documenter si nécessaire.

Ne jamais réécrire une partie fonctionnelle existante sans raison.

Ne jamais supprimer du code ou une fonctionnalité simplement parce qu'elle semble inutile sans vérifier son utilisation.

---

# 17. Tests

Utiliser Pest pour les tests du backend.

Toute fonctionnalité métier importante doit avoir des tests.

Les règles critiques doivent être testées en priorité :

- permissions ;
- paiements ;
- remboursements ;
- transitions d'abonnement ;
- Camtel ;
- visibilité des numéros ;
- idempotence ;
- sécurité.

---

# 18. Git

Branches prévues :

- `main` : production
- `develop` : recette

Les changements importants doivent être commités avec des messages clairs.

Ne jamais commit :

- `.env`
- mots de passe ;
- clés API ;
- secrets ;
- tokens ;
- credentials.

---

# 19. Règles concernant les dépendances

Ne pas installer une dépendance simplement parce qu'elle semble pratique.

Avant d'ajouter une dépendance :

1. vérifier si Laravel fournit déjà la fonctionnalité ;
2. vérifier si elle est compatible avec Laravel 13 ;
3. vérifier sa compatibilité avec PHP 8.3 ;
4. vérifier son impact sur l'architecture ;
5. éviter les dépendances redondantes.

---

# 20. Documentation

Toute API importante doit être documentée.

Le projet doit progressivement contenir :

- documentation API ;
- exemples de requêtes ;
- exemples de réponses ;
- documentation des intégrations ;
- documentation du back-office ;
- documentation des décisions techniques importantes.

---

# 21. Règle fondamentale

NE PAS INVENTER.

Si une information n'est pas présente dans :

- la documentation technique ;
- le brief design ;
- les maquettes validées ;
- le code existant ;
- ou une décision explicitement prise pendant le développement ;

alors :

1. ne pas l'inventer ;
2. signaler le manque d'information ;
3. proposer une option si nécessaire ;
4. attendre la validation lorsqu'il s'agit d'une décision d'architecture ou métier.

La documentation technique v1.3 et les décisions validées sont prioritaires sur les suppositions.

---

# 22. Workflow avec Claude Code

Le projet sera développé module par module.

Ne pas tenter d'implémenter tout Allo Vaho en une seule étape.

Pour chaque module :

1. analyser ;
2. planifier ;
3. implémenter ;
4. migrer ;
5. tester ;
6. vérifier ;
7. corriger ;
8. commit ;
9. passer au module suivant.

Toujours préserver le fonctionnement des modules déjà validés.

---

# 23. État actuel du projet

Phase actuelle :

PHASE 0 — FONDATION

Déjà terminé :

- Laravel installé ;
- Git initialisé ;
- documents organisés ;
- design général validé ;
- PostgreSQL 16 installé ;
- PostGIS installé.

À terminer :

- vérification finale de la configuration Laravel/PostgreSQL/PostGIS ;
- vérification de la compatibilité Filament avec Laravel 13 ;
- installation/configuration de Filament après validation de compatibilité ;
- mise en place de l'authentification administrateur ;
- mise en place de la structure API `/api/v1` ;
- mise en place du contrat OpenAPI ;
- mise en place progressive de l'architecture modulaire ;
- implémentation du back-office module par module.

Reporté :

- Docker Desktop ;
- Redis ;
- Laravel Horizon ;
- infrastructure de production ;
- CI/CD complet.

Ne pas considérer les éléments reportés comme disponibles.

---

# 24. Référence principale

Projet :

ALLO VAHO

Version de référence :

Dossier technique v1.3 — 28 septembre 2026

Le code doit rester cohérent avec cette version tant qu'une nouvelle décision officielle ne la remplace pas.