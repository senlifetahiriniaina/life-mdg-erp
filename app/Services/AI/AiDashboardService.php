<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;

/**
 * Generates role-aware, heuristic-based dashboard insights.
 *
 * Each insight follows the shape:
 *   type         – 'alert' | 'opportunity' | 'action' | 'info'
 *   module       – module name (e.g. 'Accounting')
 *   title        – short title
 *   description  – 1-2 sentence description in French
 *   action_label – button label
 *   action_route – Inertia route (e.g. 'Accounting/Invoices/Index')
 *   priority     – 1 (highest) to 5 (lowest)
 */
class AiDashboardService
{
    /**
     * Generate up to 5 insights for the user based on their role and current metrics.
     *
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    public function generateInsights(User $user, array $metrics): array
    {
        $role     = $metrics['role'] ?? 'employee';
        $insights = [];

        $insights = match ($role) {
            'admin', 'super-admin' => $this->adminInsights($metrics),
            'manager'              => $this->managerInsights($metrics),
            'accountant'           => $this->accountantInsights($metrics),
            'hr-manager'           => $this->hrManagerInsights($metrics),
            'sales-rep'            => $this->salesRepInsights($metrics),
            default                => $this->employeeInsights($metrics),
        };

        // Sort by priority ascending (1 = most urgent first)
        usort($insights, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_slice($insights, 0, 5);
    }

    // ─── Admin insights ──────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function adminInsights(array $metrics): array
    {
        $insights = [];

        $openTickets = (int) ($metrics['open_tickets'] ?? 0);
        if ($openTickets > 20) {
            $insights[] = $this->insight(
                'alert', 'Helpdesk', 1,
                'Volume élevé de tickets ouverts',
                "Il y a actuellement {$openTickets} tickets ouverts. Envisagez d'augmenter les ressources support ou d'activer la déflexion automatique par IA.",
                'Voir les tickets', 'Helpdesk/Tickets/Index',
            );
        } elseif ($openTickets > 10) {
            $insights[] = $this->insight(
                'info', 'Helpdesk', 4,
                'Tickets en cours',
                "{$openTickets} tickets sont en attente de traitement. La charge est modérée.",
                'Gérer les tickets', 'Helpdesk/Tickets/Index',
            );
        }

        $pendingApprovals = (int) ($metrics['pending_approvals'] ?? 0);
        if ($pendingApprovals > 5) {
            $insights[] = $this->insight(
                'action', 'Core', 2,
                'Approbations en attente',
                "{$pendingApprovals} éléments nécessitent votre validation (notes de frais, congés, documents). Traitez-les pour débloquer vos équipes.",
                'Voir les approbations', 'HR/LeaveRequests/Index',
            );
        }

        $totalRevenue = (float) ($metrics['total_revenue'] ?? 0);
        $mrr          = (float) ($metrics['mrr'] ?? 0);
        if ($mrr > 0 && $totalRevenue > 0) {
            $insights[] = $this->insight(
                'opportunity', 'Accounting', 3,
                'Revenus du mois en bonne voie',
                'Les encaissements du mois en cours représentent ' . number_format($mrr, 0, ',', ' ') . ' €. Consultez le tableau de bord financier pour le détail.',
                'Voir les finances', 'Accounting/Invoices/Index',
            );
        }

        $activeToday = (int) ($metrics['active_users_today'] ?? 0);
        $usersTotal  = (int) ($metrics['users_count'] ?? 1);
        if ($usersTotal > 0 && ($activeToday / $usersTotal) < 0.3 && $usersTotal > 5) {
            $insights[] = $this->insight(
                'info', 'Core', 5,
                'Faible activité utilisateurs',
                'Seulement ' . round(($activeToday / $usersTotal) * 100) . '% des utilisateurs se sont connectés aujourd\'hui. Pensez à communiquer sur les nouvelles fonctionnalités.',
                'Gérer les utilisateurs', 'Settings/Users/Index',
            );
        }

        // Always add a strategic suggestion
        $insights[] = $this->insight(
            'opportunity', 'BI', 4,
            'Analysez la performance globale',
            'Le tableau de bord BI vous donne une vue consolidée de toutes vos métriques métier. Consultez-le pour identifier les axes d\'amélioration.',
            'Ouvrir le BI', 'BI/Dashboard/Index',
        );

        return $insights;
    }

    // ─── Manager insights ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function managerInsights(array $metrics): array
    {
        $insights = [];

        $pendingLeaves = (int) ($metrics['pending_leave_requests'] ?? 0);
        if ($pendingLeaves > 3) {
            $insights[] = $this->insight(
                'action', 'HR', 1,
                'Demandes de congés à valider',
                "{$pendingLeaves} demandes de congés sont en attente de votre approbation. Répondez rapidement pour permettre à vos collaborateurs de planifier.",
                'Valider les congés', 'HR/LeaveRequests/Index',
            );
        }

        $overdueTasks = (int) ($metrics['overdue_tasks'] ?? 0);
        if ($overdueTasks > 0) {
            $insights[] = $this->insight(
                'alert', 'Projects', 2,
                'Tâches en retard',
                "{$overdueTasks} tâche(s) dépassent leur date d'échéance. Revoyez les priorités avec votre équipe pour maintenir les délais.",
                'Voir les tâches', 'Projects/Tasks/Index',
            );
        }

        $perfAvg = (float) ($metrics['team_performance_avg'] ?? 0);
        if ($perfAvg > 0 && $perfAvg < 3.0) {
            $insights[] = $this->insight(
                'alert', 'HR', 2,
                'Performance d\'équipe à surveiller',
                'La note moyenne d\'appréciation de votre équipe est de ' . number_format($perfAvg, 1) . '/5. Planifiez des entretiens de suivi.',
                'Voir les évaluations', 'HR/Appraisals/Index',
            );
        }

        $openTasks = (int) ($metrics['open_tasks'] ?? 0);
        $insights[] = $this->insight(
            'info', 'Projects', 3,
            'Charge de travail équipe',
            "Votre équipe a {$openTasks} tâches ouvertes en cours. Vérifiez la répartition de la charge pour éviter les goulots d'étranglement.",
            'Tableau des tâches', 'Projects/Board/Index',
        );

        $insights[] = $this->insight(
            'opportunity', 'HR', 5,
            'Développement des compétences',
            'Identifiez les besoins de formation de votre équipe pour renforcer les compétences clés et améliorer la performance collective.',
            'Plan de formation', 'HR/Training/Index',
        );

        return $insights;
    }

    // ─── Accountant insights ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function accountantInsights(array $metrics): array
    {
        $insights = [];

        $unpaidCount = (int) ($metrics['unpaid_invoices_count'] ?? 0);
        $unpaidTotal = (float) ($metrics['unpaid_invoices_total'] ?? 0);
        if ($unpaidCount > 5) {
            $insights[] = $this->insight(
                'alert', 'Accounting', 1,
                'Factures impayées en attente',
                "{$unpaidCount} factures pour un total de " . number_format($unpaidTotal, 0, ',', ' ') . " € ne sont pas encore réglées. Relancez vos clients.",
                'Voir les factures', 'Accounting/Invoices/Index',
            );
        } elseif ($unpaidCount > 0) {
            $insights[] = $this->insight(
                'action', 'Accounting', 2,
                'Suivi des paiements',
                "{$unpaidCount} facture(s) en attente de paiement pour " . number_format($unpaidTotal, 0, ',', ' ') . ' €.',
                'Gérer les factures', 'Accounting/Invoices/Index',
            );
        }

        $overdueCount = (int) ($metrics['overdue_invoices'] ?? 0);
        if ($overdueCount > 0) {
            $insights[] = $this->insight(
                'alert', 'Accounting', 1,
                'Factures en souffrance',
                "{$overdueCount} facture(s) dépassent leur date d'échéance. Lancez une relance automatique pour récupérer ces créances.",
                'Relances automatiques', 'Accounting/Invoices/Index',
            );
        }

        $expensePending = (int) ($metrics['expense_reports_pending'] ?? 0);
        if ($expensePending > 0) {
            $insights[] = $this->insight(
                'action', 'Accounting', 3,
                'Notes de frais à traiter',
                "{$expensePending} note(s) de frais soumise(s) attendent votre validation et remboursement.",
                'Traiter les notes de frais', 'Accounting/ExpenseReports/Index',
            );
        }

        $bankPending = (int) ($metrics['bank_reconciliation_pending'] ?? 0);
        if ($bankPending > 10) {
            $insights[] = $this->insight(
                'action', 'Accounting', 3,
                'Rapprochement bancaire en attente',
                "{$bankPending} transactions bancaires non rapprochées. Effectuez le rapprochement pour maintenir la comptabilité à jour.",
                'Rapprochement bancaire', 'Accounting/BankReconciliation/Index',
            );
        }

        $vatDue = (float) ($metrics['vat_due_amount'] ?? 0);
        if ($vatDue > 0) {
            $insights[] = $this->insight(
                'info', 'Accounting', 4,
                'Déclaration TVA en préparation',
                'Un montant de ' . number_format($vatDue, 0, ',', ' ') . ' € de TVA collectée est en attente de déclaration.',
                'Voir la TVA', 'Accounting/VatDeclarations/Index',
            );
        }

        // Fallback if few insights generated
        $insights[] = $this->insight(
            'opportunity', 'Accounting', 5,
            'Optimisez votre trésorerie',
            'Consultez les prévisions de trésorerie pour anticiper les besoins de liquidités et optimiser votre BFR.',
            'Prévisions de trésorerie', 'Accounting/CashFlow/Index',
        );

        return $insights;
    }

    // ─── HR Manager insights ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function hrManagerInsights(array $metrics): array
    {
        $insights = [];

        $pendingLeaves = (int) ($metrics['pending_leaves'] ?? 0);
        if ($pendingLeaves > 3) {
            $insights[] = $this->insight(
                'action', 'HR', 1,
                'Demandes de congés à traiter',
                "{$pendingLeaves} demandes de congés sont en attente. Traitez-les rapidement pour permettre la planification des équipes.",
                'Valider les congés', 'HR/LeaveRequests/Index',
            );
        }

        $openPositions = (int) ($metrics['open_positions'] ?? 0);
        if ($openPositions > 0) {
            $insights[] = $this->insight(
                'info', 'HR', 2,
                'Postes ouverts au recrutement',
                "{$openPositions} poste(s) sont actuellement en cours de recrutement. Suivez l'avancement des candidatures.",
                'Voir les recrutements', 'HR/JobPostings/Index',
            );
        }

        $upcomingReviews = (int) ($metrics['upcoming_reviews'] ?? 0);
        if ($upcomingReviews > 0) {
            $insights[] = $this->insight(
                'action', 'HR', 2,
                'Cycles d\'évaluation en cours',
                "{$upcomingReviews} cycle(s) d'évaluation de performance sont actifs. Assurez-vous que tous les entretiens sont planifiés.",
                'Cycles d\'évaluation', 'HR/PerformanceCycles/Index',
            );
        }

        $turnover = (float) ($metrics['turnover_rate'] ?? 0);
        if ($turnover > 8.0) {
            $insights[] = $this->insight(
                'alert', 'HR', 1,
                'Taux de turnover élevé',
                "Le taux de turnover est de {$turnover}%, au-dessus du seuil recommandé. Analysez les causes et mettez en place des actions de rétention.",
                'Analyse RH', 'HR/Analytics/Index',
            );
        }

        $headcount = (int) ($metrics['headcount'] ?? 0);
        $insights[] = $this->insight(
            'opportunity', 'HR', 4,
            'Plan de développement des talents',
            "Avec {$headcount} collaborateurs actifs, identifiez les potentiels à haut potentiel et construisez un plan de succession solide.",
            'Plans de succession', 'HR/Succession/Index',
        );

        $insights[] = $this->insight(
            'info', 'HR', 5,
            'Tableaux de bord RH',
            'Accédez aux indicateurs RH consolidés : absentéisme, ancienneté moyenne, répartition par département.',
            'Rapports RH', 'HR/Reports/Index',
        );

        return $insights;
    }

    // ─── Sales Rep insights ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function salesRepInsights(array $metrics): array
    {
        $insights = [];

        $quotaProgress = (float) ($metrics['quota_progress'] ?? 0);
        $dayOfMonth    = (int) now()->day;
        $daysInMonth   = (int) now()->daysInMonth;
        $monthProgress = ($dayOfMonth / $daysInMonth) * 100;

        if ($quotaProgress < ($monthProgress - 15)) {
            $insights[] = $this->insight(
                'alert', 'CRM', 1,
                'Quota mensuel en retard',
                "Vous êtes à {$quotaProgress}% de votre quota alors que le mois est avancé à " . round($monthProgress) . "%. Accélérez vos relances et closings.",
                'Mes opportunités', 'CRM/Opportunities/Index',
            );
        } elseif ($quotaProgress >= 100) {
            $insights[] = $this->insight(
                'opportunity', 'CRM', 1,
                'Quota atteint — Visez plus haut !',
                "Félicitations, vous avez atteint {$quotaProgress}% de votre quota mensuel. Concentrez-vous sur les opportunités à fort potentiel pour dépasser vos objectifs.",
                'Mes opportunités', 'CRM/Opportunities/Index',
            );
        }

        $pipelineValue = (float) ($metrics['my_pipeline_value'] ?? 0);
        $oppsCount     = (int) ($metrics['my_opportunities_count'] ?? 0);
        if ($oppsCount > 0 && $pipelineValue > 0) {
            $avgDeal = $pipelineValue / $oppsCount;
            $insights[] = $this->insight(
                'info', 'CRM', 2,
                'Pipeline commercial actif',
                "{$oppsCount} opportunité(s) pour " . number_format($pipelineValue, 0, ',', ' ') . " € en cours. Valeur moyenne par deal : " . number_format($avgDeal, 0, ',', ' ') . " €.",
                'Voir le pipeline', 'CRM/Opportunities/Index',
            );
        }

        $openActivities = (int) ($metrics['open_activities'] ?? 0);
        if ($openActivities > 10) {
            $insights[] = $this->insight(
                'action', 'CRM', 2,
                'Activités commerciales en retard',
                "{$openActivities} activités sont en attente (appels, emails, rendez-vous). Traitez-les pour maintenir votre relation client.",
                'Mes activités', 'CRM/Activities/Index',
            );
        }

        $myLeads = (int) ($metrics['my_leads_count'] ?? 0);
        if ($myLeads > 20) {
            $insights[] = $this->insight(
                'opportunity', 'CRM', 3,
                'Leads à qualifier',
                "Vous avez {$myLeads} leads actifs. Qualifiez rapidement les plus prometteurs pour alimenter votre pipeline.",
                'Mes leads', 'CRM/Leads/Index',
            );
        }

        $wonDeals = (int) ($metrics['won_deals_this_month'] ?? 0);
        $insights[] = $this->insight(
            'info', 'CRM', 4,
            'Performance du mois',
            "{$wonDeals} deal(s) remporté(s) ce mois-ci. Consultez votre tableau de bord de performance pour suivre vos indicateurs clés.",
            'Ma performance', 'CRM/Dashboard/Index',
        );

        $insights[] = $this->insight(
            'opportunity', 'Email', 5,
            'Campagnes de nurturing',
            'Activez des séquences d\'emails automatisées pour entretenir vos prospects inactifs et accélérer leur conversion.',
            'Séquences email', 'CRM/EmailSequences/Index',
        );

        return $insights;
    }

    // ─── Employee insights ───────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<array<string, mixed>>
     */
    private function employeeInsights(array $metrics): array
    {
        $insights = [];

        $myTasks = (int) ($metrics['my_open_tasks'] ?? 0);
        if ($myTasks > 5) {
            $insights[] = $this->insight(
                'action', 'Projects', 1,
                'Nombreuses tâches en cours',
                "Vous avez {$myTasks} tâches ouvertes. Priorisez-les et communiquez à votre manager si certaines sont bloquées.",
                'Mes tâches', 'Projects/Tasks/Index',
            );
        } else {
            $insights[] = $this->insight(
                'info', 'Projects', 3,
                'Vos tâches en cours',
                "{$myTasks} tâche(s) vous sont assignées. Consultez le tableau de bord projets pour les détails.",
                'Mes tâches', 'Projects/Tasks/Index',
            );
        }

        $pendingExpenses = (int) ($metrics['my_pending_expenses'] ?? 0);
        if ($pendingExpenses > 0) {
            $insights[] = $this->insight(
                'action', 'Accounting', 2,
                'Notes de frais soumises',
                "{$pendingExpenses} note(s) de frais sont en attente de validation et de remboursement.",
                'Mes notes de frais', 'Accounting/MyExpenses/Index',
            );
        }

        $myTickets = (int) ($metrics['my_open_tickets'] ?? 0);
        if ($myTickets > 0) {
            $insights[] = $this->insight(
                'info', 'Helpdesk', 3,
                'Tickets support ouverts',
                "{$myTickets} ticket(s) de support sont en cours de traitement. Suivez leur évolution depuis le portail.",
                'Mes tickets', 'Helpdesk/MyTickets/Index',
            );
        }

        $insights[] = $this->insight(
            'opportunity', 'HR', 4,
            'Développez vos compétences',
            'Consultez le catalogue de formations disponibles et inscrivez-vous aux cours pour développer vos compétences professionnelles.',
            'Catalogue formations', 'HR/Training/Index',
        );

        $insights[] = $this->insight(
            'info', 'HR', 5,
            'Planifiez vos congés',
            'Vérifiez votre solde de congés et planifiez vos prochaines absences en avance pour faciliter l\'organisation de votre équipe.',
            'Mes congés', 'HR/MyLeaves/Index',
        );

        return $insights;
    }

    // ─── Factory helper ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function insight(
        string $type,
        string $module,
        int $priority,
        string $title,
        string $description,
        string $actionLabel,
        string $actionRoute,
    ): array {
        return [
            'type'         => $type,
            'module'       => $module,
            'priority'     => $priority,
            'title'        => $title,
            'description'  => $description,
            'action_label' => $actionLabel,
            'action_route' => $actionRoute,
        ];
    }
}
