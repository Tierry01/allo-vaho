# 0008 — Design system du back-office : polices, icônes, ressources externes

- **Statut :** validée le 12/10/2026.
- **Écart au Dossier technique v1.3 :** aucun. Références : Brief Design v1.0 et maquettes validées
  (`docs/references/`).

## Décisions

- Panneau d'administration Filament 5 sous `/admin`.
- Polices **Nunito** (titres) et **Noto Sans** (texte), servies localement.
- **Aucune ressource chargée depuis un CDN à l'exécution** (polices, scripts, feuilles de style).
- Icônes : **Lucide**, comme dans les maquettes.
- Thèmes clair et sombre obligatoires.

## Polices : origine, intégrité, licence

Les fichiers proviennent des maquettes validées (`docs/references/extracted/*/assets/*.woff2`).
Les trois maquettes embarquent exactement les mêmes 13 fichiers (empreintes identiques).

Vérifications faites sur chaque fichier, par lecture de sa table `name` :

| Famille | Version | Fichiers | Graisses | Licence inscrite dans le fichier | Titulaire |
|---|---|---|---|---|---|
| Noto Sans | 2.015 | 8 (un par plage Unicode) | variable, 400 à 700 | SIL Open Font License — `https://openfontlicense.org` | © 2022 The Noto Project Authors |
| Nunito | 3.602 | 5 (un par plage Unicode) | variable, 700 à 800 dans les maquettes | SIL Open Font License — `https://scripts.sil.org/OFL` | © 2014 The Nunito Project Authors |

- **Intégrité :** signature WOFF2 valide et décompression complète des 13 fichiers, sans erreur.
- **Licence :** la SIL Open Font License 1.1 autorise l'usage, l'intégration et la redistribution, y compris
  commerciale, à condition de conserver la notice de droit d'auteur et la licence avec les fichiers et de
  ne pas vendre les polices seules. Une notice accompagne donc les fichiers dans le projet.
- Le Brief prévoit Nunito 700 / 800 / 900 ; les maquettes ne déclarent que 700 et 800.

## Icônes

Les maquettes utilisent Lucide 0.460 (licence ISC). Filament fournit Heroicons par défaut.
Lucide est la référence visuelle ; le mode d'intégration dans Filament est vérifié à l'installation,
avant tout ajout de dépendance.

## Serveur de mock

Prism est reporté (voir décision 0004) : Node n'est pas mis à niveau pour lui et il n'est pas ajouté
aux dépendances du projet.
