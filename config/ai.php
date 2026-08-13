<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => 4096,
            'timeout' => 60,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'embedding_model' => 'text-embedding-3-small',
            'timeout' => 60,
        ],
        // DeepSeek auto-hébergé (Ollama) — provider additionnel, non actif par
        // défaut (default_provider reste 'anthropic' ci-dessus). Sélectionnable
        // via AI_DEFAULT_PROVIDER=deepseek ou un override module_providers.
        // Pas de clé API réelle : Ollama ne fait pas d'authentification locale.
        'deepseek' => [
            'base_url' => env('DEEPSEEK_BASE_URL', 'http://localhost:11434/v1'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-r1:7b'),
            'timeout' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module-specific provider overrides
    |--------------------------------------------------------------------------
    | Define which provider to use per module. Falls back to default_provider.
    */
    'module_providers' => [
        'BI' => 'anthropic',
        'CRM' => 'anthropic',
        'Helpdesk' => 'anthropic',
        'WhatsApp' => 'anthropic',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings provider (for semantic search)
    |--------------------------------------------------------------------------
    */
    'embeddings_provider' => 'openai',

    /*
    |--------------------------------------------------------------------------
    | System prompts per context
    |--------------------------------------------------------------------------
    */
    'system_prompts' => [
        'default' => 'You are Life MDG ERP AI Assistant. You help users navigate and use the ERP system efficiently. Be concise, helpful, and professional. Always respond in the user\'s language.',
        'analyst' => 'You are a senior business intelligence analyst. Analyze the provided data and give actionable insights. Identify trends, anomalies, and opportunities.',
        'accountant' => 'You are an expert accountant. Help users with accounting entries, financial reports, and compliance. Be precise and reference applicable standards (IFRS/GAAP).',
        'hr' => 'You are an HR expert. Assist with HR operations, labor law compliance, and people management best practices.',
    ],
];
