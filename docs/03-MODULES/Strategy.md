# Strategy

## Rôle

Le module Strategy porte le 7ᵉ principe fondateur « Strategy First » : cockpit de pilotage stratégique avec ratios cross-module, objectifs OKR, scénarios de simulation, rituels de gouvernance (comités, revues), signaux d'alerte automatiques et recommandations générées par IA. Il s'appuie sur `KPIRegistryService`, un registre central qui expose les indicateurs des autres modules sans dépendre de leurs modèles Eloquent.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `StrategyPlan` | `strategy_plans` | Plan stratégique (contient des objectifs) |
| `StrategyObjective` / `StrategyKeyResult` | `strategy_objectives` / `strategy_key_results` | Arbre OKR (Objectives → Key Results) |
| `StrategyKpi` / `StrategyKpiValue` | `strategy_kpis` / `strategy_kpi_values` | Définition de KPI et historique de valeurs |
| `Ratio` / `RatioSnapshot` | `strategy_ratios` / `strategy_ratio_snapshots` | Définition de ratio (numérateur/dénominateur) et instantanés historiques |
| `IndustryBenchmark` | `strategy_industry_benchmarks` | Benchmarks P25/Médiane/P75 par pays/secteur |
| `Correlation` | `strategy_correlations` | Corrélations de Pearson entre séries de KPI, base de connaissance pré-seedée |
| `StrategicAlert` | `strategy_alerts` | Alertes déclenchées par déviation de ratio |
| `KRO` | `strategy_kros` | Key Result Objectives (liaison objectifs ↔ KPI) — aussi lue par `Modules\Calendar` pour agréger les jalons stratégiques |
| `StrategyScenario` / `StrategyScenarioAssumption` | `strategy_scenarios` / `strategy_scenario_assumptions` | Scénarios de simulation « what-if » et leurs hypothèses |
| `StrategyRitual` / `StrategyRitualSession` | `strategy_rituals` / `strategy_ritual_sessions` | Rituels de gouvernance (comité stratégique, etc.) et leurs séances |
| `StrategySignal` | `strategy_signals` | Signal d'alerte généré par `SignalEngineService` |
| `StrategyObjectiveLink` / `StrategyPillar` | `strategy_objective_links` / `strategy_pillars` | Liaison polymorphe objectif ↔ ressource d'un autre module, piliers stratégiques |

Toutes ces tables sont désormais réellement migrées (`Modules/Strategy/database/migrations/2026_08_16_000005_create_strategy_tables.php` + un correctif de colonnes) — voir Particularités : ce n'était pas le cas avant cette session.

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum, session.security, tenancy.user, module:Strategy, role:employee,finance-manager,manager,admin` (corrigé cette session — voir RBAC), préfixe `/api/v1/strategy/` :

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
| GET/POST/PUT/DELETE | `objective-links/*`, `resource/{type}/{id}` | Liaison polymorphe objectif ↔ ressource d'un autre module — désormais réellement fonctionnelle (voir RBAC : ces 7 endpoints renvoyaient un 403 inconditionnel avant cette session) |
| GET | `cascade` | Carte de cascade d'alignement (`CascadeController`) |
| GET | `ratios`, `ratios/{module}` | Ratios cross-module — Strategy First |
| GET | `benchmarks`, `correlations`, `alerts` | Benchmarks, corrélations, alertes actives |
| POST | `ai/recommend` | Recommandations stratégiques IA |
| GET/POST | `advisor/insights`, `advisor/recommendations`, `advisor/board-report`, `advisor/ritual-summary`, `advisor/formulate-okr` | Conseiller IA (`StrategyAdvisorController` / `AiStrategyAdvisorService`) |
| POST | `ai/assist` | Guidance IA contextuelle (`StrategyAiAssistController`) |

Routes web (`Modules/Strategy/routes/web.php`, préfixe `/strategy`, désormais `auth, module:Strategy, role:employee,finance-manager,manager,admin` — était `auth` seul avant cette session) : `/`, `/plans`, `/plans/{id}`, `/ratios`, `/benchmarks`, `/correlations`, `/objectives`, `/cascade`.

## Contrôleurs

API (`Modules/Strategy/app/Http/Controllers/Api/`) : `StrategyPlanController`, `KpiController`, `OkrController`, `RitualController`, `ScenarioController`, `SignalController`, `StrategyAdvisorController`, `RatioController`, `CascadeController`, `StrategyObjectiveLinkController`, `StrategyAiAssistController`. Les 9 premiers (hors `StrategyObjectiveLinkController`/`StrategyAiAssistController`) exposent chacun un helper privé `tenantId()`, corrigé cette session pour résoudre `(string) ($request->user()?->company_id ?? 0)` (voir RBAC).

Web (`Modules/Strategy/app/Http/Controllers/Web/`) : **`StrategyPageController`** (8 méthodes — `index`, `plans`/`planShow`, `ratios`, `benchmarks`, `correlations`, `objectives`, `cascade`), toutes servent désormais de vraies pages Inertia (voir Vues — c'était un 500 systématique avant cette session, aucun composant Vue n'existait pour aucune des 7 premières).

## Vues (Vue/Inertia)

Toutes sous `Modules/Strategy/resources/js/Pages/` — **entièrement construites cette session**, le module n'avait auparavant strictement aucune page web (chaque route `StrategyPageController` renvoyait un 500 « component not found ») :

- **`Index.vue`** — cockpit : tuiles de ratios, santé des plans, alertes, signaux, résumé OKR, corrélations, recommandations IA avec badge de mode hors-ligne.
- **`Plans/Index.vue`** — cartes de score de santé par plan.
- **`Plans/Show.vue`** — arbre récursif objectif → résultat clé.
- **`Ratios/Index.vue`** — barres de benchmark P25/Médiane/P75 + sparklines SVG à partir de vraies données de tendance.
- **`Benchmarks/Index.vue`** — tableau filtrable P25/Médiane/P75.
- **`Correlations/Index.vue`** — cartes + matrice complète.
- **`Objectives/Index.vue`** — arbre OKR avec panneau latéral câblé sur les endpoints link/unlink de `StrategyObjectiveLinkController` (nouvellement fonctionnels) — la seule des 7 pages avec une UI de mutation, les 6 autres étant en lecture/filtrage seul, cohérent avec le design de leurs contrôleurs respectifs.
- **`Cascade/Index.vue`** — déjà réelle et complète avant cette session (appelle `GET /api/v1/strategy/cascade`), mais sans route web ; ajoutée cette session (`GET /strategy/cascade`).

## Services

- **`KPIRegistryService`** — registre central des KPI par module (`Accounting`, `CRM`, `HR`, `Inventory`, `Sales`, `Manufacturing`, `Helpdesk`), chaque KPI portant un `value_callback` qui interroge directement la base (`DB::table('acc_invoices')`, etc.), avec repli sur une valeur par défaut si la requête échoue (`try/catch` systématique).
- **`StrategyRatioService`** — calcule les valeurs courantes des ratios et leur statut RAG (Red/Amber/Green) vs cible/benchmark.
- **`BenchmarkService`** — récupère les benchmarks pays/secteur et calcule la position en percentile.
- **`CorrelationAnalysisService`** — calcule les corrélations de Pearson entre séries de KPI.
- **`StrategyAIService`** / **`AiStrategyAdvisorService`** — appellent l'API Claude avec les instantanés de ratios + benchmarks pour produire des recommandations stratégiques (fallback-first).
- **`OkrService`**, **`StrategyPlanService`**, **`ScenarioService`**, **`RitualService`**, **`SignalEngineService`**, **`AlignmentCascadeService`**, **`StrategyObjectiveLinkService`**, **`KpiDataService`** — CRUD et logique métier dédiés à chaque sous-domaine.

## Permissions RBAC

Préfixe `strategy.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `ratio`, `objective`, `plan`, **`kpi`** (ajoutée cette session), actions standard `view-any|view|create|update|delete`. Aucun rôle métier ne reçoit ce préfixe par filtre dédié dans le seeder (seul `admin` l'obtient via la synchronisation globale). `RatioPolicy` et `StrategyKpiPolicy` autorisent la lecture (`viewAny`/`view`) à tout utilisateur authentifié, et n'exigent une permission ou un rôle que pour créer/modifier/supprimer.

**Vulnérabilité cross-tenant corrigée cette session (finding principal)** : les 9 contrôleurs API + `Web\StrategyPageController` scopaient les données tenant via un en-tête `X-Tenant-Id`/paramètre de requête `tenant_id` **contrôlé par le client**, sans repli sûr — n'importe quel utilisateur authentifié pouvait lire/écrire les données stratégiques de n'importe quel autre tenant en changeant cet en-tête. Corrigé par un helper `tenantId()` privé résolvant `(string) ($request->user()?->company_id ?? 0)` (cast en chaîne — les colonnes `tenant_id` de Strategy sont `string`, contrairement à celles de Reporting), avec suppression complète du repli client-contrôlé plutôt que de le garder en second recours.

**Rupture active corrigée cette session** : `StrategyServiceProvider` n'appelait jamais `registerPolicies()`/`Gate::policy()` — `RatioPolicy`/`StrategyKpiPolicy`/`StrategyObjectivePolicy` (les trois déjà correctement écrites) étaient donc invisibles pour le Gate de Laravel. Ce n'était pas qu'un trou RBAC : les 7 endpoints de `StrategyObjectiveLinkController` appelaient tous `authorize(..., StrategyObjective::class)` contre cette policy non enregistrée, ce qui renvoyait un 403 inconditionnel à tout utilisateur non-`super-admin` sur l'intégralité de la fonctionnalité de liaison d'objectifs/alignement Cascade. Corrigé par l'enregistrement + ajout des appels `authorize()` manquants sur `KpiController`/`OkrController` (qui ne les appelaient pas malgré des policies existantes) + le nouveau bloc `strategy.kpi.*` du seeder mentionné ci-dessus.

## Dépendances avec d'autres modules

- **AI** : `StrategyAiAssistController` utilise `Modules\AI\Services\AiContextualAssistantService`.
- **Accounting, CRM, HR, Inventory, Sales, Helpdesk** : consommés en lecture seule via requêtes SQL brutes dans `KPIRegistryService` (pas d'import de modèles `Modules\X\Models\*`) — Strategy ne dépend d'aucune classe PHP d'un autre module métier, seulement de leurs tables.
- **Calendar** : `Modules\Calendar\Services\ModuleEventAggregatorService` lit `strategy_kros` (table désormais réellement migrée, voir Particularités) pour agréger les jalons stratégiques dans le calendrier de l'utilisateur.
- Aucun module du périmètre n'importe de classe `Modules\Strategy\*` en PHP ; l'intégration dans les tableaux de bord des autres modules se fait côté frontend Vue (`StrategyWidget`), hors du scope de ce document backend.

## Particularités du périmètre life-mdg-erp

- **Le gap de migration documenté ici avant cette session est comblé** : `Modules/Strategy/database/migrations/` n'existait pas du tout auparavant — aucune des tables `strategy_*` (même celles avec un `$table` explicite comme `strategy_ratios`) n'avait de migration Laravel, alors que `StrategyServiceProvider::boot()` appelait bien `loadMigrationsFrom()` sur ce chemin inexistant. Cela signifiait que `php artisan migrate:fresh --seed` ne créait aucune de ces tables, et donc que l'ensemble de la persistance du module (plans, OKR, scénarios, rituels, signaux, alertes, ratios) retombait systématiquement sur des replis statiques en mémoire. Une migration réelle (`2026_08_16_000005_create_strategy_tables.php` + un correctif de colonnes) a été ajoutée cette session — toutes les tables listées dans « Modèles clés » existent désormais réellement en base.
- Ce constat va au-delà de la note déjà présente dans `CLAUDE.md` (« Strategy module's `training_roi`/`time_to_fill` HR ratios return static fallback values ») : ces deux ratios RH restent volontairement en repli statique (145.0 / 28 jours), faute de source de données Training/ATS dans ce périmètre — c'est un choix délibéré et documenté, distinct du gap de migration désormais corrigé.
