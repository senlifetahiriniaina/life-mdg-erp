# HR

## Rôle

Le module HR couvre le périmètre RH « basique » de life-mdg-erp : dossier employé, organisation (départements, postes), présence/pointage (y compris biométrique), congés/absences, documents de conformité RH, compétences de base et support à la rémunération (bandes salariales, historique, déductions). La paie elle-même (bulletins, cycles de paie) est déléguée entièrement au module `Payroll` — HR ne fait que consommer ses modèles en lecture pour le self-service employé.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Employee` | `hr_employees` | Dossier employé — PII chiffrée (national_id, passeport, téléphone, email, coordonnées bancaires) via des colonnes `*_encrypted` (`encrypted`/`encrypted:array`), masquage bancaire PCI DSS via `BankDetailsMaskingService`. Porte désormais `company_id` (Chantier 32.17, cloisonnement multi-tenant réel — voir Particularités) et un audit trail réel via `RecordsActivity` (allowlist de champs non-PII uniquement). |
| `Department` | `hr_departments` | Département/service. Porte désormais `company_id` (Chantier 32.17) et `RecordsActivity`. |
| `JobPosition` | `hr_job_positions` | Poste organisationnel (pas des offres de recrutement — voir Particularités). Porte désormais `company_id` (Chantier 32.17) et `RecordsActivity`. |
| `Attendance` / `AttendanceRecord` | `hr_attendance` / `hr_attendance_records` | Pointage employé — deux sous-systèmes distincts : `Attendance` (CRUD admin, `AttendanceController`) et `AttendanceRecord` (biométrique, `AttendanceBiometricController`). `Attendance`'s real columns are `check_in_time`/`check_out_time` (Chantier 32.17 — see Particularités for the schema-mismatch bug this closed). |
| `AttendanceException` | `hr_attendance_exceptions` | Anomalie de présence à traiter/approuver |
| `AttendanceAnalytics` | `hr_attendance_analytics` | Agrégats analytiques de présence |
| `ShiftSchedule` | `hr_shift_schedules` | Planning d'équipe/horaire |
| `BiometricDevice` | `hr_biometric_devices` | Terminal biométrique de pointage |
| `LeaveRequest` | `hr_leave_requests` | Demande de congé |
| `LeaveType` | `hr_leave_types` | Type de congé (annuel, maladie…) |
| `LeaveApprovalLog` | `hr_leave_approval_log` | Historique des décisions d'approbation de congé |
| `TimeOffRequest` | `hr_time_off_requests` | Demande d'absence (distincte de `LeaveRequest`) |
| `EmployeeDocument` | `hr_employee_documents` | Document de conformité (permis de travail, certificat…) avec suivi d'expiration — backend réel (`DocumentAlertController`), **aucune UI ne l'appelle** (voir Particularités) |
| `EmployeeSkill` / `Skill` | `hr_employee_skills` / `hr_skills` | Étiquetage de compétence simple (pas de matrice de formation) |
| `CompensationHistory` | `hr_compensation_history` | Historique des changements de rémunération |
| `SalaryBand` | `hr_salary_bands` | Grille salariale par niveau/poste |
| `EmployeeCompensation` | `hr_employee_compensation` | Rémunération active d'un employé |
| `Deduction` | `hr_deductions` | Déduction applicable à un employé — modèle réel, table réelle, **zéro contrôleur/route** (voir Particularités — Chantier 32.17) |

`Employee` utilise le trait `HelpdeskLinkable` (tout employé peut générer/lister des tickets Helpdesk) et les relations `department()`, `jobPosition()`/`position()`, `manager()`/`subordinates()` (hiérarchie auto-référentielle), `leaveRequests()`, `attendance()`.

**Modèles supprimés au Chantier 32.17 (confirmés morts, Layer 9)** : `Position`/`hr_positions` (zéro contrôleur/route, zéro écriture réelle — `JobPosition` est le vrai modèle d'organisation utilisé) et `LeaveBalance`/`hr_leave_balances` (redondant avec le calcul déjà réel `days_per_year - days_taken` utilisé partout ailleurs dans ce module). Voir Particularités pour le détail.

## Endpoints principaux

Toutes sous `auth:sanctum, session.security, tenancy.user, module:HR, role:employee,hr-manager,payroll-officer,manager,admin` (corrigé cette session — le module n'avait auparavant aucune gating de ce type sur ses ~50 endpoints), préfixe `/api/v1/hr/` (`HRServiceProvider` → `RouteServiceProvider`) :

| Méthode | Route | Description |
|---|---|---|
| GET | `employees`, `employees/{id}` | Liste / détail employé (cache 1 requête) |
| GET | `employees/by-department/{department}`, `employees/metrics` | Filtrage par département, métriques |
| POST/PUT/DELETE | `employees` | CRUD employé (throttle création) |
| POST | `employees/{employee}/skills` | Ajouter une compétence |
| CRUD | `departments`, `job-positions` | Organisation (cache 1h / 15min) |
| GET | `departments/{department}/metrics` | Métriques département |
| CRUD (lecture) | `leave-requests`, `leaves`, `leave-types` | Congés |
| GET | `leave-requests/pending` | Demandes en attente |
| POST | `leave-requests/{id}/approve`, `/reject` | Approbation / rejet |
| GET | `attendance`, `attendance/status`, `me/attendance`, `employees/{employee}/attendance` | Présence |
| POST | `attendance/clock-in`, `attendance/clock-out` | Pointage |
| POST/PUT/DELETE | `attendance`, `attendance/{id}` | CRUD admin sur `Attendance` (`hr_attendance`) — réellement routé au Chantier 32.17 (méthodes déjà écrites, zéro route avant, réservé aux rôles admin-ish via `isAttendanceAdmin()`) |
| GET | `attendance/statistics` | Statistiques du jour (présents/absents/retard/en congé) — routé au Chantier 32.17 |
| GET | `employees/export` | Export CSV des employés — routé au Chantier 32.17 (méthode réelle préexistante, zéro route avant) |
| GET | `payroll/export/{format}` | Export CSV des bulletins de paie du mois (`silae`/`dsn`/`csv` — voir Particularités pour la nuance sur SILAE/DSN) — nouveau au Chantier 32.17, `PayrollExportController` |
| CRUD (lecture) | `skills` | Compétences |
| CRUD | `salary-bands` | Grilles salariales |
| POST | `salary-bands/{salaryBand}/simulate-raise` | Simulation d'augmentation |
| GET | `me`, `me/payslips`, `me/leave-balance` | Self-service — `me/payslips` renvoie des `Modules\Payroll\Models\Payslip`, réponse `me`/`profile` désormais servie via `SelfServiceEmployeeResource` (voir Particularités — fuite PII corrigée) |
| GET | `employees/{employee}/profile` | Profil complet employé (`EmployeeManagementController`) |
| POST | `employees/onboard`, `employees/{employee}/complete-onboarding`, `employees/{employee}/terminate` | Cycle d'onboarding/terminaison (`EmployeeManagementController`, nouveau cette session) |
| GET | `employees/{employee}/compensation/{current,breakdown,history,bonus-accrual}`, `compensation/audit` | Rémunération détaillée (`CompensationController`, nouveau cette session) |
| POST | `employees/{employee}/compensation`, `.../update-vesting`, `.../benchmark` | Écritures de rémunération (`CompensationController`) |
| GET | `dashboard`, `dashboard/realtime` | Tableau de bord RH — rend désormais `Inertia::render('HR/Dashboard')` (corrigé cette session, voir Particularités) |
| POST | `ai/optimize-leave-planning`, `ai/analyze-payslip`, `ai/detect-payroll-anomalies` | IA RH (`HrAIController` → `HrAIService`) |
| GET/POST | `employee-portal`, `employee-portal/leave-balance`, `employee-portal/leave-requests`, `employee-portal/payslips`, `employee-portal/payslips/{payslip}` | Portail employé |
| PUT/POST | `me`, `self-service/leave-requests` | Mise à jour profil / soumission congé |
| GET/POST/PUT/DELETE | `documents`, `documents/expiring`, `documents/compliance-report`, `documents/{document}/remind` | Suivi d'expiration des documents de conformité (`DocumentAlertController`) |
| GET/POST | `portal/profile`, `portal/leave-balance`, `portal/leave-requests`, `portal/payslips`, `portal/payslips/{payslip}` | Portail self-service (tout utilisateur authentifié, pas seulement `hr-manager`) |
| POST | `ai/assist` | Guidance IA contextuelle (`HRAiAssistController`) |

Routes web (`/hr/…`, session `auth`) : `employees`, `employees/create`, `employees/{employee}`, `employees/{employee}/edit`, `payroll` (vue), `attendance`, `attendance/manage`, `shifts/schedule`, `leave/analytics`, `compensation`, `portal`, `departments`, `leaves`.

## Contrôleurs

API (`Modules/HR/app/Http/Controllers/Api/`) :

- **`EmployeeController`** — CRUD employé, métriques, filtrage par département.
- **`EmployeeManagementController`** (nouveau cette session) — cycle d'onboarding/terminaison/profil complet, construit à partir du service `EmployeeManagementService` réécrit (voir Services) ; `terminate()` réutilise volontairement l'habileté `update` plutôt que `EmployeePolicy::archive()`, la permission `hr.employee.archive` n'ayant jamais été seedée.
- **`CompensationController`** (nouveau cette session) — rémunération détaillée par employé (courant, décomposition, historique, accrual de prime, vesting, benchmark), distinct de `SalaryBandController::equityAnalysis()` (analyse au niveau grille salariale).
- **`EmployeeSelfServiceController`** / **`EmployeePortalController`** — self-service employé ; leurs réponses `me()`/`updateMe()`/`profile()` passent désormais par `SelfServiceEmployeeResource` (voir Particularités — fuite PII corrigée).
- **`DepartmentController`**, **`JobPositionController`**, **`LeaveTypeController`**, **`SalaryBandController`**, **`SkillController`** — CRUD organisation/paramétrage, désormais chacun couvert par une Policy dédiée (voir RBAC).
- **`LeaveController`** / **`LeaveRequestController`** — congés, avec `approve()`/`reject()` désormais gatés par `authorize('approve', ...)` (corrigé cette session — n'importe quel employé authentifié pouvait auparavant approuver/rejeter le congé de n'importe qui).
- **`AttendanceController`** — pointage simple self clock-in/out.
- **`AttendanceBiometricController`** (13 méthodes) — sous-système additif : gestion de terminaux biométriques, vérification de pointage, exceptions, plannings d'équipe, demandes d'absence, analytique — entièrement câblé cette session (routes, migrations, enregistrement de policy — voir Particularités).
- **`DocumentAlertController`** — suivi d'expiration des documents de conformité. **Réel, testé (`DocumentExpiryTest.php`), zéro consommateur UI** (confirmé au Chantier 32.17 — aucune page Vue n'appelle jamais `documents*`) — Catégorie C, non construit.
- **`HrAIController`** / **`HRAiAssistController`** — IA métier / guidance contextuelle. `HrAIController`'s 3 méthodes sont réelles/testées mais **sans consommateur UI** (Catégorie C, Chantier 32.17). L'URL de `HRAiAssistController::assist()` était doublement préfixée (`api/v1/hr/v1/hr/ai/assist`, 404 systématique) — corrigé au Chantier 32.17 (même bug de classe que Setup au Chantier 8.5sv) ; reste sans appelant frontend réel (le composable générique `useAiAssistant()` appelle `POST /api/v1/ai/assist`, pas ce endpoint dédié — motif déjà documenté au Chantier 32.2).
- **`HrDashboardController`** — tableau de bord RH.
- **`PayrollExportController`** (nouveau, Chantier 32.17) — export CSV des bulletins de paie, ferme le 404 des 3 boutons d'export de `HR/Payroll/Index.vue`.

Web (`Modules/HR/app/Http/Controllers/Web/`) : **`EmployeeWebController`** (index/create/edit/show/payroll/attendance/schedule/portal/compensation), **`LeaveAnalyticsWebController`**. Un `HRController` scaffold mort (zéro route, vues Blade jamais réelles) a été supprimé cette session.

## Vues (Vue/Inertia)

Résolution racine-first (`resources/js/Pages/HR/...` prioritaire sur `Modules/HR/resources/js/Pages/...` à nom identique — voir `resources/js/app.js::resolve()`) :

- **Racine** (`resources/js/Pages/HR/`) : `Dashboard.vue` (corrigé cette session — la route rendait auparavant une vue Blade `hr::dashboard` inexistante, 404 systématique), `Employees/{Index,Show}.vue`, `Attendance/Index.vue` (page personnelle de pointage — gagne la collision de nom sur `attendance`), `Portal.vue`, `Compensation/Index.vue`, `Payroll/Index.vue`. Chantier 10 : les 5 pages hors périmètre (`Recruitment`, `Succession`, `Training`, `Learning`, `Performance`) — confirmées appeler des endpoints qui n'ont jamais existé (`/api/v1/hr/perf-cycles`, `succession-v2`, `jobs`, `courses`, `enrollments`, `learning-paths`, `skills` en écriture — aucune trace dans `Modules/HR/routes/api.php`) et sans **aucune** route Inertia/render() les atteignant nulle part dans le dépôt — ont été **supprimées** plutôt que laissées comme code mort ou construites : ATS/recrutement/formation/succession/évaluation 360° restent une exclusion de périmètre délibérée et documentée (voir `CLAUDE.md`), pas un gap à combler.
- **Module** (`Modules/HR/resources/js/Pages/`) : `Employees/Form.vue` (create/edit), `Departments/Index.vue` (reconstruite cette session sur le vrai champ `status` — l'ancienne version supposait une hiérarchie `parent_id`/`is_active` jamais reliée au modèle), `Leaves/Index.vue`, `Leave/Analytics.vue` (reconstruite sur un vrai service `HrDashboardService::getLeaveAnalytics()` — avant cette session, 100% de données factices `Math.random()`), `Shifts/Schedule.vue` (adaptée cette session à la vraie forme de `ShiftSchedule` — modèles de planning hebdomadaires récurrents, pas un calendrier mensuel par date), `Attendance/Manage.vue` (page CRUD admin réelle, renommée cette session pour lever une collision de nom avec la page racine de pointage personnel).

**Chantier 32.17 — les 13 pages réelles ci-dessus appellent désormais toutes `useAiAssistant()`** (aucune ne le faisait avant, confirmé par grep — voir Particularités). Bugs de navigation/données réels trouvés et corrigés sur ces mêmes pages : `Employees/Index.vue` (boutons « Ajouter »/« Exporter » et clic de ligne totalement morts, `Paginator` sans gestionnaire), `Employees/Show.vue` (bloc « Soldes de congés » 100% factice + salaire jamais affiché + aucun lien « Modifier »), `Attendance/Manage.vue` (`fetch()` sans en-tête CSRF sur les mutations, bouton « Edit » réduit à un `console.log`), `HR/Dashboard.vue` (le bouton « Reject » de la file de congés en attente échouait systématiquement en 422, aucune saisie de motif).

Pages dupliquées mortes supprimées cette session : anciennes `Employees/{Index,Show}.vue`/`Leaves/RequestForm.vue` côté module (masquées par les vraies pages racine), un `EmployeeNode.vue` orphelin.

## Services

- **`EmployeeManagementService`** — réécrit cette session : `onboardEmployee()`/`getEmployeeProfile()` référençaient des colonnes `Employee` inexistantes (`department`/`position`/`salary` — les vraies colonnes sont `department_id`/`job_position_id`, le salaire vivant sur `EmployeeCompensation`) ; corrigées. `updateEmployee()`/`getEmployeesByDepartment()` ont été supprimées comme 100% redondantes avec `EmployeeController::update()`/`byDepartment()`, déjà réels et routés.
- **`AttendanceService`** — pointage simple (clock-in/out).
- **`CompensationService`** — rémunération totale, vesting, primes ; câblée sur `CompensationController` cette session (était réelle et correcte mais totalement non routée). `generateOfferLetterData()`/`getBenefitsDetails()` restent non exposés par une route — artefact de recrutement hors du périmètre RH « basique ».
- **`DocumentExpiryService`** — documents arrivant à expiration, rapport de conformité (commande planifiée `CheckDocumentExpiry`).
- **`BankDetailsMaskingService`** — masquage PCI DSS des coordonnées bancaires (4 derniers chiffres visibles), désormais réellement exposé côté self-service via `SelfServiceEmployeeResource` (voir Particularités).
- **`HrDashboardService`** — statistiques RH agrégées (effectif, absences du jour, ancienneté moyenne) + `getLeaveAnalytics()` (nouveau cette session, remplace les données factices de `Leave/Analytics.vue`).
- **`HRService`** — services génériques transverses au module.
- **`AI\HrAIService`** — appelle `Modules\Core\Services\AI\AIService::ask()` pour l'optimisation de planning de congés, l'analyse de bulletin de paie et la détection d'anomalies de paie.

Deux observers (`EmployeeObserver`, `LeaveRequestObserver`) diffusent les événements `HrEmployeeUpdated` / `HrLeaveRequestUpdated` (`App\Events`) sur `created`/`updated`.

**Services supprimés cette session** — `AbsenceManagementService` et `AdvancedAttendanceService` (~1 000 lignes combinées, zéro consommateur contrôleur) se sont révélés être des sous-systèmes parallèles cassés/redondants plutôt que des fonctionnalités à finir : `AbsenceManagementService` dupliquait le flux congés déjà réel de `LeaveController`/`LeaveRequestController` avec un type de congé américain codé en dur et une vérification FMLA (droit du travail fédéral américain, hors périmètre — voir `CLAUDE.md`) ; `AdvancedAttendanceService` contenait des valeurs factices codées en dur (`$isEnrolled = true`, taux horaire à 50$ fixe) et écrivait sur des noms de colonnes inexistants sur `AttendanceRecord`/`LeaveRequest`. Les deux ont été supprimés plutôt que réparés, après validation explicite de l'utilisateur — voir `CLAUDE.md` pour le détail complet de la décision.

## Permissions RBAC

Préfixe `hr.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `employee`, `department`, `job-position`, `leave`, `leave-type`, actions standard `view-any|view|create|update|delete`. Le rôle `hr-manager` reçoit toutes les permissions `hr.*` + `payroll.*` + `timesheets.*`.

Corrections RBAC de cette session (`HR_EXTRA_PERMISSIONS`, nouveau bloc du seeder) :
- `hr.documents.*` (view/create/edit/delete/remind) pour `DocumentAlertController`, qui n'avait aucune permission seedée alors qu'il gate chaque action.
- `hr.salary-band.*` et `hr.skill.*` — `SalaryBandController`/`SkillController` n'avaient auparavant **aucune classe Policy du tout** (trou RBAC réel, corrigé par `SalaryBandPolicy`/`SkillPolicy`, nouveaux) et ces deux ressources n'apparaissaient pas dans `MODULES['hr']`.
- `hr.attendance.*` (13 permissions : `view`, `view-personal`, `record`, `manage-devices`, `verify-records`, `handle-exceptions`, `approve-exception`, `request-time-off`, `approve-time-off`, `reject-time-off`, `manage-shifts`, `view-analytics`, `export`) — backant `AttendancePolicy`, désormais réellement seedées (c'était un vrai trou avant cette session : `AttendanceBiometricController` avait zéro route et `AttendancePolicy` n'était satisfaisable par aucun rôle non-`super-admin`).

`DepartmentPolicy`/`JobPositionPolicy`/`LeaveTypePolicy` (nouvelles cette session) couvrent les 3 contrôleurs qui n'avaient auparavant aucune classe Policy. `EmployeePolicy` vérifie toujours `hr.employee.approve`/`.export` (non couvertes par la liste `ACTIONS` générique) ; `.archive` reste non seedée par choix (voir `EmployeeManagementController::terminate()` ci-dessus, qui contourne délibérément cette permission manquante).

Fuite PII corrigée cette session : `EmployeeSelfServiceController::me()`/`updateMe()` et `EmployeePortalController::profile()` renvoyaient auparavant `response()->json($employee)` brut, sérialisant en clair `bank_details_encrypted`/`national_id`/`passport_number` déchiffrés. Une nouvelle `SelfServiceEmployeeResource` n'expose que `masked_bank_details` (via `BankDetailsMaskingService`) et omet totalement les deux champs d'identité.

`LeaveRequestPolicy::update()` comparait `$model->employee_id` (un `hr_employees.id`) directement à `$user->id` (un `users.id`) — un employé ne pouvait jamais passer ce contrôle sur sa propre demande de congé ; corrigé en comparant `$model->employee?->user_id`. `LeaveRequestController::approve()`/`reject()` n'avaient aucun `authorize()` du tout avant cette session (n'importe quel employé pouvait approuver/rejeter n'importe quelle demande) — corrigé.

**Chantier 32.17 — cloisonnement multi-tenant réel sur Employee/Department/JobPosition** (gap confirmé deux fois auparavant — Chantier 19 Lot 2 — et jamais corrigé) : `company_id` ajouté aux trois tables (nullable/indexé), peuplé sur les 3 vrais chemins de création (`EmployeeController::store()`, `EmployeeManagementService::onboardEmployee()`, `LeaveRequestController::store()`'s auto-création d'employé), `index()` scopé via `when($request->user()?->company_id, ...)` (no-op si absent, motif déjà établi par Projects/CRM), et `EmployeePolicy`/`DepartmentPolicy`/`JobPositionPolicy::view/update/delete` gagnent un `sameCompany()` (`(int)($user->company_id ?? 0) === (int)($model->company_id ?? 0)`). `LeaveRequestController::show()` avait zéro `authorize()` — corrigé (defense-in-depth ; `App\Policies\LeaveRequestPolicy::view()`, hors périmètre de fichier de ce chantier, reste vacuously `true` — gap documenté, non fermé, voir Particularités).

## Dépendances avec d'autres modules

- **Payroll** : `Modules\Payroll\Models\Payslip` est utilisé par `EmployeeSelfServiceController`, `EmployeePortalController` et lié en route-model-binding dans `HR\Providers\RouteServiceProvider` (`Route::bind('payslip', …)`) — HR ne fait que lire les bulletins produits par Payroll. Inversement, `Modules\Payroll\Services\PayrollIntegrationService` consomme désormais `Modules\HR\Models\Employee` et `Modules\HR\Models\EmployeeCompensation` (voir `docs/03-MODULES/Payroll.md`) — l'ancien bug où ce service référençait des classes `PayrollRecord`/`PayrollPeriod` inexistantes dans HR a été corrigé cette session (n'est plus une particularité active de ce module).
- **Helpdesk** : `Employee` utilise le trait `HelpdeskLinkable`.
- **Timesheets** : `Modules\Payroll\Services\PayrollIntegrationService` lit `Modules\Timesheets\Models\TimesheetEntry` pour dériver les heures supplémentaires (indirect, via Payroll — pas d'import direct de HR vers Timesheets).
- **Core** : `HrAIService` s'appuie sur `Modules\Core\Services\AI\AIService`.
- **AI** : `HRAiAssistController` utilise `AiContextualAssistantService`.
- Aucune dépendance résiduelle vers `Modules\Planning\*` n'a été trouvée dans le code actuel (voir Particularités).

## Particularités du périmètre life-mdg-erp

- **Duplication Payroll supprimée, confirmée dans le code, et le bug résiduel qui subsistait est désormais corrigé** : `Modules/HR/app/Models/` ne contient toujours aucun modèle `Payroll`/`PayrollRun`/`PayrollCalculation`/`Payslip` — la seule trace de paie côté HR reste la consommation en lecture de `Modules\Payroll\Models\Payslip`. Le module `Payroll` est bien l'unique source de vérité, conformément à `CLAUDE.md`. Le reliquat documenté précédemment ici (`PayrollIntegrationService`/`PayrollController` important des classes `Modules\HR\Models\PayrollRecord`/`PayrollPeriod` inexistantes) a été réécrit cette session pour opérer directement sur `Modules\HR\Models\EmployeeCompensation` — voir `docs/03-MODULES/Payroll.md`.
- **Découplage de `Modules\Planning\Models\EmployeeSchedule` vérifié** : aucune relation ni import de `Modules\Planning\*` n'existe dans `Employee.php` ni ailleurs dans `Modules/HR/app/`. `AdvancedAttendanceService` (supprimé cette session) était le seul rapprochement lexical trouvé, et il travaillait avec `ShiftSchedule` (modèle HR local), pas avec le module `Planning` exclu du périmètre.
- Le scope RH « basique » se reflète aussi dans le naming : `JobPosition` porte un commentaire explicite dans les routes (« basic org structure — not to be confused with recruitment job postings ») et `SkillController`/`Skill` sont qualifiés de « basic skill tagging, no training catalogue / skill matrix visualization » — cohérent avec le retrait de l'ATS/recrutement et du catalogue de formation.
- **`AttendanceBiometricController`** (device biométrique, vérification, exceptions, plannings, demandes d'absence, analytique) était entièrement écrit mais totalement non câblé avant cette session : zéro route, 6 tables manquantes (`BiometricDevice`, `TimeOffRequest`, `AttendanceException`, `AttendanceAnalytics`, `CompensationHistory`, `Deduction`), et `AttendancePolicy` (qui couvre 5 modèles différents) jamais enregistrée auprès du Gate. Tout est désormais réel : migration ajoutée, `HRServiceProvider::registerPolicies()` (nouveau) enregistre les 5 liaisons de policy, routes montées sous `biometric-devices`/`attendance-records`/`attendance-exceptions`/`shifts`/`time-off-requests`/`attendance-analytics`.

## Chantier 32.17 — audit approfondi en 14 couches (méthodologie CLAUDE.md)

Premier audit HR selon la méthodologie 14 couches (au-delà des 7 couches historiques déjà appliquées aux Chantiers 8.3/10/19/20/31). Empirique de bout en bout — chaque bug ci-dessous a été confirmé par `tinker`/requête HTTP réelle avant correction, verrouillé par `Modules/HR/tests/Feature/Chantier32HrDeepAuditTest.php` (14 tests, appelant les vraies routes HTTP).

**Bug le plus sévère (Layer 5, format de données)** : `Attendance` (`hr_attendance`, backing `AttendanceController::index/store/update/destroy` et la page admin `Attendance/Manage.vue`) déclarait `check_in`/`check_out` dans `$fillable` — colonnes qui **n'ont jamais existé** sur la vraie table catch-all-scaffoldée (4 tentatives de nommage jamais réconciliées : `clock_in`/`clock_out` datetime, `attendance_date`/`check_in_time`/`check_out_time` date/time). Confirmé via `tinker` : `Attendance::create()` avec ces clés était une erreur SQL fatale garantie sur **chaque appel réel** — invisible jusqu'ici uniquement parce qu'aucune route n'existait pour `store()`/`update()`/`destroy()` (voir ci-dessous) et qu'`AttendanceFactory` utilisait les mêmes mauvaises clés, donc aucun test n'exerçait jamais le vrai chemin d'insertion. Corrigé : `$fillable` repointé sur les vraies colonnes `check_in_time`/`check_out_time`, exposées côté JSON via des accesseurs `check_in`/`check_out` (`$appends`) pour ne rien changer au contrat déjà consommé par `Attendance/Manage.vue`. La colonne `notes` (collectée par le vrai formulaire admin) n'existait pas non plus — ajoutée par migration. `AttendanceFactory` corrigée en même temps.

**Layer 1/2 (route/contrôleur), active breakage** : `AttendanceController::store()`/`update()`/`destroy()`/`statistics()` étaient des méthodes réelles et correctement écrites, mais **zéro route enregistrée nulle part** (confirmé via `php artisan route:list`) — les boutons « Mark Attendance »/« Delete » de la vraie page admin `Attendance/Manage.vue` ont toujours 404. Routes ajoutées, gardées par un nouveau `AttendanceController::isAttendanceAdmin()` (même liste de rôles que le check déjà utilisé par `index()`) puisque marquer/supprimer la présence d'un employé arbitraire n'a pas vocation à être ouvert au rôle `employee` large présent dans le groupe de route externe. `EmployeeController::export()` (CSV, réel, bien écrit) avait le même problème — route ajoutée, et le bouton « Exporter »/« Ajouter » de `Employees/Index.vue` (**zéro gestionnaire de clic sur toute la page**, y compris le clic de ligne malgré un `cursor:pointer` explicite et le `Paginator`) enfin câblés.

**Layer 6 (sécurité), deux fuites PII réelles trouvées et corrigées** : (1) `AttendanceController::index()`'s `with('employee')` chargeait le modèle `Employee` **complet** (national_id/passport_number/bank_details déchiffrés en clair si renseignés), contournant la discipline de rédaction déjà établie partout ailleurs dans ce module — corrigé en ne chargeant que `id,first_name,last_name,department_id`+`department:id,name`. (2) `EmployeeWebController::show()` passait le modèle `$employee` **brut** à `Inertia::render()` — les props Inertia sont sérialisées directement dans le HTML initial de la page, donc chaque champ PII décrypté se retrouvait dans le code source d'une page gardée par le seul middleware `auth` (aucune restriction de rôle) — même classe de fuite déjà corrigée une fois côté API (`SelfServiceEmployeeResource`, Chantier 8.3) mais jamais fermée côté web. Corrigé en passant par `EmployeeResource` (jamais de PII) + calcul serveur des vraies infos manquantes (voir ci-dessous).

**Layer 3 (Vue), données factices et liens morts trouvés sur `Employees/Show.vue`** : le panneau « Soldes de congés » était un tableau **littéralement codé en dur** (`{ type: 'Congés annuels', total: 20, remaining: 12 }`), la même classe de bug déjà corrigée une fois pour `Leave/Analytics.vue` (Chantier 8.3) mais jamais fermée ici — remplacé par un vrai calcul serveur (`days_per_year - days_taken`, même formule que `EmployeeSelfServiceController::leaveBalance()`). Le champ « Salaire » lisait `employee.salary`, une clé qu'`EmployeeResource` n'a jamais exposée (par conception, discipline PII) — remplacé par le vrai salaire courant résolu depuis `EmployeeCompensation` (formatage `Ar`/devise réelle, pas `€` codé en dur). Aucun lien « Modifier » n'existait vers le vrai formulaire d'édition déjà routé — ajouté.

**Layer 6, `Attendance/Manage.vue`** : tous les appels mutants (`POST`/`DELETE`) utilisaient `fetch()` brut sans en-tête CSRF — cette app active `statefulApi()` de Sanctum, `axios` attache automatiquement `X-XSRF-TOKEN`, `fetch()` non ; même classe de bug déjà trouvée sur 9 pages Inventory/Logistics au Chantier 19 Lots 4-5, invisible à Pest (`VerifyCsrfToken` contourne en `APP_ENV=testing`). Convertis en `axios`. Le bouton « Edit » de chaque ligne était un `console.log()` — aucun dialogue réel ; câblé pour rouvrir le vrai dialogue de création en mode édition, `saveAttendance()` branchant `PUT` vs `POST`.

**Layer 3, `HR/Dashboard.vue`** : `handleLeave(id, 'reject')` postait sans corps — `LeaveController::reject()` exige `rejection_reason` (requis, non vide) — chaque clic « Reject » de la file de congés en attente échouait systématiquement en 422, silencieusement avalé par le `catch`. Corrigé en collectant un motif avant l'appel.

**Layer 4/5, `HRService::getHRMetrics()`/`getDepartmentMetrics()`** — le même bug de classe déjà corrigé une fois pour `HrDashboardService::getStats()` (Chantier 19) n'avait pas été répercuté sur ces deux méthodes sœurs : `open_positions` retournait un nombre négatif confirmé (`-10` sur données réelles), puisque `Position::sum('headcount')` interrogeait la table `hr_positions` confirmée-morte (toujours 0). `getDepartmentMetrics()`'s `total_payroll` était silencieusement toujours `0` — `sum('salary')` interrogeait une colonne `salary` qui n'a jamais existé sur `hr_employees` (SQLite camoufle un identifiant non résolu en résultat vide plutôt que de lever une erreur, même motif déjà documenté pour `Shared\CountryController`). Corrigé : `open_positions` ramené à `0` (repli honnête, aucune source de données de cible d'effectif par poste n'existe dans ce périmètre trimmé), `total_payroll` calculé pour de vrai depuis les vrais enregistrements `EmployeeCompensation` courants des employés du département.

**Layer 9 (fake/dead), décisions explicites** :
- **`Modules\HR\Services\EmployeeService`** — SUPPRIMÉ. Zéro appelant nulle part (ni contrôleur, ni test) — entièrement redondant avec `HRService`/`EmployeeController`, sauf une règle métier réelle et jamais câblée (« impossible de supprimer un employé actif sans forcer ») — repliée dans `EmployeeController::destroy()` (409 par défaut, `?force=1` pour outrepasser) avant suppression du service.
- **`Position`/`hr_positions`** — SUPPRIMÉ (modèle + table + factory). Zéro contrôleur/route consommateur nulle part, zéro écriture réelle (même `DemoSeeder`), déjà documenté comme mort au Chantier 19 pour un seul appelant (`HrDashboardService`) mais jamais réellement supprimé ni traité pour ses 3 autres appelants orphelins (`HRService::createPosition/updatePosition/getPositionsByDepartment`, `Department::positions()`). `PositionResource` (en réalité toujours appliqué au vrai modèle `JobPosition`, jamais à `Position` — champs `salary_min`/`salary_max`/`headcount`/`status` toujours `null`) supprimé et remplacé par le vrai `JobPositionResource` dans `EmployeeResource`/`SelfServiceEmployeeResource`. `Department::jobPositions()` (nouveau) remplace `positions()`.
- **`LeaveBalance`/`hr_leave_balances`** — SUPPRIMÉ (modèle + table + factory). Zéro contrôleur/service consommateur nulle part — doublon confirmé de l'approche déjà réelle et fonctionnelle (`days_per_year - days_taken`, calculée à la volée) utilisée par tous les vrais points de terminaison de solde de congés de ce module.
- **`Deduction`/`hr_deductions`** — modèle réel, table réelle, logique métier réelle (`isEffective()`/`canDeductAmount()`/`addToYTD()`), **zéro contrôleur/route**. Explicitement listé comme dans le périmètre RH « basique » par `CLAUDE.md` lui-même — pas du code mort à supprimer, un gap d'activation (même précédent que `Modules\Core\Models\CustomField` au Chantier 32.1) — documenté, non construit dans ce chantier faute de budget.
- **`CompensationController`** (7 méthodes), **`DocumentAlertController`** (8 méthodes), **`HrAIController`** (3 méthodes) — réels, testés, RBAC-corrects, **zéro appelant UI**. Catégorie C — documentés, non construits (aucune UI existante n'est le foyer naturel évident pour ces fonctionnalités sans en inventer une).
- **`HrDashboardService::getRecruitmentFunnel()`** interroge `hr_job_postings` (table de scaffold jamais réellement écrite, ATS/recrutement étant hors périmètre) — dégrade honnêtement vers un tableau vide, non appelée par `HrDashboardController::index()`'s réel consommateur (`Dashboard.vue`) ; laissée en l'état (motif de repli déjà établi, non nuisible).

**Layer 11 (CORE), audit trail** : aucun modèle HR n'utilisait `Modules\Core\Traits\RecordsActivity` (le vrai audit trail DB de l'app) malgré la sensibilité PII/conformité de ce module. Ajouté à `Employee` (avec un `$auditableFields` explicite limité aux champs non-PII — le comportement par défaut du trait sérialise `toArray()` en entier, ce qui aurait **réintroduit** la fuite PII déjà corrigée deux fois ailleurs dans ce module si laissé sans allowlist, confirmé/testé empiriquement), `Department`, `JobPosition` (ces deux derniers sans PII, comportement par défaut du trait laissé tel quel).

**Layer 13 (IA)** : `AiContextualAssistantService::supportedModules()['HR']` ne listait que 4 actions (`create_employee`, `approve_leave`, `run_payroll`, `onboarding`) et **aucune des 13 pages Vue réelles du module n'appelait `useAiAssistant()`** (confirmé par grep, même motif de classe déjà trouvé pour Strategy/Validation/CRM/Sales). 11 nouvelles actions ajoutées avec un vrai texte de repli fr+en (`view_dashboard`, `clock_attendance`, `view_compensation`, `view_employees_list`, `view_employee_detail`, `view_payslips`, `employee_portal`, `manage_attendance`, `manage_departments`, `view_leave_analytics`, `manage_shifts`), plus réutilisation de `create_employee` (`Employees/Form.vue`) et `approve_leave` (`Leaves/Index.vue`, déjà réelles). Les méthodes portant le contenu ont été nommées `frenchMapHrDeepAudit()`/`englishMapHrDeepAudit()` plutôt que `frenchMapChantier3217()`/`englishMapChantier3217()` (nom initialement prévu) — un agent concurrent auditant un autre module avait déjà revendiqué ce nom exact sur ce même fichier partagé au moment de l'écriture.

**Layer 1, route double-préfixée** : `HRAiAssistController::assist()` était enregistrée sous `api/v1/hr/v1/hr/ai/assist` (préfixe `api/v1/hr` déjà appliqué par `RouteServiceProvider`, un second `prefix('v1/hr')` empilé par-dessus dans `routes/api.php`) — même bug de classe déjà corrigé une fois pour Setup au Chantier 8.5sv, jamais appliqué ici. Corrigé ; reste sans appelant frontend réel (motif déjà documenté au Chantier 32.2 — `useAiAssistant()` appelle toujours l'endpoint générique).

**Gap confirmé, non fermé (hors périmètre de fichier de ce chantier)** : `App\Policies\LeaveRequestPolicy::view()` (racine `app/Policies/`, pas `Modules/HR/**`) retourne `true` sans condition pour tout utilisateur authentifié — même motif déjà documenté ailleurs dans cette app pour `BaseErpPolicy`-derived `view()` (ex. `ProjectPolicy`). `LeaveRequestController::show()` a désormais un vrai appel `authorize()` (defense-in-depth, cohérence avec les méthodes sœurs) mais la Policy elle-même reste permissive par conception — n'importe quel employé authentifié peut toujours consulter le détail d'une demande de congé de n'importe quel autre employé (potentiellement le motif — une information sensible). Flagué pour un futur chantier dédié.

**Vérifié** : `php artisan migrate:fresh --seed` propre ; suite complète `Modules/HR` verte (146 passés = 132 de référence + 14 nouveaux) ; `Modules/Payroll` (88), `Modules/Helpdesk` (292), `Modules/Timesheets` (186), `Modules/AI` (90, fichier partagé `AiContextualAssistantService.php`) re-vérifiées sans régression ; `npm run type-check`/`npm run build` propres ; `php -l` balayé sur tous les fichiers PHP modifiés/créés.
