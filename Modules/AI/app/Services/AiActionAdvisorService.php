<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiActionAdvisorService
{
    private string $apiKey;
    private string $model;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey  = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        $this->model   = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->enabled = $this->apiKey !== '';
    }

    /**
     * Get AI advice before the user executes a potentially risky action.
     *
     * @param string $module   e.g. 'CRM', 'POS', 'HR'
     * @param string $action   e.g. 'delete_contact', 'run_payroll', 'close_session'
     * @param array  $context  Anonymised current-page data (no PII without consent)
     * @param string $locale   e.g. 'fr', 'en'
     * @param string $userRole e.g. 'admin', 'accountant'
     *
     * @return array{
     *   enabled: bool,
     *   warnings: string[],
     *   options: string[],
     *   consequences: string[],
     *   considerations: string[],
     *   risk_level: string,
     *   recommendation: string
     * }
     */
    public function advise(
        string $module,
        string $action,
        array  $context  = [],
        string $locale   = 'fr',
        string $userRole = 'user',
    ): array {
        if (!$this->enabled) {
            return $this->staticFallback($module, $action, $locale);
        }

        $cacheKey = 'ai_advise:' . $module . ':' . $action . ':' . $locale . ':' . $userRole . ':' . md5(json_encode($context));

        return Cache::remember($cacheKey, 300, function () use ($module, $action, $context, $locale, $userRole) {
            return $this->callClaude($module, $action, $context, $locale, $userRole);
        });
    }

    /**
     * Call the Anthropic API and return structured advice.
     */
    private function callClaude(
        string $module,
        string $action,
        array  $context,
        string $locale,
        string $userRole,
    ): array {
        $langInstruction = $locale === 'fr'
            ? 'Respond entirely in French.'
            : "Respond entirely in the language with ISO code '{$locale}'.";

        $systemPrompt = <<<SYSTEM
You are an ERP AI advisor embedded in WideHalo ERP. {$langInstruction}
A user with role "{$userRole}" is about to perform the action "{$action}" in the ERP module "{$module}".
Analyze the provided context and return a JSON object with exactly these keys:
- "warnings"       (array of 2-4 strings): specific risks or problems the user should be aware of
- "options"        (array of 1-3 strings): alternative approaches the user could consider, if relevant
- "consequences"   (array of 2-3 strings): what will happen if the user proceeds
- "considerations" (array of 2-3 strings): edge cases or things to verify before proceeding
- "risk_level"     (string): one of "low", "medium", "high", "critical"
- "recommendation" (string): one of "proceed", "caution", "reconsider"

Return ONLY valid JSON with those exact keys. No markdown, no explanation outside the JSON object.
SYSTEM;

        $contextJson = empty($context) ? 'No additional context provided.' : json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $userMessage  = "Module: {$module}\nAction: {$action}\nContext:\n{$contextJson}";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 800,
                'system'     => [
                    [
                        'type'          => 'text',
                        'text'          => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if (!$response->successful()) {
                return $this->staticFallback('', $action, $locale, true);
            }

            $body    = $response->json();
            $content = $body['content'][0]['text'] ?? '';

            $advice = json_decode($content, true);

            if (!is_array($advice) || !isset($advice['risk_level'])) {
                return $this->staticFallback('', $action, $locale, true);
            }

            return array_merge($this->emptyAdvice(), $advice, ['enabled' => true]);
        } catch (\Throwable) {
            return $this->staticFallback('', $action, $locale, true);
        }
    }

    /**
     * Build a baseline empty advice structure.
     *
     * @return array{enabled: bool, warnings: string[], options: string[], consequences: string[], considerations: string[], risk_level: string, recommendation: string}
     */
    private function emptyAdvice(): array
    {
        return [
            'enabled'        => true,
            'warnings'       => [],
            'options'        => [],
            'consequences'   => [],
            'considerations' => [],
            'risk_level'     => 'low',
            'recommendation' => 'proceed',
        ];
    }

    /**
     * Static fallback advice for common risky action patterns.
     */
    private function staticFallback(string $module, string $action, string $locale, bool $apiError = false): array
    {
        $isFr = $locale === 'fr' || !in_array($locale, ['en', 'es', 'pt', 'ar', 'sw', 'mg', 'ha', 'zh', 'hi'], true);

        // delete_* actions
        if (str_starts_with($action, 'delete_')) {
            return [
                'enabled'        => false,
                'warnings'       => $isFr
                    ? ['Suppression irréversible — les données ne pourront pas être récupérées.', 'Vérifiez qu\'aucune autre entité ne dépend de cet enregistrement.']
                    : ['Irreversible deletion — data cannot be recovered.', 'Ensure no other records depend on this entry.'],
                'options'        => $isFr
                    ? ['Archiver l\'enregistrement plutôt que de le supprimer.']
                    : ['Archive the record instead of deleting it.'],
                'consequences'   => $isFr
                    ? ['L\'enregistrement sera définitivement supprimé.', 'Les rapports liés pourraient être affectés.']
                    : ['The record will be permanently removed.', 'Related reports may be affected.'],
                'considerations' => $isFr
                    ? ['Avez-vous exporté les données dont vous pourriez avoir besoin ?', 'Y a-t-il des transactions ou documents liés ?']
                    : ['Have you exported data you might need?', 'Are there linked transactions or documents?'],
                'risk_level'     => 'high',
                'recommendation' => 'caution',
            ];
        }

        // send_campaign
        if ($action === 'send_campaign') {
            return [
                'enabled'        => false,
                'warnings'       => $isFr
                    ? ['Envoi à tous les contacts sélectionnés — impossible d\'annuler après envoi.', 'Vérifiez le contenu et les destinataires avant de confirmer.']
                    : ['Sends to all selected contacts — cannot be undone after sending.', 'Review content and recipients before confirming.'],
                'options'        => $isFr
                    ? ['Envoyer un test à votre adresse d\'abord.', 'Planifier l\'envoi à une heure ultérieure.']
                    : ['Send a test to your own address first.', 'Schedule the send for a later time.'],
                'consequences'   => $isFr
                    ? ['Les contacts recevront immédiatement le message.', 'Les métriques d\'ouverture seront suivies.']
                    : ['Contacts will receive the message immediately.', 'Open metrics will be tracked.'],
                'considerations' => $isFr
                    ? ['Le contenu est-il conforme au RGPD / PDPL ?', 'Les liens de désinscription sont-ils actifs ?']
                    : ['Is the content GDPR/PDPL compliant?', 'Are unsubscribe links working?'],
                'risk_level'     => 'high',
                'recommendation' => 'caution',
            ];
        }

        // close_session (POS)
        if ($action === 'close_session') {
            return [
                'enabled'        => false,
                'warnings'       => $isFr
                    ? ['Vérifiez le fond de caisse avant de clôturer.', 'Toutes les transactions en attente doivent être finalisées.']
                    : ['Verify the cash drawer before closing.', 'All pending transactions must be finalized.'],
                'options'        => $isFr
                    ? ['Imprimer le rapport Z avant la clôture.']
                    : ['Print the Z report before closing.'],
                'consequences'   => $isFr
                    ? ['La session sera clôturée et les totaux enregistrés.', 'Le fond de caisse sera comptabilisé.']
                    : ['The session will be closed and totals recorded.', 'The cash drawer will be reconciled.'],
                'considerations' => $isFr
                    ? ['Des écarts de caisse ont-ils été identifiés ?']
                    : ['Have any cash discrepancies been identified?'],
                'risk_level'     => 'medium',
                'recommendation' => 'caution',
            ];
        }

        // approve_* actions
        if (str_starts_with($action, 'approve_')) {
            return [
                'enabled'        => false,
                'warnings'       => $isFr
                    ? ['L\'approbation déclenchera le flux de travail suivant.']
                    : ['Approval will trigger the next workflow step.'],
                'options'        => $isFr
                    ? ['Demander une révision supplémentaire si nécessaire.']
                    : ['Request additional review if needed.'],
                'consequences'   => $isFr
                    ? ['L\'élément sera marqué comme approuvé.', 'Les parties concernées seront notifiées.']
                    : ['The item will be marked as approved.', 'Concerned parties will be notified.'],
                'considerations' => $isFr
                    ? ['Avez-vous examiné tous les documents joints ?']
                    : ['Have you reviewed all attached documents?'],
                'risk_level'     => 'medium',
                'recommendation' => 'proceed',
            ];
        }

        // run_payroll
        if ($action === 'run_payroll') {
            return [
                'enabled'        => false,
                'warnings'       => $isFr
                    ? ['Vérifiez les congés et absences du mois.', 'Contrôlez les heures supplémentaires déclarées.', 'Assurez-vous que tous les contrats sont à jour.']
                    : ['Verify leave and absence records for the month.', 'Check declared overtime hours.', 'Ensure all contracts are up to date.'],
                'options'        => $isFr
                    ? ['Effectuer une simulation avant le lancement réel.']
                    : ['Run a simulation before the actual payroll.'],
                'consequences'   => $isFr
                    ? ['Les fiches de paie seront générées et les virements planifiés.', 'Les charges sociales seront calculées.']
                    : ['Payslips will be generated and transfers scheduled.', 'Social contributions will be calculated.'],
                'considerations' => $isFr
                    ? ['Des employés ont-ils des régularisations en attente ?', 'Le taux de change est-il correct pour les devises étrangères ?']
                    : ['Do any employees have pending adjustments?', 'Is the exchange rate correct for foreign currencies?'],
                'risk_level'     => 'high',
                'recommendation' => 'caution',
            ];
        }

        // Default low-risk fallback
        return [
            'enabled'        => false,
            'warnings'       => $isFr
                ? ['Vérifiez les informations avant de procéder.']
                : ['Review information before proceeding.'],
            'options'        => [],
            'consequences'   => $isFr
                ? ['L\'action sera exécutée immédiatement.']
                : ['The action will be executed immediately.'],
            'considerations' => $isFr
                ? ['Assurez-vous d\'avoir les permissions nécessaires.']
                : ['Make sure you have the required permissions.'],
            'risk_level'     => 'low',
            'recommendation' => 'proceed',
        ];
    }
}
