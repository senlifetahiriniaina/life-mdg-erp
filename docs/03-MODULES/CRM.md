# CRM

## Rôle

Le module CRM couvre la gestion commerciale de Life MDG ERP : contacts, comptes, leads, opportunités, pipeline de vente, devis (quotes), territoires, prévisions de vente, campagnes marketing internes, scoring de leads/opportunités par IA, téléphonie/VoIP, agents IA configurables, et détection de doublons. C'est le module « Commercial » associé à Sales dans le périmètre life-mdg-erp.

**Chantier 32.15** a fait passer ce module par l'audit approfondi en 14 couches (voir `CLAUDE.md` § « Méthodologie d'audit approfondi ») — au-delà des 7 couches historiques déjà couvertes par les Chantiers 8.2/10/19. Cette passe a supprimé un sous-système mort supplémentaire (le mini-moteur de workflow propre au module, jusque-là documenté ici comme réel), fermé 7 fuites cross-tenant supplémentaires non détectées par les audits précédents, corrigé un IDOR sur `Activity.subject_type`, corrigé un bug de perte de données silencieuse sur les leads issus de formulaires web publics, corrigé un bug d'envoi d'e-mail jamais fonctionnel depuis la construction du service, et câblé l'assistance IA contextuelle (`useAiAssistant()`) sur les 6 écrans réels qui ne l'appelaient jamais. Voir l'entrée `CLAUDE.md` « Chantier 32.15 — CRM » pour le détail complet, couche par couche.

## Modèles clés

| Modèle | Rôle |
|---|---|
| `Contact` | Personne physique liée à un compte — scopée par `company_id` (le trait `BelongsToTenant`/la relation `company()` ont été retirés au Chantier 32.15 : `crm_contacts.company_id` est le vrai numéro de société tenant, pas une FK vers un modèle `Company` de ce module) |
| `Account` | Entreprise/organisation cliente ou prospect |
| `Lead` / `LeadStatusLog` | Prospect entrant et historique de ses changements de statut — `phone`/`company`/`email` sont désormais réellement mass-assignables (Chantier 32.15, ces 3 colonnes réelles étaient silencieusement abandonnées à l'écriture) |
| `Opportunity` / `OpportunityHistory` / `OpportunityScore` | Opportunité commerciale, son historique et son score IA |
| `Pipeline` / `PipelineSnapshot` | Étapes du pipeline de vente et instantanés pour analytics |
| `Quote` / `QuoteLine` | Devis et leurs lignes |
| `Campaign` / `CampaignStage` / `CampaignEnrollment` / `CampaignAction` / `CampaignAnalytic` | Campagnes marketing internes (indépendantes du module Email, exclu du périmètre) |
| `Territory` / `TerritoryAssignment` | Gestion de territoires commerciaux et quotas (via les méthodes natives de `Territory`) |
| `Forecast` / `ForecastModel` / `ForecastInput` | Prévisions de vente |
| `EmailSequence` / `SequenceStep` / `EmailSequenceEnrollment` | Séquences d'e-mails automatisées (basées sur Laravel `Mail`, pas sur le module Email) — `crm_email_sequence_steps`/le modèle `EmailSequenceStep` (doublon mort de `SequenceStep`) ont été supprimés au Chantier 9 |
| `WebForm` / `WebFormSubmission` | Formulaires web publics de capture de leads |
| `ScoringRule` / `EngagementSignal` | Règles et signaux utilisés par le scoring d'opportunités |
| `AiAgent` / `AiAgentRun` | Agents IA configurables et leurs exécutions |
| `RevenueAnomaly` / `RevenueInsight` / `RevenueTrend` | Intelligence de revenu (anomalies, tendances) — scopées par `company_id` depuis le Chantier 10 |
| `WinLossRecord` / `PipelineSnapshot` | Analyse gagné/perdu et instantanés de pipeline |
| `CallLog` / `CallRecording` | Journal d'appels VoIP et enregistrements associés |

**Supprimés au Chantier 32.15 (couche 9, mort/factice confirmé, zéro appelant réel)** : `Workflow` / `WorkflowNode` / `WorkflowEdge` / `WorkflowExecution` (mini-moteur de workflow propre au module, table `crm_workflows` et consœurs — un doublon incomplet du vrai `Modules\Workflow`, sans jamais avoir eu de front-end réel ni de véritable moteur d'exécution) ; `Customer` (doublon de `App\Models\Customer`, jamais utilisé par un contrôleur CRM réel) ; `Company` (doublon du vrai `App\Models\Company`, source de confusion avec le vrai concept de tenant). Les tables `crm_workflows`/`crm_workflow_nodes`/`crm_workflow_edges`/`crm_workflow_executions`/`crm_customers`/`crm_companies` ont été droppées par une migration dédiée.

## Endpoints principaux

Routes API sous `auth:sanctum`, préfixées `v1/crm` (`Modules/CRM/routes/api.php`). Extraits représentatifs :

| Méthode | Route | Description |
|---|---|---|
| POST | `v1/crm/forms/{slug}/submit` | Soumission publique d'un formulaire web (sans auth) — le lead créé hérite désormais du `tenant_id` du formulaire (Chantier 32.15, bug de perte de tenant corrigé) |
| GET/POST/PUT/DELETE | `crm/contacts` (apiResource) | CRUD contacts (lecture ouverte, écriture restreinte par rôle) |
| GET | `crm/contacts/{contact}/duplicates` | Détection de doublons IA pour un contact (Chantier 32.15, endpoint réel jusque-là jamais câblé) |
| POST | `crm/contacts/email/bulk` | Envoi en masse — désormais strictement filtré aux contacts de la société de l'appelant (Chantier 32.15) |
| GET/POST/PUT/DELETE | `crm/accounts` (apiResource) | CRUD comptes |
| GET/POST/PUT/DELETE | `crm/leads` (apiResource) | CRUD leads |
| GET | `crm/opportunities/kanban` \| `/pipeline` | Vue Kanban et pipeline des opportunités |
| PUT | `crm/opportunities/{opportunity}` | Modification d'opportunité — la transition d'étape (`stage`) est désormais validée côté serveur contre les étapes réelles du pipeline associé (Chantier 32.15) |
| GET | `crm/opportunities/{opportunity}/history` | Historique d'une opportunité — désormais scopé par société (Chantier 32.15, aucun `authorize()` avant) |
| POST | `crm/contacts/{contact}/email/welcome` \| `/custom` | Envoi d'e-mails de bienvenue/personnalisés (`ContactEmailController`) — l'envoi réel était cassé depuis la construction du service (aucun destinataire jamais défini sur le `Mailable`), corrigé au Chantier 32.15 |
| GET/POST/PUT/DELETE | `crm/email-sequences[/{sequence}]`, `/steps`, `/enroll` | Séquences d'e-mail automatisées — désormais scopées par société (Chantier 32.15) |
| GET/POST/PUT/DELETE | `crm/forms[/{form}]` | Gestion des formulaires web — désormais scopée par société (Chantier 32.15) |
| GET/POST/PUT/DELETE | `crm/quotes[/{quote}]`, `/duplicate`, `/pdf` | Devis CPQ — désormais scopés par société ; le PDF affiche Ar, plus € (Chantier 32.15) |
| POST | `score-leads`, `suggest-next-action`, `draft-follow-up`, `detect-duplicates`, `generate-prospecting-email`, `analyze-sentiment`, `transcribe-call` | Fonctions IA (`CrmAIController`, `CrmProspectingController`) |
| GET/POST | `crm/territories/*`, `crm/territory-management/*` | Territoires, quotas, équilibrage automatique — désormais scopés par société, y compris l'assignation d'opportunité (Chantier 32.15) |
| GET/POST | `crm/einstein-forecasting/*` | Prévisions de vente par représentant/produit |
| GET/POST | `crm/opportunity-scores`, `crm/scoring-rules` | Scoring d'opportunités et règles associées |
| GET/POST | `crm/voip/call-logs[/{callLog}]` | Journal d'appels VoIP — désormais scopé par société (Chantier 32.15) |
| GET/POST | `crm/ai-agents[/{agent}]`, `/{agent}/run` | Agents IA configurables — désormais scopés par société (Chantier 32.15) |
| POST | `crm/activities` | Création d'activité — `subject_type` validé contre une allowlist morph-map réelle (`contact`/`account`/`lead`/`opportunity`) plutôt qu'une chaîne de classe arbitraire, et `subject_id` vérifié appartenir à la société de l'appelant (Chantier 32.15, IDOR fermé) |
| POST | `v1/ai/assist` (module `AI`) | Guidance IA contextuelle (AI Assisted First) — 9 actions CRM réelles désormais enregistrées, voir § IA ci-dessous |

## Contrôleurs

23 contrôleurs Api (`Modules/CRM/app/Http/Controllers/Api/`) et 5 contrôleurs Web (`Http/Controllers/Web/`).

Contrôleurs Api : `ContactController`, `AccountController`, `LeadController`, `OpportunityController`/`OpportunityHistoryController`/`OpportunityScoringController`, `PipelineController`/`PipelineAnalyticsController`, `QuoteController`, `TerritoryController`, `ForecastController`/`EinsteinForecastingController`, `CampaignController`, `ActivityController`, `EmailSequenceController`, `ContactEmailController`, `WebFormController`, `RevenueIntelligenceController`, `VoipController`, `CallRecordingController`, `AiAgentController`, `CrmAIController`/`CrmProspectingController`/`CRMAiAssistController`.

**`WorkflowBuilderController` supprimé au Chantier 32.15** (couche 9) — la route `crm/workflows` n'existe plus (404 confirmé par test). C'est le seul contrôleur retiré de la liste depuis le Chantier 8.2 (qui l'avait alors corrigé, pas supprimé — le sous-système entier s'est avéré mort à l'audit approfondi, sans producteur réel ni front-end fonctionnel).

**RBAC fixé (Chantier 8.2)** : `CampaignController` vivait directement sous `Http/Controllers/` (pas `Api/`) et n'appelait jamais `authorize()` malgré une policy (`CampaignPolicy`) déjà écrite. Corrigé en déplaçant le contrôleur dans `Http/Controllers/Api/`, en ajoutant `extends Controller` + les appels `authorize()` manquants, et en seedant les permissions `crm.campaigns.*` (bloc `CRM_EXTRA_PERMISSIONS`, verbe non-standard `.edit`).

**Sous-système mort supprimé (Chantier 8.2)** : `TerritoryManagementController`/`TerritoryManagementService`/`TerritoryQuota`/`TerritoryAlert` formaient un sous-système parallèle entièrement cassé, redondant avec le vrai `TerritoryController`/`TerritoryService`/`TerritoryForecastService` déjà routé et testé — supprimé intégralement plutôt que réparé. Le seul concept réellement nouveau de l'ancien sous-système, une analyse d'écart de couverture, a été ajouté comme méthode `coverage()` sur le vrai `TerritoryController`/`TerritoryService` (`GET crm/territory-management/coverage`).

**Cloisonnement multi-tenant, couche 6/7 (Chantier 32.15)** : 7 contrôleurs n'avaient **aucune** vérification de société sur au moins une partie de leurs actions, malgré 3 passes d'audit précédentes (Chantier 8.2, 10, 19) — `VoipController`/`CallLogPolicy` (nouvelle policy), `EmailSequenceController`/`EmailSequencePolicy` (policy existante, jamais enregistrée sur le Gate ni appelée), `QuoteController`/`QuotePolicy` (nouvelle), `TerritoryController`/`TerritoryPolicy` (nouvelle, y compris `assignOpportunity()`/`autoAssign()`/`teamQuotas`/`coverage`/`rebalance`/`territoryForecast`), `WebFormController`/`WebFormPolicy` (nouvelle), `PipelineAnalyticsController`/`PipelineAnalyticsService` (aucune classe de policy — chaque méthode du service prend désormais un `?int $companyId` et filtre sans condition sur `tenant_id`), `AiAgentController`/`AiAgentPolicy` (nouvelle). Toutes les nouvelles policies sont enregistrées dans `CRMServiceProvider::boot()` (les policies namespacées `Modules\*` ne s'auto-découvrent jamais).

**IDOR fermé sur `ActivityController`** : `subject_type` acceptait n'importe quelle chaîne de classe PHP arbitraire (ex. `App\Models\User`) sans validation, et `subject_id` n'était jamais vérifié appartenir à la société de l'appelant. Corrigé avec un vrai morph-map allowlist (`Relation::morphMap(['contact'=>Contact::class,'account'=>Account::class,'lead'=>Lead::class,'opportunity'=>Opportunity::class])`, enregistré dans `CRMServiceProvider::registerActivitySubjectMorphMap()`, même patron que le morph-map de `HelpdeskServiceProvider`) et une vérification explicite d'appartenance.

**Validation métier serveur, couche 8** : `OpportunityController::store()`/`update()` acceptaient auparavant n'importe quelle chaîne comme `stage`, sans jamais vérifier qu'elle correspond réellement à une étape définie sur le pipeline de l'opportunité — une transition Kanban invalide côté client passait silencieusement. Corrigé avec `assertValidStage()`, qui rejette (422) toute étape absente du tableau `stages` réel du pipeline.

## Vues (Vue/Inertia)

- **Racine** (`resources/js/Pages/CRM/`) : `Contacts/`, `Leads/`, `Accounts/`, `Territories/`, `Quotes/`, `Forecast/`, `Opportunities/`, `EmailSequences/`, `CallLogs/` — pages réelles servies par les contrôleurs Web.
- **Module** (`Modules/CRM/resources/js/Pages/`) : `Dashboard/`, `Opportunities/` (Kanban), `Duplicates/`, `Accounts/`.

5 pages dupliquées côté module (masquées par les copies racine plus complètes) ont été supprimées lors du Chantier 8.2. 4 pages réelles mais jusque-là sans route (`Quotes/Index`+`Show`, `Territories/Index`, `Forecast/Index`, `Opportunities/Scoring`) ont reçu de nouveaux contrôleurs Web légers (`QuoteWebController`, `TerritoryWebController`, `ForecastWebController`, `OpportunityScoringWebController`) et sont désormais routées sous `/crm/quotes`, `/crm/territories`, `/crm/forecast`, `/crm/opportunities/scoring`.

**CSRF (Chantier 32.15)** : 8 pages (`Contacts/Form.vue`, `Contacts/Create.vue`, `Contacts/Index.vue`, `Accounts/Index.vue`, `Leads/Index.vue`, `EmailSequences/Index.vue`, `Modules/CRM/.../Opportunities/Kanban.vue`, `Modules/CRM/.../Opportunities/CreateForm.vue`) utilisaient `fetch()` brut sans en-tête `X-CSRF-TOKEN` sur leurs requêtes mutantes — même motif de bug déjà documenté ailleurs dans `CLAUDE.md` (Chantier 19, Achats/Inventory/Logistics), invisible à tout test Pest puisque `VerifyCsrfToken::runningUnitTests()` désactive systématiquement la vérification en environnement de test. Toutes corrigées avec le même patron `getCsrf()` déjà établi.

**Assistance IA contextuelle câblée (Chantier 32.15, couche 13)** : `Contacts/Index.vue`, `Leads/Index.vue`, `Modules/CRM/.../Opportunities/Kanban.vue`, `Quotes/Index.vue`, `Territories/Index.vue`, `Forecast/Index.vue` — 6 écrans réels et routés qui n'appelaient jamais `useAiAssistant()` — appellent désormais l'assistant contextuel via le composable standard + `<AIAssistantPanel>`. Les écrans `EmailSequences/Index.vue`, `CallLogs/Index.vue`, `Opportunities/Scoring.vue`, et les pages de campagnes (aucune page Vue de campagne réelle n'existe — voir « Gaps documentés » ci-dessous) restent un gap résiduel documenté, pas câblés dans ce chantier.

## Services

- **`ContactEmailService`** — envoi d'e-mails de notification aux contacts via `Illuminate\Support\Facades\Mail` avec des templates **statiques en dur** (constante `TEMPLATES`). **Bug corrigé au Chantier 32.15** : `sendWelcomeEmail()`/`sendNotificationEmail()` appelaient `Mail::send($mailable)` sans jamais définir de destinataire sur le `Mailable` — une exception garantie à chaque appel réel, systématiquement avalée par le `catch` englobant (`report($e); return false;`). Ce service n'a donc **jamais réellement envoyé un seul e-mail** depuis sa construction, pour aucun de ses 3 points d'entrée. Corrigé en `Mail::to($contact->email)->send(...)`.
- **`EmailSequenceService`** — orchestration des séquences d'e-mails automatisées (`ProcessEmailSequenceJob` en file d'attente).
- **`LeadScoringService`** / **`OpportunityScoringService`** — scoring par règles pondérées.
- **`LeadStatusService`** — transitions de statut des leads avec journalisation (`LeadStatusLog`).
- **`PipelineService`** / **`PipelineAnalyticsService`** — gestion et analytics du pipeline de vente. `PipelineAnalyticsService` scopée par société pour toutes ses méthodes depuis le Chantier 32.15 (`recordWin`/`recordLoss`/`recordOutcome`/`getWinRate`/`getConversionFunnel`/`getSalesVelocity`/`getStageDistribution`/`takeSnapshot`/`getPipelineTrend`/`getTopPerformers`/`getWinLossReasons`/`getAvgSalesCycle`/`getDashboard`).
- **`ForecastService`** / **`CRMForecastingService`** / **`EinsteinForecastingService`** / **`TerritoryForecastService`** — prévisions de vente à différents niveaux de granularité.
- **`TerritoryService`** / **`TerritoryForecastService`** — gestion, auto-équilibrage, attribution et prévision des territoires (`Territory::ytdRevenue()`/`quotaAttainment()`/`quotaForecast()`). Depuis le Chantier 32.15, `autoAssign()`/`getTeamQuotas()`/`rebalance()`/`coverage()`/`territoryForecast()` prennent tous un `?int $companyId` filtré sans condition (au lieu d'un `when()` conditionnel qui laissait fuiter toutes les sociétés quand l'appelant n'avait pas de `company_id`) ; `rebalance()`'s corrigé pour ne plus lire les assignations de **toutes** les sociétés.
- **`CpqService`** — configuration/pricing/devis (Configure-Price-Quote). `duplicate()` propage désormais le `tenant_id` de l'original ; `generatePdf()` affiche Ar au lieu de € (Chantier 32.15).
- **`ActivityTimelineService`** — historique d'activité consolidé par contact/compte.
- **`AiAgentService`** — exécution des agents IA configurables. `runAgent()`'s actions `add_note`/`create_task` peuplent désormais `company_id` sur l'activité créée ; `runScheduledAgents()` prend un `?int $companyId` filtré sans condition (Chantier 32.15).
- **`SequenceService`** — séquences génériques (distinctes des `EmailSequence`).
- **`VoipService`** — intégration téléphonie/enregistrement d'appels (`CallLog`, `CallRecording`). `initiateCall()` propage désormais `tenant_id` vers le `CallLog` créé ; `startRecording()` lisait un champ `company_id` inexistant sur `CallLog` (le vrai est `tenant_id`) — corrigé (Chantier 32.15).
- **`AI\DuplicateDetectionService`** — détection de doublons par embeddings IA. `detectDuplicates()` comparait auparavant `tenant_id` d'un contact contre `auth()->user()->tenant_id` (colonne fantôme jamais peuplée) — corrigé pour comparer `company_id` du contact appelant contre celui des candidats. Désormais câblée sur un vrai point d'entrée (`GET crm/contacts/{contact}/duplicates`, Chantier 32.15) — jusque-là orpheline, zéro consommateur réel malgré le service déjà écrit et testé.

**Supprimés au Chantier 8.2** : `CustomerManagementService`, `SalesOpportunityService` (doublons/logique morte). **Supprimé au Chantier 19 follow-up** : `CampaignOrchestrationService` (zéro appelant, cassé contre le vrai schéma — le vrai `CampaignController`/`CampaignPolicy` couvre déjà ce besoin).

## Permissions RBAC

CRM a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'crm' => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline']`, générant les permissions `crm.contact.*`, `crm.lead.*`, `crm.opportunity.*`, `crm.account.*`, `crm.activity.*`, `crm.pipeline.*` (5 actions × 6 ressources). Un bloc `CRM_EXTRA_PERMISSIONS` (Chantier 8.2) ajoute les 8 permissions `crm.campaigns.{view,create,edit,delete}`/`crm.workflows.{view,create,edit,delete}` — ce dernier groupe (`crm.workflows.*`) est désormais orphelin depuis la suppression du mini-moteur de workflow au Chantier 32.15 (laissé en l'état, une permission inutilisée n'est pas un risque de sécurité, seulement un résidu mineur).

Le rôle `sales-rep` reçoit l'intégralité des permissions `crm.*`. Le rôle `sales-manager` reçoit `crm.*` + `sales.*` + accès en lecture à `bi.*`/`accounting.invoice.*`. Le rôle `customer-service` reçoit un accès CRM restreint (`crm.contact.view*`, `crm.account.view*`, `crm.activity.view*`/`create`) en complément de `helpdesk.*`. Au niveau route, `Modules/CRM/routes/api.php` impose `module:CRM` sur le groupe `v1` (vérifié dans le code).

## IA (AI Assisted First)

`AiContextualAssistantService::supportedModules()` listait auparavant seulement 3 actions CRM (`create_contact`, `view_dashboard`, `create_opportunity`) alors que le module compte ~13 écrans Vue réels — confirmé par grep exhaustif que **zéro** page CRM n'appelait `useAiAssistant()` avant le Chantier 32.15 (le même motif déjà trouvé et corrigé pour Strategy au Chantier 30, Validation au Chantier 32.7, Sales au Chantier 32.16). Corrigé : 6 nouvelles actions enregistrées avec un vrai texte de repli fr+en (`view_contacts_list`, `manage_leads`, `manage_opportunities_kanban`, `manage_quotes`, `manage_territories`, `view_sales_forecast`), 9 au total désormais, et les 6 écrans correspondants câblés pour de vrai (voir § Vues ci-dessus). `EmailSequences`/`CallLogs`/`Opportunities/Scoring`/campagnes restent un gap résiduel documenté.

Séparément, `AiDataImportService::ENTITY_SCHEMAS['contacts']` (module `Setup`, pipeline d'import en masse) cible `full_name`/`email`/`phone` — spot-vérifié au Chantier 32.15 contre le vrai schéma `crm_contacts` (`Schema::getColumnListing()`) : `first_name`/`last_name` (via `splitFullName()`, corrigé au Chantier 12), `email`, `phone` sont bien des colonnes réelles — le mapping tient toujours correctement.

## Performance (couche 14f, Chantier 32.15)

`OpportunityController::index()` et `::kanban()` ont été profilés empiriquement avec 60 opportunités réelles (comptes/contacts/propriétaires distincts) : 5 requêtes SQL constantes dans les deux cas, indépendamment du volume — confirmant l'eager-loading déjà en place (`with('account','contact','owner','pipeline','territory')`/`with('account','contact','owner','territory')`) empêche bien tout N+1, y compris à travers la sérialisation JSON brute des modèles (ni `index()` ni `kanban()` ne passent par `OpportunityResource`, qui elle-même n'accède qu'à des attributs simples des relations déjà chargées).

## Dépendances avec d'autres modules

CRM importe (`use Modules\...`) : `Modules\AI` (guidance/scoring IA), `Modules\Core`, `Modules\Helpdesk` (trait `HelpdeskLinkable`), `Modules\Shared`. En sens inverse, seuls **BI** et **Core** importent explicitement des classes CRM dans le reste du dépôt. Sales ne référence pas CRM par un import PHP direct, mais `SalesOrder` porte des colonnes `contact_id`/`account_id`/`opportunity_id` qui pointent fonctionnellement vers les entités CRM.

## Particularités du périmètre life-mdg-erp

`Modules/CRM/app/Services/ContactEmailService.php` porte, dans son docblock, la trace explicite du choix fait lors de l'extraction : *« Life MDG runs without the Email module, so templates are static instead of database-driven (`Modules\Email\Models\EmailTemplate`) »*. Le service original (dans WideHalo-ERP) s'appuyait sur `Modules\Email` (`DynamicMail`, `EmailTemplate` piloté par base de données) — un module hors périmètre life-mdg-erp. La version actuelle envoie via `Illuminate\Mail` natif avec un seul template codé en dur (`contact.welcome`) et un rendu par simple remplacement de `{placeholder}` (`renderTemplate()`).

## Gaps documentés, non corrigés au Chantier 32.15

- **`KPIRegistryService::crmKPIs()` (module `Strategy`)** agrège `crm_leads`/`crm_opportunities` sans aucun filtre de société — un ratio calculé toutes sociétés confondues. Spot-vérifié empiriquement que le calcul reste correct (`win_rate` : 38.2 de repli sans donnée, 50.0 avec 1 gagné/1 perdu réels), mais le cloisonnement multi-tenant de ce fichier appartient au périmètre du module `Strategy`, pas de cet audit CRM — flagué pour un futur Chantier 32.<Strategy>.
- **Aucune page Vue de campagne réelle n'existe** (`CampaignController` est réel et routé, mais rien dans `resources/js/Pages/CRM/` ni `Modules/CRM/resources/js/Pages/` ne l'appelle) — confirmé, pas construit dans ce chantier (aurait été une nouvelle fonctionnalité, pas une correction de bug).
- **`Duplicates/Index.vue`** reste une page mock (gap déjà documenté au Chantier 10 — construire la vraie fonctionnalité de fusion de doublons nécessiterait une nouvelle infrastructure de scan par lot, hors périmètre d'un audit).
- **Les contrôleurs `<Module>AiAssistController` dédiés (dont `CRMAiAssistController`)** délèguent tous à `AiContextualAssistantService::getGuidance()`, mais le composable frontend réel (`useAiAssistant.ts`) poste toujours vers l'endpoint générique `POST /api/v1/ai/assist`, jamais vers ces contrôleurs dédiés — confirmés inatteignables depuis le frontend réel de ce module (comme documenté au Chantier 30 pour d'autres modules) — une piste de nettoyage pour un futur chantier, pas creusée davantage ici.
