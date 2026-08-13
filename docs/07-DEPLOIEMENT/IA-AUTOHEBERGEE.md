# IA auto-hébergée (DeepSeek via Ollama)

## Pourquoi

Les fonctionnalités IA de life-mdg-erp (`AiContextualAssistantService` — panneau de guidance « AI Assisted First » — et les services `<Module>AIService` qui passent par `Modules\Core\Services\AI\AIService`) reposent par défaut sur l'API Anthropic (`AI_DEFAULT_PROVIDER=anthropic`). Un provider **DeepSeek auto-hébergé**, servi localement via [Ollama](https://ollama.com), est disponible comme alternative sélectionnable : pas de dépendance à une clé API externe pour les fonctionnalités IA de base, pas de données envoyées hors de l'infrastructure Life MDG, coût d'usage maîtrisé (pas de facturation au token).

**Ce changement est strictement additif** : tant que `AI_DEFAULT_PROVIDER` reste sur sa valeur par défaut (`anthropic`) et que `.env` n'est pas modifié, le comportement de l'application est identique à avant — DeepSeek n'est jamais sollicité.

## Cible matérielle : CPU uniquement

Ollama est choisi comme moteur de serving parce qu'il tourne correctement sur CPU seul (latence plus élevée qu'avec un GPU, mais fonctionnel), gère nativement le téléchargement/versionning de modèles (`ollama pull`), et expose une API compatible OpenAI (`/v1/chat/completions`) — donc réutilisable telle quelle par le client `openai-php/client` déjà présent dans le projet (`DeepSeekProvider`, voir plus bas). Le modèle par défaut, `deepseek-r1:7b`, est une version distillée/quantifiée pensée pour tourner sans GPU.

## Démarrage

```bash
docker compose -f docker-compose.deepseek.yml up -d
```

Ceci lance deux services (voir `docker-compose.deepseek.yml` à la racine) :

- **`deepseek`** — le serveur Ollama lui-même (image `ollama/ollama:latest`), port `11434` exposé, volume nommé `ollama_data` pour persister les modèles téléchargés entre redémarrages, `healthcheck` basé sur `ollama list`.
- **`deepseek-model-init`** — service à usage unique (`restart: "no"`) qui attend que `deepseek` soit en bonne santé puis exécute `ollama pull "$DEEPSEEK_MODEL"` (par défaut `deepseek-r1:7b`, override possible via la variable d'environnement `DEEPSEEK_MODEL`). Premier démarrage : téléchargement de plusieurs Go, peut prendre plusieurs minutes selon la bande passante. Démarrages suivants : le modèle est déjà dans le volume, ce service se termine immédiatement (no-op).

Vérifier que le modèle est bien disponible :

```bash
curl http://localhost:11434/api/tags
```

## Configuration applicative

Dans `.env` (voir `.env.example` pour le bloc complet) :

```bash
# Base URL du serveur Ollama — localhost si l'app tourne hors docker-compose,
# http://deepseek:11434/v1 si l'app et deepseek partagent le même réseau docker-compose
DEEPSEEK_BASE_URL=http://localhost:11434/v1
DEEPSEEK_MODEL=deepseek-r1:7b

# Pour basculer TOUT le module IA sur DeepSeek :
AI_DEFAULT_PROVIDER=deepseek
```

Ces valeurs alimentent `config('ai.providers.deepseek')` (`config/ai.php`). `default_provider` (`config('ai.default_provider')`, lu depuis `AI_DEFAULT_PROVIDER`) reste `anthropic` par défaut — le changer vers `deepseek` bascule tous les appels IA qui passent par `Modules\Core\Services\AI\AIService` (ce qui inclut `AiContextualAssistantService`, voir `docs/03-MODULES/AI.md` et `docs/03-MODULES/Core.md`).

### Activer DeepSeek pour un seul module

Plutôt que de basculer toute l'application, `config/ai.php` accepte un override ciblé par module dans `module_providers` :

```php
// config/ai.php
'module_providers' => [
    'AI' => 'deepseek', // seul le panneau de guidance contextuelle passe par DeepSeek
],
```

## Périmètre couvert

DeepSeek est branché sur les deux piles IA du projet :

- `Modules\Core\Services\AI\AIService` (via `DeepSeekProvider implements AIProviderContract`) — utilisé par les services `<Module>AIService` (CRM, Accounting, HR, Inventory, Projects, Helpdesk…).
- `Modules\AI\Services\AiContextualAssistantService` — délègue à `AIService::forModule('AI')`, donc bénéficie de DeepSeek de la même façon.

**Hors périmètre** : 17 autres fichiers du codebase (`Modules\{Analytics,Strategy,Workflow,Reporting,Setup,Accounting,Logistics}\...`) appellent directement `https://api.anthropic.com/v1/messages` via `Http::post(...)`, indépendamment du contrat `AIProviderContract`. Ces sites continuent d'utiliser Anthropic sans changement — les rebrancher sur `AIService` serait une refonte indépendante, pas une conséquence directe de l'ajout de DeepSeek.

## Limites connues

- **Pas d'embeddings** : `DeepSeekProvider::embed()` lève une `\RuntimeException` (comme `AnthropicProvider`) — utiliser le provider OpenAI pour les cas nécessitant des embeddings.
- **Pas de prompt caching** : le paramètre `cache_system` (utilisé par `AnthropicProvider` pour le cache de prompts `cache_control: ephemeral`, spécifique à l'API Anthropic) n'a pas d'équivalent chez DeepSeek/Ollama — il est simplement ignoré si passé à `DeepSeekProvider::chat()`.
- **Latence CPU** : sans GPU, les temps de réponse sont significativement plus élevés qu'avec Anthropic/OpenAI — à évaluer avant un usage en production sur des flux à fort volume.
- **Validation CI limitée** : `docker-build.yml` valide uniquement la syntaxe/interpolation de `docker-compose.deepseek.yml` (`docker compose config`) à chaque changement pertinent — il ne télécharge ni l'image Ollama ni le modèle (trop lourd pour tourner à chaque push) et ne teste donc pas d'inférence réelle. Un test d'inférence de bout en bout doit être fait manuellement, sur une machine avec Docker et accès réseau réel, en suivant les étapes ci-dessus.

## Dépannage

| Symptôme | Cause probable |
|---|---|
| `enabled: false` dans la réponse de `POST /api/v1/ai/assist` alors que `AI_DEFAULT_PROVIDER=deepseek` | `DEEPSEEK_BASE_URL` vide ou non chargée (`isConfigured()` de `DeepSeekProvider` retourne `false`) — vérifier `.env` et `php artisan config:clear` |
| Le panneau IA retombe systématiquement sur le contenu statique (`fallbackGuidance`) | Le conteneur `deepseek` n'est pas démarré, ou le modèle n'est pas encore téléchargé (`deepseek-model-init` toujours en cours) — vérifier `docker compose -f docker-compose.deepseek.yml ps` et `curl http://localhost:11434/api/tags` |
| Timeout sur les requêtes IA | Latence CPU normale sur un modèle 7B non quantifié agressivement — envisager un modèle plus petit ou allouer plus de CPU au conteneur |
