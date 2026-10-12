# API Allo Vaho — contrat et outillage

Le contrat de l'API est le fichier [`openapi/v1.yaml`](openapi/v1.yaml). Il fait foi pour le
backend Laravel et pour l'application Flutter. La stratégie est décrite dans
[`../decisions/0004-contrat-openapi.md`](../decisions/0004-contrat-openapi.md).

## Règle de travail

1. Modifier `openapi/v1.yaml`.
2. Valider le fichier (lint).
3. Implémenter côté Laravel.
4. Écrire ou adapter les tests.
5. Vérifier l'impact sur l'application mobile.

Un endpoint n'est ajouté au contrat que s'il est prévu par le dossier technique ou par une décision validée.

## Commandes

Les outils ne sont pas encore installés dans le projet ; les commandes ci-dessous passent par `npx`
avec des versions fixées, vérifiées sur Node 24.13.

Valider le contrat :

```bash
npx --yes @redocly/cli@2.62.1 lint docs/api/openapi/v1.yaml
```

Lancer le serveur de mock (port 4010) :

```bash
npx --yes @stoplight/prism-cli@5.14.2 mock docs/api/openapi/v1.yaml -p 4010
```

Puis, par exemple : `curl http://127.0.0.1:4010/health`.

Prism 5.15 et suivants exigent Node 24.14 ou plus : conserver la version 5.14.2 tant que Node n'est pas mis à jour.

## Tests de conformité

Prévus avec `hotmeteor/spectator` (non installé, en attente de validation) : chaque test d'API
vérifiera que la réponse réelle respecte le contrat.
