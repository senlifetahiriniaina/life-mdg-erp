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

Toutes les routes sont sous `auth:sanctum` (`routes/api.php`) :

**Wizard d'onboarding** (`prefix: wizard`)
| Méthode | Route | Description |
|---|---|---|
| GET | `state` | État courant du wizard (mis en cache par tenant, TTL 24h) |
| POST | `company` \| `admin` \| `modules` \| `workflows` \| `apps` | Sauvegarder chaque étape |
| POST | `complete` | Finaliser l'onboarding |
| GET | `modules/catalog` | Catalogue des modules disponibles à activer |

**Administration** (`prefix: v1/admin`, `middleware: role:admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| GET/PUT/POST | `modules`, `modules/{module}`, `modules/bulk` | Gestion des modules activés — **stub non implémenté**, voir Services |
| GET/PUT/POST | `company`, `company/logo` | Profil société et logo |

**Import de données**
| Méthode | Route | Description |
|---|---|---|
| GET/POST | `import-jobs`, `import-jobs/{id}` | Liste/création/détail d'un job d'import |
| POST | `import-jobs/{id}/analyze` | Analyse du fichier → construit le `SourceSchema` |
| POST | `import-jobs/{id}/suggest-mappings` | Suggestions de mapping par IA |
| PUT | `import-jobs/{id}/mappings` | Enregistrer les mappings (remplacement en masse) |
| POST | `import-jobs/{id}/validate` | Validation à blanc (champs requis mappés) |
| POST | `import-jobs/{id}/execute` | Exécuter l'import réel |
| GET | `import-jobs/{id}/errors` | Erreurs paginées d'un job |
| GET | `source-schemas` | Catalogue des cibles d'import disponibles (module + entité + champs) |
| POST | `test-connection` | Tester une connexion à une base externe |

**Métriques d'onboarding**
| Méthode | Route | Description |
|---|---|---|
| POST | `onboarding/start` | Démarrer une session |
| GET | `onboarding/stats` (+ `?days=`) | Statistiques du funnel |
| GET | `onboarding/stats/export` | Export CSV |
| POST | `onboarding/{id}/step` \| `/complete` \| `/abandon` | Événement d'étape / complétion / abandon |

**API d'import générique** (`import/*`) — `upload`, `{jobId}/status`, `{jobId}/mapping`, `history`, `{jobId}` (DELETE), `validate` : ces routes sont définies **inline en closures directement dans `routes/api.php`** (pas de contrôleur dédié) et renvoient des réponses statiques/factices (ex. `job_id` UUID généré à la volée, statut toujours `pending`) — une façade d'API non branchée sur `ImportJob`/`ImportExecutorService`, distincte du flux `import-jobs/*` ci-dessus qui, lui, est réellement implémenté.

Enfin `POST v1/setup/ai/assist` fournit la guidance IA contextuelle standard.

## Services

- **`SetupWizardService`** — état du wizard tenu en cache (clé `setup:wizard:{tenantId}`, TTL 24h), fusion incrémentale à chaque étape (`saveCompany`, `saveAdmin`, `saveModules`, `saveWorkflows`, `saveApps`), `complete()` finalise.
- **`AiDataImportService`** — pipeline d'import assisté par IA : `analyzeFile()` (détection structure + mapping suggéré via Claude), `validateMapping()`, `executeImport()` (dispatch de `ImportDataJob` en file d'attente), `getImportStatus()`. Registre `ENTITY_SCHEMAS` (contacts, produits, fournisseurs, employés, factures) avec champs requis/optionnels par entité cible.
- **`AiMappingService`** — appelle l'API Claude (`claude-sonnet-4-6` par défaut, configurable via `setup.ai_model`) pour suggérer des mappings colonne source → champ cible avec score de confiance ; dégrade gracieusement (tableau vide) si `ANTHROPIC_API_KEY` n'est pas configurée.
- **`FileAnalysisService`** — analyse les fichiers Excel/CSV/PDF uploadés et construit/upsert le `SourceSchema` correspondant à un `ImportJob`.
- **`DatabaseSourceService`** — connexion en lecture seule à une base ERP source externe (MySQL/PostgreSQL/MSSQL/SQLite), avec un registre `KNOWN_ERPS` pré-rempli pour les ERP francophones courants (Sage Compta/SAARI, Cegid/Quadra, EBP Compta) — pertinent pour la migration de données de PME africaines/francophones.
- **`ImportExecutorService`** — exécute l'import réel une fois les mappings confirmés : lit la source (fichier ou DB), applique les transformations par champ, insère en masse dans la table cible avec isolation par tenant.
- **`OnboardingMetricsService`** — enregistre et interroge le funnel d'onboarding (`startSession`, événements par étape, complétion, abandon) sans jamais passer par une file d'attente, pour ne jamais ralentir le wizard utilisateur.
- **`ModuleManagerService`** — **service stub explicitement documenté comme non implémenté** dans son propre commentaire de classe : `activate()`, `bulkActivate()`, `deactivate()`, `getAll()` retournent tous `['implemented' => false, 'message' => '...::<méthode> is not yet implemented.']`. `AdminModulesController` (routes `/api/v1/admin/modules*`) l'injecte et l'appelle tel quel — ces endpoints d'administration des modules répondent donc, mais sans effet réel.

## Permissions RBAC

Préfixe `setup.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `import`, `mapping`, `wizard`, actions standard. `ImportSessionPolicy` applique ces permissions (`setup.import.view-any`, `setup.import.view`, `setup.import.create`, `setup.import.update`, `setup.import.delete`) sur le modèle `ImportJob`. Les routes d'administration (`v1/admin/modules`, `v1/admin/company`) sont, elles, protégées par le middleware de rôle `role:admin,super-admin` plutôt que par des permissions Spatie nommées.

## Dépendances avec d'autres modules

Aucun autre module ne dépend de `Modules\Setup` (`grep` sur `Modules\Setup` hors du module lui-même ne renvoie rien). `Setup` reste un module d'entrée autonome : il écrit potentiellement dans les tables cibles d'autres modules lors d'un import (`ImportExecutorService`), mais sans dépendance de code PHP vers ces modules — le mapping de table cible passe par `TargetSchemas` (catalogue statique de schémas), pas par les modèles Eloquent des modules cibles.
