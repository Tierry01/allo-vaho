# 0002 — Environnement local sans Docker/Redis, tests sur PostgreSQL

- **Statut :** validée le 12/10/2026, en place.
- **Écart :** le Dossier technique v1.3 prévoit Docker Compose en local et Redis + Horizon.

## Décision

- Développement local sur PHP et PostgreSQL 16 + PostGIS installés sur le poste.
  Docker, Redis et Horizon sont reportés et ne doivent pas être supposés disponibles.
- Session, cache et files utilisent le driver `database` (PostgreSQL) en attendant Redis.
- Les tests s'exécutent sur une base PostgreSQL dédiée, `allo_vaho_test`, jamais sur SQLite :
  le modèle repose sur PostGIS, JSONB et `timestamptz`.

## Mise en œuvre

- `phpunit.xml` force (`force="true"`) `APP_ENV=testing`, `DB_CONNECTION=pgsql`,
  `DB_DATABASE=allo_vaho_test`. Hôte, utilisateur et mot de passe viennent du `.env` local.
- `tests/TestCase.php` refuse de démarrer si la base ciblée n'est pas une base PostgreSQL
  dont le nom se termine par `_test` (protège notamment contre un `config:cache` oublié).
- Migrer la base de test :
  `DB_DATABASE=allo_vaho_test APP_ENV=testing php artisan migrate`
- PostGIS est activé par la migration `2026_10_12_000000_enable_postgis_extension`
  (idempotente ; nécessite un rôle superuser uniquement lorsque l'extension est absente).

## Dates

- Application et session PostgreSQL en UTC (`config/app.php`, `config/database.php` → `timezone`).
- Colonnes de date en `timestamptz` (§ 13 du dossier).
- Affichage en heure de Douala via `config('app.display_timezone')`.
