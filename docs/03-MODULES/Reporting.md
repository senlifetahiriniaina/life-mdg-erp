# Reporting

## Rôle

Le module Reporting centralise la génération de rapports dans l'ERP : rapports financiers OHADA/SYSCOHADA, requêtes en langage naturel converties en SQL par Claude, exécution planifiée (scheduling) avec livraison par email, et un constructeur de tableaux de bord à widgets. Il consomme les données d'autres modules (Accounting, Sales…) directement via des requêtes SQL sur leurs tables plutôt que via leurs modèles Eloquent.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ReportDefinition` | `report_definitions` | Définition d'un rapport réutilisable (template de requête, schéma de paramètres, format de sortie, portée tenant/global) |
| `ReportExecution` | `report_executions` | Historique d'exécution d'un rapport (paramètres utilisés, résultat, fichier exporté) |
| `ReportSchedule` | `report_schedules` | Planification récurrente d'un rapport avec livraison automatique |
| `ReportShare` | `report_shares` | Partage d'un rapport avec des utilisateurs ou rôles spécifiques |
| `ReportWidget` | `report_widgets` | Définition d'un widget affiché sur un tableau de bord |
| `Dashboard` | `dashboards` | Configuration d'un tableau de bord (ensemble de widgets) |
| `SavedQuery` | `saved_queries` | Requête réutilisable enregistrée (notamment issues du NL→SQL) |

`ReportDefinition` expose les scopes `visibleTo()` (tenant-spécifique OU global), `active()`, `forModule()` et `forTenant()`, et la relation `shares()` vers `ReportShare`.

## Endpoints principaux

Toutes les routes passent par un unique `ReportingController`, sous `auth:sanctum` + middleware `module:Reporting`, préfixe `/api/v1/reporting/`.

| Méthode | Route | Description |
|---|---|---|
| GET | `reporting/reports` | Liste des définitions de rapport |
| POST | `reporting/reports` | Créer une définition de rapport |
| GET/PUT/DELETE | `reporting/reports/{id}` | Afficher / modifier / supprimer |
| POST | `reporting/reports/{slug}/execute` | Exécution par slug (route legacy) |
| POST | `reporting/reports/{id}/run` | Lancer l'exécution (renvoie un id d'exécution) |
| GET | `reporting/reports/{id}/executions` | Historique des exécutions |
| POST | `reporting/reports/{id}/share` | Partager un rapport |
| GET | `reporting/executions/{id}` / `.../download` | Détail / téléchargement d'une exécution |
| GET/POST | `reporting/schedules` | Planifications de rapport |
| GET | `reporting/ohada/balance-sheet` | Bilan SYSCOHADA |
| GET | `reporting/ohada/income-statement` | Compte de résultat |
| GET | `reporting/ohada/trial-balance` | Balance générale |
| GET | `reporting/ohada/journal` | Journal comptable |
| GET | `reporting/ohada/aged-receivables` / `aged-payables` | Balance âgée clients/fournisseurs |
| GET | `reporting/ohada/tva` | Déclaration TVA |
| GET | `reporting/ohada/is` | Impôt sur les sociétés |
| POST | `reporting/nl-query` | Requête en langage naturel → SQL |
| GET/POST | `reporting/saved-queries` | Requêtes enregistrées |
| GET/POST | `reporting/dashboards` | Liste / création de tableaux de bord |
| GET/PUT | `reporting/dashboards/{id}` | Détail / mise à jour |
| GET | `reporting/dashboards/{id}/summary` | Résumé narratif IA du dashboard |
| GET | `reporting/widgets/{id}/data` | Données d'un widget |
| POST | `reporting/ai/assist` | Guidance IA contextuelle (`ReportingAiAssistController`, `auth:sanctum` seul, sans middleware `module:Reporting`) |

## Contrôleurs

3 contrôleurs : `Api/ReportingController` (l'essentiel du module — tous les endpoints listés ci-dessus), `Api/ReportingAiAssistController` (guidance IA), `Web/ReportingWebController` (nouveau, Chantier 8.5ars).

## Vues (Vue/Inertia)

`Modules/Reporting/resources/js/Pages/` : `ReportsIndex.vue` (réelle, tuiles de rapport rapide, historique, raccourci bilan OHADA), `Create.vue`, `Show.vue` — ces deux dernières construites au Chantier 8.5ars, aucune n'existait avant.

**Le module n'avait aucune couche web avant ce chantier** : `Http/Controllers/` ne contenait qu'un dossier `Api/`, et `routes/web.php` était un placeholder littéralement vide — `ReportsIndex.vue`, pourtant réelle et complète, était totalement inaccessible. `ReportingWebController` (`index`/`create`/`show`) + les routes (`/reporting`, `/reporting/create`, `/reporting/reports/{id}`) ont été construits pour de vrai, sous `['auth', 'module:Reporting']`. Les tuiles de rapport rapide de `ReportsIndex.vue` appelaient un `POST /api/v1/reporting/generate` fictif — repointées vers le vrai `POST reporting/reports/{slug}/execute` déjà routé, chaque tuile mappée sur son vrai slug de template seedé plutôt que d'inventer un nouveau endpoint.

**Gap documenté, non résolu** : la section « historique » de `ReportsIndex.vue` lit `GET reports` (des enregistrements `ReportDefinition`), mais la forme de données qu'elle affiche (`status`/`generated_at`/`file_url`) n'existe que sur `ReportExecution` — réconcilier les deux est une décision produit (« historique » doit-il désigner les définitions ou les exécutions ?), hors périmètre du chantier de re-câblage.

## Services

- **`OhadaReportService`** — génère les états financiers SYSCOHADA (bilan classes 1-5, compte de résultat, balance, journal, TVA, IS, balances âgées). Contient les taux de TVA (17 pays OHADA, 10 % à 20,25 %) et d'IS (6 pays) codés en dur par code pays ISO.
- **`NlToSqlService`** — convertit une requête en langage naturel (fr/en/es/pt) en SQL paramétré via `claude-opus-4-7`. Retombe sur une réponse de secours statique si `ANTHROPIC_API_KEY` est absente ou si l'appel échoue. `ReportingController::nlQuery()` appelait `NlToSqlService::translate()`/`saveQuery()`, deux méthodes qui n'existaient pas (`BadMethodCallException` garantie sur chaque appel) — corrigé au Chantier 8.5ars en ajoutant les deux comme des enveloppes fines autour des vraies méthodes déjà testées, `queryToSql()`/`SavedQuery::create()`.
- **`ReportGenerationService`** — génération des exports PDF/Excel/CSV et création des `ReportExecution`. Son fallback `?? 1` (même famille de bug que la fuite tenant ci-dessous) a été laissé tel quel — confirmé par grep : ni ce service ni ses deux jobs (`RunReportJob`, `DeliverScheduledReportJob`) ne sont jamais dispatchés nulle part dans l'app, donc aucun contexte de requête réel n'existe pour en dériver un vrai tenant.
- **`DashboardService`** — résolution des données de widgets et agrégats (interroge directement `sales_orders`, `sales_order_lines` via `DB::table`).
- **`ReportingService`** — orchestration centrale : liste des rapports visibles par tenant, exécution synchrone d'un `ReportDefinition`.
- Jobs asynchrones : `RunReportJob` (exécution en file d'attente) et `DeliverScheduledReportJob` (livraison programmée par email).

## Permissions RBAC

Préfixe `reporting.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `report`, `template`, `schedule`, avec les actions standard `view-any|view|create|update|delete`. `ReportPolicy` vérifie précisément `reporting.report.view-any`, `reporting.report.view`, `reporting.report.create`, `reporting.report.update`, `reporting.report.delete`. Aucun rôle métier ne reçoit ce préfixe explicitement par un filtre dédié dans le seeder (contrairement à `hr-manager` ou `accountant`) ; seuls `admin` (toutes permissions) et `sales-manager` (qui inclut des permissions `bi.report.*`, distinctes de `reporting.*`) sont mentionnés nommément — l'accès à Reporting pour les autres rôles métiers passe donc par `admin`/`manager`. Route-level : `module:Reporting` sur tout le groupe (`Modules/Reporting/routes/api.php`, confirmé dans le code).

## Dépendances avec d'autres modules

- **AI** : `ReportingAiAssistController` utilise `Modules\AI\Services\AiContextualAssistantService` pour la guidance contextuelle ; `NlToSqlService` appelle directement l'API Anthropic.
- **Accounting / Sales** : consommées en lecture seule, sans imports de modèles — `OhadaReportService` et `DashboardService` interrogent directement les tables (`acc_invoices`, `sales_orders`, `sales_order_lines`, etc.) via `DB::table()` plutôt que via les Eloquent models de ces modules.
- Aucun autre module du périmètre n'importe de classe `Modules\Reporting\*` — Reporting est un consommateur de données, pas un fournisseur consommé ailleurs.

## Particularités du périmètre life-mdg-erp

**Fuite cross-tenant corrigée (Chantier 8.5ars, la découverte principale de ce chantier)** : `ReportingController` (30 sites) et `ReportingService::execute()`/`schedule()`/sa méthode privée `runQuery()` (celle qui lie réellement `{{tenant_id}}` dans le SQL ad-hoc exécuté) résolvaient tous le tenant via `$user->tenant_id ?? 1` — `users.tenant_id` est une colonne fantôme jamais peuplée par le vrai flux d'inscription, donc toutes les sociétés retombaient dans le même compartiment « tenant 1 ». Corrigé pour `$user->company_id ?? 0`, la vraie colonne de frontière multi-tenant (confirmé dans le code : `ReportingController` contient de multiples occurrences de `tenant_id` référençant désormais un `$tenantId` dérivé de `company_id`).

**Une seconde instance, plus sévère, du même bug a été trouvée dans `DashboardService`** : chaque template de widget de tableau de bord ne renseignait jamais `params.tenant_id` dans `data_source` (seulement `{module, query}`), donc le repli `$params['tenant_id'] ?? 1` de chaque résolveur était systématiquement atteint sur *tout* widget réel — chaque tableau de bord de chaque tenant affichait silencieusement les données du tenant 1, pas seulement les requêtes sans tenant. Corrigé en faisant passer la vraie colonne `tenant_id` de chaque widget à travers la chaîne de résolution plutôt qu'un lookup dans `params`.
