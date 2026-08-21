# Settings

## Rôle

`Settings` centralise le paramétrage applicatif clé/valeur, par module et par tenant, avec cache Laravel Cache pour éviter les allers-retours DB répétés. Un réglage peut être global (`tenant_id` null) ou spécifique à un tenant, et une valeur tenant l'emporte toujours sur une valeur globale de même clé.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Setting` | `settings` | Un couple `module`/`key` → `value` typé (`value_type`: string/integer/boolean/json/encrypted), avec visibilité `is_public` et scope `tenant_id`. Utilise `Modules\AuditLog\Traits\HasAuditLog`. |

**`SettingGroup`/`setting_groups` supprimés au Chantier 32.9** (audit approfondi en 14 couches) : confirmé zéro consommateur réel nulle part dans le dépôt (aucun contrôleur, aucune route, aucune page Vue, jamais référencé même par sa propre factory dans un test) — et, indépendamment, cassé par conception : `SettingGroup::scopeForModule()` filtrait une colonne `module` que la table `setting_groups` n'a jamais eue (seulement `name`/`label`/`icon`). Classé « mort confirmé, à supprimer » (pas « à activer ») per la méthodologie Chantier 32 — modèle, factory, table, colonne FK `settings.group_id`, et les colonnes mortes sœurs `settings.{type,label,is_system}` (jamais dans `Setting::$fillable`, jamais lues/écrites par aucun code réel) tous supprimés dans une seule migration additive.

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum, session.security, tenancy.user, module:Settings, role:employee,manager,admin,super-admin` (`routes/api.php`, préfixe `v1/settings` appliqué par le service provider du module) :

| Méthode | Route | Description |
|---|---|---|
| GET | `/` | Liste de tous les réglages (admin uniquement — `authorize('viewAll', Setting::class)`), **scopée à la société de l'appelant sauf pour un vrai `super-admin`** (Chantier 32.9 — voir Sécurité ci-dessous) |
| GET | `/{module}` | Tous les réglages d'un module, scopés au tenant courant, **filtrés par visibilité `is_public`/`settings.view`** (Chantier 32.9) |
| POST | `/{module}/bulk` | Mise à jour en masse des réglages d'un module |
| PUT | `/{module}/{key}` | Mise à jour (ou création) d'un réglage unique, **avec validation de type serveur si `value_type` est déclaré** (Chantier 32.9) |
| POST | `v1/settings/ai/assist` | Guidance IA contextuelle (`SettingsAiAssistController`) |

## Contrôleurs

`Modules/Settings/app/Http/Controllers/Api/` (2 fichiers, module sans dossier `Web/`) :

| Contrôleur | Rôle |
|---|---|
| `SettingsController` | `index`/`showModule`/`bulk`/`update` |
| `SettingsAiAssistController` | Guidance IA contextuelle (délègue à `AiContextualAssistantService`, module `'Settings'` — 3 actions réelles avec repli fr+en : `configure_settings`, `manage_integrations`, `notification_preferences`) |

**Correctifs de cette session** :
- **Chantier 8.5-light** : `SettingsController::update()` n'avait aucun appel `authorize()` — corrigé pour appliquer le même contrôle que `bulk()`.
- **Chantier 19 Lot 3** : `Setting::boot()`/`get()`/`set()` et `SettingsService::currentTenantId()` retombaient sur un en-tête `X-Company-ID` contrôlé par le client — corrigé.
- **Chantier 32.9 (audit approfondi 14 couches)** — 4 bugs réels supplémentaires trouvés par exécution empirique, pas par relecture :
  1. **`index()` avait une vraie fuite cross-tenant** : `SettingPolicy::viewAll()` ne vérifie que le rôle `admin` (un rôle *par société* dans cette app, contrairement à `super-admin`) mais la requête n'appliquait aucun filtre `company_id` — n'importe quel `admin` d'une société pouvait lister les réglages de toutes les autres sociétés. Corrigé : scope à la société de l'appelant sauf `super-admin`.
  2. **Index unique en base incomplet** (voir « Format de données » ci-dessous) : le même nom de clé réutilisé dans deux modules différents pour un même tenant provoquait une vraie `UniqueConstraintViolationException`.
  3. **`SettingsService::getModule()` inversait l'ordre de fusion tenant/global** : un réglage global par défaut écrasait silencieusement une vraie surcharge spécifique au tenant (confirmé empiriquement — l'exact opposé de son propre commentaire de code et du comportement correct de `Setting::get()` sur les mêmes données). Corrigé.
  4. **`update()` n'avait aucune validation de conformité de type** : déclarer `value_type=boolean` avec `value="false"` (une chaîne non vide, donc *truthy* en PHP) stockait silencieusement `true` au lieu de `false`, ou d'être rejeté. Corrigé avec des règles de validation conditionnelles selon `value_type`.

## Vues (Vue/Inertia)

Pas de page propre au module (`Modules/Settings/resources/js/Pages/` n'existe pas). L'écran de configuration est `resources/js/Pages/Settings/Index.vue` (racine du dépôt), rendu par une route directe du fichier racine `routes/web.php` (`GET /settings` → `Inertia::render('Settings/Index')`) — hors du module lui-même, qui n'a pas de `routes/web.php`.

**Trouvaille majeure du Chantier 32.9 (couche 9 — code mort/factice)** : jusqu'à ce chantier, cette page **ne parlait à AUCUNE API** — ni à l'API réelle de `Modules\Settings` (confirmé par grep exhaustif : zéro appel `axios`/`fetch` dans le fichier), ni aucun autre module ne lisait/écrivait jamais via `Setting::get()`/`Setting::set()`/`SettingsService` (confirmé par grep sur tout le dépôt). L'onglet « Notifications » (4 interrupteurs) était un état de composant purement local, jamais persisté nulle part. **L'intégralité de l'API CRUD réelle, testée, correctement RBAC-gated de `Modules\Settings` avait donc zéro consommateur, frontend ou backend, malgré son bon état de code.** Classé « à activer » (pas « à supprimer ») — infrastructure réelle avec de vrais bugs déjà corrigés dans ce même chantier. L'onglet Notifications est désormais câblé pour de vrai sur `GET/POST /api/v1/settings/notifications` (module `notifications`, `showModule()`/`bulk()`), et un panneau `AIAssistantPanel`/`useAiAssistant('Settings','configure_settings')` a été ajouté (suivant le patron déjà établi Chantier 30). Les onglets « Général » (langue → `/profile`, un système différent) et « Modules » (liste `enabledModules` via les props Inertia partagées, un système de tenant-modules différent) restent délibérément hors du périmètre de ce module — non touchés.

## Services

- **`SettingsService`** — accès centralisé en lecture/écriture avec cache par tenant (clé `settings:{tenant_id}:{module}:{key}` pour une valeur unique, `settings:{tenant_id}:{module}:v2` pour un instantané de module — suffixe `:v2` depuis Chantier 32.9, voir ci-dessous) :
  - `get()`/`set()` — lecture/écriture typée d'un réglage unique, avec invalidation du cache à l'écriture (`set()`/`setTyped()` invalident désormais **aussi** le cache de niveau module depuis Chantier 32.9 — avant, un `showModule()` juste après un `update()`/`bulk()` pouvait renvoyer un instantané périmé jusqu'à 1h).
  - `setTyped()` — écrit un réglage avec un `value_type` explicite ; le cas `encrypted` chiffre la valeur via `Crypt::encryptString()` avant stockage.
  - `getModule()` — récupère tous les réglages d'un module en fusionnant valeur globale (base) et valeur tenant (override, qui gagne réellement depuis le correctif Chantier 32.9), **filtrés par visibilité** : un réglage `is_public=false` n'est retourné qu'à un appelant disposant de `settings.view` — l'instantané en cache reste complet (avec le flag `is_public` par entrée) pour que le filtre s'applique fraîchement à chaque appel, jamais depuis une version pré-filtrée qui fuiterait vers un appelant moins privilégié partageant la même clé de cache.
  - `setMany()` — écriture en masse pour un module, avec invalidation de toutes les clés de cache concernées.
  - `flushTenantCache()` — tente un flush par tag (`Cache::tags(...)`), avec repli silencieux (`BadMethodCallException`) sur un driver de cache non-taggable (file/database, le driver réel de cette app).

## Format de données

- `settings` : `id, tenant_id (nullable), module, key, value, value_type, description, is_public, created_at, updated_at`.
- **Correctif Chantier 32.9** : l'index unique original était `(tenant_id, key)` — n'incluait jamais `module`, alors que toute écriture réelle (`Setting::set()`/`setTyped()`/`SettingsService`) clé son `updateOrCreate()` sur `(tenant_id, module, key)`. Le même nom de clé réutilisé dans deux modules pour un même tenant (`theme`, `enabled`, `currency`...) provoquait une vraie violation de contrainte unique, confirmée empiriquement, pas hypothétique. Corrigé : index unique élargi à `(tenant_id, module, key)`.

## Permissions RBAC

Préfixe `settings.*` dans `RolesAndPermissionsSeeder::MODULES` — ressource `setting` uniquement depuis Chantier 32.9 (`group` retiré, `SettingGroup` supprimé — ces chaînes `settings.group.*` n'étaient de toute façon jamais lues par `SettingPolicy`, pure hygiène). En pratique, `SettingPolicy` combine ces permissions Spatie flat (`hasPermissionTo('settings.view')`, etc. — pas `settings.setting.*`, voir le commentaire dédié dans `RolesAndPermissionsSeeder::SETTINGS_PERMISSIONS`) **avec** une vérification de rôle `admin` en `OR`, et une vérification d'appartenance au tenant (`belongsToTenant()`) pour `view`/`update`/`delete` — les réglages globaux (`tenant_id = null`) ne sont modifiables que par le rôle `admin`. `viewAll()` (utilisé par `index()`) ne vérifie que `hasRole('admin')` — **désormais complété au niveau contrôleur** par un scope société explicite (Chantier 32.9, voir Contrôleurs ci-dessus), puisque `admin` est un rôle par société dans cette app, pas global comme `super-admin`.

## IA (couche 13)

`Modules\AI\Services\AiContextualAssistantService::supportedModules()` enregistre `'Settings'` avec 3 actions réelles (`configure_settings`, `manage_integrations`, `notification_preferences`), chacune avec un vrai texte de repli fr+en — confirmé non-vide par exécution réelle via la vraie route générique `POST /api/v1/ai/assist` (celle que le composable frontend `useAiAssistant` appelle réellement, pas le contrôleur dédié `SettingsAiAssistController`, qui délègue au même service mais n'a — comme la plupart des contrôleurs IA dédiés par module de cette app — aucun appelant Vue réel aujourd'hui). Le panneau `AIAssistantPanel` de la page `Settings/Index.vue` utilise cette route générique, comme toutes les autres pages de l'app.

## Performance (couche 14f)

`get()`/`getModule()` sont mis en cache (TTL 1h) — pas de requête DB répétée sur un chemin de lecture chaud une fois le cache chaud. `index()` (liste admin) est paginé (100/page), pas de N+1 (table unique, aucune relation chargée). Aucun goulot d'étranglement réel identifié pour ce module, config-only et de faible volume.

## Dépendances avec d'autres modules

`Settings` dépend de `Modules\AuditLog\Traits\HasAuditLog` pour l'audit du modèle `Setting` (écrit dans `storage/logs/audit-*.log`, pas en base — voir `docs/03-MODULES/AuditLog.md` pour le détail de ce trait après son propre audit Chantier 32.4). Après le Chantier 32.9, l'API REST du module a enfin un vrai consommateur : le root-level `resources/js/Pages/Settings/Index.vue` (onglet Notifications). Aucun autre module de life-mdg-erp n'importe `Modules\Settings` directement dans son code PHP (`grep` sur `Modules\Settings` hors du module lui-même ne renvoie toujours aucun résultat) — la configuration par module reste exclusivement accessible via l'API REST, jamais par injection du service dans un autre module.
