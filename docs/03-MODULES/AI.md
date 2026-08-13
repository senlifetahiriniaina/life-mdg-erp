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

- `AiContextualAssistantService` — service central du principe AI Assisted First. `getGuidance(module, action, context, locale, userRole)` retourne un JSON structuré (`what_to_do`, `how_to_do`, `decision_indicators`, `warnings`, `next_actions`, `tips`). Ne parle plus directement à `api.anthropic.com` : elle délègue à `Modules\Core\Services\AI\AIService::forModule('AI')->chat(...)`, qui résout le provider actif (`AI_DEFAULT_PROVIDER`, ou un override `module_providers.AI` — voir `docs/03-MODULES/Core.md`). `$enabled` reflète `isConfigured()` du provider effectivement résolu (pas seulement `ANTHROPIC_API_KEY`) : avec le provider par défaut `anthropic`, c'est équivalent au comportement historique ; avec `AI_DEFAULT_PROVIDER=deepseek`, `enabled` reflète la présence de `DEEPSEEK_BASE_URL`. Toute exception levée par `chat()` (provider injoignable, erreur HTTP) est absorbée et retombe sur `fallbackGuidance()` — `getGuidance()` ne lève jamais d'exception, quel que soit le provider actif. `supportedModules()` liste tous les couples module/action couverts par le fallback statique — la carte couvre bien plus large que les 27 modules du périmètre life-mdg-erp (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, MarketingAutomation, etc. y figurent encore alors que ces modules ont été retirés).
- `AiActionAdvisorService` / `AiUsageBudgetService` — conseille des actions et calcule l'usage/coût IA par période (`daily/weekly/monthly`), avec tarification codée en dur (`claude-sonnet-4-6` : 3 $/M tokens input, 15 $/M output)
- `AiAnomalyDetectionService` — détection d'anomalies exposée via `AiAnomalyController`
- `AiNaturalLanguageSearchService` — recherche en langage naturel exposée via `AiSearchController`
- `AutomatedInsightsService`, `NaturalLanguageProcessingService`, `PredictiveAnalyticsService`, `RecommendationEngineService` — services support pour les endpoints `analytics/*`, `nlp/*`, `insights/*`, `recommendations/*` (voir Particularités : les contrôleurs correspondants sont absents)
- `AnthropicCacheService` — gestion du cache de prompts Anthropic (`cache_control: ephemeral`)

## Permissions RBAC

Le module AI n'a pas de bloc `ai.*.*` dans `RolesAndPermissionsSeeder::MODULES` — aucune permission Spatie granulaire n'est seedée pour lui. Le contrôle d'accès repose uniquement sur `auth:sanctum` au niveau des routes et, pour les modèles `AiRequest`/`AiInsight`, sur des policies (`AiRequestPolicy`, `AiInsightPolicy`) qui vérifient `hasAnyRole(['ai-analyst', 'admin', 'super-admin'])`. Le rôle `ai-analyst` **n'est jamais créé** par `RolesAndPermissionsSeeder` (absent de la liste des 22 rôles documentés en tête de fichier) : en pratique, seuls `admin` et `super-admin` peuvent jamais satisfaire ces policies.

## Dépendances avec d'autres modules

- **Utilisé par** : `AiContextualAssistantService` est importé par 33 fichiers hors du module AI, dans quasiment tous les modules métier (CRM, Accounting, HR, Inventory, BI, Helpdesk, Projects…) sous forme de services `<Module>AIService` dédiés qui l'enveloppent (ex. `Modules\CRM\Services\AI\CrmAIService`, `Modules\Accounting\Services\AI\AccountingAIService`, `Modules\HR\Services\AI\HrAIService`).
- **Utilise** : `Modules\Core\Services\AI\AIService` (voir `docs/03-MODULES/Core.md`) pour exécuter réellement l'appel au provider IA actif — c'est la seule dépendance du module AI vers un autre module. Aucun autre module métier n'est importé.

## Provider auto-hébergé (DeepSeek)

En plus d'Anthropic/OpenAI, un provider DeepSeek auto-hébergé (servi via Ollama, voir `docker-compose.deepseek.yml`) est disponible pour ce module au même titre que pour `AIService` en général. Comportement par défaut inchangé (`AI_DEFAULT_PROVIDER=anthropic`) ; activation via `AI_DEFAULT_PROVIDER=deepseek` ou un override `module_providers.AI` dans `config/ai.php`. Détails de mise en place : `docs/07-DEPLOIEMENT/IA-AUTOHEBERGEE.md`.

## Particularités du périmètre life-mdg-erp

- **Routes orphelines** : `routes/api.php` déclare ~25 routes sous `analytics/*`, `recommendations/*`, `nlp/*` et `insights/*` pointant vers `Modules\AI\Http\Controllers\{AnalyticsController, RecommendationsController, NLPController, InsightsController}` — aucun de ces quatre fichiers n'existe sous `Modules/AI/app/Http/Controllers/` (seul le sous-dossier `Api/` contient réellement `AiAssistantController`, `AiAnomalyController`, `AiSearchController`, `AiActionAdvisorController`). Tout appel HTTP à ces routes échouerait avec une erreur de classe introuvable ; seule la partie « Contextual AI Assistant », « Anomaly Detection », « Natural Language Search » et « Action Advisor » du module est réellement fonctionnelle.
- **`supportedModules()` n'a pas été retaillé pour le périmètre 27 modules** : la carte de fallback statique de `AiContextualAssistantService` référence encore des modules hors périmètre (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, Documents, MarketingAutomation, CustomerService, Notes, SmartTable…). Ce n'est pas bloquant — un appel `getGuidance('POS', 'open_session')` renvoie simplement un fallback qui ne sera jamais affiché puisque le module POS n'existe pas côté frontend — mais la liste ne reflète pas fidèlement le scope de Life MDG.
