# HR

## Rôle

Le module HR couvre le périmètre RH « basique » de life-mdg-erp : dossier employé, organisation (départements, postes), présence/pointage (y compris biométrique), congés/absences, documents de conformité RH, compétences de base et support à la rémunération (bandes salariales, historique, déductions). La paie elle-même (bulletins, cycles de paie) est déléguée entièrement au module `Payroll` — HR ne fait que consommer ses modèles en lecture pour le self-service employé.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Employee` | `hr_employees` | Dossier employé — PII chiffrée (national_id, passeport, téléphone, email, coordonnées bancaires) via des colonnes `*_encrypted` (`encrypted`/`encrypted:array`), masquage bancaire PCI DSS via `BankDetailsMaskingService` |
| `Department` | `hr_departments` | Département/service |
| `JobPosition` / `Position` | `hr_job_positions` / `hr_positions` | Poste organisationnel (pas des offres de recrutement — voir Particularités) |
| `Attendance` / `AttendanceRecord` | `hr_attendance` / `hr_attendance_records` | Pointage employé |
| `AttendanceException` | `hr_attendance_exceptions` | Anomalie de présence à traiter/approuver |
| `AttendanceAnalytics` | `hr_attendance_analytics` | Agrégats analytiques de présence |
| `ShiftSchedule` | `hr_shift_schedules` | Planning d'équipe/horaire |
| `BiometricDevice` | `hr_biometric_devices` | Terminal biométrique de pointage |
| `LeaveRequest` | `hr_leave_requests` | Demande de congé |
| `LeaveBalance` | `hr_leave_balances` | Solde de congés par employé/type |
| `LeaveType` | `hr_leave_types` | Type de congé (annuel, maladie…) |
| `LeaveApprovalLog` | `hr_leave_approval_log` | Historique des décisions d'approbation de congé |
| `TimeOffRequest` | `hr_time_off_requests` | Demande d'absence (distincte de `LeaveRequest`) |
| `EmployeeDocument` | `hr_employee_documents` | Document de conformité (permis de travail, certificat…) avec suivi d'expiration |
| `EmployeeSkill` / `Skill` | `hr_employee_skills` / `hr_skills` | Étiquetage de compétence simple (pas de matrice de formation) |
| `CompensationHistory` | `hr_compensation_history` | Historique des changements de rémunération |
| `SalaryBand` | `hr_salary_bands` | Grille salariale par niveau/poste |
| `EmployeeCompensation` | `hr_employee_compensation` | Rémunération active d'un employé |
| `Deduction` | `hr_deductions` | Déduction applicable à un employé (support à la paie, hors module Payroll) |

`Employee` utilise le trait `HelpdeskLinkable` (tout employé peut générer/lister des tickets Helpdesk) et les relations `department()`, `jobPosition()`/`position()`, `manager()`/`subordinates()` (hiérarchie auto-référentielle), `leaveRequests()`, `attendance()`.

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
- **`DocumentAlertController`** — suivi d'expiration des documents de conformité.
- **`HrAIController`** / **`HRAiAssistController`** — IA métier / guidance contextuelle.
- **`HrDashboardController`** — tableau de bord RH.

Web (`Modules/HR/app/Http/Controllers/Web/`) : **`EmployeeWebController`** (index/create/edit/show/payroll/attendance/schedule/portal/compensation), **`LeaveAnalyticsWebController`**. Un `HRController` scaffold mort (zéro route, vues Blade jamais réelles) a été supprimé cette session.

## Vues (Vue/Inertia)

Résolution racine-first (`resources/js/Pages/HR/...` prioritaire sur `Modules/HR/resources/js/Pages/...` à nom identique — voir `resources/js/app.js::resolve()`) :

- **Racine** (`resources/js/Pages/HR/`) : `Dashboard.vue` (corrigé cette session — la route rendait auparavant une vue Blade `hr::dashboard` inexistante, 404 systématique), `Employees/{Index,Show}.vue`, `Attendance/Index.vue` (page personnelle de pointage — gagne la collision de nom sur `attendance`), `Portal.vue`, `Compensation/Index.vue`, `Payroll/Index.vue`. Chantier 10 : les 5 pages hors périmètre (`Recruitment`, `Succession`, `Training`, `Learning`, `Performance`) — confirmées appeler des endpoints qui n'ont jamais existé (`/api/v1/hr/perf-cycles`, `succession-v2`, `jobs`, `courses`, `enrollments`, `learning-paths`, `skills` en écriture — aucune trace dans `Modules/HR/routes/api.php`) et sans **aucune** route Inertia/render() les atteignant nulle part dans le dépôt — ont été **supprimées** plutôt que laissées comme code mort ou construites : ATS/recrutement/formation/succession/évaluation 360° restent une exclusion de périmètre délibérée et documentée (voir `CLAUDE.md`), pas un gap à combler.
- **Module** (`Modules/HR/resources/js/Pages/`) : `Employees/Form.vue` (create/edit), `Departments/Index.vue` (reconstruite cette session sur le vrai champ `status` — l'ancienne version supposait une hiérarchie `parent_id`/`is_active` jamais reliée au modèle), `Leaves/Index.vue`, `Leave/Analytics.vue` (reconstruite sur un vrai service `HrDashboardService::getLeaveAnalytics()` — avant cette session, 100% de données factices `Math.random()`), `Shifts/Schedule.vue` (adaptée cette session à la vraie forme de `ShiftSchedule` — modèles de planning hebdomadaires récurrents, pas un calendrier mensuel par date), `Attendance/Manage.vue` (page CRUD admin réelle, renommée cette session pour lever une collision de nom avec la page racine de pointage personnel).

Pages dupliquées mortes supprimées cette session : anciennes `Employees/{Index,Show}.vue`/`Leaves/RequestForm.vue` côté module (masquées par les vraies pages racine), un `EmployeeNode.vue` orphelin.

## Services

- **`EmployeeService`** — cycle de vie employé basique (création, mise à jour), consommé par `EmployeeController`.
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
