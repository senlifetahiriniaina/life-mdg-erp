# Setup

## Rôle

`Setup` est l'assistant d'onboarding de life-mdg-erp : c'est l'implémentation concrète du pilier « Simplicity First ». Il couvre deux parcours distincts : (1) l'**assistant de configuration** (wizard société → administrateur → modules → workflows → applications) et (2) l'**import de données assisté par IA** depuis un fichier (Excel/CSV/PDF) ou une base de données ERP externe, avec suggestion de mapping de colonnes par Claude et repli heuristique quand la clé API est absente. Ce dernier existe en réalité en **deux pipelines réels et distincts** (voir Particularités) — le flux pas-à-pas piloté par `SetupController`/`ImportExecutorService` (celui qu'utilise réellement l'assistant de configuration) et le flux en une seule page `DataImportController`/`AiDataImportService`/`ImportDataJob` (activé pour la première fois lors du Chantier 32.10, voir plus bas). Il porte aussi le suivi analytique du funnel d'onboarding (Simplicity First : mesurer si l'utilisateur termine en moins de 5 minutes).

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `ImportJob` | `setup_import_jobs` | Un job d'import : type de source (`excel`/`csv`/`pdf`/DB externe), statut, tenant. |
| `SourceSchema` | `setup_source_schemas` | Structure détectée du fichier/table source (colonnes, types) après analyse. |
| `FieldMapping` | `setup_field_mappings` | Mapping colonne source → champ cible Life MDG (avec type de transformation, suggéré par IA ou manuel). |
| `ImportError` | `setup_import_errors` | Erreur de validation/exécution par ligne, rattachée à un `ImportJob`. |
| `OnboardingSession` | `setup_onboarding_sessions` | Session utilisateur du wizard (début, fin, abandon, source). |
| `OnboardingStepEvent` | `setup_onboarding_step_events` | Événement horodaté par étape du wizard, rattaché à une session. |
| `FunnelSnapshot` | `setup_onboarding_funnel_snapshots` | Photo agrégée quotidienne du taux de complétion du funnel par tenant — **générée pour de vrai depuis le Chantier 32.10** par une vraie commande planifiée (voir Particularités), auparavant un modèle réel sans aucun producteur malgré son propre docblock affirmant le contraire. |
| `CompanyProfile` | `setup_company_profiles` | Profil société capturé par le wizard/l'admin (nom légal, TVA, devise, pays…) — porte désormais une vraie trace d'audit (`HasAuditLog`, Chantier 32.10). |

## Endpoints principaux

Toutes les routes API sont sous `auth:sanctum, session.security, tenancy.user` (`routes/api.php`) :

**Wizard d'onboarding** (`prefix: wizard`, `middleware: module:Setup, role:employee,admin,super-admin` — trou RBAC corrigé au Chantier 32.10, voir RBAC)
| Méthode | Route | Description |
|---|---|---|
| GET | `state` | État courant du wizard (mis en cache par tenant, TTL 24h) |
| POST | `company` \| `admin` \| `modules` \| `workflows` \| `apps` | Sauvegarder chaque étape |
| POST | `complete` | Finaliser l'onboarding |
| GET | `modules/catalog` | Catalogue des modules disponibles à activer |
| GET | `thresholds/{module}` (`role:admin,super-admin`) | Seuils de configuration d'un module |

**Administration** (`prefix: v1/admin`, `middleware: module:Setup, role:admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| GET/PUT/POST | `modules`, `modules/{module}`, `modules/bulk` | Activation/désactivation de modules (`AdminModulesController` → `ModuleManagerService`, réel) |
| GET/PUT/POST | `company`, `company/logo` | Profil société et logo |

**Import de données (pipeline manuel pas-à-pas — celui réellement utilisé par l'assistant de configuration)** (`middleware: module:Setup, role:employee,admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| GET/POST | `import-jobs`, `import-jobs/{id}` | Liste/création/détail d'un job d'import — chaque méthode passe par `$this->authorize()` contre `ImportSessionPolicy` |
| POST | `import-jobs/{id}/analyze` | Analyse du fichier → construit le `SourceSchema` |
| POST | `import-jobs/{id}/suggest-mappings` | Suggestions de mapping par IA |
| PUT | `import-jobs/{id}/mappings` | Enregistrer les mappings (remplacement en masse) — `target_field` désormais restreint par `Rule::in()` aux vrais champs réels de `TargetSchemas` pour l'entité du job (Chantier 32.10, voir Particularités) |
| POST | `import-jobs/{id}/validate` | Validation à blanc (champs requis mappés) |
| POST | `import-jobs/{id}/execute` | Exécuter l'import réel |
| GET | `import-jobs/{id}/errors` | Erreurs paginées d'un job |
| GET | `source-schemas` | Catalogue des cibles d'import disponibles (module + entité + champs réels — voir `TargetSchemas`) |
| POST | `test-connection` | Tester une connexion à une base externe |

**Métriques d'onboarding** (même groupe `module:Setup, role:employee,admin,super-admin`)
| Méthode | Route | Description |
|---|---|---|
| POST | `onboarding/start` | Démarrer une session |
| GET | `onboarding/stats` (+ `?days=`) | Statistiques du funnel |
| GET | `onboarding/stats/export` | Export CSV |
| POST | `onboarding/{id}/step` \| `/complete` \| `/abandon` | Événement d'étape / complétion / abandon |

**Pipeline d'import assisté par IA en une page (`DataImportController`)** (même groupe — **réellement activé pour la première fois au Chantier 32.10**, voir Particularités)
| Méthode | Route | Description |
|---|---|---|
| POST | `import/analyze` | Upload d'un fichier → suggestions de mapping IA/heuristique |
| POST | `import/validate` | Valider un mapping → rapport de qualité des données |
| POST | `import/execute` | Démarrer un job d'import asynchrone (`ImportDataJob`) |
| GET | `import/status/{jobId}` | Suivre la progression d'un job |
| GET | `import/templates` | Liste des 5 types d'entités supportés + leurs schémas de champs réels |

Enfin `POST v1/setup/ai/assist` fournit la guidance IA contextuelle standard — désormais réelle pour les 6 étapes du wizard en plus du sous-flux d'import (Chantier 32.10, voir IA).

**Routes web** (`middleware: auth`) : `GET /setup`, `GET /setup/wizard`, et — nouveau au Chantier 32.10 — `GET /setup/ai-import` (la page du pipeline en une page ci-dessus).

## Contrôleurs

- **`Api\SetupWizardController`** — état du wizard, sauvegarde par étape, catalogue de modules.
- **`Api\SetupThresholdsController`** — seuils de configuration par module (admin/super-admin).
- **`Api\AdminModulesController`** — activation/désactivation de modules, via `ModuleManagerService`.
- **`Api\AdminCompanyController`** — profil société, logo.
- **`Api\SetupController`** — CRUD `ImportJob`, analyse, mapping, validation, exécution, erreurs, catalogue de schémas cibles, testeur de connexion. Chaque méthode passe par `$this->authorize()` contre `ImportSessionPolicy`.
- **`Api\OnboardingMetricsController`** — funnel d'onboarding (sessions, étapes, stats, export CSV).
- **`Api\DataImportController`** — pipeline d'import IA en une page, adossé à `AiDataImportService`/`ImportDataJob`. **A désormais un vrai producteur frontend** (voir Vues).
- **`Api\SetupAiAssistController`** — guidance IA contextuelle.
- **`Web\SetupWebController`** — rend `Setup/SetupIndex` et `Setup/SetupWizard` avec l'état initial calculé côté serveur (`SetupWizardService::getState()`).

## Vues (Vue/Inertia)

- `Setup/SetupIndex.vue` — tableau de bord (sessions d'import, lignes importées, dernier import), bouton « Nouvel import » (ouvre `ImportDataFlow.vue`, le pipeline manuel pas-à-pas) et — nouveau — bouton « Import IA rapide » vers `/setup/ai-import`.
- `Setup/SetupWizard.vue` — les 6 étapes réelles du wizard, plus le sous-flux d'import optionnel après complétion. Porte désormais un panneau d'assistance IA réel par étape (Chantier 32.10, voir IA).
- `Setup/AiImport/Index.vue` (**nouveau, Chantier 32.10**) — page autonome à 4 phases (upload → analyse IA → revue du mapping → import en tâche de fond avec sondage de progression) consommant le pipeline `DataImportController`/`AiDataImportService`/`ImportDataJob`, jusqu'ici entièrement réel, RBAC-correct, routé, mais sans aucun appelant Vue nulle part dans l'application (confirmé par grep exhaustif) — voir Particularités pour le raisonnement « activer plutôt que supprimer ».
- `Components/ImportDataFlow.vue` — le composant réel du pipeline manuel (create-job → analyze → suggest-mappings → save-mappings → execute, sondage asynchrone), monté depuis `SetupIndex.vue`/`SetupWizard.vue`.

**Cas non résolu, laissé en gap plutôt que deviné** : `resources/js/Pages/Import/Index.vue` (racine, routé à `/import`, distinct du wizard Setup) appelle un quatrième schéma d'API entièrement fictif (`/api/v1/import/upload`, `/api/v1/import/jobs*` — sans préfixe `setup`, sans contrôleur correspondant nulle part) ; lequel des **trois** vrais backends désormais existants (le flux `import-jobs` du wizard, le pipeline `DataImportController`, ou un quatrième à construire) devrait l'alimenter reste une décision produit non tranchée.

## Services

- **`SetupWizardService`** — état du wizard tenu en cache (clé `setup:wizard:{tenantId}`, TTL 24h), fusion incrémentale à chaque étape (`saveCompany`, `saveAdmin`, `saveModules`, `saveWorkflows`, `saveApps`), `complete()` finalise.
- **`AiDataImportService`** — pipeline d'import assisté par IA : `analyzeFile()` (détection structure + mapping suggéré via Claude), `validateMapping()`, `executeImport()` (dispatch de `ImportDataJob` en file d'attente), `getImportStatus()`. Registre `ENTITY_SCHEMAS` — **5 entités** (contacts, produits, fournisseurs, employés, factures ; `stock` retiré au Chantier 32.10, voir Particularités) avec champs requis/optionnels **désormais alignés sur les vraies colonnes des tables réelles** (voir Particularités).
- **`AiMappingService`** — appelle l'API Claude (`claude-sonnet-4-6` par défaut, configurable via `setup.ai_model`) pour suggérer des mappings colonne source → champ cible avec score de confiance ; dégrade gracieusement (tableau vide, pas de repli heuristique — voir Particularités) si `ANTHROPIC_API_KEY` n'est pas configurée.
- **`FileAnalysisService`** — analyse les fichiers Excel/CSV/PDF uploadés et construit/upsert le `SourceSchema` correspondant à un `ImportJob`.
- **`DatabaseSourceService`** — connexion en lecture seule à une base ERP source externe (MySQL/PostgreSQL/MSSQL/SQLite), avec un registre `KNOWN_ERPS` pré-rempli pour les ERP francophones courants (Sage Compta/SAARI, Cegid/Quadra, EBP Compta).
- **`ImportExecutorService`** — exécute l'import réel une fois les mappings confirmés : lit la source (fichier ou DB), applique les transformations par champ, insère en masse dans la table cible avec isolation par tenant. **Réécrit en profondeur au Chantier 32.10** — voir Particularités pour les 3 bugs réels trouvés (table cible arbitraire, colonne de tenant incorrecte, `TypeError` garanti à chaque exécution réelle).
- **`OnboardingMetricsService`** — enregistre et interroge le funnel d'onboarding sans jamais passer par une file d'attente, pour ne jamais ralentir le wizard utilisateur. `generateDailySnapshot()` a désormais un vrai producteur (voir Particularités).
- **`ModuleManagerService`** — construit sur le propre `FileActivator` de `nwidart/laravel-modules` (`config('modules.activator')`) plutôt que de réimplémenter à la main l'I/O JSON. Ajoute la validation d'un nom de module client contre la vraie liste de modules, le blocage d'une désactivation qui casserait le `requires` déclaré d'un autre module actif, et une piste d'audit.
- **`Data\TargetSchemas`** — registre statique module+entité → définition de champs pour le pipeline manuel. **Réécrit au Chantier 32.10** pour porter aussi le vrai nom de table de destination et la vraie colonne de tenant (`TargetSchemas::table()`/`tenantColumn()`), la seule source de vérité désormais consultée par `ImportExecutorService::resolveTargetTable()` — voir Particularités.

## Permissions RBAC

Préfixe `setup.*` dans `RolesAndPermissionsSeeder::MODULES` — ressources `import`, `mapping`, `wizard`, actions standard. `ImportSessionPolicy` applique ces permissions (`setup.import.view-any`, `setup.import.view`, `setup.import.create`, `setup.import.update`, `setup.import.delete`) sur le modèle `ImportJob`, réellement appelée par `SetupController`. Les routes d'administration (`v1/admin/modules`, `v1/admin/company`) restent protégées par `role:admin,super-admin` (+ `module:Setup`, ajouté au Chantier 32.10).

**Trou RBAC corrigé au Chantier 32.10** : le groupe `wizard/*` (les 6 étapes du wizard + le catalogue de modules) n'avait **aucune** gating `module:`/`role:` au-delà de l'authentification de base — n'importe quel utilisateur authentifié de n'importe quel rôle pouvait reconfigurer le profil société, relancer la sélection de modules, et marquer l'onboarding « terminé ». Corrigé avec le même `module:Setup, role:employee,admin,super-admin` déjà utilisé par le reste du module.

**Vulnérabilité cross-tenant historique** (résolue en plusieurs temps sur plusieurs chantiers, voir aussi Particularités pour un dernier site manqué trouvé au Chantier 32.10) : le helper `tenantId()` partagé de `SetupController`/`OnboardingMetricsController` résolvait à l'origine sur un en-tête client `X-Company-ID`, puis (Chantier 8.5sv) sur la colonne fantôme `users.tenant_id` (jamais alimentée par un vrai chemin d'inscription), collabant toutes les entreprises dans un panier partagé `tenant_id=0`. Corrigé au Chantier 10 vers `$request->user()?->company_id ?? 0`. **`SetupWebController`** (le contrôleur derrière les deux pages web réellement servies) avait été manqué par cette correction — voir Particularités.

## Dépendances avec d'autres modules

Aucun autre module ne dépend de `Modules\Setup` (`grep` sur `Modules\Setup` hors du module lui-même ne renvoie rien). `Setup` reste un module d'entrée autonome : il écrit potentiellement dans les tables cibles d'autres modules lors d'un import (`ImportExecutorService`/`ImportDataJob`), mais sans dépendance de code PHP vers ces modules — le mapping de table cible passe par des catalogues statiques (`TargetSchemas`, `ENTITY_SCHEMAS`), pas par les modèles Eloquent des modules cibles.

## Chantier 32.10 — audit approfondi en 14 couches

Voir la section « Méthodologie d'audit approfondi (14 couches) » et l'entrée « Chantier 32.10 » de `CLAUDE.md` pour le détail complet, la liste exacte des fichiers touchés, et les comptages de tests avant/après. Résumé des trouvailles :

- **Sécurité critique — écriture arbitraire dans n'importe quelle table de la base** : `ImportExecutorService::resolveTargetTable()` faisait confiance à `FieldMapping.target_table`, une valeur entièrement contrôlée par le client et validée uniquement `required|string|max:100`, zéro liste blanche — un utilisateur authentifié du module Setup (rôle `employee` inclus) pouvait viser `users`, `core_secrets`, ou toute autre vraie table, avec `target_field` tout aussi libre. Corrigé : la résolution ne consulte plus jamais que `TargetSchemas::table()`, une liste blanche codée en dur ; `target_field` est désormais contraint par `Rule::in()` aux vrais champs réels de l'entité du job.
- **Le vrai pipeline d'import piloté par l'assistant (`ImportDataFlow.vue` → `SetupController`/`ImportExecutorService`) n'avait jamais fonctionné, pour aucune entité, depuis sa construction** : `resolveTargetTable()`'s repli (`strtolower(module)_strtolower(entity)`) produisait des noms de table qui n'ont jamais existé (`accounting_invoices` au lieu de `acc_invoices`, `accounting_accounts` au lieu de `acc_chart_of_accounts`) et, de toute façon, le vrai frontend n'a jamais envoyé qu'un slug d'entité (`'contacts'`) comme `target_table`, jamais un vrai nom de table. Confirmé empiriquement que 5 des 6 entités échouaient systématiquement à l'insertion et que la 6ᵉ (`products`) écrivait par coïncidence dans la mauvaise table (`products`, un vestige générique de racine, pas `inventory_products`). `TargetSchemas` a été entièrement réécrit avec les vrais noms de colonnes de chaque vraie table cible (`crm_contacts`, `crm_accounts`, `hr_employees`, `inventory_products`, `achats_suppliers`, `acc_chart_of_accounts`, `acc_invoices`).
- **Un deuxième bug indépendant, trouvé en corrigeant le premier — la mauvaise colonne de tenant était écrite** : même une fois la bonne table résolue, `insertBatch()` écrivait toujours `tenant_id` — mais `ContactController`/`SupplierController` (les vrais contrôleurs CRM/Achats) filtrent réellement par `company_id`, pas `tenant_id`. Chaque contact/fournisseur importé restait donc invisible en pratique, `company_id` toujours `NULL`. Corrigé : `TargetSchemas::tenantColumn()` porte désormais la vraie colonne de délimitation par table (confirmée en lisant chaque contrôleur réel, pas supposée), et `insertBatch()` l'utilise.
- **Un troisième bug, un vrai `TypeError` garanti sur chaque exécution réelle, trouvé uniquement par exécution (pas par lecture)** : `insertBatch(..., int $tenantId, ...)` était appelé avec `$job->tenant_id`, une vraie colonne `string(36)` — sous `declare(strict_types=1)`, ceci levait une exception fatale à chaque appel réel de `execute()` (jamais détecté avant car aucun test n'exerçait ce chemin via une vraie tâche dispatchée avec un vrai modèle `ImportJob`). Corrigé (`int|string $tenantId`).
- **`AiDataImportService::ENTITY_SCHEMAS`/`ImportDataJob` (le second pipeline) — l'écart déjà documenté comme non résolu était plus large que ce que la note précédente disait** : confirmé par exécution réelle (import réel de produits/factures, lignes DB inspectées) que 4 des 6 entités avaient des noms de champs cibles qui ne correspondaient à aucune vraie colonne — `invoices` perdait silencieusement `date`/`client_name`/`amount` (3 des 4 champs « obligatoires » !), `products` perdait `price`/`cost`. Corrigé avec les vrais noms de colonnes (`invoice_date`/`partner_name`/`total`, `selling_price`/`cost_price`, `job_title` pour `employees`). `stock` était **100 % cassé** (violation `NOT NULL` garantie sur chaque ligne, aucune colonne réelle pour `product_sku`/`warehouse`/`unit_cost`) et a été **retiré**, plutôt que réparé — le Chantier 16 a déjà construit une fonctionnalité réelle et correcte pour ce besoin exact (`Modules\Inventory\Services\StockImportService`/`Stock/Import.vue`), et dupliquer cette logique ici aurait reproduit le motif de sous-système parallèle mort déjà éliminé plusieurs fois cette session. `contacts` a aussi perdu 3 champs cibles offerts (`company`/`country`/`address`) sans jamais avoir de colonne réelle correspondante sur `crm_contacts` — retirés plutôt qu'offerts puis silencieusement ignorés. `sku` (produits) est passé d'optionnel à obligatoire dans les deux registres (`TargetSchemas` et `ENTITY_SCHEMAS`) : `inventory_products.sku` est `NOT NULL` sans défaut, confirmé par `Schema::getColumnListing()`.
- **Un deuxième site indépendant portant le bug de colonne de tenant fantôme, manqué par toutes les corrections précédentes de ce module** : `SetupWebController::index()`/`wizard()` lisaient encore `$request->user()->tenant_id ?? 'default'` — chaque société visitant `/setup` ou `/setup/wizard` recevait, dans la prop Inertia initiale côté serveur, l'état d'onboarding partagé de la société `'default'` plutôt que le sien propre, alors même que les endpoints API du même module avaient déjà été corrigés vers `company_id`. Corrigé, verrouillé par un vrai test de régression cross-tenant.
- **CSRF — le vrai flux d'import de l'application ne fonctionnait jamais en usage réel (navigateur), invisible à toute la suite Pest** : `ImportDataFlow.vue` (les 5 appels mutants) et `SetupWizard.vue` (les 3 siens — bascule de module, chaque étape du wizard, complétion) utilisaient tous `fetch()` brut sans aucun en-tête CSRF — cette application tourne avec `statefulApi()` de Sanctum, qui active une vraie vérification CSRF sur chaque requête de navigateur de même origine ; `axios` l'attache automatiquement, `fetch()` non. Invisible à tout test Pest (`VerifyCsrfToken::runningUnitTests()` contourne systématiquement la vérification quand `APP_ENV=testing`) — exactement le même motif de bug déjà trouvé et corrigé pour 9 pages Inventory/Logistics au Chantier 19. Corrigé sur les 8 appels concernés.
- **Layer 9 (fake/dead), deux trouvailles, toutes deux classées « activer »** : (1) le pipeline `DataImportController`/`AiDataImportService`/`ImportDataJob` — entièrement réel, RBAC-correct, routé, corrigé des bugs ci-dessus — avait zéro appelant Vue nulle part dans le dépôt (confirmé par grep exhaustif) ; activé via la nouvelle page `Setup/AiImport/Index.vue` + route web + lien de découvrabilité depuis `SetupIndex.vue`. (2) `OnboardingMetricsService::generateDailySnapshot()` — dont le propre docblock affirme « Called by a scheduled command once per day » — n'avait jamais eu une telle commande nulle part dans l'application ; activé via `setup:generate-funnel-snapshots` (nouvelle commande, planifiée quotidiennement à 01:00 via le même mécanisme `callAfterResolving(Schedule::class, ...)` déjà éprouvé pour Analytics/Helpdesk/Sales), un vrai bug de type (`Carbon\Carbon` vs `Illuminate\Support\Carbon`) trouvé et corrigé en l'exécutant réellement pour la première fois.
- **IA (layer 13)** : le wizard lui-même n'avait aucune intégration `AiContextualAssistantService` (seul le sous-flux d'import en avait une) — même motif déjà trouvé et corrigé pour Strategy au Chantier 30. 6 nouvelles actions (`wizard_company`/`wizard_admin`/`wizard_modules`/`wizard_workflows`/`wizard_apps`/`wizard_complete`), texte de repli réel fr+en pour chacune, câblées sur `SetupWizard.vue` via un panneau réactif au changement d'étape (le composable partagé `useAiAssistant()` ne réagit pas à une action changeante, donc câblé localement plutôt que d'élargir la portée de ce fichier partagé). `AiMappingService::suggestMappings()` reste sans repli heuristique quand la clé API est absente (contrairement à `AiDataImportService`) — **documenté, non corrigé** : un test préexistant (`AITest.php`) affirme explicitement ce comportement vide comme la spécification actuelle voulue, et construire un second registre de mots-clés heuristiques dupliquerait un troisième registre de champs — un choix produit plutôt qu'un bug sans ambiguïté.
- **Performance (layer 14f)** : un import réel de 500 lignes de produits via `ImportDataJob` (le pipeline le plus exposé, chunké par lots de 100) s'exécute en 0,138 s pour 15 requêtes SQL au total — confirmé qu'aucun N+1 n'existe, les lots sont correctement groupés.
- **Non touché, hors périmètre de ce module, signalé pour un futur chantier** : le motif « appel `insertBatch()` avec un type incompatible sous strict_types, jamais réellement exécuté avant » pourrait exister ailleurs dans l'app partout où un job asynchrone typé `int` reçoit en réalité une colonne `string` — une passe grep-et-corrige dédiée serait utile, hors périmètre d'un seul module.
