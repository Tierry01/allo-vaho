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
- Le Brief prévoit Nunito 700 / 800 / 900 ; les maquettes ne déclarent que 700 et 800. Les fichiers sont
  variables (Nunito 200 à 1000, Noto Sans 100 à 900) : `fonts.css` déclare Nunito 700–900 et Noto Sans 400–700.

### Sous-ensembles conservés dans le projet

Six fichiers sur treize sont conservés dans `resources/fonts/` (336 Ko), avec les deux `OFL.txt`.
Les maquettes de `docs/references/` gardent les treize fichiers d'origine.

| Sous-ensemble | Conservé | Raison (couverture mesurée dans les fichiers) |
|---|---|---|
| `latin` | Oui | Tout le français : lettres accentuées, œ, æ, guillemets, tirets, apostrophe, €, espace insécable |
| `latin-ext` | Oui | Ÿ, et l'alphabet général des langues camerounaises : ɓ ɗ ə ɛ ɨ ɔ ŋ ʉ ƴ ẅ et leurs majuscules, voyelles à ton précomposées |
| `vietnamese` | Oui | Seul à fournir les diacritiques combinants de ton U+0300, U+0301, U+0303, U+0323 (ɛ́, ɔ̀… n'ont pas de forme précomposée) |
| `cyrillic`, `cyrillic-ext`, `greek`, `greek-ext`, `devanagari` | Non | Écritures non utilisées par les contenus prévus |

Limites constatées, déjà présentes avec les treize fichiers sauf mention :

- Nunito (titres) n'a pas ɛ, ɔ, ɨ, ʉ ni quelques voyelles à ton : ces caractères s'affichent en Noto Sans dans un titre.
- Les diacritiques combinants U+0302 (circonflexe), U+030C (caron), U+0327 et U+0330 ne sont servis par aucun
  sous-ensemble : le navigateur utilise une police du système.
- L'espace fine insécable U+202F (séparateur de milliers français) n'est dans aucun sous-ensemble.
- Le signe № (U+2116) n'était fourni que par le sous-ensemble cyrillique : il n'est plus servi par les polices du projet.

## Icônes

Les maquettes utilisent Lucide 0.460 (licence ISC). Filament fournit Heroicons par défaut.
Lucide est la référence visuelle (voir « Mise en œuvre »).

## Serveur de mock

Prism est reporté (voir décision 0004) : Node n'est pas mis à niveau pour lui et il n'est pas ajouté
aux dépendances du projet.

## Mise en œuvre (Phase 1 — Design system)

### Fichiers

| Fichier | Rôle |
|---|---|
| `resources/css/admin/tokens.css` | Tokens `--av-*` clair et sombre, repris tels quels de la maquette Dashboard |
| `resources/css/admin/components.css` | Composants `av-*` de la maquette, repris tels quels |
| `resources/css/admin/fonts.css` | `@font-face` locaux (6 fichiers dans `resources/fonts/`, avec `OFL.txt`) |
| `resources/css/filament/admin/theme.css` | Thème Filament : habille les composants Filament avec les tokens |
| `resources/views/components/admin/` | Composants Blade : `icon`, `badge`, `alert`, `state`, `skeleton` |
| `app/Filament/Pages/DesignSystem.php` | Page de référence `/admin/design-system`, visible en environnement local uniquement |
| `app/Filament/AvatarProviders/InitialsAvatarProvider.php` | Avatars à initiales générés localement |

Le thème sombre des tokens s'active par la classe `.dark` que pose Filament, et reste compatible avec
`data-theme="dark"` des maquettes.

### Icônes

`mallardduck/blade-lucide-icons` 2.0 (MIT, jeu d'icônes Blade, pas un plugin Filament) fournit Lucide :
`lucide-<nom>` dans Filament, `<x-admin.icon name="<nom>" />` dans les vues. Les icônes de l'habillage du
panneau (menu, thème, recherche, déconnexion…) sont remplacées par leur équivalent Lucide dans
`AdminPanelProvider::icons()`. Les icônes internes des composants Filament non listées restent des Heroicons.

### Ressources externes

Vérifié dans un navigateur : aucune requête hors de l'application sur l'écran d'accès et dans le panneau.
Deux réglages y contribuent : `LocalFontProvider` pour les polices, et un fournisseur d'avatars local à la
place de `ui-avatars.com`, utilisé par défaut par Filament.

### Écarts aux maquettes

- **Structure du panneau :** dans Filament la barre supérieure couvre toute la largeur ; la maquette place la
  marque en tête de sidebar. La zone de marque de la barre est donc teinte comme la sidebar pour retrouver
  le même aspect.
- **Écran d'accès :** c'est l'écran par défaut de Filament, habillé par le thème. La mise en page des maquettes
  Connexion et Activation relève de la Phase 2.
- **Barre supérieure :** fil d'Ariane, recherche globale, notifications, aide et carte utilisateur de la maquette
  ne sont pas reproduits ; ils dépendent de fonctionnalités des phases suivantes.
- **Navigation :** compteurs, sections repliables, pastille d'état système et étiquette d'environnement de la
  sidebar ne sont pas reproduits (pas encore de contenu).
- **Focus :** voir « Focus clavier » ci-dessous.
- **Composants `av-*` sans composant Blade :** StatCard, menus, modale, tiroir, toast, timeline, JsonViewer.
  Leurs styles sont présents ; les composants seront écrits avec les écrans qui les utilisent.

### Focus clavier

Décision du 12/10/2026 : un indicateur unique, fondé sur le halo de 3 px des maquettes.

Contraste mesuré de l'indicateur par rapport au fond (seuil visé : 3:1) :

| Indicateur | Thème clair | Thème sombre |
|---|---|---|
| Halo de la maquette seul (`--av-focus-ring`) | 1,4:1 à 1,5:1 — insuffisant | 2,4:1 — insuffisant |
| Contour `#009FE3` seul (`CLAUDE.md` § 14) | 2,6:1 à 3,0:1 — insuffisant | 6,3:1 |
| Liseré `--av-link` (`#0077B6` clair, `#4FC4F5` sombre) | 4,3:1 | 9,4:1 |
| Anneau de la sidebar (`--av-sb-accent` sur le bleu profond) | 7,0:1 | 9,2:1 |

Le halo seul ne suffit pas. L'indicateur retenu garde donc le halo de la maquette et lui ajoute un
liseré de 2 px en `--av-link`, décalé de 3 px. Il n'utilise que des tokens existants et s'applique par
une seule règle à tous les éléments du panneau, composants Filament comme composants `av-*`.

Exceptions :

- **Sidebar et zone de marque :** anneau plein de 2 px en `--av-sb-accent`, comme dans la maquette.
- **Champs :** l'indicateur est porté par le cadre du champ, pas par l'élément de saisie interne.

Vérifié dans Chrome par tabulation réelle : 50 éléments parcourus (écran d'accès, page de référence en
clair et en sombre), tous porteurs de l'indicateur, sans élément à double style.

Non vérifié : lecteurs d'écran, ordre de tabulation complet, contraste des textes, autres navigateurs.
Le liseré ajouté au halo est une correction de ma part à valider côté design.
