# API Allo Vaho — contrat et outillage

Le contrat de l'API est le fichier [`openapi/v1.yaml`](openapi/v1.yaml). Il fait foi pour le
backend Laravel et pour l'application Flutter. La stratégie est décrite dans
[`../decisions/0004-contrat-openapi.md`](../decisions/0004-contrat-openapi.md).

## Règle de travail

1. Modifier `openapi/v1.yaml`.
2. Valider le fichier (lint).
3. Implémenter côté Laravel.
4. Écrire ou adapter les tests, dont le test de conformité.
5. Vérifier l'impact sur l'application mobile.

Un endpoint n'est ajouté au contrat que s'il est prévu par le dossier technique ou par une décision validée.
Le contrat n'est jamais régénéré depuis le code.

## Valider le contrat

```bash
npm install        # une fois
npm run api:lint
```

Redocly CLI est fixé en version exacte dans `package.json`.

## Vérifier que le code respecte le contrat

```bash
php artisan test --filter=Contract
```

Les tests utilisent Spectator : la requête et la réponse réelles sont comparées au contrat.
Modèle à suivre : `tests/Feature/Api/V1/ContractTest.php`.

## Serveur de mock

Prism n'est pas installé dans le projet : la version compatible avec Node 24.13 (5.14.2) comporte des
avis de sécurité, et la version corrigée (5.16.0) exige Node 24.18 ou plus. Il est reporté
(décision 0004).

En attendant, le mock peut être lancé à la demande, hors du projet :

```bash
npx --yes @stoplight/prism-cli@5.14.2 mock docs/api/openapi/v1.yaml -p 4010
curl http://127.0.0.1:4010/health
```
