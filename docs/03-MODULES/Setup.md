# Setup

## Rôle

`Setup` est l'assistant d'onboarding de life-mdg-erp : c'est l'implémentation concrète du pilier « Simplicity First ». Il couvre deux parcours distincts : (1) l'**assistant de configuration** (wizard société → administrateur → modules → workflows → applications) et (2) l'**import de données assisté par IA** depuis un fichier (Excel/CSV/PDF) ou une base de données ERP externe, avec suggestion de mapping de colonnes par Claude et repli heuristique quand la clé API est absente. Il porte aussi le suivi analytique du funnel d'onboarding (Simplicity First : mesurer si l'utilisateur termine en moins de 5 minutes).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ImportJob` | `setup_import_jobs` | Un job d'import : type de source (`excel`/`csv`/`pdf`/DB externe), statut, tenant. |
| `SourceSchema` | `setup_source_schemas` | Structure détectée du fichier/table source (colonnes, types) après analyse. |
| `FieldMapping` | `setup_field_mappings` | Mapping colonne source → champ cible WideHalo (avec type de transformation, suggéré par IA ou manuel). |
| `ImportError` | `setup_import_errors` | Erreur de validation/exécution par ligne, rattachée à un `ImportJob`. |
| `OnboardingSession` | `setup_onboarding_sessions` | Session utilisateur du wizard (début, fin, abandon, source). |
| `OnboardingStepEvent` | `setup_onboarding_step_events` | Événement horodaté par étape du wizard, rattaché à une session. |
| `FunnelSnapshot` | `setup_onboarding_funnel_snapshots` | Photo agrégée du taux de complétion du funnel sur une période. |

## Endpoints principaux

Toutes les routes sont sous `auth:sanctum, session.security, tenancy.user` (`routes/api.php`) :

**Wizard d'onboarding** (`prefix: wizard`)
| Méthode | Route | Description |
|---|---|---|
| GET | `state` | État courant du wizard (mis en cache par tenant, TTL 24h) |
| POST | `company` \| `admin` \| `modules` \| `workflows` \| `apps` | Sauvegarder chaque étape |
| POST | `complete` | Finaliser l'onboarding |
| GET | `modules/catalog` | Catalogue des modules disponibles à activer |
| GET | `thresholds/{module}` (`role:admin,super-admin`) | Seuils de configuration d'un module |

**Administration** (`prefix: v1/admin`, `middleware: role:admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| GET/PUT/POST | `modules`, `modules/{module}`, `modules/bulk` | Activation/désactivation de modules (`AdminModulesController` → `ModuleManagerService`, désormais réel — voir Services) |
| GET/PUT/POST | `company`, `company/logo` | Profil société et logo |

**Import de données** (`middleware: module:Setup, role:employee,admin,super-admin` — trou RBAC corrigé cette session, voir RBAC)
| Méthode | Route | Description |
|---|---|---|
| GET/POST | `import-jobs`, `import-jobs/{id}` | Liste/création/détail d'un job d'import — chaque méthode passe désormais par `$this->authorize()` contre `ImportSessionPolicy` |
| POST | `import-jobs/{id}/analyze` | Analyse du fichier → construit le `SourceSchema` |
| POST | `import-jobs/{id}/suggest-mappings` | Suggestions de mapping par IA |
| PUT | `import-jobs/{id}/mappings` | Enregistrer les mappings (remplacement en masse) |
| POST | `import-jobs/{id}/validate` | Validation à blanc (champs requis mappés) |
| POST | `import-jobs/{id}/execute` | Exécuter l'import réel |
| GET | `import-jobs/{id}/errors` | Erreurs paginées d'un job |
| GET | `source-schemas` | Catalogue des cibles d'import disponibles (module + entité + champs) |
| POST | `test-connection` | Tester une connexion à une base externe |

**Métriques d'onboarding** (même groupe `module:Setup, role:employee,admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| POST | `onboarding/start` | Démarrer une session |
| GET | `onboarding/stats` (+ `?days=`) | Statistiques du funnel |
| GET | `onboarding/stats/export` | Export CSV |
| POST | `onboarding/{id}/step` \| `/complete` \| `/abandon` | Événement d'étape / complétion / abandon |

**Pipeline d'import assisté par IA** (`DataImportController`, même groupe — remplace cette session 6 closures mortes, voir Particularités)
| Méthode | Route | Description |
|---|---|---|
| POST | `import/analyze` | Upload d'un fichier → suggestions de mapping IA |
| POST | `import/validate` | Valider un mapping → rapport de qualité des données |
| POST | `import/execute` | Démarrer un job d'import asynchrone (`ImportDataJob`) |
| GET | `import/status/{jobId}` | Suivre la progression d'un job |
| GET | `import/templates` | Liste des types d'entités supportés + leurs schémas de champs |

Enfin `POST v1/setup/ai/assist` fournit la guidance IA contextuelle standard.

## Contrôleurs

- **`Api\SetupWizardController`** — état du wizard, sauvegarde par étape, catalogue de modules.
- **`Api\SetupThresholdsController`** — seuils de configuration par module (admin/super-admin).
- **`Api\AdminModulesController`** — activation/désactivation de modules, désormais réellement fonctionnel via `ModuleManagerService`.
- **`Api\AdminCompanyController`** — profil société, logo.
- **`Api\SetupController`** — CRUD `ImportJob`, analyse, mapping, validation, exécution, erreurs, catalogue de schémas cibles, testeur de connexion. Chaque méthode passe désormais par `$this->authorize()` contre `ImportSessionPolicy` (précédemment écrite mais jamais appelée).
- **`Api\OnboardingMetricsController`** — funnel d'onboarding (sessions, étapes, stats, export CSV).
- **`Api\DataImportController`** (désormais réellement routé — voir Particularités) — pipeline d'import IA complet, adossé à `AiDataImportService`/`ImportDataJob`.
- **`Api\SetupAiAssistController`** — guidance IA contextuelle.

Aucun contrôleur Web dédié n'existe pour ce module (pas de `routes/web.php`) — le wizard et l'import sont des flux 100% API, consommés par des pages Vue self-fetching.

## Vues (Vue/Inertia)

Le module n'a pas de dossier `Modules/Setup/resources/js/Pages/` propre exposant des pages métier distinctes ; l'assistant de configuration et l'import de données sont pilotés par des composants du frontend racine consommant directement l'API ci-dessus. Un cas notable et **volontairement laissé en gap plutôt que deviné** (voir Particularités) : `resources/js/Pages/Import/Index.vue` (routé à `/import`, distinct du wizard Setup) appelle un quatrième schéma d'API entièrement fictif (`/api/v1/import/upload`, `/api/v1/import/jobs*` — sans préfixe `setup`, sans contrôleur correspondant nulle part) ; lequel des deux vrais backends (le flux `import-jobs` du wizard, ou le nouveau pipeline `DataImportController`) devrait l'alimenter est une décision produit non tranchée par cette session.

## Services

- **`SetupWizardService`** — état du wizard tenu en cache (clé `setup:wizard:{tenantId}`, TTL 24h), fusion incrémentale à chaque étape (`saveCompany`, `saveAdmin`, `saveModules`, `saveWorkflows`, `saveApps`), `complete()` finalise.
- **`AiDataImportService`** — pipeline d'import assisté par IA : `analyzeFile()` (détection structure + mapping suggéré via Claude), `validateMapping()`, `executeImport()` (dispatch de `ImportDataJob` en file d'attente), `getImportStatus()`. Registre `ENTITY_SCHEMAS` (contacts, produits, fournisseurs, employés, factures) avec champs requis/optionnels par entité cible. **Désormais réellement exposé par API** (voir Endpoints/Particularités).
- **`AiMappingService`** — appelle l'API Claude (`claude-sonnet-4-6` par défaut, configurable via `setup.ai_model`) pour suggérer des mappings colonne source → champ cible avec score de confiance ; dégrade gracieusement (tableau vide) si `ANTHROPIC_API_KEY` n'est pas configurée.
- **`FileAnalysisService`** — analyse les fichiers Excel/CSV/PDF uploadés et construit/upsert le `SourceSchema` correspondant à un `ImportJob`.
- **`DatabaseSourceService`** — connexion en lecture seule à une base ERP source externe (MySQL/PostgreSQL/MSSQL/SQLite), avec un registre `KNOWN_ERPS` pré-rempli pour les ERP francophones courants (Sage Compta/SAARI, Cegid/Quadra, EBP Compta).
- **`ImportExecutorService`** — exécute l'import réel une fois les mappings confirmés : lit la source (fichier ou DB), applique les transformations par champ, insère en masse dans la table cible avec isolation par tenant.
- **`OnboardingMetricsService`** — enregistre et interroge le funnel d'onboarding sans jamais passer par une file d'attente, pour ne jamais ralentir le wizard utilisateur.
- **`ModuleManagerService`** — **réellement implémenté cette session** (remplace un stub explicite dont chaque méthode retournait `['implemented' => false, ...]`). Construit sur le propre `FileActivator` de `nwidart/laravel-modules` (`config('modules.activator')`, le chemin de lecture/écriture testé du paquet pour `config/modules_statuses.json`) plutôt que de réimplémenter à la main l'I/O JSON. Ajoute ce que la bibliothèque ne fournit pas nativement : validation d'un nom de module fourni par le client contre la vraie liste de modules avant d'agir dessus (jamais de résolution brute d'une chaîne client vers un chemin fichier/activation), blocage d'une désactivation qui casserait le `requires` déclaré d'un autre module actif, et une piste d'audit.

## Permissions RBAC

Préfixe `setup.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `import`, `mapping`, `wizard`, actions standard. `ImportSessionPolicy` applique ces permissions (`setup.import.view-any`, `setup.import.view`, `setup.import.create`, `setup.import.update`, `setup.import.delete`) sur le modèle `ImportJob` — désormais réellement appelée par `SetupController` (voir Endpoints/Contrôleurs), alors qu'elle existait mais n'était jamais invoquée avant cette session. Les routes d'administration (`v1/admin/modules`, `v1/admin/company`) restent protégées par le middleware de rôle `role:admin,super-admin`.

**Trou RBAC corrigé cette session** : le groupe `import-jobs*`/`source-schemas`/`test-connection`/`onboarding/*` n'avait auparavant **aucune** gating `module:`/`role:` (seuls les deux sous-groupes admin en avaient une) — corrigé avec `module:Setup, role:employee,admin,super-admin`.

**Vulnérabilité cross-tenant corrigée cette session (finding principal)** : le helper `tenantId()` partagé de `SetupController`/`OnboardingMetricsController` résolvait `$request->user()?->company_id ?? $request->header('X-Company-ID') ?? 0` — `company_id` étant fréquemment `null` pour un utilisateur ordinaire, le repli sur l'en-tête client était atteint dans le cas courant, permettant à tout utilisateur authentifié de définir `X-Company-ID: <id-du-tenant-victime>` pour lire/écrire les jobs d'import et sessions d'onboarding d'un autre tenant. Corrigé en `$request->user()?->tenant_id ?? 0` (aucun repli sur en-tête), aligné sur la vraie colonne de délimitation multi-tenant déjà utilisée par `App\Http\Middleware\InitializeTenancyFromAuthenticatedUser`.

## Dépendances avec d'autres modules

Aucun autre module ne dépend de `Modules\Setup` (`grep` sur `Modules\Setup` hors du module lui-même ne renvoie rien). `Setup` reste un module d'entrée autonome : il écrit potentiellement dans les tables cibles d'autres modules lors d'un import (`ImportExecutorService`/`ImportDataJob`), mais sans dépendance de code PHP vers ces modules — le mapping de table cible passe par des catalogues statiques (`TargetSchemas`, `ENTITY_SCHEMAS`), pas par les modèles Eloquent des modules cibles.

## Particularités du périmètre life-mdg-erp

- **Le pipeline d'import IA `DataImportController`/`AiDataImportService`/`ImportDataJob` était entièrement construit mais totalement injoignable avant cette session** : `routes/api.php` définissait 6 closures inline (`import/upload`, `{jobId}/status`, `{jobId}/mapping`, `history`, `{jobId}` DELETE, `validate`) renvoyant des réponses statiques factices (`job_id` généré à la volée via `Str::uuid()`, statut toujours `'pending'`/`'queued'`, historique toujours `[]`) — une façade jamais branchée sur le vrai pipeline. Supprimées et remplacées par de vraies routes vers `DataImportController` (voir Endpoints). Un bug réel a été trouvé et corrigé en câblant ce pipeline : `ImportDataJob::entityToTable()` mappait `'invoices' => 'accounting_invoices'`, une table qui n'existe pas (la vraie est `acc_invoices`) — chaque import de factures échouait silencieusement sur chaque ligne. `ImportDataJob::bulkInsert()` a aussi été durci pour filtrer chaque ligne aux colonnes qui existent réellement sur la table cible (via `Schema::getColumnListing()`) plutôt que de supposer que toute table cible a `tenant_id`/`created_at`/`updated_at` — `acc_invoices`/`crm_contacts`/`inventory_stock_movements` n'ont pas de colonne `tenant_id` du tout.
- **Écart non corrigé, documenté plutôt que deviné** : les noms de champs cibles d'`AiDataImportService::ENTITY_SCHEMAS` (ex. `contacts` → `full_name`) ne correspondent pas aux vraies colonnes des tables cibles réelles (`crm_contacts.first_name`/`last_name`, aucune colonne `full_name` ; même problème pour `employees` → `hr_employees`) — réconcilier cet écart supposerait d'inventer une logique métier de découpage/remappage de champs par entité jamais spécifiée nulle part, laissée comme gap connu pour une session future plutôt que devinée silencieusement.
- **`resources/js/Pages/Import/Index.vue` (racine, hors module Setup) appelle un quatrième schéma d'API fictif** — voir Vues ci-dessus.
- **`ModuleManagerService` était un stub explicitement documenté comme non implémenté** avant cette session (chaque méthode renvoyait `['implemented' => false, ...]`) — désormais réel, voir Services. `AdminModulesController` l'utilisait déjà tel quel (les endpoints répondaient donc, mais sans effet réel avant ce correctif).
