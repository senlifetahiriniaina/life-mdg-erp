<?php

namespace Modules\CRM\Services\AI;

use App\Services\AI\AIService;
use Illuminate\Support\Facades\Log;
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
     */
    public function detectDuplicates(Contact $contact, ?float $threshold = 0.85): array
    {
        try {
            // Generate embedding for contact
            $contactText = $this->generateContactText($contact);
            $contactEmbedding = $this->ai->embed($contactText);

            // Find similar contacts
            $allContacts = Contact::where('tenant_id', auth()->user()->tenant_id)
                ->where('id', '!=', $contact->id)
                ->get();

            $candidates = [];

            foreach ($allContacts as $other) {
                $otherText = $this->generateContactText($other);
                $otherEmbedding = $this->ai->embed($otherText);

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
            $contact->company_name ?? '',
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
