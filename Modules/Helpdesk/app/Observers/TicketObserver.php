<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\Helpdesk\Models\LanguageDetection;
use Modules\Helpdesk\Models\SentimentScore;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\SentimentAnalysisService;

class TicketObserver
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function created(Ticket $ticket): void
    {
        $this->recordSentimentAndLanguage($ticket);

        if (! $ticket->assignee_id) {
            return;
        }

        $this->notifier->notifyProcess(
            [$ticket->assignee],
            $ticket->reporter,
            'Nouveau ticket assigné',
            sprintf('Le ticket %s ("%s") vous a été assigné.', $ticket->ticket_number, $ticket->subject),
            ['type' => 'warning', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
        );
    }

    /**
     * Chantier 32.21 (layer 9, fake/dead): SentimentAnalysisService was
     * fully written, deterministic (no AI-provider call, pure keyword
     * heuristics — confirmed via grep), and used internally by 3 other
     * orphaned services (PredictiveEscalationService/AiResponseService/
     * SatisfactionPredictionService) — but had zero real producer of its
     * own anywhere in the app: CustomerServiceAIController::
     * getSentimentAnalysis()/getLanguageDetection() only ever *read*
     * SentimentScore/LanguageDetection, and neither model was ever
     * created by any code path, so both endpoints have always 404'd in
     * real use. Wired here, mirroring the same "runs automatically on
     * ticket creation" pattern Ticket::booted() already established for
     * SlaService::apply() — never blocks ticket creation on failure
     * (fallback-first, matching the app-wide AI-degradation principle).
     *
     * EmotionAnalysis was deliberately left unwired: its fixed 6 emotion-
     * score columns (anger/frustration/satisfaction/confusion/urgency/
     * disappointment) don't match SentimentAnalysisService::EMOTIONS'
     * different 6-emotion set (anger/frustration/satisfaction/confusion/
     * gratitude/fear) — reconciling that vocabulary mismatch is a real
     * design decision, not a wiring job, and is documented rather than
     * guessed at here.
     */
    private function recordSentimentAndLanguage(Ticket $ticket): void
    {
        try {
            $service = app(SentimentAnalysisService::class);
            $analysis = $service->analyzeTicketSentiment($ticket);

            if (isset($analysis['error'])) {
                return;
            }

            // calculateSentimentScore() returns a single 0-100 positivity
            // percentage (positive-sentiment-words / total-sentiment-words),
            // not an independent 3-way split — positive_score/negative_score
            // are the direct fractional decomposition of that same number
            // (never a fabricated one), neutral_score stays 0 since the
            // service has no real notion of a neutral-word count to report.
            $positiveFraction = ($analysis['score'] ?? 50) / 100;

            SentimentScore::create([
                'ticket_id' => $ticket->id,
                'language_detected' => $analysis['language'] ?? 'en',
                'sentiment' => $analysis['sentiment'] ?? 'neutral',
                'positive_score' => round($positiveFraction, 4),
                'negative_score' => round(1 - $positiveFraction, 4),
                'neutral_score' => 0,
                'confidence' => $analysis['confidence'] ?? 0,
                'analyzed_text' => mb_substr((string) ($ticket->description ?? $ticket->subject), 0, 2000),
                'status' => 'completed',
                'analyzed_at' => $analysis['analyzed_at'] ?? now(),
            ]);

            LanguageDetection::create([
                'ticket_id' => $ticket->id,
                'detected_language' => $analysis['language'] ?? 'en',
                // The heuristic keyword-matcher defaults to 'en' whenever no
                // fr/es/de keyword matched at all, rather than genuinely
                // detecting English — an honestly lower confidence for that
                // fallback case rather than claiming certainty it doesn't have.
                'confidence' => ($analysis['language'] ?? 'en') === 'en' ? 0.5 : 0.9,
                'requires_translation' => false,
                'translation_status' => 'not_required',
                'detected_at' => $analysis['analyzed_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Helpdesk: sentiment/language analysis failed on ticket creation', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Ticket $ticket): void
    {
        // Ticket::booted()'s static::created() hook calls SlaService::apply(),
        // which itself calls $ticket->update() on this same in-flight instance
        // — a nested save that runs before Eloquent's own finishSave()/
        // syncOriginal() for the outer insert, so wasChanged() spuriously
        // reports every originally-set attribute (including assignee_id/
        // status) as changed on that nested update. getOriginal('id') is
        // still null at that point (syncOriginal() hasn't run yet for the
        // outer insert) even though the row already exists — unlike
        // wasRecentlyCreated, which stays true for this instance's whole
        // lifetime and would wrongly suppress every later legitimate
        // update too, this precisely targets only the nested-save case.
        if ($ticket->exists && is_null($ticket->getOriginal('id'))) {
            return;
        }

        // The owner of a status-change/reassign action is whoever performed
        // it (an agent), not the ticket's reporter — fall back to the
        // assignee when there's no authenticated request context (e.g. a
        // console job) so the notification still has a real owner to
        // resolve a hierarchy superior from.
        $actor = Auth::user() ?? $ticket->assignee;

        if ($ticket->wasChanged('status')) {
            $this->notifier->notifyProcess(
                array_filter([$ticket->reporter, $ticket->assignee], fn ($u) => $u && $actor && $u->id !== $actor->id),
                $actor,
                'Statut du ticket mis à jour',
                sprintf('Le ticket %s est passé au statut "%s".', $ticket->ticket_number, $ticket->status),
                ['type' => 'info', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
            );
        }

        if ($ticket->wasChanged('assignee_id') && $ticket->assignee_id) {
            $this->notifier->notifyProcess(
                [$ticket->assignee],
                $actor,
                'Ticket réassigné',
                sprintf('Le ticket %s ("%s") vous a été assigné.', $ticket->ticket_number, $ticket->subject),
                ['type' => 'warning', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
            );
        }
    }
}
