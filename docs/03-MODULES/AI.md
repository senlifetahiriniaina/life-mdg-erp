# AI

## Rôle

`Modules/AI` porte le principe « AI Assisted First » de life-mdg-erp : il fournit le service central `AiContextualAssistantService::getGuidance()` que tous les autres modules interrogent pour afficher une aide contextuelle générée par Claude sur chaque écran/action, avec un repli statique (`fallback`) garanti quand la clé API Anthropic est absente ou que l'appel échoue. Il expose en complément des briques IA plus spécialisées : détection d'anomalies, recherche en langage naturel, conseiller d'actions et suivi budgétaire des appels IA (coût, tokens, quotas). Selon le contexte du projet, l'AI module n'importe aucun autre module — c'est la dépendance racine du graphe applicatif.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `AiRecommendation` | `ai_recommendations` | Recommandations produites par le moteur IA |
| `AiAnomaly` | `ai_anomalies` | Anomalies détectées (données financières, comportementales, etc.) — désormais calculées sur les vraies tables métier (voir Services) |
| `AiUsageLimit` | `ai_usage_limits` | Quotas d'usage IA par tenant/utilisateur (`limit_type`, `limit_value`, `period`, `block_on_exceed`) — utilise le trait `HasAuditLog` du module AuditLog |

`AiInsight`/`AiRequest` (modèles + policies + migrations) ont été **supprimés** cette session : zéro consommateur réel, le suivi de l'usage IA effectif passe par la table `ai_usage_logs` (distincte, alimentée par `Modules\Core\Services\AI\AIService`), pas par ces deux modèles.

## Endpoints principaux

Tous préfixés `/api/v1/ai/`, protégés `auth:sanctum` (voir `Modules/AI/routes/api.php`) :

- **Assistant contextuel (AI Assisted First)** : `POST assist`, `GET assist/modules` — implémentés par `AiAssistantController`, qui délègue directement à `AiContextualAssistantService`
- **Anomalies** : `POST anomalies/detect`, `GET anomalies`, `DELETE anomalies/{id}`
- **Recherche en langage naturel** : `POST search`
- **Conseiller d'actions** : `POST advise`, `GET usage/me`
- **Administration budget IA** (`/api/v1/ai/admin/`, `hasAnyRole(['admin','super-admin'])`) : `GET usage`, `GET limits`, `POST limits`, `DELETE limits/{id}`

`analytics/*`, `recommendations/*`, `nlp/*`, `insights/*` (~25 routes vers `AnalyticsController`/`RecommendationsController`/`NLPController`/`InsightsController`, qui n'existent pas sous `Http/Controllers/`) ont été **commentées hors du fichier de routes** cette session plutôt que laissées actives à pointer vers des classes introuvables — voir Particularités.

## Contrôleurs

`Modules/AI/app/Http/Controllers/Api/` (4 fichiers, tous les contrôleurs réellement routés du module) :

| Contrôleur | Rôle |
|---|---|
| `AiAssistantController` | Assistant contextuel — délègue à `AiContextualAssistantService` |
| `AiAnomalyController` | Détection/liste/rejet d'anomalies, scopé `company_id` |
| `AiSearchController` | Recherche en langage naturel, scopée `company_id` |
| `AiActionAdvisorController` | Conseiller d'actions + administration du budget IA (`requireAdmin()`) |

## Vues (Vue/Inertia)

Aucune route web n'existe pour ce module (`Modules/AI/routes/web.php` absent) — conforme au principe « API First » : le module n'a pas d'interface propre, son unique point de contact frontend est le composable `useAiAssistant` consommé par les autres modules. Deux pages Vue existent néanmoins dans le dépôt sans être routées : `Modules/AI/resources/js/Pages/Agents/Index.vue` (« Agents IA Autonomes », 100 % mock) et `AiActionAdvisor.vue` (racine du dépôt, réelle mais jamais montée) — les deux ont été examinées et **délibérément laissées non routées** cette session : où monter la seconde et si construire la première sont des décisions produit, pas des corrections de câblage.

## Services

- `AiContextualAssistantService` — service central du principe AI Assisted First. `getGuidance(module, action, context, locale, userRole)` retourne un JSON structuré (`what_to_do`, `how_to_do`, `decision_indicators`, `warnings`, `next_actions`, `tips`). Ne parle plus directement à `api.anthropic.com` : elle délègue à `Modules\Core\Services\AI\AIService::forModule('AI')->chat(...)`, qui résout le provider actif (`AI_DEFAULT_PROVIDER`, ou un override `module_providers.AI` — voir `docs/03-MODULES/Core.md`). `$enabled` reflète `isConfigured()` du provider effectivement résolu (pas seulement `ANTHROPIC_API_KEY`) : avec le provider par défaut `anthropic`, c'est équivalent au comportement historique ; avec `AI_DEFAULT_PROVIDER=deepseek`, `enabled` reflète la présence de `DEEPSEEK_BASE_URL`. Toute exception levée par `chat()` (provider injoignable, erreur HTTP) est absorbée et retombe sur `fallbackGuidance()` — `getGuidance()` ne lève jamais d'exception, quel que soit le provider actif. `supportedModules()` liste tous les couples module/action couverts par le fallback statique — la carte couvre bien plus large que les 27 modules du périmètre life-mdg-erp (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, MarketingAutomation, etc. y figurent encore alors que ces modules ont été retirés).
- `AiActionAdvisorService` / `AiUsageBudgetService` — conseille des actions et calcule l'usage/coût IA par période (`daily/weekly/monthly`), avec tarification codée en dur (`claude-sonnet-4-6` : 3 $/M tokens input, 15 $/M output)
- `AiAnomalyDetectionService` — détection d'anomalies exposée via `AiAnomalyController`. **Repointée cette session** sur les vraies tables préfixées par module (`acc_*`, `crm_*`, `inventory_*`, etc.) — elle interrogeait auparavant des noms de table génériques (`products`, `invoices`, `journal_entries`, `contacts`…) qui n'existent pas dans ce schéma, donc n'avait jamais surfacé de vraie donnée métier, seulement le texte de repli de son propre `catch`. Les entités POS/Ecommerce/Contracts/Assets (hors périmètre) ont été retirées de la carte d'entités.
- `AiNaturalLanguageSearchService` — recherche en langage naturel exposée via `AiSearchController`, même correctif de schéma que ci-dessus ; le filtre tenant ne fait désormais confiance qu'à une vraie colonne `tenant_id` (vérifiée via `Schema::hasColumn()`), jamais à `company_id` — `crm_contacts.company_id` est une FK vers une société *cliente* CRM, pas la frontière tenant de l'application, une collision de nom à ne pas reproduire.
- `AutomatedInsightsService`, `NaturalLanguageProcessingService`, `PredictiveAnalyticsService`, `RecommendationEngineService` — services support pour les endpoints `analytics/*`, `nlp/*`, `insights/*`, `recommendations/*`, toujours liés dans le conteneur (`AIServiceProvider::register()`) mais sans couche contrôleur HTTP pour les exposer (voir Particularités)
- `AnthropicCacheService` — gestion du cache de prompts Anthropic (`cache_control: ephemeral`)

## Permissions RBAC

Le module AI n'a pas de bloc `ai.*.*` dans `RolesAndPermissionsSeeder::MODULES` — aucune permission Spatie granulaire n'est seedée pour lui. Le contrôle d'accès repose sur `auth:sanctum` au niveau des routes, sur `AiActionAdvisorController::requireAdmin()` (corrigé cette session — vérifiait la colonne fantôme jamais peuplée `users.role` au lieu de `hasAnyRole(['admin','super-admin'])`, ce qui verrouillait tous les vrais admins hors de `/api/v1/ai/admin/*`), et sur le scope `company_id` désormais appliqué par `AiAnomalyController`/`AiSearchController` (voir Particularités — c'était auparavant une faille cross-tenant réelle).

## Dépendances avec d'autres modules

- **Utilisé par** : `AiContextualAssistantService` est importé par 33 fichiers hors du module AI, dans quasiment tous les modules métier (CRM, Accounting, HR, Inventory, BI, Helpdesk, Projects…) sous forme de services `<Module>AIService` dédiés qui l'enveloppent (ex. `Modules\CRM\Services\AI\CrmAIService`, `Modules\Accounting\Services\AI\AccountingAIService`, `Modules\HR\Services\AI\HrAIService`).
- **Utilise** : `Modules\Core\Services\AI\AIService` (voir `docs/03-MODULES/Core.md`) pour exécuter réellement l'appel au provider IA actif — c'est la seule dépendance du module AI vers un autre module. Aucun autre module métier n'est importé.

## Provider auto-hébergé (DeepSeek)

En plus d'Anthropic/OpenAI, un provider DeepSeek auto-hébergé (servi via Ollama, voir `docker-compose.deepseek.yml`) est disponible pour ce module au même titre que pour `AIService` en général. Comportement par défaut inchangé (`AI_DEFAULT_PROVIDER=anthropic`) ; activation via `AI_DEFAULT_PROVIDER=deepseek` ou un override `module_providers.AI` dans `config/ai.php`. Détails de mise en place : `docs/07-DEPLOIEMENT/IA-AUTOHEBERGEE.md`.

## Particularités du périmètre life-mdg-erp

- **Faille cross-tenant corrigée cette session** : `AiAnomalyController`/`AiSearchController::resolveTenantId()` faisait `isset($user->tenant_id) ? ... : (int) $request->header('X-Tenant-Id', 1)` — `isset()` sur la colonne fantôme toujours-nulle `tenant_id` valait systématiquement `false`, donc chaque requête retombait sur l'en-tête contrôlé par le client (défaut 1), permettant à n'importe quel utilisateur de lire/rejeter les anomalies d'un autre tenant ou d'exécuter une recherche NL sur les données d'un autre tenant en forgeant cet en-tête. Corrigé sur `$user->company_id ?? 0`, le repli par en-tête entièrement supprimé.
- **Routes orphelines, désormais neutralisées plutôt que dangereuses** : `routes/api.php` ne référence plus activement `analytics/*`, `recommendations/*`, `nlp/*`, `insights/*` (les ~25 routes sont commentées, avec un commentaire expliquant que les 4 classes de contrôleur ciblées — `AnalyticsController`, `RecommendationsController`, `NLPController`, `InsightsController` — n'existent toujours pas sous `Modules/AI/app/Http/Controllers/`, alors que les services métier sous-jacents, eux, existent bel et bien et sont liés dans le conteneur). Construire ces ~24 endpoints est explicitement documenté comme du backlog de fonctionnalité, pas une correction de câblage.
- **`supportedModules()` n'a pas été retaillé pour le périmètre 27 modules** : la carte de fallback statique de `AiContextualAssistantService` référence encore des modules hors périmètre (POS, Manufacturing, Ecommerce, Quality, PLM, Contracts, Assets, Documents, MarketingAutomation, CustomerService, Notes, SmartTable…). Ce n'est pas bloquant — un appel `getGuidance('POS', 'open_session')` renvoie simplement un fallback qui ne sera jamais affiché puisque le module POS n'existe pas côté frontend — mais la liste ne reflète pas fidèlement le scope de Life MDG.
