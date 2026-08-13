<?php

namespace Modules\CRM\Services\AI;

use App\Services\AI\AIService;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Contact;

/**
 * AI-powered prospect email generation
 * Generates personalized prospecting emails using Claude
 */
class ProspectEmailGeneratorService
{
    public function __construct(private AIService $ai) {}

    /**
     * Generate personalized prospecting email
     */
    public function generateProspectEmail(Contact $contact, array $context = []): ?string
    {
        try {
            $prompt = $this->buildPrompt($contact, $context);

            $email = $this->ai->ask($prompt, context: [
                'contact' => $contact->toArray(),
                'company' => $contact->company_name,
                'last_interaction' => $contact->last_activity_date,
            ], module: 'CRM');

            return $email;
        } catch (\Exception $e) {
            Log::error('Email generation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildPrompt(Contact $contact, array $context): string
    {
        $company = $contact->company_name ?: 'their company';
        $industry = $context['industry'] ?? 'Unknown';
        $companySize = $context['company_size'] ?? 'Unknown';
        $offering = $context['offering'] ?? 'Our solutions';

        return <<<PROMPT
Generate a short, personalized prospecting email to contact {$contact->full_name} at {$company}.

Context:
- Email: {$contact->email}
- Phone: {$contact->phone}
- Industry: {$industry}
- Company Size: {$companySize}
- Products/Services Offered: {$offering}

Requirements:
1. Keep it under 100 words
2. Start with a genuine compliment or observation
3. Include one clear call-to-action
4. Professional but conversational tone
5. Use French if the contact language is French
6. Subject line format: [SUBJECT: ...]
7. Email body format: [BODY: ...]

Generate the email in this exact format.
PROMPT;
    }
}
