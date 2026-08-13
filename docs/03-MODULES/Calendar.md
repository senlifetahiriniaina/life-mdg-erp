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

## Services

- **`CalendarService`** — gestion cœur des calendriers/événements.
- **`ModuleEventAggregatorService`** — service central de l'agrégation cross-module. Point d'entrée `aggregateForUser(int $userId, ?array $sources = null): int`, qui itère sur 8 sources (`hr_leaves`, `project_tasks`, `helpdesk_sla`, `strategy_milestones`, `manufacturing`, `accounting`, `workflow`, `timesheets`), chacune enveloppée dans un `try/catch` silencieux et protégée par `Schema::hasTable(...)` avant toute requête. Chaque source upsert ses événements (`CalendarEvent::updateOrCreate`) dans un calendrier de type `module` dédié (couleur et libellé propres), gardés indépendants les uns des autres. Sources effectivement actives dans le périmètre life-mdg-erp : `hr_leaves` (table `hr_leaves`+`hr_employees`, congés approuvés), `project_tasks` (table `project_tasks`), `helpdesk_sla` (table `hd_tickets`, échéances SLA non résolues), `strategy_milestones` (table `strategy_kros`), `accounting` (table `accounting_invoices`, échéances de paiement à 30 jours), `workflow` (table `workflow_executions`, exécutions planifiées), et `timesheets` (double lecture de `timesheet_entries` **et** `timesheets_entries`, les deux variantes de schéma du module Timesheets).
- **`GoogleCalendarService`** / **`OutlookCalendarService`** / **`AppleCalendarService`** — intégrations OAuth/CalDAV par fournisseur.
- **`ICalExportService`** — génération du flux iCal exportable.

## Permissions RBAC

Calendar n'a pas d'entrée dans `RolesAndPermissionsSeeder::MODULES` : aucune permission granulaire `calendar.*.*` n'est seedée. L'accès API passe par le middleware de route `role:employee,manager,admin` (accès large à tout utilisateur ayant l'un de ces trois rôles), et la policy `CalendarEventPolicy` autorise `view`/`viewAny`/`create` à tout utilisateur authentifié, en réservant `update`/`delete` au créateur de l'événement (`created_by`) ou à `admin`/`super_admin`.

## Dépendances avec d'autres modules

Calendar est un **consommateur passif** d'autres modules : `ModuleEventAggregatorService` lit directement, via `DB::table()` et non via des modèles Eloquent importés, les tables `hr_leaves`/`hr_employees` (HR), `project_tasks` (Projects), `hd_tickets` (Helpdesk), `strategy_kros` (Strategy), `accounting_invoices` (Accounting), `workflow_executions` (Workflow) et `timesheet_entries`/`timesheets_entries` (Timesheets). Ce choix (accès SQL direct plutôt qu'imports `use Modules\X\Models\...`) explique qu'aucun autre module n'apparaît comme dépendance PHP formelle du module Calendar, et que Calendar lui-même n'a aucune dépendance `use Modules\*` déclarée dans son propre code — le couplage est uniquement au niveau schéma de base de données, chaque source étant tolérante à l'absence de la table correspondante.

## Particularités du périmètre life-mdg-erp

`ModuleEventAggregatorService::importManufacturingOrders()` reste présent dans le code et référencé dans la liste des sources agrégées, alors que le module Manufacturing est hors périmètre life-mdg-erp. Il est inoffensif : la méthode commence par `Schema::hasTable('manufacturing_orders')` et retourne `0` immédiatement puisque cette table n'existe pas dans ce dépôt (elle n'est créée par aucune migration), et l'appel est de toute façon enveloppé dans le `try/catch` silencieux de `aggregateForUser()`. Aucune donnée manufacturing n'apparaît donc jamais dans le calendrier ; c'est du code mort inoffensif plutôt qu'une régression fonctionnelle.
