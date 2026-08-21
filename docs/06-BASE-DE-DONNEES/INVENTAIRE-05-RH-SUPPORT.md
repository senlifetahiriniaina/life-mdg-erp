# Inventaire des tables — RH basique et Support (5 modules)

HR, Payroll, Timesheets, Projects, Helpdesk. Voir `SCHEMA-GENERAL.md` pour la méthode et la légende **réelle / stub patché / stub pur**.

## HR (52 tables : 21 réelles, 31 stub dont 14 patchées / 17 pures)

Rappel de périmètre (`CLAUDE.md`) : seules les tables listées ci-dessous comme « réelles » correspondent au scope **« basique »** retenu (Employee, Department, Position, Attendance, LeaveRequest/Balance/Type, EmployeeDocument, Skill, Compensation…). La majorité des 31 tables stub — ATS/recrutement, évaluations 360°, formation, succession — correspond aux fonctionnalités **explicitement retirées** du périmètre HR ; elles restent scaffoldées en base (schéma générique inerte) mais aucun module réel ne les consomme.

| Table | Rôle |
|---|---|
| `hr_employees` | Dossier employé — PII chiffrée (`national_id`, `passport_number`, `bank_details` en JSON) via colonnes `*_encrypted`, masquage bancaire PCI DSS. FK réelles : `user_id` (`users`), `department_id` (`hr_departments`), `job_position_id` (`hr_job_positions`), `manager_id` (auto-référence `hr_employees`, hiérarchie manager/subordonnés) |
| `hr_departments` / `hr_job_positions` | Département et poste organisationnel (pas des offres de recrutement) |
| `hr_leave_requests` / `hr_leave_balances` / `hr_leave_types` / `hr_leave_approval_log` | Demande, solde, type de congé, historique d'approbation. `LeaveRequestPolicy::update()` comparait `$model->employee_id` (`hr_employees.id`) à `$user->id` (`users.id`) — bug d'ID-space corrigé en comparant `$model->employee?->user_id` |
| `hr_time_off_requests` | Demande d'absence, distincte de `LeaveRequest` |
| `hr_biometric_devices` | Terminal biométrique de pointage |
| `hr_shift_schedules` | Planning d'équipe — modèle de gabarit récurrent (`days_of_week` + `effective_from`/`effective_to`), pas d'instance par date (`Shifts/Schedule.vue` a été adapté au backend réel plutôt que l'inverse, décision checkpointée) |
| `hr_attendance_exceptions` / `hr_attendance_analytics` | Anomalie de présence à traiter et agrégats analytiques |
| `hr_employee_documents` | Document de conformité avec suivi d'expiration |
| `hr_compensation_history` / `hr_employee_compensation` / `hr_deductions` | Historique de rémunération, rémunération active, déductions applicables (support à la paie, hors module Payroll lui-même) |
| `hr_payroll_records` | **Table legacy** — `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` sont référencés ailleurs dans le code mais absents du dépôt ; le vrai schéma paie migré et à jour est `payroll_runs`/`payslips`/`salary_components` (module **Payroll**, voir plus bas) |
| `hr_shifts` / `hr_timesheets` / `hr_employee_loans` | Trois tables sans consommateur réel confirmé — `calculateOvertime()`/`calculateLoanRepayment()` (Payroll) lisaient `hr_timesheets.overtime_hours`/`hr_employee_loans.monthly_installment`, deux tables stub mortes sans aucun écrivain ; corrigé au Chantier 8.3 Payroll partie 3 en dérivant l'heure sup depuis `TimesheetEntry.hours_worked` (module Timesheets) à la place. Le prêt employé n'a **aucune** source de donnée réelle nulle part dans l'app — resté au fallback 0, documenté plutôt que construit |
| `hr_succession_candidates_v2` / `hr_performance_goals` | Résidus du périmètre ATS/360° retiré, avec des colonnes patchées malgré tout |

**Tables stub, patchées depuis (14)** : `hr_appraisals`, `hr_attendance_records` (colonnes `device_id`/`clock_in_method`/`verification_status`/`break_minutes`/`location_lat`/`location_lng` ajoutées au Chantier 8.3 partie 2 — le contrôleur `AttendanceBiometricController` écrivait déjà ces champs avant même que la colonne existe), `hr_candidates`, `hr_critical_positions`, `hr_employee_skills` (+ colonne `expires_at` ajoutée), `hr_job_postings`, `hr_performance_goals`, `hr_performance_reviews`, `hr_positions`, `hr_review_cycles`, `hr_salary_bands`, `hr_succession_candidates`, `hr_succession_plans` (2 patches), `hr_training_courses`. **Stub pures (17, correspondent au périmètre ATS/360°/formation explicitement retiré)** : `hr_appraisal_competencies`, `hr_appraisal_cycles`, `hr_appraisal_goals`, `hr_attendance`, `hr_course_enrollments`, `hr_courses`, `hr_interview_schedules`, `hr_interviews`, `hr_job_applications`, `hr_learning_paths`, `hr_performance_appraisals`, `hr_performance_cycles`, `hr_recruitment_applicants`, `hr_recruitment_jobs`, `hr_review_goals`, `hr_skills` (attention : distincte de `hr_employee_skills` ci-dessus, bien réelle), `hr_training_enrollments`.

Une fuite PII réelle (`EmployeeSelfServiceController::me()`/`updateMe()`, `EmployeePortalController::profile()` sérialisaient `bank_details_encrypted`/`national_id`/`passport_number` en clair) a été corrigée au Chantier 8.3 via `SelfServiceEmployeeResource`. 6 tables ont été ajoutées de zéro au Chantier 8.3 partie 2 pour `AttendanceBiometricController` (13 méthodes déjà écrites, zéro route) : `BiometricDevice`, `TimeOffRequest`, `AttendanceException`, `AttendanceAnalytics`, `CompensationHistory`, `Deduction` — toutes listées « réelles » ci-dessus. Les services `AbsenceManagementService`/`AdvancedAttendanceService` (dupliquant le vrai flux congés avec FMLA américain en dur, colonnes inexistantes) ont été **supprimés** au Chantier 8.3 partie 8 — aucune de leurs hypothèses de schéma n'a donc été construite.

## Payroll (3 tables, toutes réelles — le trio actuel et migré)

| Table | Rôle |
|---|---|
| `payroll_runs` | Cycle de paie tenant/période — `draft → processing → validated → paid`, totaux agrégés |
| `payslips` | Bulletin individuel rattaché à un `PayrollRun`, `salary_components` en JSON |
| `salary_components` | Composant de rémunération configurable (fixe/pourcentage), taxable/statutaire, `applies_to` |

**Le bug « salaire zéro silencieux »** (Chantier 8.3 partie 2, le plus sévère trouvé dans ce module) : `PayrollIntegrationService` lisait `base_salary`/`monthly_salary` directement sur `Employee`, colonnes physiquement présentes en base (`hr_employees`, héritage d'un schéma antérieur) mais **absentes du `$fillable`** du modèle — silencieusement ignorées en mass-assignment sur tout chemin d'écriture réel, donc tout bulletin généré via l'UI/API réelle calculait un salaire quasi nul. La vraie source est `hr_employee_compensation` (module HR). Le vrai tenant est `users.tenant_id` (colonne réellement lue par `InitializeTenancyFromAuthenticatedUser`), pas `hr_employees.tenant_id` (troisième colonne fantôme, référencée nulle part en code HR vivant). `StatutorySchemes.php` (8 barèmes fiscaux africains réels SN/CI/CM/MG/BJ/TG/BF/ML) a été câblé pour la première fois au Chantier 8.3 partie 4 dans le calcul d'impôt/cotisations réel.

## Timesheets (7 tables : 2 réelles, 5 stub dont 5 patchées — aucune stub pure)

Aucune migration propre au module — 100 % du schéma vit à la racine. Quatre conventions de nom différentes coexistent pour un concept proche (« saisie de temps »).

| Table | Rôle |
|---|---|
| `ts_timesheet_periods` | Période hebdo/bi-hebdo de soumission groupée (heures sup Afrique First : >40h/semaine à 125%) — modèle `TimesheetPeriod` schéma-correct mais **sans aucune migration nulle part** avant le Chantier 8.4, qui l'a ajoutée. `canBeSubmitted()`/statut par défaut utilisaient `'open'` alors que le reste de l'app utilise `'draft'` — aligné |
| `ts_project_billing` | Facturation de projet par jalon/%/régie T&M/forfait, compte OHADA 7061 — même situation, migration ajoutée au Chantier 8.4. `ProjectBillingService::billTimeAndMaterial()` interrogeait une 4ᵉ table différente (`ts_timesheets`, colonnes ne correspondant à aucun modèle réel) — repointé sur `timesheet_entries` |

**Tables stub, toutes patchées** : `timesheet_entries` (le vrai modèle `TimesheetEntry` — `employee_id`, `entry_date`, `hours_worked`, `billable_hours`, `hourly_rate`, `status`, `project_id`, `task_id`, `submitted_by`/`approved_by` ajoutés par patch après la création générique ; `TimesheetEntryPolicy` comparait `$user->id` à `employee_id`, même bug d'ID-space que `LeaveRequestPolicy`, corrigé), `timesheets_entries` (second modèle `TimeEntry`, taux horaire + facturabilité, coexiste), `timesheets_sheets` (`Timesheet`, feuille groupée par période — **son `$fillable` ne correspondait toujours pas au schéma patché**, source de bugs avant que le module `TimesheetAdvancedController` cassé qui s'appuyait dessus soit repointé sur `TimesheetEntry`/`TimesheetPeriod` réels au Chantier 8.4), `time_allocations` (répartition des heures entre projet/tâche/centre de coût), `time_tracking_projects` (projet de suivi « léger », distinct des `Project` du module Projects).

Le module `Api/TimeEntryController`/`Models/TimeEntry` (un **second** `TimeEntry`, entièrement différent de celui ci-dessus, cassé et jamais routé) a été supprimé au Chantier 8.4.

## Projects (22 tables : 13 réelles, 9 stub dont 4 patchées / 5 pures)

| Table | Rôle |
|---|---|
| `prj_projects` | Projet — `owner_id` (FK `users`), `status`, dates, `budget`, devise (`USD` par défaut ici, contrairement à `XOF` ailleurs — attention en cross-module). Utilise `HelpdeskLinkable` |
| `prj_tasks` | Tâche — hiérarchie parent/sous-tâches, épic/sprint/jalon, priorité, points d'histoire. `ProjectTask` (supprimé au Chantier 8.4, doublon de `Task` sur la même table) |
| `prj_milestones` | Jalon, utilisé aussi pour la facturation par jalon (Timesheets) |
| `prj_members` | Membres de projet (table racine, distincte de `prj_team_members` ci-dessous) |
| `prj_time_logs` | Table réellement utilisée par le vrai `TimeEntryController` (task-scoped), rewiré au Chantier 8.4 sur le modèle `ProjectTimeLog` — le modèle `TimeLog` dont le `$fillable` ne correspondait jamais à cette table a été supprimé |
| `prj_budget_lines` / `prj_expense_ledger` / `prj_risks` | Ajoutées de zéro au Chantier 8.4 — le groupe de routes Phase 49 (`budget`/`kpis`/`risks`/`portfolio*`) n'avait ni gating RBAC ni tables, chaque appel plantait en « table not found » |
| `project_billings` | `ProjectBilling` (Projects) — homonyme du `ProjectBilling` de Timesheets mais table et namespace différents |
| `project_documents` | Documents de projet |
| `projects_custom_fields` / `projects_custom_field_values` | Champs personnalisés par projet |
| `projects_saved_views` | Vue sauvegardée (filtre Kanban/Gantt/Calendrier) |

**Tables stub, patchées depuis** : `prj_resource_allocations`, `prj_sprints`, `prj_team_members`, `prj_time_entries`. **Stub pures (5)** : `prj_automation_rules`, `prj_epics`, `prj_project_billing` (distincte de `project_billings` ci-dessus — 3ᵉ variante du même concept), `prj_resource_capacity`, `project_task_dependencies` (porte pourtant la détection de cycle documentée dans `docs/03-MODULES/Projects.md` — à vérifier avant usage, schéma probablement encore générique).

Un piège Laravel réel documenté au Chantier 8.4 : toute méthode de contrôleur sur une route à N segments de modèles liés doit type-hinter **chaque** modèle, même ceux dont le corps ne se sert pas — en omettre un fait échouer la résolution de **tous** les modèles liés de cette route avec un `TypeError` brut au lieu d'un 404 propre (`index()` de `TimeEntryController` omettait `Project $project`).

## Helpdesk (54 tables : 38 réelles, 16 stub dont 6 patchées / 10 pures — module full, couplé aux 8 autres modules)

Trois familles de préfixes : `hd_` (principal), `helpdesk_` (forums/CSAT/assignations), `cs_` (27 tables d'IA service client).

| Table | Rôle |
|---|---|
| `hd_tickets` | Ticket de support — sujet, statut, priorité, canal, SLA, satisfaction. Relation polymorphe `source()`/`source_type`/`source_id` ajoutée **après coup** par une migration module (voir `SCHEMA-GENERAL.md` pour l'exemple détaillé) — c'est elle qui porte le trait `HelpdeskLinkable` documenté dans `CLAUDE.md` et `DEPENDANCES-MODULES.md` |
| `hd_kb_articles` / `hd_kb_categories` | Base de connaissance interne |
| `hd_team_members` | Membres d'équipe de support |
| `hd_development_plans` | Plans de développement agent |
| `hd_conversation_analytics` | Analytique de conversation |
| `helpdesk_forums` / `helpdesk_forum_threads` / `helpdesk_forum_replies` / `helpdesk_forum_votes` | Forum communautaire — `helpdesk_forum_posts` (stub, voir plus bas) est un 5ᵉ nom parallèle |
| `helpdesk_ticket_assignments` | Historique d'assignation d'un ticket |
| `cs_sentiment_scores` / `cs_sentiment_history` / `cs_sentiment_models` | Analyse de sentiment |
| `cs_emotion_analysis` / `cs_language_detection` | Émotion, détection de langue |
| `cs_escalation_predictions` / `cs_escalation_models` / `cs_escalation_history` / `cs_escalation_workflows` | Prédiction et workflow d'escalade |
| `cs_satisfaction_predictions` / `cs_satisfaction_factors` / `cs_satisfaction_history` / `cs_satisfaction_models` / `cs_nps_predictors` | Prédiction de satisfaction/NPS |
| `cs_response_templates` / `cs_response_suggestions` / `cs_ai_response_variants` / `cs_response_customization` / `cs_response_performance` | Suggestions et performance de réponse |
| `cs_agent_metrics` / `cs_agent_performance_trends` / `cs_agent_skill_analysis` / `cs_agent_coaching_recommendations` / `cs_performance_goals` / `cs_team_benchmarking` | **Talent management agent — explicitement exclu du périmètre** (`CLAUDE.md` Known Gaps : « du 360°-performance-review portant un badge Helpdesk »), 19 tests non construits ; ces tables existent en base (schéma réel, `2026_08_16_000001_create_customer_service_ai_tables.php`) mais aucune UI/route ne les exploite pour du coaching réel |
| `cs_routing_rules` / `cs_urgency_factors` | Règles de routage et facteurs d'urgence |

Toutes les 27 tables `cs_*` proviennent de `CustomerServiceAIPolicy` (46 abilities) — écrite mais **jamais enregistrée auprès du Gate ni appelée** (elle ne correspond à aucun modèle Eloquent unique, donc ni l'auto-découverte ni `AppServiceProvider::$policies` ne pouvaient la trouver) jusqu'au Chantier 8.2, qui a enregistré chaque ability comme une Gate ability individuelle et ajouté 44 permissions `helpdesk.*`.

**Tables stub, patchées depuis (6)** : `hd_escalation_rules`, `hd_kb_portal_articles` (2), `hd_kb_portal_categories` (2), `hd_sla_policies`, `hd_teams` (2), `hd_ticket_comments`. **Stub pures (10)** : `hd_chat_messages`, `hd_chat_sessions`, `hd_escalation_events`, `hd_helpdesk_sla_policies` (distincte de `hd_sla_policies` — deux modèles `SlaPolicy`/`HelpdeskSlaPolicy` documentés dans `docs/03-MODULES/Helpdesk.md`, deux tables réellement séparées, pas un doublon), `hd_kb_article_views`, `hd_sla_breaches`, `helpdesk_bot_deflections`, `helpdesk_csat_campaigns`, `helpdesk_csat_surveys`, `helpdesk_forum_posts`.

Un bug d'enregistrement de route « le dernier gagne » a été trouvé et corrigé au Chantier 8.2 : les routes CRUD Knowledge Base étaient enregistrées **deux fois** (`KbCategoryController`/`KbArticleController` puis, plus loin dans le même fichier, `KnowledgeBaseController`) — Laravel indexe les routes par méthode+URI dans un tableau associatif, donc la **seconde** définition (la plus tardive dans le fichier) gagnait silencieusement au dispatch, pas la première. `KbCategoryController` (100% redondant) a été supprimé, `KbArticleController` réduit à sa seule méthode non-doublon (`suggest()`).

## Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté dans `SCHEMA-GENERAL.md`. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

### HR (43 tables)

**`hr_appraisal_cycles`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,start_date:date,end_date:date,type:varchar,is_active:tinyint!,cycle_type:varchar!,year:integer,review_deadline:date,self_assessment_deadline:date,description:text`

**`hr_appraisals`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,employee_id:integer,reviewer_id:integer,cycle_id:integer,period:varchar,rating:numeric,comments:varchar,submitted_at:datetime,overall_rating:numeric`

**`hr_attendance`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,employee_id:integer,date:date,clock_in:datetime,clock_out:datetime,type:varchar!,ip_address:varchar,attendance_date:date,check_in_time:time,check_out_time:time`

**`hr_attendance_analytics`** — `id:integer!,employee_id:integer!,analytics_date:date!,month:integer,year:integer,days_present:integer!,days_absent:integer!,days_late:integer!,days_early_departure:integer!,total_late_minutes:integer!,total_early_minutes:integer!,total_working_minutes:integer!,total_expected_minutes:integer!,attendance_percentage:numeric,punctuality_percentage:numeric,trend:varchar,created_at:datetime,updated_at:datetime`

**`hr_attendance_exceptions`** — `id:integer!,employee_id:integer!,attendance_date:date!,exception_type:varchar!,minutes_late:integer,minutes_early:integer,reason:text,status:varchar!,approved_by:integer,manager_notes:text,employee_response:text,acknowledged_at:datetime,approved_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_attendance_records`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,employee_id:integer,date:date,check_in:datetime,check_out:datetime,hours_worked:numeric,source:varchar!,clock_in:datetime,clock_out:datetime,type:varchar!,ip_address:varchar,location:varchar,break_minutes:integer!,notes:text,location_lat:numeric,location_lng:numeric,device_id:integer,clock_in_method:varchar,device_name:varchar,verification_status:varchar`

**`hr_biometric_devices`** — `id:integer!,device_id:varchar!,device_name:varchar!,device_type:varchar!,manufacturer:varchar,model:varchar,location:varchar!,building:varchar,floor:varchar,zone:varchar,latitude:numeric,longitude:numeric,ip_address:varchar,mac_address:varchar,status:varchar!,last_sync:datetime,firmware_version:varchar,capacity:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_candidates`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,first_name:varchar,last_name:varchar,email:varchar,phone:varchar,source:varchar,cv_path:varchar,rating:integer,applied_at:datetime,name:varchar,job_id:integer,resume_url:text,job_posting_id:integer`

**`hr_compensation_history`** — `id:integer!,employee_id:integer!,change_type:varchar!,previous_amount:numeric,new_amount:numeric,amount_difference:numeric,currency:varchar!,effective_date:date!,approved_by:integer,reason:text,status:varchar!,metadata:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_courses`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,description:text,duration:integer,category:varchar,duration_hours:integer,level:varchar!`

**`hr_critical_positions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,succession_plan_id:integer,title:varchar,department:varchar,risk_level:varchar!,current_holder_id:integer,impact_description:text,is_vacant:tinyint!`

**`hr_deductions`** — `id:integer!,employee_id:integer!,name:varchar!,type:varchar!,category:varchar,amount:numeric!,frequency:varchar!,limit:numeric,ytd_amount:numeric!,is_active:tinyint!,effective_from:date!,effective_to:date,metadata:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_departments`** — `id:integer!,name:varchar!,code:varchar!,manager_id:integer,parent_id:integer,description:text,is_active:tinyint!,created_at:datetime,updated_at:datetime,budget_allocation:numeric,status:varchar!,deleted_at:datetime`

**`hr_employee_compensation`** — `id:integer!,employee_id:integer!,base_salary:numeric,currency:varchar!,bonus_amount:numeric,bonus_frequency:varchar,equity_granted:numeric,equity_vesting_period_months:integer,equity_vesting_schedule:text,equity_vested_percentage:numeric,benefits_annual_value:numeric,total_compensation:numeric,effective_date:date!,end_date:date,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_employee_documents`** — `id:integer!,employee_id:integer!,document_type:varchar!,title:varchar!,reference_number:varchar,country:varchar,issue_date:date,expiry_date:date,status:varchar!,alert_days_before:integer!,alert_sent_60:tinyint!,alert_sent_30:tinyint!,alert_sent_7:tinyint!,file_path:varchar,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_employee_loans`** — `id:integer!,employee_id:integer!,amount:numeric!,monthly_installment:numeric!,status:varchar!,start_date:date!,end_date:date,created_at:datetime,updated_at:datetime`

**`hr_employee_skills`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,employee_id:integer,skill_id:integer,level:integer!,certified_at:datetime,expires_at:datetime`

**`hr_employees`** — `id:integer!,user_id:integer,department_id:integer,job_position_id:integer,manager_id:integer,employee_number:varchar!,first_name:varchar!,last_name:varchar!,email:varchar!,phone:varchar,date_of_birth:date,gender:varchar,nationality:varchar,national_id:varchar,passport_number:varchar,address:text,hire_date:date!,probation_end_date:date,termination_date:date,employment_type:varchar!,status:varchar!,avatar:varchar,emergency_contacts:text,bank_details:text,custom_fields:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,national_id_encrypted:text,passport_number_encrypted:text,bank_details_encrypted:text,emergency_contacts_encrypted:text,base_salary:numeric,salary_currency:varchar!,country_code:varchar,tenant_id:varchar,annual_leave_balance:numeric!,full_name:varchar,termination_reason:varchar,sick_leave_balance:numeric,job_title:varchar`

**`hr_job_positions`** — `id:integer!,department_id:integer,title:varchar!,level:varchar,description:text,requirements:text,is_active:tinyint!,created_at:datetime,updated_at:datetime`

**`hr_job_postings`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,department_id:integer,location:varchar,type:varchar,salary_min:numeric,salary_max:numeric,currency:varchar,description:text,requirements:text,is_published:tinyint!,closes_at:date,created_by:integer,work_mode:varchar!,show_salary:tinyint!`

**`hr_leave_approval_log`** — `id:integer!,leave_request_id:integer!,level:integer!,approver_id:integer,approver_role:varchar,action:varchar!,comment:text,actioned_at:datetime,created_at:datetime`

**`hr_leave_balances`** — `id:integer!,employee_id:integer!,leave_type:varchar!,accrual_days_per_year:numeric!,balance:numeric!,used:numeric!,pending:numeric!,year:integer!,reset_date:date,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_leave_requests`** — `id:integer!,employee_id:integer!,leave_type_id:integer!,approved_by:integer,start_date:date!,end_date:date!,days:integer!,status:varchar!,reason:text,rejection_reason:text,approved_at:datetime,created_at:datetime,updated_at:datetime,type:varchar,leave_type:varchar,days_requested:integer,deleted_at:datetime,approval_notes:text`

**`hr_leave_types`** — `id:integer!,name:varchar!,code:varchar!,days_per_year:integer!,is_paid:tinyint!,carry_forward:tinyint!,max_carry_forward_days:integer!,created_at:datetime,updated_at:datetime,description:text,is_active:tinyint!,approval_levels:integer!,status:varchar!`

**`hr_payroll_records`** — `id:integer!,employee_id:integer,pay_period_start:date!,pay_period_end:date!,gross_salary:numeric!,deductions:numeric!,net_salary:numeric!,status:varchar!,processed_at:datetime,created_at:datetime,updated_at:datetime,gross_salary_encrypted:text,total_deductions_encrypted:text,net_salary_encrypted:text,total_deductions:numeric,currency:varchar!,breakdown:text,payroll_config_id:integer,payment_date:date`

**`hr_performance_appraisals`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_performance_cycles`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,description:text,start_date:date,end_date:date,is_active:tinyint!,type:varchar!`

**`hr_performance_goals`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,appraisal_id:integer,employee_id:integer,title:varchar,description:text,due_date:date,progress:numeric,weight:numeric,score:numeric`

**`hr_performance_reviews`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,cycle_id:integer,employee_id:integer,reviewer_id:integer,overall_score:numeric,comments:text,submitted_at:datetime,review_type:varchar!,overall_rating:numeric`

**`hr_positions`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,description:text,department_id:integer,level:varchar,salary_min:numeric,salary_max:numeric,headcount:integer`

**`hr_recruitment_jobs`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,department_id:integer,description:text,is_active:tinyint!,job_position_id:integer,positions_available:integer!`

**`hr_review_cycles`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,start_date:date,end_date:date,type:varchar,is_active:tinyint!,period_start:date,period_end:date,self_review_enabled:tinyint!,peer_review_enabled:tinyint!,due_date:date,notes:text,created_by:integer,cycle_id:integer`

**`hr_salary_bands`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,level:varchar,min_salary:numeric,max_salary:numeric,currency:varchar!,mid_salary:numeric`

**`hr_shift_schedules`** — `id:integer!,employee_id:integer!,shift_name:varchar,shift_code:varchar,start_time:time,end_time:time,working_hours:integer,days_of_week:text,is_night_shift:tinyint!,is_flexible:tinyint!,effective_from:date,effective_to:date,status:varchar!,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_shifts`** — `id:integer!,employee_id:integer!,shift_type:varchar,date:date,start_time:varchar,end_time:varchar,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_skills`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,category:varchar,description:text`

**`hr_succession_candidates`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,critical_position_id:integer,employee_id:integer,readiness:varchar,priority:integer!,strengths:text,development_needs:text,development_plan:text,last_reviewed_at:datetime`

**`hr_succession_candidates_v2`** — `id:integer!,plan_id:integer,employee_id:integer,readiness:varchar,potential:varchar,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_succession_plans`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,position_id:integer,incumbent_id:integer,created_by:integer,review_date:date,is_active:tinyint!,name:varchar,fiscal_year:integer,description:text,reviewed_by:integer`

**`hr_time_off_requests`** — `id:integer!,employee_id:integer!,request_type:varchar!,start_date:date!,end_date:date!,duration_days:integer,duration_hours:numeric,status:varchar!,approved_by:integer,reason:text,rejection_reason:text,approved_at:datetime,rejected_at:datetime,partial_day:tinyint!,partial_day_details:text,is_urgent:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`hr_timesheets`** — `id:integer!,employee_id:integer!,date:date!,hours_worked:numeric!,overtime_hours:numeric!,status:varchar!,notes:text,created_at:datetime,updated_at:datetime`

**`hr_training_courses`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,description:text,provider:varchar,duration_hours:integer,cost:numeric,currency:varchar,is_mandatory:tinyint!,type:varchar!,created_by:integer`

**`salary_components`** — `id:integer!,tenant_id:integer!,name:varchar!,component_type:varchar!,calculation_type:varchar!,amount:numeric,rate:numeric,is_taxable:tinyint!,is_statutory:tinyint!,applies_to:text,is_active:tinyint!,created_at:datetime,updated_at:datetime`

### Helpdesk (54 tables)

**`cs_agent_coaching_recommendations`** — `id:integer!,agent_id:integer,generated_by:integer,recommendation_type:varchar,priority:varchar,description:text,target_metric:varchar,current_performance:integer,target_performance:integer,recommended_actions:text,training_program:varchar,resources:text,target_completion_date:date,status:varchar!,acknowledged_at:datetime,completed_at:datetime,expected_improvement:numeric,actual_improvement:numeric,feedback_notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_agent_metrics`** — `id:integer!,agent_id:integer,metric_date:date,tickets_handled:integer,tickets_resolved:integer,first_contact_resolution_count:integer,first_contact_resolution_rate:numeric,avg_resolution_time_minutes:integer,avg_response_time_seconds:integer,avg_handle_time_seconds:integer,avg_satisfaction_rating:numeric,satisfaction_survey_responses:integer,avg_sentiment_improvement:numeric,escalation_count:integer,escalation_rate:numeric,repeat_contact_count:integer,repeat_contact_rate:numeric,quality_audit_score:integer,nps_detractor_count:integer,nps_passive_count:integer,nps_promoter_count:integer,nps_score:numeric,productivity_score:numeric,quality_score:numeric,overall_performance_score:numeric,performance_rating:varchar,created_at:datetime,updated_at:datetime`

**`cs_agent_performance_trends`** — `id:integer!,agent_id:integer,period_type:varchar,period_start_date:date,period_end_date:date,satisfaction_trend:numeric,resolution_time_trend:numeric,productivity_trend:numeric,quality_trend:numeric,escalation_trend:numeric,trend_summary:text,performance_direction:varchar,improvement_points:integer,decline_points:integer,top_improvements:text,areas_needing_improvement:text,created_at:datetime,updated_at:datetime`

**`cs_agent_skill_analysis`** — `id:integer!,agent_id:integer,skill_category:varchar,skill_name:varchar,skill_level:varchar,proficiency_score:integer,tickets_handled_for_skill:integer,avg_satisfaction_for_skill:numeric,first_contact_resolution_rate_for_skill:numeric,avg_resolution_time_for_skill_minutes:integer,escalation_count_for_skill:integer,escalation_rate_for_skill:numeric,skill_improvement_points:integer,skill_certified_at:datetime,skill_last_practiced_at:datetime,proficiency_trend:varchar,days_since_practice:integer,needs_training:tinyint!,training_recommendations:text,created_at:datetime,updated_at:datetime`

**`cs_ai_response_variants`** — `id:integer!,template_id:integer,variant_type:varchar,content:text,target_sentiment:varchar,target_emotion:varchar,target_context:varchar,relevance_score:numeric,triggers:text,avg_satisfaction_rating:numeric,usage_count:integer!,positive_feedback_count:integer!,negative_feedback_count:integer!,ai_generated:tinyint!,generation_model:varchar,status:varchar,generated_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_emotion_analysis`** — `id:integer!,ticket_id:integer,sentiment_score_id:integer,anger_score:numeric,frustration_score:numeric,satisfaction_score:numeric,confusion_score:numeric,urgency_score:numeric,disappointment_score:numeric,dominant_emotion:varchar,emotional_state:varchar,emotional_intensity:integer,sentiment_shift_detected:tinyint!,emotion_sequence:text,context_notes:text,analyzed_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_escalation_history`** — `id:integer!,ticket_id:integer,escalation_prediction_id:integer,predicted_urgency:numeric,predicted_probability:numeric,prediction_correct:tinyint,actual_escalation_probability:numeric,actual_action:varchar,was_escalated:tinyint!,escalation_delay_minutes:integer,model_accuracy_impact:numeric,feedback:text,notes:text,created_at:datetime,updated_at:datetime`

**`cs_escalation_models`** — `id:integer!,name:varchar!,model_type:varchar,provider:varchar,model_identifier:varchar,precision:numeric,recall:numeric,f1_score:numeric,training_samples:integer,trained_at:datetime,deployed_at:datetime,status:varchar,feature_importance:text,hyperparameters:text,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_escalation_predictions`** — `id:integer!,ticket_id:integer,escalation_model_id:integer,urgency_score:numeric,escalation_probability:numeric,confidence:numeric,recommended_action:varchar,escalation_level:varchar,estimated_resolution_hours:integer,contributing_factors:text,status:varchar,escalated:tinyint!,escalated_at:datetime,notes:text,predicted_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_escalation_workflows`** — `id:integer!,name:varchar!,description:text,escalation_level:varchar,min_urgency_score:integer!,approval_required:tinyint!,approval_roles:text,escalation_time_minutes:integer,next_level:varchar,send_notifications:tinyint!,notification_recipients:text,notification_template:varchar,reassignment_rules:text,priority_level:varchar,is_active:tinyint!,order:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_language_detection`** — `id:integer!,ticket_id:integer,detected_language:varchar,confidence:numeric,original_text_language:varchar,supported_language:varchar,requires_translation:tinyint!,translation_provider:varchar,translated_text:text,translation_status:varchar,translation_confidence:numeric,language_alternatives:text,detection_notes:text,detected_at:datetime,translated_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_nps_predictors`** — `id:integer!,ticket_id:integer,predicted_nps_score:numeric,promoter_likelihood:varchar,promoter_probability:numeric,passive_probability:numeric,detractor_probability:numeric,nps_influencing_factors:text,recommendation_likelihood:varchar,recommendation_sentiment:text,advocacy_score:numeric,is_repeat_customer:tinyint!,lifetime_value_segment:integer,customer_segment:varchar,churn_risk_indicators:text,churn_probability:numeric,actual_nps_score:integer,predicted_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_performance_goals`** — `id:integer!,agent_id:integer,created_by:integer,goal_type:varchar,goal_description:text,goal_category:varchar,metric_name:varchar,baseline_value:integer,target_value:integer,measurement_unit:varchar,start_date:date,end_date:date,frequency:varchar,weight:integer,current_progress:numeric,status:varchar!,progress_status:varchar,milestone_dates:text,achievements:text,notes:text,achieved_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_response_customization`** — `id:integer!,agent_id:integer,template_id:integer,preferred_variation:varchar,tone_preference:varchar,frequent_modifications:text,customization_score:numeric!,times_used:integer!,times_modified:integer!,avg_satisfaction_with_variant:numeric,has_custom_variant:tinyint!,custom_variant_id:integer,learning_data:text,is_learning_enabled:tinyint!,last_used_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_response_performance`** — `id:integer!,template_id:integer,variant_id:integer,ticket_id:integer,response_type:varchar,satisfaction_rating:numeric,feedback_category:varchar,response_time_seconds:integer,ticket_resolution_time_minutes:integer,issue_resolved:tinyint!,resolution_effectiveness:numeric,follow_up_count:integer!,required_escalation:tinyint!,required_additional_response:tinyint!,customer_sentiment_after:varchar,metrics:text,notes:text,created_at:datetime,updated_at:datetime`

**`cs_response_suggestions`** — `id:integer!,ticket_id:integer,template_id:integer,variant_id:integer,suggested_response:text,suggestion_reason:text,relevance_score:numeric,confidence:numeric,matching_factors:text,used:tinyint!,accepted:tinyint,modified_response:text,modification_significant:tinyint,feedback_rating:numeric,feedback_type:varchar,feedback_notes:text,suggested_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_response_templates`** — `id:integer!,created_by:integer,title:varchar!,content:text,category:varchar,language:varchar,tags:text,use_case:varchar,variables:text,tone:varchar,avg_resolution_time_minutes:integer,avg_satisfaction_rating:numeric,usage_count:integer!,positive_feedback_count:integer!,negative_feedback_count:integer!,status:varchar!,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_routing_rules`** — `id:integer!,team_id:integer,name:varchar!,rule_type:varchar!,sentiment_trigger:varchar,target_queue:varchar,priority_boost:integer,requires_specialist:tinyint!,skill_required:varchar,routing_conditions:text,escalation_path:varchar,max_wait_minutes:integer,sla_hours_override:integer,notify_customer:tinyint!,notification_message:text,is_active:tinyint!,order:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_satisfaction_factors`** — `id:integer!,ticket_id:integer,resolution_time_minutes:integer,resolution_time_factor:numeric!,first_contact_resolution:tinyint!,agent_professionalism:varchar,agent_professionalism_factor:numeric!,agent_friendliness:varchar,agent_friendliness_factor:numeric!,agent_knowledge_level:varchar,agent_knowledge_factor:numeric!,communication_quality:varchar,communication_factor:numeric!,problem_understanding:varchar,problem_understanding_factor:numeric!,solution_effectiveness:varchar,solution_effectiveness_factor:numeric!,customer_expectation_met:tinyint!,expectation_factor:numeric!,follow_up_quality_rating:integer,follow_up_factor:numeric!,created_at:datetime,updated_at:datetime`

**`cs_satisfaction_history`** — `id:integer!,ticket_id:integer,satisfaction_prediction_id:integer,predicted_score:integer,actual_score:integer,prediction_error:integer,prediction_accurate:tinyint,satisfaction_category:varchar,improvement_actions:text,score_improved:tinyint,score_improvement_points:integer,model_feedback:text,model_learning_impact:numeric,notes:text,created_at:datetime,updated_at:datetime`

**`cs_satisfaction_models`** — `id:integer!,name:varchar!,model_type:varchar,provider:varchar,model_identifier:varchar,rmse:numeric,mae:numeric,r_squared:numeric,training_samples:integer,trained_at:datetime,deployed_at:datetime,status:varchar,feature_importance:text,hyperparameters:text,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_satisfaction_predictions`** — `id:integer!,ticket_id:integer,satisfaction_model_id:integer,predicted_satisfaction_score:integer,confidence:numeric,satisfaction_category:varchar,contributing_factors:text,risk_factors:text,improvement_suggestions:text,status:varchar,prediction_correct:tinyint,actual_satisfaction_score:integer,prediction_error:integer,predicted_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_sentiment_history`** — `id:integer!,ticket_id:integer,sentiment_score_id:integer,sentiment_trend:varchar,sentiment_change:numeric,positive_score:numeric,negative_score:numeric,neutral_score:numeric,trigger_event:varchar,message_count:integer,metrics:text,created_at:datetime,updated_at:datetime`

**`cs_sentiment_models`** — `id:integer!,name:varchar!,language:varchar,model_type:varchar,provider:varchar,model_identifier:varchar,accuracy:numeric,training_samples:integer,trained_at:datetime,deployed_at:datetime,status:varchar,hyperparameters:text,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cs_sentiment_scores`** — `id:integer!,ticket_id:integer,sentiment_model_id:integer,language_detected:varchar,sentiment:varchar,positive_score:numeric,negative_score:numeric,neutral_score:numeric,confidence:numeric,analyzed_text:text,tokens:text,status:varchar,error_message:text,analyzed_at:datetime,created_at:datetime,updated_at:datetime`

**`cs_team_benchmarking`** — `id:integer!,team_id:integer,benchmark_date:date,team_size:integer,avg_satisfaction_rating:numeric,median_satisfaction_rating:numeric,top_performer_satisfaction:numeric,bottom_performer_satisfaction:numeric,avg_resolution_time_minutes:integer,best_resolution_time_minutes:integer,worst_resolution_time_minutes:integer,avg_escalation_rate:numeric,avg_first_contact_resolution_rate:numeric,avg_nps_score:numeric,avg_quality_score:numeric,avg_productivity_score:numeric,top_performer_rank:integer,bottom_performer_rank:integer,performance_distribution:text,strengths:text,improvement_areas:text,team_trend:numeric,team_performance_rating:varchar,created_at:datetime,updated_at:datetime`

**`cs_urgency_factors`** — `id:integer!,ticket_id:integer,wait_time_hours:numeric,sentiment_factor:numeric,issue_complexity_factor:numeric,agent_skill_factor:numeric,customer_vip_factor:numeric,sla_breach_factor:numeric,repeat_issue_factor:numeric,channel_factor:numeric,business_hours_factor:numeric,concurrent_escalations_factor:numeric,total_urgency_score:numeric,factor_breakdown:text,calculated_at:datetime,created_at:datetime,updated_at:datetime`

**`hd_chat_messages`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,sender_type:varchar!,session_id:integer,sender_id:integer,message:text,sent_at:datetime,type:varchar!,attachments:text,is_read:tinyint!,chat_session_id:integer`

**`hd_chat_sessions`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,visitor_id:varchar,visitor_name:varchar,visitor_email:varchar,started_at:datetime,channel:varchar!,metadata:text,agent_id:integer,ended_at:datetime,status:varchar!,assigned_agent_id:integer,closed_at:datetime,ticket_id:integer`

**`hd_conversation_analytics`** — `id:integer!,company_id:integer,ticket_id:integer,channel:varchar,message_count:integer!,response_count:integer!,first_response_time_seconds:integer,avg_response_time_seconds:integer,resolution_time_seconds:integer,sentiment_score:numeric,delivery_rate:numeric!,read_rate:numeric!,click_rate:numeric!,escalations_count:integer!,transfers_count:integer!,created_at:datetime,updated_at:datetime`

**`hd_development_plans`** — `id:integer!,agent_id:integer!,created_by:integer,goals:text,focus_areas:text,duration_months:integer!,milestones:text,milestones_completed:integer!,status:varchar!,start_date:date,end_date:date,notes:text,created_at:datetime,updated_at:datetime`

**`hd_escalation_events`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,ticket_id:integer,rule_id:integer,triggered_at:datetime,action_taken:varchar,result:varchar`

**`hd_escalation_rules`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,condition:varchar,action:varchar,threshold_hours:integer,is_active:tinyint!,priority:integer!,sla_policy_id:integer,trigger_after:integer!,trigger_type:varchar!,escalate_to_role:varchar,trigger_hours:integer,action_type:varchar,action_config:text`

**`hd_helpdesk_sla_policies`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,priority:varchar!,first_response_hours:integer!,resolution_hours:integer!,business_hours_only:tinyint!,is_default:tinyint!`

**`hd_kb_article_views`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,article_id:integer,user_id:integer,ip_address:varchar,viewed_at:datetime,helpful:tinyint`

**`hd_kb_articles`** — `id:integer!,category_id:integer!,author_id:integer,title:varchar!,slug:varchar!,content:text,view_count:integer!,helpful_count:integer!,unhelpful_count:integer!,is_published:tinyint!,published_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,status:varchar!,created_by:integer,views_count:integer!,excerpt:text,tags:text,not_helpful_count:integer!,reading_time_minutes:integer!,last_reviewed_at:datetime,archived_at:datetime,updated_by:integer,reviewed_by:integer`

**`hd_kb_categories`** — `id:integer!,parent_id:integer,name:varchar!,slug:varchar!,description:text,sort_order:integer!,is_published:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,icon:varchar,is_active:tinyint!,article_count:integer!`

**`hd_kb_portal_articles`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,title:varchar,content:text,category_id:integer,author_id:integer,is_published:tinyint!,views:integer!,slug:varchar,excerpt:varchar,status:varchar!,view_count:integer!,helpful_count:integer!,not_helpful_count:integer!,deleted_at:datetime`

**`hd_kb_portal_categories`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,description:varchar,parent_id:integer,is_active:tinyint!,created_by:integer,slug:varchar,is_public:tinyint!,sort_order:integer!`

**`hd_sla_breaches`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,ticket_id:integer,sla_type:varchar!,breached_at:datetime,policy_id:integer,breach_type:varchar!,acknowledged_at:datetime,breach_minutes:integer,escalated:tinyint!,escalated_at:datetime,notes:text`

**`hd_sla_policies`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,first_response_hours:integer,resolution_hours:integer,is_active:tinyint!,priority:varchar,description:text,response_time_minutes:integer!,resolution_time_minutes:integer!,business_hours_only:tinyint!,escalation_enabled:tinyint!,escalation_after_minutes:integer,is_default:tinyint!,response_time_hours:integer!,resolution_time_hours:integer!,business_hours:text`

**`hd_team_members`** — `id:integer!,team_id:integer!,user_id:integer!,created_at:datetime,updated_at:datetime`

**`hd_teams`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,description:varchar,lead_id:integer,is_active:tinyint!,email:varchar,auto_assignment:tinyint!`

**`hd_ticket_comments`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,ticket_id:integer,user_id:integer,body:text,is_internal:tinyint!`

**`hd_tickets`** — `id:integer!,ticket_number:varchar!,user_id:integer,assigned_to:integer,subject:varchar!,description:text,priority:varchar!,status:varchar!,category:varchar,resolved_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,reporter_id:integer,channel:varchar,sla_breached:tinyint!,sla_due_at:datetime,source_ref:varchar,sla_id:integer,customer_id:integer,team_id:integer,assignee_id:integer,contact_id:integer,type:varchar,satisfaction_score:integer,first_response_at:datetime,source_type:varchar,source_id:integer`

**`helpdesk_bot_deflections`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,question:text,matched_article_id:integer,deflected:tinyint!,ticket_created:tinyint!,session_id:varchar`

**`helpdesk_csat_campaigns`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,trigger:varchar,delay_hours:integer!,question_text:text,active:tinyint!`

**`helpdesk_csat_surveys`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,campaign_id:integer,ticket_id:integer,score:integer,comment:text,status:varchar!,sent_at:datetime,responded_at:datetime,agent_id:integer`

**`helpdesk_forum_posts`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,title:varchar,content:text,author_id:integer,category:varchar,votes:integer!,views:integer!,accepted_answer_id:integer,status:varchar!`

**`helpdesk_forum_replies`** — `id:integer!,thread_id:integer,author_id:integer!,content:text!,is_accepted_answer:tinyint!,upvotes:integer!,created_at:datetime,updated_at:datetime,post_id:integer,votes:integer!,is_accepted:tinyint!`

**`helpdesk_forum_threads`** — `id:integer!,forum_id:integer!,author_id:integer!,title:varchar!,slug:varchar!,content:text!,status:varchar!,views:integer!,is_answered:tinyint!,created_at:datetime,updated_at:datetime,upvotes:integer!`

**`helpdesk_forum_votes`** — `id:integer!,votable_type:varchar!,votable_id:integer!,user_id:integer!,vote:integer!,created_at:datetime,updated_at:datetime`

**`helpdesk_forums`** — `id:integer!,name:varchar!,slug:varchar!,description:text,category:varchar,is_public:tinyint!,sort_order:integer!,created_at:datetime,updated_at:datetime`

**`helpdesk_ticket_assignments`** — `id:integer!,ticket_id:integer!,assigned_to_id:integer,assigned_by_id:integer,assigned_at:datetime,note:text,created_at:datetime,updated_at:datetime`

### Payroll (2 tables)

**`payroll_runs`** — `id:integer!,tenant_id:integer!,period:date!,status:varchar!,currency:varchar!,total_gross:numeric!,total_deductions:numeric!,total_net:numeric!,processed_by:integer,processed_at:datetime,validated_at:datetime,created_at:datetime,updated_at:datetime`

**`payslips`** — `id:integer!,payroll_run_id:integer!,tenant_id:integer!,employee_id:integer!,employee_name:varchar!,period:date!,salary_components:text,gross_salary:numeric!,total_deductions:numeric!,net_salary:numeric!,currency:varchar!,status:varchar!,paid_at:datetime,created_at:datetime,updated_at:datetime`

### Projects (23 tables)

**`prj_automation_rules`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,project_id:integer,trigger:varchar,action:text,is_active:tinyint!,conditions:text,actions:text,active:tinyint!`

**`prj_budget_lines`** — `id:integer!,project_id:integer!,description:varchar!,category:varchar!,ohada_account:varchar,estimated_amount:numeric!,actual_amount:numeric!,phase:varchar,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`prj_epics`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,title:varchar,project_id:integer,description:text,start_date:date,end_date:date,color:varchar,status:varchar!`

**`prj_expense_ledger`** — `id:integer!,budget_line_id:integer!,amount:numeric!,reference:varchar,recorded_at:datetime,created_at:datetime,updated_at:datetime`

**`prj_members`** — `id:integer!,project_id:integer!,user_id:integer,role:varchar!,created_at:datetime,updated_at:datetime`

**`prj_milestones`** — `id:integer!,project_id:integer!,name:varchar!,due_date:date,is_reached:tinyint!,reached_at:datetime,created_at:datetime,updated_at:datetime`

**`prj_project_billing`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,project_id:integer,billing_type:varchar!,rate:numeric,amount:numeric!,hourly_rate:numeric,budget_hours:numeric,budget_amount:numeric,total_billed:numeric!,last_invoice_at:datetime,total_hours:numeric!,status:varchar!`

**`prj_projects`** — `id:integer!,owner_id:integer,name:varchar!,code:varchar,description:text,status:varchar!,start_date:date,end_date:date,budget:numeric,currency:varchar!,color:varchar!,is_billable:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime,company_id:integer`

**`prj_resource_allocations`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,project_id:integer,user_id:integer,task_id:integer,hours_allocated:numeric!,date_from:date,date_to:date,allocation_type:varchar!,start_date:date,end_date:date,percentage:integer!,allocation_percent:numeric!,hours_per_day:numeric,actual_hours_logged:numeric!,status:varchar!,notes:text`

**`prj_resource_capacity`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,user_id:integer,week_start:date,capacity_hours:numeric!,allocated_hours:numeric!,date:date,available_hours:numeric,is_holiday:tinyint!,is_leave:tinyint!,leave_type:varchar,notes:text`

**`prj_risks`** — `id:integer!,project_id:integer!,title:varchar!,description:text,status:varchar!,probability:varchar!,impact:varchar!,probability_score:integer,impact_score:integer,mitigation_plan:text,owner_id:integer,due_date:date,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`prj_sprints`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,project_id:integer,name:varchar,goal:varchar,start_date:date,end_date:date,velocity:integer,status:varchar!,capacity_points:integer`

**`prj_tasks`** — `id:integer!,project_id:integer!,milestone_id:integer,parent_id:integer,assignee_id:integer,created_by:integer,title:varchar!,description:text,status:varchar!,priority:varchar!,start_date:date,due_date:date,estimated_hours:integer!,logged_hours:integer!,sequence:integer!,tags:text,dependencies:text,completed_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,sprint_id:integer,story_points:integer,blocked_by:integer,epic_id:integer`

**`prj_team_members`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,project_id:integer,user_id:integer,role:varchar,allocation_percent:integer!,joined_at:datetime,left_at:datetime,can_edit_tasks:tinyint!,can_manage_members:tinyint!`

**`prj_time_entries`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,project_id:integer,task_id:integer,user_id:integer,hours:numeric!,date:date,description:text,billable:tinyint!,started_at:datetime,ended_at:datetime,duration_minutes:integer,hourly_rate:numeric,billed:tinyint!,invoice_id:integer,timesheet_entry_id:integer,source:varchar`

**`prj_time_logs`** — `id:integer!,project_id:integer!,task_id:integer,user_id:integer,started_at:datetime!,ended_at:datetime,duration_minutes:integer,description:text,billable:tinyint!,hourly_rate:numeric,created_at:datetime,updated_at:datetime`

**`project_billings`** — `id:integer!,tenant_id:integer,project_id:integer,client_id:integer,billing_reference:varchar,invoice_reference:varchar,billing_type:varchar!,amount_ht:numeric!,tva_rate:numeric!,tva_amount:numeric!,amount_ttc:numeric!,currency:varchar!,status:varchar!,billing_date:date,due_date:date,milestone_percentage:numeric,hours_billed:numeric,hourly_rate:numeric,description:text,notes:text,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`project_documents`** — `id:integer!,project_id:integer!,document_id:integer!,attached_by:integer,attached_at:datetime!`

**`project_task_dependencies`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,task_id:integer,depends_on:integer,type:varchar!,depends_on_task_id:integer,lag_days:integer!`

**`projects_custom_field_values`** — `id:integer!,custom_field_id:integer!,entity_type:varchar!,entity_id:integer!,value:text,created_at:datetime,updated_at:datetime`

**`projects_custom_fields`** — `id:integer!,entity_type:varchar!,field_name:varchar!,field_label:varchar!,field_type:varchar!,options:text,is_required:tinyint!,sort_order:integer!,created_by:integer,deleted_at:datetime,created_at:datetime,updated_at:datetime`

**`projects_saved_views`** — `id:integer!,entity_type:varchar!,name:varchar!,filters:text,sort_by:varchar,sort_direction:varchar!,group_by:varchar,visible_columns:text,is_default:tinyint!,is_shared:tinyint!,created_by:integer!,deleted_at:datetime,created_at:datetime,updated_at:datetime`

**`quality_inspections`** — `id:integer!,tenant_id:integer,status:varchar,data:text,created_at:datetime,updated_at:datetime,inspection_code:varchar,product_id:integer,inspector_id:integer,result:varchar!,notes:text,inspected_at:datetime,inspection_type:varchar,quantity_inspected:numeric,quantity_accepted:numeric,quantity_rejected:numeric!,defects_found:integer!,completed_at:datetime`

### Timesheets (5 tables)

**`time_allocations`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,timesheet_entry_id:integer,entry_id:integer,project_id:integer,cost_center_id:integer,task_id:integer,hours_allocated:numeric!,allocation_type:varchar!,hourly_rate:numeric,cost_amount:numeric,description:text,billable:varchar!,deleted_at:datetime`

**`time_tracking_projects`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,code:varchar,department_id:integer,project_id:integer,tracking_code:varchar,description:text,budget_hours:numeric,hours_tracked:numeric!,hours_remaining:numeric,budget_cost:numeric,status:varchar!,start_date:datetime,end_date:datetime,assigned_employees:text,deleted_at:datetime`

**`timesheet_entries`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,employee_id:integer,date:date,project_id:integer,task_id:integer,hours:numeric!,description:text,billable:tinyint!,entry_date:date,hours_worked:numeric!,billable_hours:numeric!,hourly_rate:numeric,status:varchar!,notes:text,submitted_by:integer,submitted_at:datetime,approved_by:integer,approved_at:datetime,approval_notes:text,rejection_reason:text,deleted_at:datetime`

**`ts_project_billing`** — `id:integer!,project_id:integer!,reference:varchar!,billing_type:varchar!,amount:numeric!,tva_amount:numeric!,total_ttc:numeric!,status:varchar!,ohada_account:varchar!,description:text,billing_date:date,milestone_id:integer,percentage:numeric,period_start:date,period_end:date,invoice_reference:varchar,created_at:datetime,updated_at:datetime`

**`ts_timesheet_periods`** — `id:integer!,tenant_id:integer,employee_id:integer!,period_start:date!,period_end:date!,total_hours:numeric!,billable_hours:numeric!,overtime_hours:numeric!,status:varchar!,submitted_by:integer,submitted_at:datetime,approved_by:integer,approved_at:datetime,rejected_reason:text,created_at:datetime,updated_at:datetime`

