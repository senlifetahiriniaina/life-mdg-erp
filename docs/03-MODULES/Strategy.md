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
- **`strategy:snapshot-ratios`** (commande Artisan, nouvelle Chantier 32.27) — instantané réel de chaque ratio par société, planifiée quotidiennement (02h00) ; producteur de l'historique désormais réellement affiché par `Ratios/Index.vue`'s sparkline.

## Permissions RBAC

Préfixe `strategy.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `ratio`, `objective`, `plan`, **`kpi`** (ajoutée cette session), actions standard `view-any|view|create|update|delete`. Aucun rôle métier ne reçoit ce préfixe par filtre dédié dans le seeder (seul `admin` l'obtient via la synchronisation globale). `RatioPolicy` autorise toujours la lecture (`viewAny`/`view`) à tout utilisateur authentifié (données de référence globales, pas de business data par tenant — voir Particularités). `StrategyKpiPolicy`/`StrategyPlanPolicy` n'exigent une permission/un rôle que pour créer/modifier/supprimer, **et depuis le Chantier 32.27 vérifient aussi que l'enregistrement appartient réellement à la société de l'appelant** (`view()`/`update()`/`delete()` — voir ci-dessous).

**Vulnérabilité cross-tenant corrigée en deux temps (finding principal, re-confirmé Chantier 10)** : les 9 contrôleurs API + `Web\StrategyPageController` scopaient à l'origine les données tenant via un en-tête `X-Tenant-Id`/paramètre de requête `tenant_id` **contrôlé par le client**, sans repli sûr. Un premier correctif (Chantier 8.6, documenté ici) a bien supprimé ce repli client-contrôlé, mais l'a remplacé par `$request->user()?->tenant_id ?? 'default'` — sa propre docblock affirmait à tort que `users.tenant_id` était « la colonne correcte, non contrôlée par le client », alors que c'est la colonne fantôme documentée ailleurs dans `CLAUDE.md` (jamais dans `User::$fillable`, jamais peuplée par le vrai flux d'inscription). Résultat : chaque tenant retombait silencieusement dans un même compartiment partagé `'default'` — la même fuite cross-tenant que celle censée être corrigée, juste sans la syntaxe d'en-tête permettant de choisir une victime arbitraire. Le re-audit Chantier 10 a confirmé et corrigé ce résidu sur les 10 sites (les 9 helpers privés `tenantId()` + le site inline d'`OkrController::tree()`) : `(string) ($request->user()?->company_id ?? 0)` (cast en chaîne — les colonnes `tenant_id` de Strategy sont `string`, contrairement à celles de Reporting) est désormais la valeur réellement en place, avec suppression complète du repli client-contrôlé.

**Rupture active corrigée cette session** : `StrategyServiceProvider` n'appelait jamais `registerPolicies()`/`Gate::policy()` — `RatioPolicy`/`StrategyKpiPolicy`/`StrategyObjectivePolicy` (les trois déjà correctement écrites) étaient donc invisibles pour le Gate de Laravel. Ce n'était pas qu'un trou RBAC : les 7 endpoints de `StrategyObjectiveLinkController` appelaient tous `authorize(..., StrategyObjective::class)` contre cette policy non enregistrée, ce qui renvoyait un 403 inconditionnel à tout utilisateur non-`super-admin` sur l'intégralité de la fonctionnalité de liaison d'objectifs/alignement Cascade. Corrigé par l'enregistrement + ajout des appels `authorize()` manquants sur `KpiController`/`OkrController` (qui ne les appelaient pas malgré des policies existantes) + le nouveau bloc `strategy.kpi.*` du seeder mentionné ci-dessus.

**Chantier 32.27 (audit 14 couches) — trouvaille principale : `KPIRegistryService` (le cœur du calcul des 32 ratios Strategy First) n'a jamais réellement filtré par tenant, malgré les correctifs répétés (Chantiers 8.5ars/8.6/10) qui n'ont fixé que la valeur passée aux contrôleurs, jamais son usage réel.** `StrategyRatioService::enrichRatio()` recevait bien un `$tenantId` réel de la part des 9 contrôleurs (tous corrects depuis Chantier 10), mais ne le transmettait jamais à `$this->registry->getValue($module, $key)` — chaque ratio affiché sur le cockpit de chaque société (CA, CAC, turnover RH, taux de rupture stock, CSAT, …) était en réalité calculé sur l'agrégat de **toutes** les sociétés confondues, confirmé empiriquement (2 sociétés réelles, données CRM distinctes, un seul chiffre agrégé partagé par les deux). Corrigé en filetant `$tenantId` à travers `all()`/`forModule()`/`getValue()` et chacun des 32 générateurs de KPI, chacun filtrant sur la vraie colonne de cloisonnement de la table qu'il interroge réellement (`crm_leads`/`crm_opportunities`/`sales_orders`/`mfg_production_orders`/`hd_chat_sessions`/`hr_job_postings`/`inventory_products` → `tenant_id` réel, peuplé depuis `company_id` par chaque module d'origine ; `hr_employees`/`hd_tickets` → `company_id` réel) — voir le docblock de `KPIRegistryService` lui-même pour le détail complet, y compris les deux gaps cross-module confirmés mais non corrigeables depuis Strategy seul (`acc_invoices`/`acc_journal_entries` n'ont toujours aucune colonne de tenant nulle part dans ce dépôt).

**Un second cockpit central avait la même fuite indépendamment** : `AlignmentCascadeService::getCascadeMap($tenantId)` recevait aussi un `$tenantId` réel mais ne l'appliquait jamais à sa requête `StrategyObjective::with(...)->get()` — la carte de cascade (`Cascade/Index.vue`, `/strategy/cascade`) affichait donc les objectifs stratégiques réels de **toutes** les sociétés mélangés, confirmé empiriquement. Corrigé via `whereHas('plan', fn ($q) => $q->where('tenant_id', $tenantId))`.

**Sept endpoints/contrôleurs supplémentaires avaient un vrai IDOR cross-tenant sur leurs actions par id**, malgré `index()`/`store()` déjà correctement scopés depuis Chantier 10 : `StrategyPlanController::show()` (aucun `authorize()` du tout), `tree()`/`duplicate()`/`health()` (aucune vérification de propriété — `duplicate()` en particulier copiait le contenu complet du plan d'une autre société dans la réponse JSON) ; `KpiController::values()`/`refresh()` (aucun `authorize()`, aucun cloisonnement) ; `RitualController::update()`/`sessions()`/`createSession()`/`startSession()`/`completeSession()` ; `ScenarioController::show()`/`update()`/`addAssumption()`/`updateAssumption()`/`impact()`/`compare()` (ce dernier laissait comparer — et donc lire le détail complet de — n'importe quels scénarios d'autres sociétés) ; `SignalController::markRead()`/`dismiss()` ; `StrategyAdvisorController::insights()`/`boardReport()`/`ritualSummary()` (validation Laravel `exists:` ne vérifie jamais la propriété — `boardReport()` générait un vrai rapport IA de conseil d'administration à partir du plan réel d'une société tierce). Tous corrigés avec des vérifications de propriété par enregistrement (`StrategyPlanPolicy`/`StrategyKpiPolicy` — comparaison réelle de `tenant_id`/`company_id`, plus des helpers `*InTenant()` dédiés pour les modèles sans policy propre, suivant le même patron déjà établi par `OkrController::objectiveInTenant()`/`StrategyObjectiveLinkController`).

**Cinq méthodes `apiResource` inexistantes — erreur fatale garantie, pas hypothétique** : `Route::apiResource()` enregistre toujours les 5 verbes standard, mais `KpiController::show()`, `RitualController::show()`/`destroy()`, `ScenarioController::destroy()` et `OkrController::show()` n'existaient sur aucun de ces contrôleurs (confirmé par réflexion PHP, pas seulement par lecture) — un « call to undefined method » garanti sur chacune de ces 5 routes réelles. Construites pour de vrai plutôt que la route retirée, puisque chacune couvre un besoin légitime déjà implicite dans le reste du CRUD de son contrôleur.

**Fake/dead (couche 9)** : `KRO`/`strategy_kros` (Key Result Objectives) — modèle réel, table réellement migrée, mais **zéro appelant nulle part** dans tout le module (ni contrôleur, ni route, ni même un test isolé) et déjà cassé indépendamment de son inutilisation (sa relation `kpi()` référence `KPI::class`, une classe qui n'existe nulle part dans ce module — le vrai modèle est `StrategyKpi`). Le vrai concept qu'il visait à couvrir (résultat-clé lié à un objectif) est déjà pleinement construit par `StrategyKeyResult`/`strategy_key_results`, réellement utilisé par tout le flux OKR. Classé **« mort confirmé, à supprimer »** — supprimé (modèle, factory, table via une nouvelle migration). `StrategyRatioService::storeSnapshot()`/`getHistory()` (distincts de `KpiDataService::getHistory()`, qui lui est bien câblé) étaient un mécanisme réel et testé, mais **sans aucun producteur** — `Ratios/Index.vue`'s sparkline appelait en réalité `generateMockTrend()`, un générateur de bruit aléatoire (`mt_rand`) re-tiré à chaque chargement de page, jamais une vraie tendance historique malgré la documentation antérieure de ce fichier affirmant le contraire. Classé **« à activer »** — activé pour de vrai via une nouvelle commande planifiée `strategy:snapshot-ratios` (quotidienne, `StrategyServiceProvider::registerCommandSchedules()` était jusqu'ici un stub vide) qui prend un instantané réel de chaque ratio par société, et `enrichRatio()` préfère désormais cet historique réel dès que ≥2 points existent (repli sur le générateur mock sinon — fallback-first). `Ratio`/`strategy_ratios` (catalogue de définitions de ratio, distinct du tableau statique `ratioDefinitions()` réellement utilisé par `RatioController`) reste un modèle réel, enregistré sur le Gate, avec une factory et une couverture de test, mais **sans aucun contrôleur/route qui le crée ou le lit** — classé **« gap d'adoption »**, pas supprimé (rien de cassé, aucun doublon actif, contrairement à `KRO`), même précédent que `CustomField`/`HasCustomFields` au Chantier 32.1.

## Dépendances avec d'autres modules

- **AI** : `StrategyAiAssistController` utilise `Modules\AI\Services\AiContextualAssistantService`.
- **Accounting, CRM, HR, Inventory, Sales, Helpdesk** : consommés en lecture seule via requêtes SQL brutes dans `KPIRegistryService` (pas d'import de modèles `Modules\X\Models\*`) — Strategy ne dépend d'aucune classe PHP d'un autre module métier, seulement de leurs tables.
- **Calendar** : `Modules\Calendar\Services\ModuleEventAggregatorService` lit `strategy_objectives` (via `strategy_plans.tenant_id`) pour agréger les jalons stratégiques dans le calendrier de l'utilisateur — corrigé au Chantier 19 Lot 3 (interrogeait à l'origine `strategy_kros`, un modèle purement numérique sans titre ni date, jamais le bon). Ce fichier documentait encore par erreur `strategy_kros` comme la table réellement lue avant le Chantier 32.27, qui a confirmé (par grep exhaustif sur `Modules/Calendar`) que ce n'était plus le cas depuis longtemps et a supprimé `KRO`/`strategy_kros` en tant que doublon mort — voir Particularités.
- Aucun module du périmètre n'importe de classe `Modules\Strategy\*` en PHP ; l'intégration dans les tableaux de bord des autres modules se fait côté frontend Vue (`StrategyWidget`), hors du scope de ce document backend.

## Particularités du périmètre life-mdg-erp

- **Le gap de migration documenté ici avant cette session est comblé** : `Modules/Strategy/database/migrations/` n'existait pas du tout auparavant — aucune des tables `strategy_*` (même celles avec un `$table` explicite comme `strategy_ratios`) n'avait de migration Laravel, alors que `StrategyServiceProvider::boot()` appelait bien `loadMigrationsFrom()` sur ce chemin inexistant. Cela signifiait que `php artisan migrate:fresh --seed` ne créait aucune de ces tables, et donc que l'ensemble de la persistance du module (plans, OKR, scénarios, rituels, signaux, alertes, ratios) retombait systématiquement sur des replis statiques en mémoire. Une migration réelle (`2026_08_16_000005_create_strategy_tables.php` + un correctif de colonnes) a été ajoutée cette session — toutes les tables listées dans « Modèles clés » existent désormais réellement en base.
- Ce constat va au-delà de la note déjà présente dans `CLAUDE.md` (« Strategy module's `training_roi`/`time_to_fill` HR ratios return static fallback values ») : ces deux ratios RH restent volontairement en repli statique (145.0 / 28 jours), faute de source de données Training/ATS dans ce périmètre — c'est un choix délibéré et documenté, distinct du gap de migration désormais corrigé.
