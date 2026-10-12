# Brief design Allo Vaho
v1.0 · 28 septembre 2026 · Étape 1 sur 3 · Document de référence (1 dev Flutter, 1 dev Laravel, 16 semaines)

Ce document fixe la marque, les couleurs, la typographie, le ton et les composants de l’app mobile Flutter et des interfaces web Laravel. Si un besoin sort du cadre, on complète le brief d’abord.

---

## 01 · Positionnement et personnalité

**Promesse : « Tout le monde à portée d’allo. »**

Allo Vaho est l’annuaire de confiance du Cameroun : on y souscrit un forfait Camtel illimité, on retrouve ses proches et les pros du quartier, et on les appelle en un geste. Ce n’est pas une banque ni une app d’opérateur : c’est un carnet d’adresses vivant, qui encaisse proprement et dit toujours la vérité sur l’état du forfait.

- **Chaleureuse** : on parle comme un voisin serviable, pas comme un guichet.
- **Fiable** : chaque franc est tracé, chaque statut est daté.
- **Directe** : une action principale par écran, des phrases courtes.
- **D’ici** : noms, quartiers, FCFA, Mobile Money : le Cameroun réel.

Nous sommes un carnet d’adresses, un guichet honnête, un lien entre proches. Nous ne sommes pas une fintech froide, un réseau social bruyant, une pub d’opérateur.

### Trois principes de design
1. **On sait toujours où on en est.** Tout statut montre l’étape actuelle, l’heure du dernier changement, la prochaine étape et son délai maximum. L’attente Camtel (jusqu’à 24 h) est une étape normale. *Test : l’utilisateur peut-il dire ce qui va se passer ensuite ?*
2. **Payer sans avoir peur.** Avant paiement : montant exact, bénéficiaire (numéro complet), moyen et numéro débité sur un seul écran. Le bouton répète le montant (« Payer 5 000 FCFA »). Après : un reçu, toujours. *Test : une capture d’écran prouve-t-elle le paiement ?*
3. **Léger comme un SMS.** Android 1–2 Go de RAM, 3G, plein soleil. Pas de photo décorative, peu d’animations, texte foncé sur fond clair. Numéros utiles et répertoire consulté disponibles hors ligne. *Test : l’écran est-il utilisable en 3G lente, images bloquées ?*

---

## 02 · Personas

### Mireille Ngo Bassong — 42 ans, vendeuse de pagnes, marché Mboppi, Douala
> « Mes fournisseurs sont à Bafoussam, mes clientes partout. Je passe ma journée au téléphone. »
- Téléphone : Tecno Spark, écran fissuré, souvent en mode économie
- Paiement : Orange Money, tous les jours
- Objectif : appeler sans compter, retrouver le numéro d’un grossiste
- Frein : peur de payer « dans le vide » sans savoir si le forfait arrivera
- Pour nous : statut toujours visible, reçu clair, gros boutons d’appel

### Brice Fotso — 21 ans, étudiant en génie civil, Ngoa-Ekellé, Yaoundé
> « Je paie le forfait de ma mère à Bafang depuis Yaoundé, elle ne sait pas le faire. »
- Téléphone : Itel A70, stockage presque plein, 3G la nuit
- Paiement : MTN Mobile Money
- Objectif : souscrire pour un proche, inviter ses camarades
- Frein : données chères, appli lourde = désinstallée
- Pour nous : parcours « pour un proche », app légère, lien d’invitation

### Emmanuel Ekotto — 35 ans, électricien-plombier à son compte, Bonamoussadi, Douala
> « Mes clients me trouvent par le bouche-à-oreille. Je veux qu’ils me trouvent sur la carte. »
- Téléphone : Samsung A05, souvent en plein soleil sur les chantiers
- Paiement : Orange Money ; Google Play pour le plan Entreprise
- Objectif : fiche pro géolocalisée, appels de nouveaux clients
- Frein : formulaires longs, peur d’exposer son numéro perso
- Pour nous : création de fiche en étapes, contrôle du numéro, lisible au soleil

---

## 03 · Palette

Le bleu du logo **#009FE3** reste la couleur de marque, mais il ne contraste qu’à 3,0:1 échec avec le blanc. Règle : **#009FE3 pour la marque, les icônes et les grands aplats ; Vaho 600 #0077B6 pour le texte et les boutons sur fond clair.** Neutres chauds (argile) pour sortir du gris-bleu fintech. Ratios WCAG 2.1 calculés.

### Bleu Vaho
| Nom | Hex | Usage | Sur blanc | Sur encre #1F1C18 |
|---|---|---|---|---|
| Vaho 50 | `#E6F6FD` | Fonds teintés, sélection | 1,1:1 échec | 15,3:1 AAA |
| Vaho 100 | `#C2E9FA` | Survol, pastilles | 1,3:1 échec | 13,2:1 AAA |
| Vaho 300 | `#5CC4F0` | Illustrations | 2,0:1 échec | 8,6:1 AAA |
| Vaho 500 (marque) | `#009FE3` | Logo, icônes, focus, grands aplats | 3,0:1 échec | 5,7:1 AA |
| Vaho 600 | `#0077B6` | Boutons, liens, texte bleu | 4,9:1 AA | 3,5:1 AA gros texte |
| Vaho 700 | `#005F91` | Pressé, titres bleus | 6,9:1 AA | 2,5:1 échec |
| Vaho 900 | `#063A57` | Texte sur fonds bleu clair | 12,0:1 AAA | 1,4:1 échec |

### Neutres argile et accent Soleil
| Nom | Hex | Usage | Sur blanc |
|---|---|---|---|
| Blanc | `#FFFFFF` | Cartes, champs | 1,0:1 échec |
| Sable 50 | `#FAF7F2` | Fond de l’app | 1,1:1 échec |
| Sable 100 | `#F2EDE5` | Fonds secondaires | 1,2:1 échec |
| Sable 200 | `#E4DDD2` | Bordures, séparateurs | 1,3:1 échec |
| Sable 300 | `#CFC6B8` | Bordures fortes | 1,7:1 échec |
| Argile 600 | `#6E665A` | Texte secondaire (minimum) | 5,7:1 AA |
| Argile 800 | `#3A352E` | Texte de paragraphe | 12,1:1 AAA |
| Encre 900 | `#1F1C18` | Texte principal | 17,0:1 AAA |
| Soleil | `#F2A93B` | Accent favoris / mise en avant, jamais en texte | 2,0:1 échec |

### Statuts d’abonnement
Un statut n’est jamais porté par la couleur seule : icône + libellé + couleur.

| Statut | Icône | Clair (texte / fond) | Contraste | Sombre (texte / fond) | Contraste |
|---|---|---|---|---|---|
| En attente de paiement | `schedule` | `#8A5A00` / `#FDF1D8` | 5,3:1 AA | `#F5C46B` / `#33270F` | 9,0:1 AAA |
| Payé | `payments` | `#00679E` / `#E1F3FC` | 5,4:1 AA | `#6CCBF3` / `#0E2A3A` | 8,1:1 AAA |
| Transmis à Camtel | `send` | `#5B45A8` / `#EEEAFB` | 6,2:1 AA | `#B3A3F0` / `#251E3D` | 7,1:1 AAA |
| Actif | `check_circle` | `#17693F` / `#E3F4EA` | 5,9:1 AA | `#6FD49B` / `#15301F` | 7,8:1 AAA |
| Rejeté | `cancel` | `#B42318` / `#FDE8E6` | 5,6:1 AA | `#FF8A7E` / `#3A1714` | 7,0:1 AAA |
| Remboursé | `currency_exchange` | `#0F6B6B` / `#DFF3F1` | 5,5:1 AA | `#6FD1C9` / `#12302E` | 7,8:1 AAA |
| Expiré | `event_busy` | `#5C5549` / `#EEEAE3` | 6,1:1 AA | `#C9C0B2` / `#2D2924` | 8,0:1 AAA |

### Mode sombre
| Nom | Hex | Usage |
|---|---|---|
| Fond | `#171512` | Arrière-plan |
| Surface | `#221F1B` | Cartes, barres |
| Surface 2 | `#2D2924` | Champs, survol |
| Bordure | `#3A352E` | Séparateurs |
| Texte | `#F4EFE7` | Texte principal · 15,9:1 AAA sur fond |
| Texte 2 | `#B9B0A2` | Secondaire · 7,7:1 AAA sur surface |
| Marque | `#009FE3` | Bouton principal, texte #0B1A24 · 6,0:1 AA |
| Lien bleu | `#33B5EC` | Liens, icônes actives · 7,0:1 AAA sur surface |

En sombre, le bouton principal est #009FE3 avec texte encre #0B1A24 (le blanc sur #009FE3 échoue). Fond brun très foncé plutôt que noir pur.

```dart
// lib/theme/av_colors.dart
AvColors.brand500 = Color(0xFF009FE3); // marque, icônes, focus
AvColors.brand600 = Color(0xFF0077B6); // ColorScheme.primary (clair)
AvColors.ink900   = Color(0xFF1F1C18); // onSurface
AvColors.sand50   = Color(0xFFFAF7F2); // surface
// Sombre : primary = brand500, onPrimary = 0xFF0B1A24, surface = 0xFF171512
// Laravel : mêmes tokens en variables CSS --av-brand-600, etc.
```

---

## 04 · Typographie

- **Titres : Nunito** 700 / 800 / 900 — terminaisons arrondies, cousines du lettrage « allo ». Titres, montants, boutons.
- **Texte : Noto Sans** 400 / 500 / 600 — rendu net en petit, accents parfaits, chiffres tabulaires (`tnum`) pour montants et numéros.

| Token | Police | Taille / interligne | Exemple |
|---|---|---|---|
| display | Nunito 900 | 32 / 38 | Bienvenue, Mireille |
| titre-1 | Nunito 800 | 26 / 32 | Votre forfait est actif |
| titre-2 | Nunito 800 | 22 / 28 | Choisir un moyen de paiement |
| titre-3 | Nunito 700 | 18 / 24 | Mes contacts sur Allo Vaho |
| montant | Nunito 900 tnum | 28 / 32 | 5 000 FCFA |
| corps | Noto 400 | 16 / 24 | Camtel active votre forfait en moins de 24 h. |
| corps-fort | Noto 600 tnum | 16 / 24 | +237 677 12 34 56 |
| petit | Noto 400 | 14 / 20 | Douala · Akwa · inscrite depuis mars |
| légende | Noto 500 | 12 / 16 (minimum) | Mis à jour à 14 h 03 |

- Taille minimale 12 sp (légendes). Corps à 16 sp.
- Les majuscules gardent leurs accents (« ÉTAPE 2 »). Pas de capitales au-delà de 3 mots.
- Le français est 15–25 % plus long que l’anglais : aucun bouton à largeur fixe.
- Espace insécable avant « : ; ? ! » et dans montants et heures : « 5 000 FCFA », « 14 h 03 ».
- Agrandissement système testé jusqu’à 130 %.

---

## 05 · Icônes, illustrations, formes

- **La bulle, forme signature** : rayon 24/24/24/8 (coin bas-gauche resserré, comme la queue de la bulle du logo). Cartes de forfait, badges de statut, avatars, toasts. Champs et listes en rayon régulier.
- **Icônes : Material Symbols Rounded**, police variable native Flutter, trait 400, 24 dp. Contour par défaut, plein pour l’état actif. Couleur encre ; bleu pour l’élément actif.
- **Illustrations** : aplats, 3 couleurs max (bleu Vaho, Soleil, encre), personnages camerounais du quotidien, nuage du logo en motif. SVG ≤ 15 Ko, jamais de photo d’ambiance.

---

## 06 · Ton rédactionnel

On vouvoie. Phrases de 12 mots max, verbes d’action, montant et délai en chiffres. On dit ce qui s’est passé, puis quoi faire. Jamais « transaction », « erreur 500 », « requête ».

| Contexte | Oui | Non |
|---|---|---|
| Bouton | Payer 5 000 FCFA | Valider la transaction |
| Bouton | Demander le numéro | Envoyer une requête |
| Bouton | Arrêter le renouvellement | Désactiver l’auto-renew |
| Erreur | Le paiement n’a pas abouti. Aucun montant n’a été débité. Réessayez ou changez de moyen de paiement. | Erreur 402 : paiement refusé. |
| Erreur | Vous n’avez pas validé sur votre téléphone à temps. Relancez le paiement. | Timeout USSD. |
| Erreur | Ce numéro semble incomplet : il faut 9 chiffres. | Numéro invalide ! |
| Vide | Aucun de vos contacts n’est encore là. Invitez-en un, il vous remerciera. | Aucun résultat. |
| Vide | Pas encore de reçu. Il apparaîtra ici après votre premier forfait. | Historique vide. |
| Notification | Forfait actif ! Appels, SMS et internet illimités jusqu’au 28 octobre. | Votre abonnement a changé de statut. |
| Notification | Christelle Mbarga est sur Allo Vaho. Dites-lui bonjour. | Nouvel utilisateur détecté dans vos contacts. |
| Notification | Votre forfait se termine dans 3 jours. Renouvelez en 1 minute. | URGENT : votre forfait expire !!! |

---

## 07 · Bibliothèque de composants

- **Boutons** (h 52, rayon 16) : principal (#0077B6, texte blanc), secondaire (contour), tonal (Vaho 50), destructif texte, désactivé, chargement (libellé conservé).
- **Champ numéro camerounais** (h 56) : préfixe fixe +237, saisie groupée « 677 12 34 56 », aide « 9 chiffres », erreur explicite (« Il manque 2 chiffres »).
- **OTP** : 6 cases 48×56, lecture auto du SMS (SMS Retriever), collage accepté, « Renvoyer le code dans 0:42 », « Changer de numéro ».
- **Carte de forfait** : forme bulle, nom, inclus (appels / SMS / internet), prix Nunito 900, durée, bouton « Choisir », étiquette Soleil « Le plus choisi ».
- **Badge de statut** (h 28) : icône + libellé, couleurs de la section 03.
- **Carte de statut (Accueil)** : statut, phrase datée (« Activation au plus tard demain à 14 h 03 »), forfait, numéro, montant payé.
- **Timeline d’abonnement** : Paiement reçu → Transmis à Camtel → Activation par Camtel (en cours, délai max) → Forfait actif. Rejet : étape rouge + étape « Remboursement ».
- **Ligne d’annuaire** (h 72) : avatar bulle 48, nom, numéro, favori, boutons SMS et Appeler 48 dp.
- **Numéro masqué** : « +237 6•• •• •• 42 » + « Demander le numéro ». États : envoyée / acceptée / refusée (pas de nouvelle demande avant 7 jours).
- **Sélecteur de paiement** (h 64) : Orange Money, MTN Mobile Money, Carte Visa via Nokash ; radio, numéro débité prérempli.
- **Onglets** : segmenté (Tous · Mes contacts · Favoris) ; barre de navigation 5 onglets (Accueil, Annuaire, Entreprises, Numéros utiles, Profil), pastille active Vaho 50, icône pleine.
- **Feuille modale** : rayon 28 en haut, poignée, récapitulatif avant paiement.
- **Toasts** : en bas, 4 s, forme bulle, fond encre. Jamais pour une info de paiement.

---

## 08 · États obligatoires (chaque écran)

- **Chargement** : squelettes à la forme du contenu, cache d’abord. Après 8 s : « C’est plus long que d’habitude… » + Réessayer.
- **Vide** : icône dans une bulle, une phrase, une action. Jamais « Aucune donnée ».
- **Erreur** : ce qui s’est passé + Réessayer ; code technique copiable ; données déjà affichées conservées.
- **Hors ligne** : bandeau discret avec date du cache. Appel et SMS natifs possibles ; paiement et demande de numéro désactivés avec explication.

---

## 09 · Grille, espacements, rayons, ombres, zones tactiles

- **Espacements (base 4)** : 4 icône↔texte · 8 entre cibles · 12 gouttière · 16 marge écran · 20 padding carte · 24 entre blocs · 32 entre sections · 40 haut d’écran · 48 zone tactile.
- **Grille mobile** : 4 colonnes, marges 16 dp (20 dès 400 dp), gouttière 12. Conçu pour 360×640, vérifié en 390×844. Web : 12 colonnes, 1440, contenu max 1200.
- **Rayons** : sm 8 · md 12 · lg 16 · xl 28 · bulle 24/24/24/8 · rond.
- **Ombres** : par défaut bordure 1 px #E4DDD2. e1 `0 1px 2px rgba(31,28,24,.08), 0 1px 3px rgba(31,28,24,.06)` ; e2 `0 8px 24px rgba(31,28,24,.14)`. Pas d’ombre en sombre.
- **Zones tactiles** : 48×48 dp min, 8 dp entre cibles ; boutons principaux 52 dp, pleine largeur, tiers bas. Actions destructives éloignées et confirmées.

---

## 10 · Accessibilité et performance visuelle

**Accessibilité** : AA partout (4,5:1 texte, 3:1 icônes/bordures actives) ; jamais la couleur seule ; libellés TalkBack/VoiceOver (« Appeler Christelle Mbarga ») ; numéros lus chiffre par chiffre ; texte à 130 % sans coupure ; focus visible 2 px #009FE3 sur le web.

**Plein soleil** : mode clair par défaut ; pas de gris plus clair que #6E665A ; pas de texte sur photo ni dégradé ; statut, montant, échéance en 18 sp min ; graisses ≥ 400.

**Performance** : aucune photo décorative ; SVG ≤ 15 Ko, avatars WebP 96 px ; icônes en police ; animations 150–200 ms ; cache local (annuaire, numéros utiles, statuts, reçus) ; carte chargée à l’ouverture.

Budgets : APK/AAB ≤ 25 Mo · polices ≈ 600 Ko · premier affichage ≤ 2 s en 3G sur Tecno Spark / Itel A70 · pages de 30 contacts · avatars ≤ 8 Ko.

---

## 11 · Le logo dans les interfaces

- Versions : bleu #009FE3 sur clair ou sombre ; blanc sur bleu Vaho ; symbole seul (bulle-combiné) pour icône d’app, avatar, favicon.
- **La version blanche actuelle est provisoire** : générée depuis le PNG, le nuage du « o » y devient une pastille pleine. Version monochrome vectorielle à produire.
- Zone de protection = hauteur du « o » nuage. Taille min : 24 dp écran, 18 mm impression.
- Placement : splash (blanc, 120 dp, sur #009FE3) · connexion/onboarding (couleur, 56 dp, centré) · accueil (couleur, 28 dp, barre du haut) · reçus/e-mails (32 px) · web (40 px, symbole en favicon).
- À éviter : déformer, recolorer, fond concurrent, ombre, contour, photo chargée.
- Fichiers SVG + PDF à produire avant la semaine 2.

---

## 12 · Supports de communication hors application

Aplat bleu Vaho, grande phrase en Nunito 900, bulle comme cadre, un seul message et un prix par support.

- **Affiche A3 / flyer A5** : « Appelez sans compter. » + cartouche « 5 000 FCFA / mois » + QR code. Marchés, universités, moto-taxis.
- **Kakémono 80×200** : « Tout le monde à portée d’allo. » + 3 bénéfices + capture de l’app.
- **Statut WhatsApp 9:16** : « 14 de tes contacts sont déjà sur Allo Vaho » + lien d’invitation (généré depuis l’app).
- **Post réseaux 1:1** : « Un électricien à Bonamoussadi ? » — Espace Entreprises.
- **Carte de visite 85×55** : recto blanc, verso bleu.
- **Autocollant vitrine Ø 20 cm** : « Abonnement ici · Orange Money · MTN MoMo ».
- **T-shirt / gilet ambassadeur** : logo blanc sur bleu, dos « Posez-moi vos questions ».
- **Bâche de kiosque 3×1 m** : « Forfait illimité à 5 000 FCFA ».

Impression : bleu ≈ C100 M20 Y0 K0, à valider sur épreuve. Un prix par support. Jamais de prix de plan Entreprise hors stores. QR et liens courts tracés par canal.

---

## 13 · Aperçu accueil et connexion

- **Mobile · Splash** : fond #009FE3, logo blanc centré, « Tout le monde à portée d’allo. », « Forfaits opérés par Camtel ».
- **Mobile · Connexion** : logo 56 dp, « Entrez votre numéro », champ +237, « Recevoir le code », Continuer avec Google / Apple (numéro vérifié ensuite), accès « Numéros utiles sans compte », mentions légales.
- **Mobile · Accueil** : logo 28 dp + cloche ; « Bonjour Mireille » ; carte de statut Actif (illimité jusqu’au 10 octobre, 12 jours restants) ; bandeau de renouvellement auto ; raccourcis Souscrire / Pour un proche / Favoris / Inviter ; « Mes contacts sur Allo Vaho : 14 » ; barre 5 onglets.
- **Web · Page d’accueil** : en-tête (Forfaits, Annuaire, Entreprises, Aide, Espace Entreprise, Télécharger l’app) ; hero « Tout le monde à portée d’allo. » + badges stores + téléphone sur bulle bleue ; 3 packs.
- **Web · Connexion Espace Entreprise** : panneau bleu « Votre entreprise, sur la carte de tous les abonnés. » ; formulaire numéro + OTP 6 chiffres + « Se connecter ».

---

## Choix clés et hypothèses

**Choix clés**
- #009FE3 conservé pour la marque ; #0077B6 porte texte et boutons pour passer AA.
- Neutres chauds « argile » + accent Soleil #F2A93B.
- La bulle du logo comme forme signature, réservée aux éléments humains et au statut.
- Nunito + Noto Sans.
- Icônes en police (Material Symbols Rounded).

**Hypothèses à valider**
- Vouvoiement par défaut.
- Noms et prix des packs (1 500 / 5 000 / 13 500 FCFA) = exemples, définis par l’admin.
- Statuts « Payé » et « Remboursé » ajoutés.
- Refus de demande de numéro : pas de nouvelle demande avant 7 jours.
- Logos Orange Money, MTN MoMo, Visa, stores à obtenir avec leurs chartes.
