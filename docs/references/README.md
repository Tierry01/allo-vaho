# Maquettes de référence du back-office

Les trois fichiers HTML de ce dossier sont les maquettes validées. Ils font foi et ne doivent pas être modifiés :

- `Allo Vaho Back-office Connexion.html`
- `Allo Vaho Back-office Activation.html`
- `Allo Vaho Back-office Dashboard.html`

Ce sont des bundles auto-extractibles : ils s'ouvrent dans un navigateur, mais leur code n'est pas
lisible directement (ressources encodées en base64, scripts compressés).

## Version lisible

Le dossier `extracted/` contient la même chose, décodée, pour servir de référence à l'implémentation :

```
extracted/<maquette>/index.html     HTML et CSS de la page (variables --av-*, thèmes clair et sombre)
extracted/<maquette>/assets/        polices woff2, logo, scripts
```

Il est produit par un script qui ne fait que lire les originaux :

```bash
php docs/references/tools/extract-mockups.php
```

Relancer le script après toute mise à jour d'une maquette. Ne pas modifier `extracted/` à la main.

## Contenu constaté

- Polices : Nunito (titres) et Noto Sans (texte), conformes au Brief Design, embarquées en woff2.
  Les pages contiennent aussi un lien vers Google Fonts, inutile une fois les polices servies localement.
- Icônes : Lucide 0.460 (maquette Dashboard).
- Chaque maquette inclut un sélecteur d'états de démonstration (réussite, erreur, expiré…) :
  il documente les états à prévoir, il ne fait pas partie de l'interface à livrer.
