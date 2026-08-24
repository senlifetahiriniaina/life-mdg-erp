<?php

declare(strict_types=1);

namespace Modules\Payroll\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\Payroll\Models\Payslip;

/**
 * Chantier 32.18 (Payroll deep audit — layer 11, CORE): Payroll never wired
 * into Modules\Core\Services\ParticipantNotificationService (Chantier 20's
 * app-wide "notify every participant + the action owner's direct manager"
 * rule) despite having a real, well-defined process an employee genuinely
 * cares about — their own payslip being approved or paid. HR's
 * LeaveRequestObserver/Helpdesk's TicketObserver already establish the
 * exact pattern this follows: react to a real status transition, notify the
 * employee whose payslip it is, with the owner resolved to the real acting
 * payroll staff member (Auth::user() — same actor-resolution convention
 * Chantier 20 already established for Helpdesk's TicketObserver, rather
 * than a participant who didn't perform the action).
 */
class PayslipObserver
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function updated(Payslip $payslip): void
    {
        if (! $payslip->wasChanged('status') || ! in_array($payslip->status, ['approved', 'paid'], true)) {
            return;
        }

        $employeeUser = $payslip->employee?->user;

        $this->notifier->notifyProcess(
            array_filter([$employeeUser]),
            Auth::user(),
            $payslip->status === 'approved' ? 'Fiche de paie approuvée' : 'Fiche de paie payée',
            $payslip->status === 'approved'
                ? sprintf('Votre fiche de paie pour %s a été approuvée.', optional($payslip->period)->format('F Y'))
                : sprintf('Votre fiche de paie pour %s a été payée.', optional($payslip->period)->format('F Y')),
            [
                'type'       => $payslip->status === 'approved' ? 'success' : 'success',
                'action_url' => '/payroll',
            ],
        );
    }
}
