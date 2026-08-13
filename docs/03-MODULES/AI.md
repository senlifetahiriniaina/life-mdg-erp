# AI

## Rôle

`Modules/AI` porte le principe « AI Assisted First » de life-mdg-erp : il fournit le service central `AiContextualAssistantService::getGuidance()` que tous les autres modules interrogent pour afficher une aide contextuelle générée par Claude sur chaque écran/action, avec un repli statique (`fallback`) garanti quand la clé API Anthropic est absente ou que l'appel échoue. Il expose en complément des briques IA plus spécialisées : détection d'anomalies, recherche en langage naturel, conseiller d'actions et suivi budgétaire des appels IA (coût, tokens, quotas). Selon le contexte du projet, l'AI module n'importe aucun autre module — c'est la dépendance racine du graphe applicatif.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `AiRequest` | `ai_requests` | Trace chaque appel à l'API Claude (module, action, tokens prompt/completion, `cost_usd`, `response_time_ms`, statut `pending/success/error/cached`) |
| `AiInsight` | `ai_insights` | Insights générés automatiquement |
| `AiRecommendation` | `ai_recommendations` | Recommandations produites par le moteur IA |
| `AiAnomaly` | `ai_anomalies` | Anomalies détectées (données financières, comportementales, etc.) |
| `AiUsageLimit` | `ai_usage_limits` | Quotas d'usage IA par tenant/utilisateur (`limit_type`, `limit_value`, `period`, `block_on_exceed`) — utilise le trait `HasAuditLog` du module AuditLog |

## Endpoints principaux

Tous préfixés `/api/v1/ai/`, protégés `auth:sanctum` (voir `Modules/AI/routes/api.php`) :

- **Assistant contextuel (AI Assisted First)** : `POST assist`, `GET assist/modules` — implémentés par `AiAssistantController`, qui délègue directement à `AiContextualAssistantService`
- **Anomalies** : `POST anomalies/detect`, `GET anomalies`, `DELETE anomalies/{id}`
- **Recherche en langage naturel** : `POST search`
- **Conseiller d'actions** : `POST advise`, `GET usage/me`
- **Administration budget IA** (`/api/v1/ai/admin/`) : `GET usage`, `GET limits`, `POST limits`, `DELETE limits/{id}`
- **Predictive Analytics / Recommendations / NLP / Insights** (`analytics/*`, `recommendations/*`, `nlp/*`, `insights/*`) : déclarées dans `routes/api.php` vers `Modules\AI\Http\Controllers\{AnalyticsController,RecommendationsController,NLPController,InsightsController}` — voir Particularités, ces classes n'existent pas dans ce périmètre.

## Services

- `AiContextualAssistantService` — service central du principe AI Assisted First. `getGuidance(module, action, context, locale, userRole)` retourne un JSON structuré (`what_to_do`, `how_to_do`, `decision_indicators`, `warnings`, `next_actions`, `tips`). Appelle Claude (`claude-sonnet-4-6` par défaut, cache serveur 5 min via `Cache::remember`) quand `ANTHROPIC_API_KEY` est présente, sinon retourne `fallbackGuidance()` avec `enabled: false`. `supportedModules()` liste tous les couples module/action couverts par le fallback statique — la carte couvre bien plus large que les 27 modules du périmètre life-mdg-erp (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, MarketingAutomation, etc. y figurent encore alors que ces modules ont été retirés).
- `AiActionAdvisorService` / `AiUsageBudgetService` — conseille des actions et calcule l'usage/coût IA par période (`daily/weekly/monthly`), avec tarification codée en dur (`claude-sonnet-4-6` : 3 $/M tokens input, 15 $/M output)
- `AiAnomalyDetectionService` — détection d'anomalies exposée via `AiAnomalyController`
- `AiNaturalLanguageSearchService` — recherche en langage naturel exposée via `AiSearchController`
- `AutomatedInsightsService`, `NaturalLanguageProcessingService`, `PredictiveAnalyticsService`, `RecommendationEngineService` — services support pour les endpoints `analytics/*`, `nlp/*`, `insights/*`, `recommendations/*` (voir Particularités : les contrôleurs correspondants sont absents)
- `AnthropicCacheService` — gestion du cache de prompts Anthropic (`cache_control: ephemeral`)

## Permissions RBAC

Le module AI n'a pas de bloc `ai.*.*` dans `RolesAndPermissionsSeeder::MODULES` — aucune permission Spatie granulaire n'est seedée pour lui. Le contrôle d'accès repose uniquement sur `auth:sanctum` au niveau des routes et, pour les modèles `AiRequest`/`AiInsight`, sur des policies (`AiRequestPolicy`, `AiInsightPolicy`) qui vérifient `hasAnyRole(['ai-analyst', 'admin', 'super-admin'])`. Le rôle `ai-analyst` **n'est jamais créé** par `RolesAndPermissionsSeeder` (absent de la liste des 22 rôles documentés en tête de fichier) : en pratique, seuls `admin` et `super-admin` peuvent jamais satisfaire ces policies.

## Dépendances avec d'autres modules

- **Utilisé par** : `AiContextualAssistantService` est importé par 33 fichiers hors du module AI, dans quasiment tous les modules métier (CRM, Accounting, HR, Inventory, BI, Helpdesk, Projects…) sous forme de services `<Module>AIService` dédiés qui l'enveloppent (ex. `Modules\CRM\Services\AI\CrmAIService`, `Modules\Accounting\Services\AI\AccountingAIService`, `Modules\HR\Services\AI\HrAIService`).
- **Utilise** : aucun autre module métier — le module AI n'importe que ses propres classes (`use Modules\AI\...`). C'est la dépendance racine confirmée par le code.

## Particularités du périmètre life-mdg-erp

- **Routes orphelines** : `routes/api.php` déclare ~25 routes sous `analytics/*`, `recommendations/*`, `nlp/*` et `insights/*` pointant vers `Modules\AI\Http\Controllers\{AnalyticsController, RecommendationsController, NLPController, InsightsController}` — aucun de ces quatre fichiers n'existe sous `Modules/AI/app/Http/Controllers/` (seul le sous-dossier `Api/` contient réellement `AiAssistantController`, `AiAnomalyController`, `AiSearchController`, `AiActionAdvisorController`). Tout appel HTTP à ces routes échouerait avec une erreur de classe introuvable ; seule la partie « Contextual AI Assistant », « Anomaly Detection », « Natural Language Search » et « Action Advisor » du module est réellement fonctionnelle.
- **`supportedModules()` n'a pas été retaillé pour le périmètre 27 modules** : la carte de fallback statique de `AiContextualAssistantService` référence encore des modules hors périmètre (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, Documents, MarketingAutomation, CustomerService, Notes, SmartTable…). Ce n'est pas bloquant — un appel `getGuidance('POS', 'open_session')` renvoie simplement un fallback qui ne sera jamais affiché puisque le module POS n'existe pas côté frontend — mais la liste ne reflète pas fidèlement le scope de Life MDG.
