<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services\AI;

use Illuminate\Support\Facades\DB;
use Modules\Core\Services\AI\AIService;

class KbChatbotService
{
    public function __construct(private readonly AIService $ai) {}

    public function answerFromKb(string $question, string $locale = 'fr'): array
    {
        // Get KB articles (simplified)
        $kbArticles = DB::table('hd_kb_articles')->limit(5)->get()->pluck('content', 'title')->toArray();

        $prompt = 'Answer this customer question using ONLY the provided knowledge base. Be helpful and concise.\\nKB: '.json_encode($kbArticles);
        $answer = $this->ai->ask($prompt, ['question' => $question], 'Helpdesk', $locale);

        return ['question' => $question, 'answer' => $answer, 'from_kb' => true];
    }
}
