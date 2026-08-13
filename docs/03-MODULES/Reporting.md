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

## Services

- **`OhadaReportService`** — génère les états financiers SYSCOHADA (bilan classes 1-5, compte de résultat, balance, journal, TVA, IS, balances âgées). Contient les taux de TVA (17 pays OHADA, 10 % à 20,25 %) et d'IS (6 pays) codés en dur par code pays ISO.
- **`NlToSqlService`** — convertit une requête en langage naturel (fr/en/es/pt) en SQL paramétré via `claude-opus-4-7`. Retombe sur une réponse de secours statique si `ANTHROPIC_API_KEY` est absente ou si l'appel échoue.
- **`ReportGenerationService`** — génération des exports PDF/Excel/CSV et création des `ReportExecution`.
- **`DashboardService`** — résolution des données de widgets et agrégats (interroge directement `sales_orders`, `sales_order_lines` via `DB::table`).
- **`ReportingService`** — orchestration centrale : liste des rapports visibles par tenant, exécution synchrone d'un `ReportDefinition`.
- Jobs asynchrones : `RunReportJob` (exécution en file d'attente) et `DeliverScheduledReportJob` (livraison programmée par email).

## Permissions RBAC

Préfixe `reporting.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `report`, `template`, `schedule`, avec les actions standard `view-any|view|create|update|delete`. `ReportPolicy` vérifie précisément `reporting.report.view-any`, `reporting.report.view`, `reporting.report.create`, `reporting.report.update`, `reporting.report.delete` — ces cinq permissions existent bien dans le seeder (ressource `report`). Aucun rôle métier ne reçoit ce préfixe explicitement par un filtre dédié dans le seeder (contrairement à `hr-manager` ou `accountant`) ; seuls `admin` (toutes permissions) et `sales-manager` (qui inclut des permissions `bi.report.*`, distinctes de `reporting.*`) sont mentionnés nommément — l'accès à Reporting pour les autres rôles métiers passe donc par `admin`/`manager`.

## Dépendances avec d'autres modules

- **AI** : `ReportingAiAssistController` utilise `Modules\AI\Services\AiContextualAssistantService` pour la guidance contextuelle ; `NlToSqlService` appelle directement l'API Anthropic.
- **Accounting / Sales** : consommées en lecture seule, sans imports de modèles — `OhadaReportService` et `DashboardService` interrogent directement les tables (`acc_invoices`, `sales_orders`, `sales_order_lines`, etc.) via `DB::table()` plutôt que via les Eloquent models de ces modules.
- Aucun autre module du périmètre n'importe de classe `Modules\Reporting\*` — Reporting est un consommateur de données, pas un fournisseur consommé ailleurs.
