# Calendar

## Rôle

Le module Calendar est le hub de planification centralisé de Life MDG ERP : il gère des calendriers et événements propres (`Calendar`, `CalendarEvent`), la synchronisation bidirectionnelle avec Google Calendar, Microsoft Outlook et Apple Calendar, l'export iCal, et surtout l'agrégation automatique d'événements en provenance d'autres modules métier (congés RH, tâches de projet, échéances SLA Helpdesk, jalons Strategy, échéances comptables, automatisations planifiées Workflow, feuilles de temps) dans un calendrier unifié par utilisateur.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Calendar` | `calendar_calendars` | Un calendrier par utilisateur/type (`personal`, `shared`, `module`), avec couleur, source (`local`/`google`/`outlook`/`apple`) |
| `CalendarEvent` | `calendar_events` | Événement (titre, dates, récurrence, statut `confirmed`/`tentative`/`cancelled`, visibilité, source, `module_type`/`module_id` polymorphiques pour rattacher un événement à un enregistrement d'un autre module) |
| `CalendarAttendee` | `calendar_attendees` | Participants d'un événement, avec suivi accept/refuse |
| `CalendarReminder` | `calendar_reminders` | Rappels associés à un événement (`email`/`push`/`popup`, `minutes_before`) |
| `CalendarSyncToken` | `calendar_sync_tokens` | Jetons OAuth/sync pour Google, Outlook, Apple (un par utilisateur+fournisseur) |

## Endpoints principaux

Toutes les routes API sont sous `auth:sanctum`, middleware `module:Calendar`, `role:employee,manager,admin`, préfixe `v1/calendar` (`Modules/Calendar/routes/api.php`), sauf les webhooks fournisseurs (voir plus bas).

| Méthode | Route | Description |
|---|---|---|
| GET/POST | `calendars` | Lister / créer un calendrier |
| PUT/DELETE | `calendars/{calendar}` | Modifier / supprimer un calendrier |
| GET/POST | `events` | Lister / créer un événement |
| GET/PUT/DELETE | `events/{event}` | Consulter / modifier / supprimer un événement |
| GET/POST | `events/{event}/attendees` | Lister / ajouter des participants |
| GET | `upcoming` | Widget « prochains événements » (aucun consommateur réel actuellement, voir Particularités) |
| GET | `export/ics` | Export iCal réel et fonctionnel (RFC 5545) |
| GET | `sync/status` | Statut de synchronisation par fournisseur |
| GET/POST/DELETE | `sync/google/{auth,callback,sync,disconnect}` | Cycle OAuth + sync Google Calendar |
| GET/POST/DELETE | `sync/outlook/{auth,callback,sync,disconnect}` | Cycle OAuth + sync Outlook (Microsoft Graph) |
| POST/DELETE | `sync/apple/{connect,sync,disconnect}` | Connexion CalDAV/iCal Apple |
| POST | `v1/calendar/webhooks/google` \| `/outlook` | Callbacks webhook fournisseurs (hors `auth:sanctum`, préfixe séparé, throttlés `webhook`) |
| POST | `v1/calendar/ai/assist` | Guidance IA contextuelle par module — **non atteint par le frontend réel**, voir Particularités |

Routes web (`auth` + `module:Calendar`, préfixe `/calendar`) : `index`, `settings` (alias intentionnel de `integrations`), `events/create`, `events/{event}`, `events/{event}/edit` (nouveau, Chantier 32.12 — voir Vues), `integrations`, `teams`.

## Format de réponse API (Chantier 32.12)

`indexCalendars()`, `indexEvents()` et `upcoming()` renvoient désormais `{"data": [...]}`, et `CalendarSyncController::status()` renvoie `{"data": {...}}` — auparavant des tableaux/objets JSON nus. Chaque page Vue réelle consommant ces endpoints (`Index.vue`, `Teams.vue`, `Event/Create.vue`, `Integrations.vue`) lit `response.data`, suivant la convention dominante de cette app (collections de ressources Laravel) : avant ce correctif, ces 4 pages n'ont **jamais** affiché un seul événement/calendrier/statut de connexion réel, confirmé empiriquement. `GET events` acceptait auparavant uniquement `start`/`end` obligatoires — aucune des 2 pages qui l'appellent ne les envoyait jamais (422 systématique, silencieusement avalé par leur propre `.catch()`) ; ils sont désormais optionnels avec une fenêtre de repli raisonnable (aujourd'hui −2 mois à +6 mois).

## Contrôleurs

- **`Api\CalendarController`** — CRUD calendriers/événements/participants + export iCal + widget « à venir ». `updateEvent()` applique désormais une contrainte de cohérence croisée `end_at >= start_at` même sur une mise à jour partielle (via un hook `Validator::after()`, puisqu'une règle `after_or_equal` classique est ignorée par Laravel quand le champ de référence n'est pas dans la requête) — confirmé empiriquement qu'un `PUT` n'envoyant que `end_at` pouvait auparavant le positionner avant le `start_at` réellement stocké, sans erreur.
- **`Api\CalendarSyncController`** — cycle OAuth/CalDAV Google/Outlook/Apple + statut de sync + webhooks fournisseurs. Les 2 endpoints webhook (`webhookGoogle`/`webhookOutlook`) ne déclenchent désormais un `SyncCalendarJob` que si l'utilisateur résolu depuis l'en-tête/le corps (contrôlé par le client, puisque ces routes sont hors `auth:sanctum` par nature) possède réellement un `CalendarSyncToken` pour ce fournisseur — voir Sécurité.
- **`Api\CalendarAiAssistController`** — guidance IA contextuelle propre au module ; confirmé non atteint par le frontend réel (voir Particularités).
- **`Web\CalendarPageController`** — rend les pages Inertia (`index`, `settings`/`integrations` alias intentionnels de la même page, `events.show`, `teams`).

## Vues (Vue/Inertia)

Toutes sous `Modules/Calendar/resources/js/Pages/` :

- **`Index.vue`** — page principale, réellement servie par `resources/js/app.js::resolve()` pour la clé `Calendar/Index`.
- **`Event/Create.vue`** — corrigé cette session (Chantier 32.12) : le champ « Participants » envoyait un tableau de chaînes email brutes alors que l'API valide un tableau d'objets `{email}` (422 systématique dès qu'un participant était saisi) ; le sélecteur « Rappel » n'était jamais traduit en un vrai tableau `reminders` (silencieusement non fonctionnel depuis la création de la page).
- **`Event/Show.vue`** — détail d'un événement, self-fetching.
- **`Event/Edit.vue`** — **nouveau (Chantier 32.12)**. Le bouton « Modifier » de `Event/Show.vue` a toujours pointé vers `/calendar/events/{id}/edit`, une route qui n'a jamais existé (404 systématique) — la véritable API `PUT events/{event}` fonctionnait déjà correctement, seule la page/route manquait.
- **`Integrations.vue`** — corrigé cette session : lisait `data.auth_url` alors que `googleAuth()`/`outlookAuth()` renvoient `{url}` — cliquer sur « Connecter » pour Google/Outlook n'a jamais redirigé nulle part avant ce correctif.
- **`Teams.vue`** — calendrier d'équipe, self-fetching.

## Services

- **`CalendarService`** — gestion cœur des calendriers/événements.
- **`ModuleEventAggregatorService`** — service central de l'agrégation cross-module. Point d'entrée `aggregateForUser(int $userId, ?array $sources = null): int`, qui itère sur 8 sources, chacune enveloppée dans un `try/catch` silencieux et protégée par `Schema::hasTable(...)` avant toute requête. Chaque source upsert ses événements (`CalendarEvent::updateOrCreate`) dans un calendrier de type `module` dédié. Sources et leur état réel après le Chantier 32.12 :
  - `hr_leaves` (tables réelles `hr_leave_requests`+`hr_employees`, congés approuvés de l'utilisateur) — correct depuis Chantier 19.
  - `project_tasks` (table réelle `prj_tasks`, tâches assignées à l'utilisateur) — correct depuis Chantier 19.
  - `helpdesk_sla` (table `hd_tickets`, échéances SLA non résolues assignées à l'utilisateur) — correct.
  - `strategy_milestones` (table réelle `strategy_objectives`) — **cloisonnement multi-société corrigé au Chantier 32.12** : la méthode ne filtrait auparavant par aucune société, confirmé empiriquement qu'un objectif stratégique d'une autre société apparaissait dans le calendrier personnel de n'importe quel utilisateur. `strategy_objectives` n'a pas de colonne `tenant_id`/`company_id` propre — la société est désormais résolue via `plan_id -> strategy_plans.tenant_id`.
  - `accounting` (table `acc_invoices`, échéances de paiement à 30 jours) — aucun filtrage par société : confirmé et **volontairement laissé tel quel**, puisque le grand livre Accounting de cette app n'a lui-même aucune notion de cloisonnement par société (« un seul grand livre partagé », déjà documenté ailleurs dans `CLAUDE.md` pour `OhadaReportService`) — ce n'est donc pas une fuite mais le comportement correct pour ce module.
  - `workflow` (« Workflows Planifiés ») — **entièrement réécrit au Chantier 32.12**. Interrogeait auparavant `workflow_executions.scheduled_at`, une colonne qui n'a jamais existé sur cette table (colonnes réelles : `started_at`/`completed_at`/`triggered_at`) — une erreur SQL garantie sur chaque appel réel, silencieusement avalée, sans compter que `workflow_executions` modélise des exécutions déjà déclenchées, pas des automatisations à venir. Rebranché sur le vrai modèle `Modules\Workflow\Models\Automation\AutomationFlow` (table `automation_flows`, `trigger_type = 'schedule'`, prochaine échéance dans `trigger_config->next_run_at`), scopé aux flux créés par l'utilisateur lui-même.
  - `manufacturing` — code mort inoffensif confirmé toujours inchangé (voir Particularités).
  - `timesheets` (double lecture de `timesheet_entries` et `timesheets_entries`) — correct, `timesheets_entries` gracieusement absent depuis le Chantier 9.
- **`GoogleCalendarService`** / **`OutlookCalendarService`** / **`AppleCalendarService`** — intégrations OAuth/CalDAV par fournisseur. Dégradent proprement en l'absence d'identifiants réels configurés (confirmé empiriquement — voir Particularités).
- **`ICalExportService`** — génération du flux iCal exportable (RFC 5545), réelle et fonctionnelle.

## Sécurité (Chantier 32.12)

Les 2 endpoints webhook fournisseurs (`webhooks/google`/`webhooks/outlook`) sont volontairement hors `auth:sanctum` (un vrai callback fournisseur ne porte pas de session) — mais ils faisaient jusqu'ici confiance à un identifiant d'utilisateur entièrement contrôlé par le client (`X-Goog-Channel-ID`/`clientState`) sans aucune corrélation avec une véritable connexion existante. `GoogleCalendarService::watchCalendar()`/`OutlookCalendarService::subscribeToDelta()` — les seuls mécanismes qui enregistreraient réellement un canal webhook légitime auprès du fournisseur — n'ont **aucun appelant nulle part** dans l'app (confirmé par grep) : aucun webhook légitime n'a donc jamais pu atteindre ces routes non plus, qui restaient pourtant accessibles à quiconque, pour n'importe quel id. Ces deux méthodes ne sont **pas activées** dans ce chantier (nécessiteraient un vrai enregistrement d'application OAuth Google/Microsoft, indisponible dans ce bac à sable, plus une tâche planifiée de renouvellement de canal — Google expire les canaux après 7 jours — une décision produit hors du périmètre d'un audit) mais **conservées, pas supprimées**, puisque les supprimer retirerait le seul chemin légitime qui rendrait un jour ces webhooks réels. Correctif pragmatique appliqué : les 2 endpoints ne déclenchent plus `SyncCalendarJob` que pour un utilisateur possédant réellement un `CalendarSyncToken` pour ce fournisseur, et les 2 routes portent désormais le limiteur `throttle:webhook` déjà utilisé ailleurs dans l'app pour ce même profil « callback externe non authentifié ».

## Permissions RBAC

Calendar n'a pas d'entrée dans `RolesAndPermissionsSeeder::MODULES` (confirmé inchangé) : aucune permission granulaire `calendar.*.*` n'est seedée. L'accès API passe par le middleware de route `role:employee,manager,admin`, et les policies `CalendarPolicy`/`CalendarEventPolicy` autorisent `view`/`viewAny`/`create` à tout utilisateur authentifié, en réservant `update`/`delete` au créateur de l'événement/calendrier (`created_by`/`user_id`) ou à `admin`/`super-admin`. `storeCalendar()` appelle désormais `authorize('create', Calendar::class)` (Chantier 32.12, cohérence — `create()` reste inconditionnellement `true`, donc sans changement de comportement).

**Enregistrement Gate re-confirmé (Chantier 32.12)** : `CalendarServiceProvider::registerPolicies()` (ajouté au Chantier 8.6 après une rupture active où `authorize()` renvoyait systématiquement 403) reste correctement enregistré — re-vérifié empiriquement par une vraie requête HTTP `PUT` d'un propriétaire sur son propre événement, qui réussit (200), tandis qu'un collègue de la même société non propriétaire reçoit bien 403.

## Dépendances avec d'autres modules

Calendar est un **consommateur passif** d'autres modules : `ModuleEventAggregatorService` lit directement, via `DB::table()` et non via des modèles Eloquent importés, les tables `hr_leave_requests`/`hr_employees` (HR), `prj_tasks` (Projects), `hd_tickets` (Helpdesk), `strategy_objectives`/`strategy_plans` (Strategy), `acc_invoices` (Accounting), `automation_flows` (Workflow) et `timesheet_entries`/`timesheets_entries` (Timesheets). Ce choix (accès SQL direct plutôt qu'imports `use Modules\X\Models\...`) explique qu'aucun autre module n'apparaît comme dépendance PHP formelle du module Calendar, et que Calendar lui-même n'a aucune dépendance `use Modules\*` déclarée dans son propre code — le couplage est uniquement au niveau schéma de base de données, chaque source étant tolérante à l'absence de la table correspondante. Une exception : `use App\Models\User` (résolution de `company_id` pour le scoping Strategy) — dépendance sur le modèle racine `User`, pas sur un autre module.

## AI Assisted First (couche 13, Chantier 32.12)

5 pages Vue réelles appellent `useAiAssistant('Calendar', <action>)` : `view_calendar` (Index.vue), `create_event` (Event/Create.vue), `view_event` (Event/Show.vue), `team_calendar` (Teams.vue), `calendar_integrations` (Integrations.vue). Les 6 actions du module (les 5 ci-dessus + `calendar_settings`, référencée uniquement comme suggestion de navigation, jamais appelée directement) sont toutes enregistrées dans `AiContextualAssistantService::supportedModules()` avec un vrai texte de repli en français et en anglais — re-confirmé empiriquement (requêtes HTTP réelles vers `POST /api/v1/ai/assist`) que les 5 actions réellement appelées renvoient un contenu substantiel dans les 2 langues, aussi bien avant qu'après ce chantier (aucune régression, la découverte du Chantier 30/32.2 tenait toujours).

**`CalendarAiAssistController`** (`POST v1/calendar/ai/assist`) reste **non atteint par le frontend réel** : le composable `useAiAssistant()` appelle toujours l'unique point d'entrée global `POST /api/v1/ai/assist`, jamais les contrôleurs `<Module>AiAssistController` dédiés — motif déjà documenté à l'échelle de l'app entière par le Chantier 32.2 (`Modules\AI`), qui a explicitement choisi de ne pas le corriger module par module mais de le signaler pour un futur nettoyage global. Cohérent avec cette décision : non modifié ici.

## Particularités du périmètre life-mdg-erp

`ModuleEventAggregatorService::importManufacturingOrders()` reste présent dans le code et référencé dans la liste des sources agrégées, alors que le module Manufacturing est hors périmètre life-mdg-erp. Il est inoffensif : la méthode commence par `Schema::hasTable('manufacturing_orders')` et retourne `0` immédiatement puisque cette table n'existe pas dans ce dépôt, et l'appel est de toute façon enveloppé dans le `try/catch` silencieux de `aggregateForUser()`. Aucune donnée manufacturing n'apparaît donc jamais dans le calendrier ; c'est du code mort inoffensif plutôt qu'une régression fonctionnelle — reconfirmé au Chantier 32.12, inchangé.

**Dégradation gracieuse en l'absence d'identifiants fournisseur réels (Chantier 32.12, re-confirmé empiriquement)** : ce bac à sable n'a aucun identifiant Google/Outlook/Apple configuré. `GET sync/{google,outlook}/auth` construit tout de même une URL OAuth valide (avec `client_id`/`redirect_uri` vides) sans planter ; `POST sync/{google,outlook}/sync` déclenche `SyncCalendarJob` (exécuté en synchrone puisque `QUEUE_CONNECTION=sync` dans ce bac à sable) qui se termine normalement (200, `queued: true`) même sans jeton de synchronisation stocké, chaque service capturant l'absence de jeton en amont d'un vrai appel réseau ; `POST sync/apple/connect` avec des identifiants fictifs effectue un véritable appel PROPFIND réseau, échoue rapidement (proxy sortant du bac à sable renvoie une erreur de connexion en ~0,3s) et renvoie un 422 propre avec un message d'erreur clair — jamais de 500 ni de blocage.

**`watchCalendar()`/`subscribeToDelta()` — code réel orphelin, classification explicite (couche 9, Chantier 32.12)** : ces deux méthodes (`GoogleCalendarService`/`OutlookCalendarService`) sont réelles, bien écrites, et constituent le seul mécanisme qui enregistrerait légitimement un canal de notification push auprès de Google/Microsoft — mais elles n'ont aucun appelant nulle part dans l'app. Classées « orphelines, conservées, non activées » plutôt que supprimées (voir section Sécurité ci-dessus pour le raisonnement complet) : les activer nécessiterait une vraie inscription d'application OAuth (indisponible ici) et une tâche planifiée de renouvellement, une décision produit distincte d'un audit de bugs.

**Nettoyage mineur trouvé, non corrigé (couche 10, priorité faible)** : `CalendarService::deleteCalendar()` fait un `$calendar->events()->delete()` en masse (soft-delete Eloquent bulk) qui ne déclenche pas le nettoyage explicite des participants/rappels que `deleteEvent()` fait, lui, événement par événement — laisse des lignes `calendar_attendees`/`calendar_reminders` orphelines (mais invisibles, jamais interrogées indépendamment de leur événement) pointant vers des événements soft-deleted. Aucune fuite de données ni plantage constaté ; signalé pour un futur nettoyage plutôt que corrigé dans ce chantier.

**Performance — N+1 confirmé mais non corrigé dans ce chantier (couche 14f)** : chacune des 8 méthodes `import*()` de `ModuleEventAggregatorService` boucle en PHP sur sa collection de lignes sources et appelle `CalendarEvent::updateOrCreate()` individuellement (2 requêtes par ligne : vérification d'existence + écriture) plutôt qu'un upsert en lot. Mesuré empiriquement : synchroniser 30 congés RH pour un seul utilisateur exécute 64 requêtes SQL. Une vraie correction nécessiterait un index unique `(calendar_id, module_type, module_id)` sur `calendar_events` (confirmé absent aujourd'hui) plus une conversion vers `Model::upsert()` — un changement de schéma, donc non « trivial » au sens de la méthodologie d'audit, signalé ici pour un futur chantier plutôt que corrigé à la volée. `indexEvents()` lui-même (l'endpoint de lecture) n'a en revanche aucun problème de N+1 — mesuré à 8 requêtes pour 60 événements avec 120 participants et 60 rappels, grâce à l'eager-loading déjà en place dans `CalendarService::getEvents()`.

**Confusion racine/module déjà corrigée avant ce chantier** : Calendar était le seul module du dépôt à avoir eu un sous-dossier `Pages/Calendar/` imbriqué à l'intérieur de son propre `Pages/` — déjà corrigé au Chantier 8.6 (voir l'historique complet dans `CLAUDE.md`), non retouché ici au-delà de la vérification que la structure actuelle (plate, correcte) tient toujours.
