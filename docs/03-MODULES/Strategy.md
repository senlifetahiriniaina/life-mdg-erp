# Strategy

## Rôle

Le module Strategy porte le 7ᵉ principe fondateur « Strategy First » : cockpit de pilotage stratégique avec ratios cross-module, objectifs OKR, scénarios de simulation, rituels de gouvernance (comités, revues), signaux d'alerte automatiques et recommandations générées par IA. Il s'appuie sur `KPIRegistryService`, un registre central qui expose les indicateurs des autres modules sans dépendre de leurs modèles Eloquent.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `StrategyPlan` | `strategy_plans`* | Plan stratégique (contient des objectifs) |
| `StrategyObjective` / `StrategyKeyResult` | `strategy_objectives`* / `strategy_key_results`* | Arbre OKR (Objectives → Key Results) |
| `StrategyKpi` / `StrategyKpiValue` | `strategy_kpis`* / `strategy_kpi_values`* | Définition de KPI et historique de valeurs |
| `Ratio` / `RatioSnapshot` | `strategy_ratios` / `strategy_ratio_snapshots` | Définition de ratio (numérateur/dénominateur) et instantanés historiques |
| `IndustryBenchmark` | `strategy_industry_benchmarks` | Benchmarks P25/Médiane/P75 par pays/secteur |
| `Correlation` | `strategy_correlations` | Corrélations de Pearson entre séries de KPI, base de connaissance pré-seedée |
| `StrategicAlert` | `strategy_alerts` | Alertes déclenchées par déviation de ratio |
| `KRO` | `strategy_kros` | Key Result Objectives (liaison objectifs ↔ KPI) |
| `StrategyScenario` / `StrategyScenarioAssumption` | `strategy_scenarios`* / `strategy_scenario_assumptions`* | Scénarios de simulation « what-if » et leurs hypothèses |
| `StrategyRitual` / `StrategyRitualSession` | `strategy_rituals`* / `strategy_ritual_sessions`* | Rituels de gouvernance (comité stratégique, etc.) et leurs séances |
| `StrategySignal` | `strategy_signals`* | Signal d'alerte généré par `SignalEngineService` |
| `StrategyObjectiveLink` / `StrategyPillar` | `strategy_objective_links`* / `strategy_pillars`* | Liaison polymorphe objectif ↔ ressource d'un autre module, piliers stratégiques |

\* Table déduite de la convention Eloquent par défaut — ces modèles ne déclarent pas `protected $table` explicitement (voir « Particularités » ci-dessous).

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum`, préfixe `/api/v1/strategy/` :

| Méthode | Route | Description |
|---|---|---|
| CRUD | `plans` | Plans stratégiques |
| GET | `plans/{id}/tree`, `plans/{id}/health` | Arbre d'objectifs, santé du plan |
| POST | `plans/{id}/duplicate` | Dupliquer un plan |
| CRUD | `objectives` | Objectifs OKR |
| GET | `objectives/tree` | Arbre OKR complet |
| POST | `objectives/{id}/cascade` | Cascade des objectifs |
| POST/PUT | `key-results`, `key-results/{id}` | Résultats clés |
| POST | `key-results/{id}/progress` | Mise à jour de progression |
| CRUD | `kpis` | KPI |
| GET | `kpis/sources`, `kpis/{id}/values`, `kpis/{id}/refresh` | Sources disponibles, historique, rafraîchissement |
| CRUD | `scenarios` | Scénarios de simulation |
| POST | `scenarios/compare` | Comparaison multi-scénarios |
| GET | `scenarios/{id}/impact` | Impact simulé |
| CRUD | `rituals` | Rituels de gouvernance |
| GET | `rituals/upcoming`, `rituals/{id}/sessions` | Rituels à venir, séances |
| POST/PUT | `rituals/{id}/sessions`, `rituals/sessions/{id}/start\|complete` | Cycle de vie d'une séance |
| GET | `signals` | Signaux actifs |
| POST | `signals/refresh` | Recalcul des signaux |
| PUT | `signals/{id}/read\|dismiss` | Marquer lu / ignorer |
| GET/POST/PUT/DELETE | `objective-links/*`, `resource/{type}/{id}` | Liaison polymorphe objectif ↔ ressource d'un autre module |
| GET | `cascade` | Carte de cascade d'alignement (`CascadeController`) |
| GET | `ratios`, `ratios/{module}` | Ratios cross-module — Strategy First |
| GET | `benchmarks`, `correlations`, `alerts` | Benchmarks, corrélations, alertes actives |
| POST | `ai/recommend` | Recommandations stratégiques IA |
| GET/POST | `advisor/insights`, `advisor/recommendations`, `advisor/board-report`, `advisor/ritual-summary`, `advisor/formulate-okr` | Conseiller IA (`StrategyAdvisorController` / `AiStrategyAdvisorService`) |
| POST | `ai/assist` | Guidance IA contextuelle (`StrategyAiAssistController`) |

## Services

- **`KPIRegistryService`** — registre central des KPI par module (`Accounting`, `CRM`, `HR`, `Inventory`, `Sales`, `Manufacturing`, `Helpdesk`), chaque KPI portant un `value_callback` qui interroge directement la base (`DB::table('acc_invoices')`, etc.), avec repli sur une valeur par défaut si la requête échoue (`try/catch` systématique).
- **`StrategyRatioService`** — calcule les valeurs courantes des ratios et leur statut RAG (Red/Amber/Green) vs cible/benchmark.
- **`BenchmarkService`** — récupère les benchmarks pays/secteur et calcule la position en percentile.
- **`CorrelationAnalysisService`** — calcule les corrélations de Pearson entre séries de KPI.
- **`StrategyAIService`** / **`AiStrategyAdvisorService`** — appellent l'API Claude avec les instantanés de ratios + benchmarks pour produire des recommandations stratégiques (fallback-first).
- **`OkrService`**, **`StrategyPlanService`**, **`ScenarioService`**, **`RitualService`**, **`SignalEngineService`**, **`AlignmentCascadeService`**, **`StrategyObjectiveLinkService`**, **`KpiDataService`** — CRUD et logique métier dédiés à chaque sous-domaine (OKR, plans, scénarios, rituels, signaux, cascade d'alignement, liaisons polymorphes, données KPI).

## Permissions RBAC

Préfixe `strategy.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `ratio`, `objective`, `plan` uniquement, actions standard `view-any|view|create|update|delete`. Aucun rôle métier ne reçoit ce préfixe par filtre dédié dans le seeder (seul `admin` l'obtient via la synchronisation globale). `RatioPolicy` et `StrategyKpiPolicy` autorisent la lecture (`viewAny`/`view`) à tout utilisateur authentifié (`return true`), et n'exigent une permission ou un rôle que pour créer/modifier/supprimer — mais ces policies vérifient aussi `hasAnyRole(['strategy-analyst', ...])` et, pour `StrategyKpiPolicy`, `hasPermissionTo('strategy.kpi.create'|'update'|'delete')` : ni le rôle `strategy-analyst` ni les permissions `strategy.kpi.*` n'existent dans le seeder (dont la ressource `strategy` ne couvre que `ratio`/`objective`/`plan`, pas `kpi`) — en pratique, seuls `admin`/`super-admin` peuvent créer/modifier/supprimer des KPI stratégiques tant que ce rôle et ces permissions ne sont pas ajoutés au seeder.

## Dépendances avec d'autres modules

- **AI** : `StrategyAiAssistController` utilise `Modules\AI\Services\AiContextualAssistantService`.
- **Accounting, CRM, HR, Inventory, Sales, Helpdesk** : consommés en lecture seule via requêtes SQL brutes dans `KPIRegistryService` (pas d'import de modèles `Modules\X\Models\*`) — Strategy ne dépend d'aucune classe PHP d'un autre module métier, seulement de leurs tables.
- Aucun module du périmètre n'importe de classe `Modules\Strategy\*` en PHP ; l'intégration dans les tableaux de bord des autres modules se fait côté frontend Vue (`StrategyWidget`), hors du scope de ce document backend.

## Particularités du périmètre life-mdg-erp

`Modules/Strategy/database/` ne contient que `factories/` et `seeders/` — **il n'existe aucun répertoire `database/migrations/` pour ce module**, ni dans le module ni à la racine (`database/migrations/`) : aucune des tables `strategy_*` (y compris les six qui ont un `$table` explicite comme `strategy_ratios` ou `strategy_correlations`) n'a de migration Laravel dans ce dépôt trimmé. `StrategyServiceProvider::boot()` appelle bien `loadMigrationsFrom(module_path('Strategy', 'database/migrations'))`, mais ce chemin n'existe pas, donc `php artisan migrate:fresh --seed` ne créera aucune de ces tables. C'est cohérent avec le pattern documenté dans `CLAUDE.md` (« *Strategy module's `training_roi`/`time_to_fill` HR ratios return static fallback values* ») mais va plus loin : ce n'est pas seulement deux ratios RH qui retombent sur une valeur statique, c'est l'ensemble de la persistance du module (plans, OKR, scénarios, rituels, signaux, alertes, ratios eux-mêmes) qui n'a pas de schéma de base de données dans cette extraction — à traiter comme un gap à combler avant mise en production de ces écrans.
