# Schéma de base de données — vue générale

## Deux niveaux de migrations

- **`database/migrations/`** (143 fichiers) — schéma **partagé** entre plusieurs modules : utilisateurs, permissions RBAC (`spatie/laravel-permission`), journal d'audit (`activity_log`), activation de modules (`tenant_modules`), tokens d'API (`personal_access_tokens`), conformité RGPD (`consent_logs`/`gdpr_*`), webhooks, index de performance.
- **`Modules/<Nom>/database/migrations/`** — tables propres à chaque module. Exemples de volumétrie : `CRM` (6 fichiers), `Helpdesk` (6), `Inventory` (3), `Sales` (3), `Payroll` (3), `HR` (2, périmètre basique), `Accounting` (1).

## Convention de nommage des tables

Chaque module préfixe généralement ses tables avec une abréviation courte pour éviter les collisions et identifier rapidement le module propriétaire d'une table donnée en base :

| Module | Préfixe observé | Exemple |
|---|---|---|
| HR | `hr_` | `hr_employees` |
| Helpdesk | `hd_` | `hd_tickets` |

**Point d'attention historique** : lors de l'extraction depuis WideHalo ERP, plusieurs intégrations cross-modules (Calendar, Workflow) référençaient encore le nom `helpdesk_tickets` en SQL brut alors que la vraie table s'appelle `hd_tickets` — un bug préexistant qui rendait ces intégrations silencieusement no-op. Corrigé pendant l'extraction (voir `docs/03-MODULES/Helpdesk.md`). Vérifiez toujours le nom réel de la table dans la migration plutôt que de le déduire du nom du modèle.

## Tables partagées clés (racine)

| Table | Rôle |
|---|---|
| `users` | Comptes utilisateurs |
| `roles` / `permissions` / `model_has_roles` / `model_has_permissions` / `role_has_permissions` | RBAC (spatie/laravel-permission) |
| `personal_access_tokens` | Tokens Sanctum |
| `activity_log` | Journal d'audit (spatie/laravel-activitylog) |
| `tenant_modules` | Activation de modules (aligné avec `config/modules_statuses.json`) |
| `consent_logs` / `gdpr_*` | Conformité RGPD/PDPL |
| `webhooks` | Webhooks sortants, signés HMAC |

## Environnement de test

Les tests tournent contre SQLite en mémoire (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` dans `phpunit.xml`) — rapide, mais certaines contraintes MySQL-spécifiques (longueur de clé, `ON UPDATE CASCADE` complexes) ne sont validées qu'en CI, où `ci.yml` utilise un vrai service MySQL 8.4. C'est pourquoi le job `php-tests` de `ci.yml` exécute `php artisan migrate` contre MySQL avant `vendor/bin/pest`, même si les tests eux-mêmes utilisent SQLite localement.

## Rafraîchir le schéma localement

```bash
php artisan migrate:fresh --seed
```

Doit s'exécuter sans erreur avant tout merge (cf. `docs/08-TESTS/STRATEGIE-TESTS.md`).
