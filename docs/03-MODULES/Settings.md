# Settings

## Rôle

`Settings` centralise le paramétrage applicatif clé/valeur, par module et par tenant, avec cache Redis/Laravel Cache pour éviter les allers-retours DB répétés. Un réglage peut être global (`tenant_id` null) ou spécifique à un tenant, et une valeur tenant l'emporte toujours sur une valeur globale de même clé.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Setting` | `settings` | Un couple `module`/`key` → `value` typé (`value_type`: string/integer/boolean/json/encrypted), avec visibilité `is_public` et scope `tenant_id`. Utilise `Modules\AuditLog\Traits\HasAuditLog`. |
| `SettingGroup` | `setting_groups` | Regroupement de réglages par module pour l'affichage (nom, description, ordre). |

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum` (`routes/api.php`, pas de préfixe explicite dans le fichier lui-même — le préfixe `v1/settings` est appliqué par le service provider du module) :

| Méthode | Route | Description |
|---|---|---|
| GET | `/` | Liste de tous les réglages (admin uniquement — `authorize('viewAll', Setting::class)`) |
| GET | `/{module}` | Tous les réglages d'un module, scopés au tenant courant |
| POST | `/{module}/bulk` | Mise à jour en masse des réglages d'un module |
| PUT | `/{module}/{key}` | Mise à jour (ou création) d'un réglage unique |
| POST | `v1/settings/ai/assist` | Guidance IA contextuelle (`SettingsAiAssistController`) |

## Contrôleurs

`Modules/Settings/app/Http/Controllers/Api/` (2 fichiers, module sans dossier `Web/`) :

| Contrôleur | Rôle |
|---|---|
| `SettingsController` | `index`/`showModule`/`bulk`/`update` |
| `SettingsAiAssistController` | Guidance IA contextuelle |

**Correctif RBAC de cette session** : `SettingsController::update()` (mise à jour d'un réglage unique, `PUT /{module}/{key}`) n'avait **aucun** appel `authorize()`, contrairement à ses trois méthodes sœurs (`index`, `showModule`, `bulk`) — n'importe quel utilisateur authentifié de n'importe quel rôle pouvait modifier n'importe quel réglage individuel d'un tenant, potentiellement de la configuration paiement/sécurité, sans avoir besoin de `settings.update`. Corrigé pour appliquer le même contrôle que `bulk()`.

## Vues (Vue/Inertia)

Pas de page propre au module (`Modules/Settings/resources/js/Pages/` n'existe pas). L'écran de configuration est `resources/js/Pages/Settings/Index.vue` (racine du dépôt), rendu par une route directe du fichier racine `routes/web.php` (`GET /settings` → `Inertia::render('Settings/Index')`) — hors du module lui-même, qui n'a pas de `routes/web.php`. Une copie dupliquée `SettingsIndex.vue`, masquée par cette page racine, a été **supprimée** cette session.

## Services

- **`SettingsService`** — accès centralisé en lecture/écriture avec cache par tenant (clé `settings:{tenant_id}:{module}:{key}`, TTL 1h) :
  - `get()`/`set()` — lecture/écriture typée d'un réglage unique, avec invalidation du cache à l'écriture.
  - `setTyped()` — écrit un réglage avec un `value_type` explicite ; le cas `encrypted` chiffre la valeur via `Crypt::encryptString()` avant stockage.
  - `getModule()` — récupère tous les réglages d'un module en fusionnant valeur globale (base) et valeur tenant (override), avec cache dédié.
  - `setMany()` — écriture en masse pour un module, avec invalidation de toutes les clés de cache concernées.
  - `flushTenantCache()` — tente un flush par tag Redis (`Cache::tags(...)`), avec repli silencieux (`BadMethodCallException`) sur un driver de cache non-taggable (file/database).

## Permissions RBAC

Préfixe `settings.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `setting`, `group`, actions standard (`view-any`, `view`, `create`, `update`, `delete`). En pratique, `SettingPolicy` combine ces permissions Spatie (`hasPermissionTo('settings.view')`, etc.) **avec** une vérification de rôle `admin` en `OR`, et une vérification d'appartenance au tenant (`belongsToTenant()`) pour `view`/`update`/`delete` — les réglages globaux (`tenant_id = null`) ne sont modifiables que par le rôle `admin`.

## Dépendances avec d'autres modules

Aucun autre module de life-mdg-erp n'importe `Modules\Settings` directement dans son code PHP (`grep` sur `Modules\Settings` ne renvoie aucun résultat hors du module) : la configuration par module se fait exclusivement via l'API REST (`GET/PUT /api/v1/settings/{module}/...`), pas par injection du service dans d'autres modules. `Settings` dépend de `Modules\AuditLog\Traits\HasAuditLog` pour l'audit des modèles `Setting` et `SettingGroup`.
