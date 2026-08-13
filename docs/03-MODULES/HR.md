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

Toutes sous `auth:sanctum`, préfixe `/api/v1/hr/` (`HRServiceProvider` route `RouteServiceProvider`) :

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
| GET | `me`, `me/payslips`, `me/leave-balance` | Self-service — `me/payslips` renvoie des `Modules\Payroll\Models\Payslip` |
| GET | `dashboard`, `dashboard/realtime` | Tableau de bord RH |
| POST | `ai/optimize-leave-planning`, `ai/analyze-payslip`, `ai/detect-payroll-anomalies` | IA RH (`HrAIController` → `HrAIService`) |
| GET/POST | `employee-portal`, `employee-portal/leave-balance`, `employee-portal/leave-requests`, `employee-portal/payslips`, `employee-portal/payslips/{payslip}` | Portail employé |
| PUT/POST | `me`, `self-service/leave-requests` | Mise à jour profil / soumission congé |
| GET/POST/PUT/DELETE | `documents`, `documents/expiring`, `documents/compliance-report`, `documents/{document}/remind` | Suivi d'expiration des documents de conformité (`DocumentAlertController`) |
| GET/POST | `portal/profile`, `portal/leave-balance`, `portal/leave-requests`, `portal/payslips`, `portal/payslips/{payslip}` | Portail self-service (tout utilisateur authentifié, pas seulement `hr-manager`) |
| POST | `ai/assist` | Guidance IA contextuelle (`HRAiAssistController`) |

Routes web (`/hr/…`, session `auth`) : `employees`, `employees/{employee}`, `payroll` (vue), `attendance`, `shifts/schedule`, `leave/analytics`, `compensation`.

## Services

- **`EmployeeService`** / **`EmployeeManagementService`** — cycle de vie employé (création, onboarding, mise à jour).
- **`AttendanceService`** / **`AdvancedAttendanceService`** — pointage, intégration terminaux biométriques, détection d'exceptions, gestion des demandes d'absence.
- **`AbsenceManagementService`** — congés/absences, règles d'accumulation, workflow d'approbation.
- **`CompensationService`** — rémunération totale, vesting, primes, lettres d'offre.
- **`SkillMatrixService`** — compétences et écarts de compétences (grille simple, sans catalogue de formation).
- **`DocumentExpiryService`** — documents arrivant à expiration, rapport de conformité (commande planifiée `CheckDocumentExpiry`).
- **`BankDetailsMaskingService`** — masquage PCI DSS des coordonnées bancaires (4 derniers chiffres visibles).
- **`HrDashboardService`** — statistiques RH agrégées (effectif, absences du jour, ancienneté moyenne).
- **`HRService`** — services génériques transverses au module.
- **`AI\HrAIService`** — appelle `Modules\Core\Services\AI\AIService::ask()` pour l'optimisation de planning de congés, l'analyse de bulletin de paie et la détection d'anomalies de paie (retourne du JSON structuré généré par l'IA).

Deux observers (`EmployeeObserver`, `LeaveRequestObserver`) diffusent les événements `HrEmployeeUpdated` / `HrLeaveRequestUpdated` (`App\Events`) sur `created`/`updated`.

## Permissions RBAC

Préfixe `hr.*` dans `RolesAndPermissionsSeeder::MODULES` : ressources `employee`, `department`, `job-position`, `leave`, `leave-type`, actions standard `view-any|view|create|update|delete`. Le rôle `hr-manager` reçoit toutes les permissions `hr.*` + `payroll.*` + `timesheets.*`. `EmployeePolicy` vérifie en plus `hr.employee.approve`, `hr.employee.export`, `hr.employee.archive` — trois actions non couvertes par la liste `ACTIONS` du seeder (`view-any|view|create|update|delete`), donc ces permissions précises ne sont jamais créées automatiquement. De même, `AttendancePolicy` vérifie une dizaine de permissions `hr.attendance.*` (`view`, `view-personal`, `record`, `manage-devices`, `verify-records`, `handle-exceptions`, `approve-exception`, `request-time-off`, `approve-time-off`, `reject-time-off`, `manage-shifts`, `view-analytics`, `export`) alors que `attendance` n'apparaît pas du tout comme ressource du module `hr` dans le seeder — aucune de ces permissions n'est seedée : ces policies ne peuvent donc être satisfaites, en l'état, que par un rôle qui bypass les Gates (`super-admin`) ou par des permissions créées manuellement hors seeder.

## Dépendances avec d'autres modules

- **Payroll** : `Modules\Payroll\Models\Payslip` est utilisé par `EmployeeSelfServiceController`, `EmployeePortalController` et lié en route-model-binding dans `HR\Providers\RouteServiceProvider` (`Route::bind('payslip', …)`) — HR ne fait que lire les bulletins produits par Payroll, il ne les génère plus.
- **Helpdesk** : `Employee` utilise le trait `HelpdeskLinkable`.
- **Core** : `HrAIService` s'appuie sur `Modules\Core\Services\AI\AIService`.
- **AI** : `HRAiAssistController` utilise `AiContextualAssistantService`.
- Aucune dépendance résiduelle vers `Modules\Planning\*` n'a été trouvée dans le code actuel (voir Particularités).

## Particularités du périmètre life-mdg-erp

- **Duplication Payroll supprimée, confirmée dans le code** : `Modules/HR/app/Models/` ne contient aucun modèle `Payroll`/`PayrollRun`/`PayrollCalculation`/`Payslip` — la seule trace de paie côté HR est la consommation en lecture de `Modules\Payroll\Models\Payslip` (self-service et portail employé). Le module `Payroll` est bien l'unique source de vérité, conformément à `CLAUDE.md`.
  - Fait notable qui nuance cette séparation : `Modules\Payroll\Services\PayrollIntegrationService`, le contrôleur `Modules\Payroll\Http\Controllers\Api\PayrollController` (routes `payslips`, `generate`, `payslips/approve-batch`, `process-payment`, `taxes/by-country`) et leur test `PayrollIntegrationServiceTest` importent tous `Modules\HR\Models\PayrollRecord` (et `PayrollPeriod`) — des classes qui **n'existent nulle part dans le dépôt** (ni dans `Modules/HR/app/Models/`, ni ailleurs). Ce sont très probablement des reliquats de l'ancienne duplication HR/Payroll retirée lors de l'extraction : le nettoyage a supprimé les modèles mais pas cette branche de code qui les référence encore, ce qui rend ces routes du `PayrollController` non fonctionnelles en l'état (erreur de classe introuvable à l'exécution). Voir `docs/03-MODULES/Payroll.md` pour le détail.
- **Découplage de `Modules\Planning\Models\EmployeeSchedule` vérifié** : aucune relation ni import de `Modules\Planning\*` n'existe dans `Employee.php` ni ailleurs dans `Modules/HR/app/`. Le seul rapprochement lexical trouvé (`AdvancedAttendanceService::getEmployeeScheduledTime()` / `getEmployeeScheduledEndTime()`) travaille avec `ShiftSchedule` (modèle HR local), pas avec le module `Planning` exclu du périmètre — la coupure documentée dans `CLAUDE.md` est bien effective.
- Le scope RH « basique » se reflète aussi dans le naming : `JobPosition` porte un commentaire explicite dans les routes (« basic org structure — not to be confused with recruitment job postings ») et `SkillController`/`Skill` sont qualifiés de « basic skill tagging, no training catalogue / skill matrix visualization » — cohérent avec le retrait de l'ATS/recrutement et du catalogue de formation.
