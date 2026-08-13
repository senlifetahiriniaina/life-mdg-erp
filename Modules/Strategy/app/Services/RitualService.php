<?php

namespace Modules\Strategy\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Strategy\Models\StrategyRitual;
use Modules\Strategy\Models\StrategyRitualSession;

class RitualService
{
    public function __construct(
        private AiStrategyAdvisorService $advisor
    ) {}

    public function createRitual(string $tenantId, array $data): StrategyRitual
    {
        $data['tenant_id'] = $tenantId;

        return StrategyRitual::create($data);
    }

    /**
     * Compute the next occurrence date based on cadence and create a session.
     */
    public function generateNextSession(StrategyRitual $ritual): StrategyRitualSession
    {
        $nextDate = $this->computeNextDate($ritual);

        return StrategyRitualSession::create([
            'ritual_id'    => $ritual->id,
            'scheduled_at' => $nextDate,
        ]);
    }

    private function computeNextDate(StrategyRitual $ritual): Carbon
    {
        $now = now();

        return match ($ritual->cadence) {
            'weekly'    => $now->next($ritual->day_of_week ?? Carbon::MONDAY),
            'biweekly'  => $now->addWeeks(2)->startOfWeek(),
            'monthly'   => $now->addMonth()->setDay($ritual->day_of_month ?? 1)->startOfDay(),
            'quarterly' => $now->addMonths(3)->startOfMonth(),
            'annual'    => $now->addYear()->startOfYear(),
            default     => $now->addWeek(),
        };
    }

    public function startSession(int $sessionId): StrategyRitualSession
    {
        $session = StrategyRitualSession::findOrFail($sessionId);
        $session->update(['started_at' => now()]);

        return $session->fresh();
    }

    /**
     * Complete a session: save decisions/action_items, generate AI summary.
     */
    public function completeSession(int $sessionId, array $data): StrategyRitualSession
    {
        $session = StrategyRitualSession::findOrFail($sessionId);

        $updateData = [
            'completed_at' => now(),
            'decisions'    => $data['decisions'] ?? [],
            'action_items' => $data['action_items'] ?? [],
        ];

        // Generate AI summary
        try {
            $summary = $this->advisor->generateRitualSummary($sessionId);
            $updateData['ai_summary'] = $summary;
        } catch (\Throwable $e) {
            $updateData['ai_summary'] = 'Résumé non disponible.';
        }

        $session->update($updateData);

        return $session->fresh();
    }

    /**
     * Get upcoming sessions for a tenant within the next N days.
     */
    public function getUpcoming(string $tenantId, int $days = 30): Collection
    {
        $ritualIds = StrategyRitual::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('id');

        return StrategyRitualSession::whereIn('ritual_id', $ritualIds)
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [now(), now()->addDays($days)])
            ->with('ritual')
            ->orderBy('scheduled_at')
            ->get();
    }
}
