# 0004 — Contrat OpenAPI défini en amont

- **Statut :** stratégie retenue le 12/10/2026. Contrat créé et validé. **Installation des outils dans le projet en attente de validation.**
- **Écart partiel :** le Dossier technique v1.3 (§ 14) prévoit une « documentation OpenAPI
  générée (Scribe) » ; il prévoit aussi (§ 20, sprint 0) un « contrat OpenAPI v1 » et un
  « serveur de mock », et des modèles Flutter générés depuis l'OpenAPI.

## Constat

Scramble, Scribe et l5-swagger **génèrent** un document OpenAPI à partir du code Laravel.
Ils ne peuvent donc pas fournir un contrat avant l'implémentation, ni un mock pour
l'équipe mobile au sprint 0. Un contrat défini en amont est un fichier écrit à la main et versionné.

## Décision

1. **Source de vérité :** `docs/api/openapi/v1.yaml` (OpenAPI 3.1), modifié avant le code.
2. **Validation du fichier :** lint Redocly CLI, en local puis en CI.
3. **Conformité du code :** tests Pest avec Spectator — la réponse réelle est comparée au contrat.
4. **Mock pour le mobile :** Prism, lancé sur le même fichier.
5. **Documentation du back-office (Phase 10 — Technique) :** rendue depuis le même fichier.
6. **Scribe / Scramble :** non retenus comme source du contrat.

## Compatibilité vérifiée dans l'environnement réel (12/10/2026)

Environnement : PHP 8.3.14, Laravel 13.35.0, Node 24.13.0, npm 11.6.2.

| Outil | Version | Contrainte | Résultat constaté |
|---|---|---|---|
| @redocly/cli | 2.62.1 | Node ≥ 22.12 | Exécuté via `npx` : contrat valide, 2 avertissements |
| @stoplight/prism-cli | 5.14.2 | Node ≥ 18.20.1 | Exécuté via `npx` : `/health` renvoie l'exemple du contrat |
| @stoplight/prism-cli | 5.15.x / 5.16.0 | Node ≥ 24.14 / ≥ 24.18 | Incompatibles avec Node 24.13 |
| hotmeteor/spectator | 3.0.4 | PHP ^8.3, Laravel ≥ 12 | Simulation Composer : 8 installations, 0 mise à jour — non installé |

**Node n'a pas besoin d'être mis à niveau** : Prism 5.14.2 suffit. Passer à Prism 5.16 demanderait
Node ≥ 24.18 (mise à jour mineure de Node 24) ; rien dans le projet ne l'exige aujourd'hui.

Les deux avertissements Redocly sont attendus : serveur `localhost`, et absence de réponse `4XX`
sur `/health` tant que le format d'erreur n'est pas défini.

## État du contrat

`v1.yaml` décrit uniquement `GET /health`, seul endpoint existant. Aucun endpoint métier n'y figure.

## À valider

- installation dans le projet : `@redocly/cli` et `@stoplight/prism-cli@5.14.2` (devDependencies npm),
  `hotmeteor/spectator` (require-dev Composer) ;
- format d'erreur RFC 7807 (§ 14) : nom du champ portant le « code technique » et liste des codes,
  à décrire dans le contrat avant le premier endpoint métier ;
- abandon de Scribe comme générateur (écart au § 14).
