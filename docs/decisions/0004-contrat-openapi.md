# 0004 — Contrat OpenAPI défini en amont

- **Statut :** retenue le 12/10/2026 et en place (contrat, lint, tests de conformité).
  Le serveur de mock est **reporté** (décision du 12/10/2026).
- **Écart au Dossier technique v1.3 :** oui, sur un point — voir « Décision concernant Scribe ».

## Constat

Le dossier (§ 14) prévoit une « documentation OpenAPI générée (Scribe) ». Il prévoit aussi (§ 20, sprint 0)
un « contrat OpenAPI v1 » et un « serveur de mock », et des modèles Flutter générés depuis l'OpenAPI.

Scribe, Scramble et l5-swagger **génèrent** un document OpenAPI à partir du code Laravel. Ils ne peuvent
donc fournir ni un contrat avant l'implémentation, ni un mock pour l'équipe mobile au sprint 0.
Un contrat défini en amont est un fichier écrit à la main et versionné.

## Décision

1. **Source de vérité :** `docs/api/openapi/v1.yaml` (OpenAPI 3.1), modifié avant le code.
   Il n'est jamais remplacé par une génération depuis le code.
2. **Validation du fichier :** Redocly CLI (`npm run api:lint`).
3. **Conformité du code :** tests Pest avec Spectator — la réponse réelle est comparée au contrat.
4. **Mock pour le mobile :** Prism, lancé sur le même fichier.
5. **Documentation du back-office (Phase 10 — Technique) :** rendue depuis le même fichier.

## Décision concernant Scribe

Scribe n'est **pas installé** et n'est pas retenu.

- **Écart :** le § 14 cite Scribe comme générateur de la documentation.
- **Raison :** l'objectif du § 14 — une documentation OpenAPI publiée à chaque déploiement — est tenu
  autrement : la documentation est rendue depuis le contrat versionné. Générer en plus un second
  document depuis le code créerait deux descriptions concurrentes de la même API.
- **Ce qui est conservé du dossier :** le contrat OpenAPI v1, le serveur de mock, la publication de la
  documentation, la génération des modèles Flutter depuis l'OpenAPI.
- **Réversibilité :** Scribe (5.11.0, compatible Laravel 13) peut être ajouté plus tard sans rien défaire.

## Outils en place (12/10/2026)

Environnement : PHP 8.3.14, Laravel 13.35.0, Node 24.13.0, npm 11.6.2.

| Outil | Version | Où | Résultat constaté |
|---|---|---|---|
| @redocly/cli | 2.62.1 (exacte) | `package.json`, devDependencies | Contrat valide, 2 avertissements ; `npm audit` : 0 vulnérabilité |
| hotmeteor/spectator | 3.0.4 | `composer.json`, require-dev | Test de conformité vert ; un contrat volontairement faussé le fait échouer |

Les deux avertissements Redocly sont attendus : serveur `localhost`, et absence de réponse `4XX`
sur `/health` tant que le format d'erreur n'est pas défini.

Test de conformité : `tests/Feature/Api/V1/ContractTest.php`.

## Serveur de mock — reporté

| Version de Prism | Node requis | Constat |
|---|---|---|
| 5.14.2 | ≥ 18.20.1 | Fonctionne sur Node 24.13, mais apporte **16 avis de sécurité npm** (8 élevés, 8 modérés) dans ses dépendances |
| 5.16.0 | ≥ 24.18 | Corrige ces avis ; incompatible avec Node 24.13 |

Prism 5.14.2 a été installé puis **retiré** du projet pour ne pas verrouiller de dépendances
vulnérables dans `package-lock.json`.

**Décision du 12/10/2026 :** Prism est reporté. Node n'est pas mis à niveau uniquement pour lui et il
n'est pas ajouté aux dépendances du projet. Options étudiées, pour mémoire :

1. **Mettre Node à niveau en 24.18 ou plus** (mise à jour mineure de Node 24), puis installer Prism 5.16.0
   en version exacte. Recommandé : c'est la seule option sans avis de sécurité.
2. **Lancer Prism 5.14.2 à la demande par `npx`**, hors du projet. Disponible immédiatement ; les
   dépendances vulnérables restent dans le cache npm du poste, pour un outil local de développement.
3. **Reporter le mock** jusqu'au premier endpoint métier du contrat.

## État du contrat

`v1.yaml` décrit uniquement `GET /health`, seul endpoint existant. Aucun endpoint métier n'y figure.

## À valider

- le format d'erreur RFC 7807 (§ 14) : nom du champ portant le « code technique » et liste des codes,
  à décrire dans le contrat avant le premier endpoint métier.
