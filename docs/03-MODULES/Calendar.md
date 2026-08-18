# Calendar

## Rôle

Le module Calendar est le hub de planification centralisé de Life MDG ERP : il gère des calendriers et événements propres (`Calendar`, `CalendarEvent`), la synchronisation bidirectionnelle avec Google Calendar, Microsoft Outlook et Apple Calendar, l'export iCal, et surtout l'agrégation automatique d'événements en provenance d'autres modules métier (congés RH, tâches de projet, échéances SLA Helpdesk, jalons Strategy, échéances comptables, feuilles de temps) dans un calendrier unifié par utilisateur.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Calendar` | `calendar_calendars` | Un calendrier par utilisateur/type (`personal`, `shared`, `module`), avec couleur, source (`local`/`google`/`outlook`/`apple`) |
| `CalendarEvent` | `calendar_events` | Événement (titre, dates, récurrence, statut `confirmed`/`tentative`/`cancelled`, visibilité, source, `module_type`/`module_id` polymorphiques pour rattacher un événement à un enregistrement d'un autre module) |
| `CalendarAttendee` | (voir migrations) | Participants d'un événement, avec suivi accept/refuse |
| `CalendarReminder` | (voir migrations) | Rappels associés à un événement |
| `CalendarSyncToken` | (voir migrations) | Jetons OAuth/sync pour Google, Outlook, Apple |

## Endpoints principaux

Toutes les routes API sont sous `auth:sanctum`, middleware `module:Calendar`, `role:employee,manager,admin`, préfixe `v1/calendar` (`Modules/Calendar/routes/api.php`).

| Méthode | Route | Description |
|---|---|---|
| GET/POST | `calendars` | Lister / créer un calendrier |
| PUT/DELETE | `calendars/{calendar}` | Modifier / supprimer un calendrier |
| GET/POST | `events` | Lister / créer un événement |
| PUT/DELETE | `events/{event}` | Modifier / supprimer un événement |
| GET/POST | `events/{event}/attendees` | Lister / ajouter des participants |
| GET | `upcoming` | Widget « prochains événements » |
| GET | `export/ics` | Export iCal |
| GET | `sync/status` | Statut de synchronisation |
| GET/POST/DELETE | `sync/google/{auth,callback,sync,disconnect}` | Cycle OAuth + sync Google Calendar |
| GET/POST/DELETE | `sync/outlook/{auth,callback,sync,disconnect}` | Cycle OAuth + sync Outlook (Microsoft Graph) |
| POST/DELETE | `sync/apple/{connect,sync,disconnect}` | Connexion CalDAV/iCal Apple |
| POST | `v1/calendar/webhooks/google` \| `/outlook` | Callbacks webhook fournisseurs (hors `auth:sanctum`, préfixe séparé) |
| POST | `v1/calendar/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

Routes web (`auth` + `module:Calendar`, préfixe `/calendar`) : `index`, `settings` (rend désormais `Calendar/Integrations`, voir Vues), `events/create`, `events/{event}`, `integrations`, `teams` — les 4 dernières ajoutées cette session (elles n'avaient auparavant aucune route malgré des pages réelles et des liens qui pointaient vers elles).

## Contrôleurs

- **`Api\CalendarController`** — CRUD calendriers/événements/participants. Ses appels `authorize()` (déjà présents) ont longtemps 403 sur `update`/`delete` pour tout le monde, y compris les admins — voir RBAC.
- **`Api\CalendarSyncController`** — cycle OAuth/CalDAV Google/Outlook/Apple + export iCal + webhooks fournisseurs.
- **`Api\CalendarAiAssistController`** — guidance IA contextuelle.
- **`Web\CalendarPageController`** — rend les pages Inertia (`index`, `settings`/`integrations` désormais alias intentionnels de la même page, `events.create`, `events.show` — nouveau, `teams`).

## Vues (Vue/Inertia)

Toutes sous `Modules/Calendar/resources/js/Pages/` — Calendar est le seul module du dépôt à avoir eu un dossier `Pages/Calendar/` imbriqué à l'intérieur de son propre dossier `Pages/`, ce qui a créé une confusion corrigée cette session (voir Particularités) :

- **`Index.vue`** — page principale, réellement servie par `resources/js/app.js::resolve()` pour la clé `Calendar/Index` (résolution racine-first : `./Pages/Calendar/Index.vue` prioritaire sur le module). Contenait initialement la version la plus pauvre des deux implémentations qui coexistaient ; contient désormais la version riche (déplacée depuis l'ancien sous-dossier imbriqué, voir Particularités).
- **`Event/Create.vue`**, **`Event/Show.vue`**, **`Integrations.vue`**, **`Teams.vue`** — déplacées cette session depuis un sous-dossier `Pages/Calendar/` imbriqué mort (jamais servi par `resolve()`) vers leur emplacement réel ; toutes réelles et fonctionnelles mais sans route avant cette session.
- `/calendar/settings` (500 sur chaque visite avant cette session — `Calendar/Settings.vue` n'a jamais existé) pointe désormais directement vers `Integrations.vue`, qui couvre déjà exactement ce que `settings()` était censé fournir, plutôt que de dupliquer un composant.

## Services

- **`CalendarService`** — gestion cœur des calendriers/événements.
- **`ModuleEventAggregatorService`** — service central de l'agrégation cross-module. Point d'entrée `aggregateForUser(int $userId, ?array $sources = null): int`, qui itère sur 8 sources (`hr_leaves`, `project_tasks`, `helpdesk_sla`, `strategy_milestones`, `manufacturing`, `accounting`, `workflow`, `timesheets`), chacune enveloppée dans un `try/catch` silencieux et protégée par `Schema::hasTable(...)` avant toute requête. Chaque source upsert ses événements (`CalendarEvent::updateOrCreate`) dans un calendrier de type `module` dédié (couleur et libellé propres), gardés indépendants les uns des autres. Sources effectivement actives dans le périmètre life-mdg-erp : `hr_leaves` (table `hr_leaves`+`hr_employees`, congés approuvés), `project_tasks` (table `project_tasks`), `helpdesk_sla` (table `hd_tickets`, échéances SLA non résolues), `strategy_milestones` (table `strategy_kros`), `accounting` (table `accounting_invoices`, échéances de paiement à 30 jours), `workflow` (table `workflow_executions`, exécutions planifiées), et `timesheets` (double lecture de `timesheet_entries` **et** `timesheets_entries`, les deux variantes de schéma du module Timesheets).
- **`GoogleCalendarService`** / **`OutlookCalendarService`** / **`AppleCalendarService`** — intégrations OAuth/CalDAV par fournisseur.
- **`ICalExportService`** — génération du flux iCal exportable.

## Permissions RBAC

Calendar n'a pas d'entrée dans `RolesAndPermissionsSeeder::MODULES` : aucune permission granulaire `calendar.*.*` n'est seedée. L'accès API passe par le middleware de route `role:employee,manager,admin` (accès large à tout utilisateur ayant l'un de ces trois rôles), et les policies `CalendarPolicy`/`CalendarEventPolicy` autorisent `view`/`viewAny`/`create` à tout utilisateur authentifié, en réservant `update`/`delete` au créateur de l'événement (`created_by`) ou à `admin`/`super-admin`.

**Rupture active corrigée cette session** : `CalendarPolicy`/`CalendarEventPolicy` étaient correctement écrites et correctement appelées via `authorize()` dans `CalendarController`, mais `CalendarServiceProvider` ne les enregistrait jamais auprès du Gate de Laravel — chaque mise à jour/suppression de calendrier ou d'événement renvoyait donc un 403 pour absolument tout le monde, y compris les administrateurs. Corrigé par un `registerPolicies()` (nouveau) dans `CalendarServiceProvider`. Une coquille de nom de rôle (`super_admin` au lieu de `super-admin`) dans les deux policies a été corrigée au passage — de sévérité faible, puisque `super-admin` court-circuite de toute façon les vérifications de Gate via `Gate::before`, mais incorrecte quand même.

## Dépendances avec d'autres modules

Calendar est un **consommateur passif** d'autres modules : `ModuleEventAggregatorService` lit directement, via `DB::table()` et non via des modèles Eloquent importés, les tables `hr_leaves`/`hr_employees` (HR), `project_tasks` (Projects), `hd_tickets` (Helpdesk), `strategy_kros` (Strategy), `accounting_invoices` (Accounting), `workflow_executions` (Workflow) et `timesheet_entries`/`timesheets_entries` (Timesheets). Ce choix (accès SQL direct plutôt qu'imports `use Modules\X\Models\...`) explique qu'aucun autre module n'apparaît comme dépendance PHP formelle du module Calendar, et que Calendar lui-même n'a aucune dépendance `use Modules\*` déclarée dans son propre code — le couplage est uniquement au niveau schéma de base de données, chaque source étant tolérante à l'absence de la table correspondante.

## Particularités du périmètre life-mdg-erp

`ModuleEventAggregatorService::importManufacturingOrders()` reste présent dans le code et référencé dans la liste des sources agrégées, alors que le module Manufacturing est hors périmètre life-mdg-erp. Il est inoffensif : la méthode commence par `Schema::hasTable('manufacturing_orders')` et retourne `0` immédiatement puisque cette table n'existe pas dans ce dépôt (elle n'est créée par aucune migration), et l'appel est de toute façon enveloppé dans le `try/catch` silencieux de `aggregateForUser()`. Aucune donnée manufacturing n'apparaît donc jamais dans le calendrier ; c'est du code mort inoffensif plutôt qu'une régression fonctionnelle.

**Confusion racine/module corrigée cette session** : Calendar était le seul module du dépôt à avoir un sous-dossier `Pages/Calendar/` imbriqué *à l'intérieur* de son propre `Modules/Calendar/resources/js/Pages/`. En traçant l'algorithme réel de `resources/js/app.js::resolve()` (résolution racine-first, `./Pages/${name}.vue` avant le repli module), il s'est avéré que c'est le `Pages/Index.vue` plat qui était réellement servi pour `Calendar/Index`, pas la version imbriquée plus riche — l'audit initial avait supposé l'inverse. Le sous-dossier imbriqué (`Event/Create.vue`, `Event/Show.vue`, `Integrations.vue`, `Teams.vue`) a été remonté d'un niveau et le contenu riche a remplacé celui d'`Index.vue`, l'ancien dossier imbriqué désormais vide a été supprimé — le résultat net (construire les vraies pages, supprimer les mortes) reste celui visé, seule la direction du fichier « réel » avait été mal identifiée au départ.
