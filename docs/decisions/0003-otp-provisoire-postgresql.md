# 0003 — OTP provisoire sur cache PostgreSQL

- **Statut :** validée dans son principe le 12/10/2026. **Conception uniquement, rien n'est implémenté.**
- **Écart :** le Dossier technique v1.3 (§ 11) prévoit un code « stocké haché dans Redis ».

## Règles du dossier à respecter (inchangées)

- code à 6 chiffres, valide 5 minutes ;
- 3 essais par code ;
- 5 envois par numéro et par heure, limites par IP et par appareil ;
- réponses identiques qu'un numéro existe ou non (anti-énumération).

## Solution envisagée

1. **Abstraction** — passer par le cache Laravel via un store nommé `otp`
   (`config/cache.php`), aujourd'hui en driver `database`, demain en `redis`.
   Le code métier ne connaît que `Cache::store('otp')`.
2. **Clé** — `otp:{HMAC-SHA256(numéro E.164, secret serveur)}` : aucun numéro en clair dans le cache.
3. **Valeur** — empreinte du code + compteur d'essais. L'empreinte est un
   HMAC-SHA256 avec un secret serveur (et non un simple hachage) : un code à
   6 chiffres n'a que 10⁶ valeurs, un hachage sans secret se retrouve par force brute.
4. **Expiration** — TTL de 5 minutes porté par l'entrée de cache.
5. **Essais** — compteur incrémenté de façon atomique sous verrou (`Cache::lock`,
   table `cache_locks` existante) ; au 3ᵉ échec l'entrée est supprimée.
6. **Usage unique** — l'entrée est supprimée dès la vérification réussie ; une
   nouvelle demande remplace le code précédent.
7. **Comparaison** — en temps constant (`hash_equals`).
8. **Limitation des envois** — `RateLimiter` Laravel, par numéro (5/h), par IP et par appareil.

## Limites par rapport à Redis

- Chaque demande et chaque essai écrivent dans PostgreSQL : acceptable au volume
  de lancement, à surveiller en charge.
- Le driver `database` ne purge pas seul les entrées expirées : une purge planifiée est à prévoir.
- `php artisan cache:clear` sur le store concerné efface les codes en cours et les
  compteurs de limitation. Une table dédiée au store `otp` l'éviterait (à décider à l'implémentation).

## Transition vers Redis

Changer le driver du store `otp` (et du cache par défaut pour le `RateLimiter`).
Les codes en cours au moment de la bascule sont perdus (durée de vie : 5 minutes).
Aucune modification du code métier ni du contrat d'API.

## Points à valider avant implémentation

- valeurs des limites par IP et par appareil (non chiffrées dans le dossier) ;
- table de cache dédiée ou table `cache` partagée.
