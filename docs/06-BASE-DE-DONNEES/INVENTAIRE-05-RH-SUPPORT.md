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
