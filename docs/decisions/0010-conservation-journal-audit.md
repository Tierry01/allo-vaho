# 0010 — Conservation du journal d'audit

- **Statut :** **proposition — en attente de décision métier. Aucune purge n'est en place.**
- **Écart au Dossier technique v1.3 :** aucun. Le dossier prévoit le journal (§ 4, § 13, § 18) sans en fixer la durée.

## Ce qui est conservé aujourd'hui

Table `activity_log`, journal `auth` (décision 0009) :

| Donnée | Exemple | Nature |
|---|---|---|
| Événement et date | `auth.login`, `invitation.issued` | technique |
| Administrateur concerné ou auteur | identifiant interne | donnée personnelle (pseudonyme) |
| Adresse IP et navigateur | pour les événements web | donnée personnelle |
| Adresse e-mail d'une personne invitée | `invitation.issued` | donnée personnelle |
| Empreinte d'une adresse inconnue | échec de connexion | pseudonyme, non réversible sans la clé |
| Opérateur et motif | récupération, réinitialisation de la 2FA | donnée personnelle, valeur probatoire |

Aucun secret n'y figure. La table grossit à chaque connexion et à chaque échec, y compris lors d'attaques
par essais répétés.

Le paquet propose une purge (`activitylog:clean`) et un réglage de 365 jours (`config/activitylog.php`), mais
**rien ne la déclenche** : aucune tâche planifiée n'est configurée.

## Proposition

| Catégorie | Événements | Durée proposée | Raison |
|---|---|---|---|
| Courante | `auth.login`, `auth.logout`, `auth.login_failed`, `auth.two_factor_failed` | 12 mois | Enquête sur un incident, détection d'abus ; au-delà, peu d'utilité et beaucoup de volume |
| Cycle de vie des comptes | `invitation.*`, `auth.two_factor_enrolled`, `auth.password_reset*` | 24 mois | Savoir qui a donné quel accès, et quand |
| Exceptionnelle | `auth.two_factor_reset`, `auth.super_admin_recovered` | 36 mois | Actions rares et sensibles, à valeur de preuve |

Option à décider : effacer l'adresse IP et le navigateur des entrées courantes après 90 jours, en gardant
l'événement.

## Méthode de purge proposée

1. Une commande dédiée, `admin:prune-audit-log`, qui applique les durées par catégorie. La commande
   `activitylog:clean` du paquet ne sait purger qu'avec une seule durée par journal.
2. Exécutée chaque nuit par le planificateur Laravel, en dehors des heures d'activité.
3. Suppression par lots (par exemple 5 000 lignes), pour ne pas verrouiller la table.
4. Chaque exécution écrit une entrée `audit.pruned` : nombre de lignes, catégorie, date limite.
5. Un index sur `activity_log.created_at`, à ajouter avant la mise en service.

## Précautions d'exploitation

- **Sauvegardes :** une purge ne touche pas les sauvegardes. Leur durée de conservation doit être alignée,
  sinon les données restent disponibles plus longtemps que prévu.
- **Gel en cas d'incident :** pouvoir suspendre la purge (réglage de configuration) pendant une enquête.
- **Recette d'abord :** valider la commande sur la recette avec un volume réaliste.
- **Planificateur :** il n'est pas configuré en local et suppose une tâche système (cron ou planificateur
  Windows) en recette et en production.
- **Accès au journal :** sa consultation est une permission de la Phase 11 ; la purge ne doit pas dépendre d'un écran.

## Décisions métier nécessaires avant implémentation

1. Les durées par catégorie (ou une durée unique).
2. Le cadre légal applicable aux données personnelles du journal, à confirmer par le responsable juridique.
3. L'effacement anticipé de l'IP et du navigateur, ou non.
4. La durée de conservation des sauvegardes.
5. Qui peut suspendre la purge en cas d'incident.
6. L'éventuel export du journal avant purge.
