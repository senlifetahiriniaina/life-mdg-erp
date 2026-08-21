<?php

namespace Modules\CRM\Services\AI;

use Illuminate\Support\Facades\Log;
use Modules\Core\Services\AI\AIService;
use Modules\CRM\Models\Contact;

/**
 * AI-powered duplicate detection for contacts
 * Uses OpenAI embeddings to find similar contacts
 */
class DuplicateDetectionService
{
    public function __construct(private AIService $ai) {}

    /**
     * Detect potential duplicate contacts using semantic similarity
     *
     * Chantier 32.15: was filtering by `tenant_id` against `auth()->user()->tenant_id` — the
     * well-documented phantom `users.tenant_id` column (never populated by any real
     * registration path) compared against `crm_contacts.tenant_id`, itself never populated by
     * any real write path either (Contact's real tenant boundary is `company_id`, populated
     * by ContactController::store()). Both sides always resolved to null, and Laravel's
     * `where('col', null)` compiles to a strict `col = ?` bound to NULL, which matches nothing
     * in SQL — so this method has never once found a real candidate for any real contact.
     * Confirmed zero real callers (no route/controller ever reached it before this fix) —
     * only its own unit test exercised it, and that test used `Model::unguarded()`-style
     * factory writes that never exhibited the bug. Fixed to the module's real tenant boundary.
     */
    public function detectDuplicates(Contact $contact, ?float $threshold = 0.85): array
    {
        try {
            // Generate embedding for contact
            $contactText = $this->generateContactText($contact);
            $contactEmbedding = $this->ai->embeddings()->embed($contactText);

            // Find similar contacts
            $allContacts = Contact::where('company_id', $contact->company_id)
                ->where('id', '!=', $contact->id)
                ->get();

            $candidates = [];

            foreach ($allContacts as $other) {
                $otherText = $this->generateContactText($other);
                $otherEmbedding = $this->ai->embeddings()->embed($otherText);

                $similarity = $this->cosineSimilarity($contactEmbedding, $otherEmbedding);

                if ($similarity >= $threshold) {
                    $candidates[] = [
                        'contact_id' => $other->id,
                        'name' => $other->full_name,
                        'email' => $other->email,
                        'similarity' => round($similarity * 100, 2),
                        'reason' => $this->explainSimilarity($contact, $other),
                    ];
                }
            }

            return collect($candidates)
                ->sortByDesc('similarity')
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('Duplicate detection failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function generateContactText(Contact $contact): string
    {
        return implode(' ', [
            $contact->first_name,
            $contact->last_name,
            $contact->email,
            $contact->phone,
            // Chantier 32.15: company_name has never existed on Contact — the account
            // relation's real name is the closest equivalent ("employer" concept).
            $contact->account?->name ?? '',
        ]);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] ** 2;
            $magnitudeB += $b[$i] ** 2;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    private function explainSimilarity(Contact $contact, Contact $other): string
    {
        $reasons = [];

        if (strtolower($contact->email) === strtolower($other->email)) {
            $reasons[] = 'Same email';
        }

        if (strtolower($contact->phone) === strtolower($other->phone) && $contact->phone) {
            $reasons[] = 'Same phone';
        }

        if (levenshtein($contact->first_name, $other->first_name) <= 2) {
            $reasons[] = 'Similar first name';
        }

        return implode(', ', $reasons) ?: 'Semantic similarity';
    }
}
