# Vue d'ensemble de l'architecture

## Positionnement

Life MDG ERP est un ERP Laravel 12 + Vue 3 modulaire, extrait du monorepo WideHalo ERP (49 modules) en août 2026 pour le lancement de Life MDG. Contrairement à WideHalo ERP — qui répartit son code sur 5 dépôts (`Widehalo-ERP`, `widehalo-erp-core`, `widehalo-erp-ecommerce`, `widehalo-erp-mobile`, `widehalo-erp-admin`) avec une arborescence `apps/api` + `apps/webapp` — life-mdg-erp est **un dépôt unique et autonome**, sans dépendance vers ces dépôts frères, avec une structure Laravel standard (une seule application, pas de monorepo interne).

## Les 7 piliers WideHalo, dans le périmètre life-mdg-erp

Voir `docs/02-ARCHITECTURE/PRINCIPES.md` pour le détail de comment chaque pilier est concrètement implémenté dans ce dépôt.

## Architecture modulaire

L'application s'appuie sur `nwidart/laravel-modules` : chaque domaine métier vit dans `Modules/<Nom>/` avec sa propre structure MVC complète (Models, Controllers, Services, Policies, Routes, Migrations, Tests, pages Vue). `config/modules_statuses.json` liste les 27 modules actifs — désactiver un module revient à passer sa valeur à `false` ou à supprimer son dossier, sans câblage de routes manuel ailleurs (chaque module enregistre ses propres routes via son `RouteServiceProvider`).

```
27 modules actifs :
AI, API, Accounting, Achats, Analytics, AuditLog, BI, CRM, Calendar, Core,
HR, Helpdesk, Integration, Inventory, Logistics, Payroll, Projects,
Reporting, Sales, Security, Settings, Setup, Shared, Strategy, Timesheets,
Validation, Workflow
```

Voir `docs/02-ARCHITECTURE/CARTE-MODULES.md` pour le détail des dépendances entre ces modules.

## Backend

- Laravel 12, PHP 8.2+ (8.3/8.4 en CI/prod)
- `app/` : le vrai point d'entrée Laravel (routes globales `routes/api.php`/`routes/web.php`, contrôleurs transverses comme `HealthController`/`MetricsController`/`AiChatController`) — **pas** `apps/api`, qui n'existe pas dans ce dépôt (contrairement à Widehalo-ERP où cette confusion apps/ vs racine était déjà documentée comme piège).
- Authentification : Laravel Sanctum (tokens API, expiration 30 jours par défaut, `SANCTUM_TOKEN_EXPIRATION`) + un flux JWT dédié sous `/api/v1/auth/jwt/*` pour certains clients.
- Autorisation : `spatie/laravel-permission` (RBAC, 22 rôles — voir `docs/09-RBAC-SECURITE/MATRICE-RBAC.md`).
- Base de données : migrations racine (`database/migrations/`, schéma partagé — utilisateurs, permissions, audit log, RGPD) + migrations par module (`Modules/*/database/migrations/`).

## Frontend

- Vue 3 + Inertia.js (`resources/js/app.js`), pas de SPA séparée servie par un autre serveur — Inertia fait le pont entre les contrôleurs Laravel et les composants Vue sans API REST intermédiaire pour le rendu des pages.
- PrimeVue (composants UI), Pinia (state), vue-i18n (10 langues disponibles dans `lang/`, 4 câblées par défaut : en/fr/pt/es), Vite (build, découpage manuel des chunks vendor pour le cache navigateur — voir `vite.config.js`).
- Chaque module peut avoir ses propres pages Vue sous `Modules/<Nom>/resources/js/Pages/`, en plus des pages globales sous `resources/js/Pages/`.

## Multi-tenant / déploiement

Contrairement à WideHalo ERP qui vise un déploiement multi-tenant avec bases isolées par entreprise, life-mdg-erp est actuellement configuré pour un déploiement mono-tenant (une instance = Life MDG). L'infrastructure de tenancy (`stancl/tenancy`, présente dans `composer.json`) reste disponible si un besoin multi-société apparaît, mais n'est pas activement exploitée dans le périmètre de lancement.
