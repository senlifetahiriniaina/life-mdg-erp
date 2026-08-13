# CRM

## Rôle

Le module CRM couvre la gestion commerciale de Life MDG ERP : contacts, comptes, leads, opportunités, pipeline de vente, devis (quotes), territoires, prévisions de vente, campagnes marketing internes, scoring de leads/opportunités par IA, et un mini-moteur de workflow propre au module (indépendant du module `Workflow`). C'est le module « Commercial » associé à Sales dans le périmètre life-mdg-erp.

## Modèles clés

| Modèle | Rôle |
|---|---|
| `Contact` | Personne physique liée à un compte |
| `Account` | Entreprise/organisation cliente ou prospect |
| `Lead` / `LeadStatusLog` | Prospect entrant et historique de ses changements de statut |
| `Opportunity` / `OpportunityHistory` / `OpportunityScore` | Opportunité commerciale, son historique et son score IA |
| `Pipeline` / `PipelineSnapshot` | Étapes du pipeline de vente et instantanés pour analytics |
| `Quote` / `QuoteLine` | Devis et leurs lignes |
| `Campaign` / `CampaignStage` / `CampaignEnrollment` / `CampaignAction` / `CampaignAnalytic` | Campagnes marketing internes (indépendantes du module Email, exclu du périmètre) |
| `Territory` / `TerritoryAssignment` / `TerritoryQuota` / `TerritoryAlert` | Gestion de territoires commerciaux et quotas |
| `Forecast` / `ForecastModel` / `ForecastInput` | Prévisions de vente |
| `EmailSequence` / `EmailSequenceStep` / `EmailSequenceEnrollment` | Séquences d'e-mails automatisées (basées sur Laravel `Mail`, pas sur le module Email) |
| `WebForm` / `WebFormSubmission` | Formulaires web publics de capture de leads |
| `ScoringRule` / `EngagementSignal` | Règles et signaux utilisés par le scoring d'opportunités |
| `Workflow` / `WorkflowNode` / `WorkflowEdge` / `WorkflowExecution` (table `crm_workflows`) | Mini-moteur de workflow **propre au module CRM** — distinct du module `Modules\Workflow` |
| `AiAgent` / `AiAgentRun` | Agents IA configurables et leurs exécutions |
| `RevenueAnomaly` / `RevenueInsight` / `RevenueTrend` | Intelligence de revenu (anomalies, tendances) |
| `WinLossRecord` | Analyse gagné/perdu des opportunités |

## Endpoints principaux

Routes API sous `auth:sanctum`, préfixées `v1/crm` (`Modules/CRM/routes/api.php`, 282 lignes). Extraits représentatifs :

| Méthode | Route | Description |
|---|---|---|
| POST | `v1/crm/forms/{slug}/submit` | Soumission publique d'un formulaire web (sans auth) |
| GET/POST/PUT/DELETE | `crm/contacts` (apiResource) | CRUD contacts (lecture ouverte, écriture restreinte par rôle) |
| GET/POST/PUT/DELETE | `crm/accounts` (apiResource) | CRUD comptes |
| GET/POST/PUT/DELETE | `crm/leads` (apiResource) | CRUD leads |
| GET | `crm/opportunities/kanban` \| `/pipeline` | Vue Kanban et pipeline des opportunités |
| GET | `crm/opportunities/{opportunity}/history` | Historique d'une opportunité |
| POST | `crm/contacts/{contact}/email/welcome` \| `/custom`, `crm/contacts/email/bulk` | Envoi d'e-mails de bienvenue/personnalisés/en masse (`ContactEmailController`) |
| GET/POST/PUT/DELETE | `crm/email-sequences[/{sequence}]`, `/steps`, `/enroll` | Séquences d'e-mail automatisées |
| GET/POST/PUT/DELETE | `crm/forms[/{form}]` | Gestion des formulaires web |
| POST | `score-leads`, `suggest-next-action`, `draft-follow-up`, `detect-duplicates`, `generate-prospecting-email`, `analyze-sentiment`, `transcribe-call` | Fonctions IA (`CrmAIController`, `CrmProspectingController`) |
| GET/POST | `crm/territories/*`, `crm/territory-management/*` | Territoires, quotas, équilibrage automatique |
| GET/POST | `crm/einstein-forecasting/*` | Prévisions de vente par représentant/produit |
| GET/POST | `crm/opportunity-scores`, `crm/scoring-rules` | Scoring d'opportunités et règles associées |
| POST | `v1/crm/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

## Services

- **`ContactEmailService`** — envoi d'e-mails de notification aux contacts via `Illuminate\Support\Facades\Mail` avec des templates **statiques en dur** (constante `TEMPLATES`), et non plus via un modèle `EmailTemplate` piloté par base de données.
- **`EmailSequenceService`** — orchestration des séquences d'e-mails automatisées (`ProcessEmailSequenceJob` en file d'attente).
- **`LeadScoringService`** / **`OpportunityScoringService`** — scoring par règles pondérées.
- **`LeadStatusService`** — transitions de statut des leads avec journalisation (`LeadStatusLog`).
- **`PipelineService`** / **`PipelineAnalyticsService`** — gestion et analytics du pipeline de vente.
- **`ForecastService`** / **`CRMForecastingService`** / **`EinsteinForecastingService`** / **`TerritoryForecastService`** — prévisions de vente à différents niveaux de granularité.
- **`TerritoryManagementService`** / **`TerritoryService`** — gestion, auto-équilibrage et attribution des territoires.
- **`CampaignOrchestrationService`** — pilotage des campagnes internes.
- **`CustomerManagementService`** / **`SalesOpportunityService`** — logique métier client et opportunité.
- **`CpqService`** — configuration/pricing/devis (Configure-Price-Quote).
- **`ActivityTimelineService`** — historique d'activité consolidé par contact/compte.
- **`AiAgentService`** — exécution des agents IA configurables.
- **`SequenceService`** — séquences génériques (distinctes des `EmailSequence`).
- **`VoipService`** — intégration téléphonie/enregistrement d'appels (`CallLog`, `CallRecording`).

## Permissions RBAC

CRM a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'crm' => ['contact', 'lead', 'opportunity', 'account', 'activity', 'pipeline']`, générant les permissions `crm.contact.*`, `crm.lead.*`, `crm.opportunity.*`, `crm.account.*`, `crm.activity.*`, `crm.pipeline.*` (5 actions × 6 ressources). Le rôle `sales-rep` reçoit l'intégralité des permissions `crm.*`. Le rôle `sales-manager` reçoit `crm.*` + `sales.*` + accès en lecture à `bi.*`/`accounting.invoice.*`. Le rôle `customer-service` reçoit un accès CRM restreint (`crm.contact.view*`, `crm.account.view*`, `crm.activity.view*`/`create`) en complément de `helpdesk.*`.

## Dépendances avec d'autres modules

CRM importe (`use Modules\...`) : `Modules\AI` (guidance/scoring IA), `Modules\Core`, `Modules\Helpdesk` (trait `HelpdeskLinkable` probable sur `Contact`/`Account`), `Modules\Shared`. En sens inverse, seuls **BI** et **Core** importent explicitement des classes CRM dans le reste du dépôt. Sales ne référence pas CRM par un import PHP direct, mais `SalesOrder` porte des colonnes `contact_id`/`account_id`/`opportunity_id` qui pointent fonctionnellement vers les entités CRM.

## Particularités du périmètre life-mdg-erp

`Modules/CRM/app/Services/ContactEmailService.php` porte, dans son docblock, la trace explicite du choix fait lors de l'extraction : *« Life MDG runs without the Email module, so templates are static instead of database-driven (`Modules\Email\Models\EmailTemplate`) »*. Le service original (dans WideHalo-ERP) s'appuyait sur `Modules\Email` (`DynamicMail`, `EmailTemplate` piloté par base de données) — un module hors périmètre life-mdg-erp. La version actuelle envoie via `Illuminate\Mail` natif (`Mail::send(new ContactNotificationMail(...))`) avec un seul template codé en dur (`contact.welcome`) et un rendu par simple remplacement de `{placeholder}` (`renderTemplate()`), ce qui confirme le remplacement volontaire mentionné dans le périmètre du projet.
