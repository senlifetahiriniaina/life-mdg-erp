<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\AI\AIService;

class AiContextualAssistantService
{
    private AIService $aiService;
    private bool $enabled;

    /**
     * @param AIService|null $aiService Optional — resolved from the container
     *   when omitted, so plain `new AiContextualAssistantService()` (used
     *   throughout this module's tests) keeps working outside of Laravel's
     *   DI container while `app(AiContextualAssistantService::class)` still
     *   gets the shared singleton properly injected.
     */
    public function __construct(?AIService $aiService = null)
    {
        $this->aiService = $aiService ?? app(AIService::class);
        // Reflects whichever provider is actually configured for the 'AI'
        // module (config('ai.module_providers.AI'), falling back to
        // AI_DEFAULT_PROVIDER) — anthropic, openai, or a self-hosted
        // deepseek, per config/ai.php.
        $this->enabled = $this->aiService->forModule('AI')->isConfigured();
    }

    /**
     * Get contextual AI guidance for a user action.
     *
     * @param string $module    e.g. 'CRM', 'Accounting', 'HR'
     * @param string $action    e.g. 'create_contact', 'view_dashboard', 'post_invoice'
     * @param array  $context   Current data context (filtered, no PII unless consented)
     * @param string $locale    e.g. 'fr', 'en', 'sw'
     * @param string $userRole  e.g. 'admin', 'accountant', 'sales_rep'
     * @return array{
     *   enabled: bool,
     *   what_to_do: string,
     *   how_to_do: string[],
     *   decision_indicators: array<string,mixed>[],
     *   warnings: string[],
     *   next_actions: array<string,mixed>[],
     *   tips: string[]
     * }
     */
    public function getGuidance(
        string $module,
        string $action,
        array  $context  = [],
        string $locale   = 'fr',
        string $userRole = 'user',
    ): array {
        if (!$this->enabled) {
            return $this->fallbackGuidance($module, $action, $locale);
        }

        $cacheKey = 'ai_assist:' . $module . ':' . $action . ':' . $locale . ':' . $userRole . ':' . md5(json_encode($context));

        return Cache::remember($cacheKey, 300, function () use ($module, $action, $context, $locale, $userRole) {
            return $this->callClaude($module, $action, $context, $locale, $userRole);
        });
    }

    /**
     * Return the list of supported modules and their actions.
     *
     * @return array<string, string[]>
     */
    public function supportedModules(): array
    {
        return [
            // 'view_contacts_list'/'manage_leads'/'manage_opportunities_kanban'/
            // 'manage_quotes'/'manage_territories'/'view_sales_forecast' added
            // Chantier 32.15 (CRM deep 14-layer audit): confirmed via grep that
            // ZERO of this module's ~13 real, routed Vue pages ever called
            // useAiAssistant() at all — the same "real pages never wired to AI
            // guidance" pattern already found and fixed for Strategy
            // (Chantier 30), Validation (Chantier 32.7), and Sales
            // (Chantier 32.16). These 6 cover the module's highest-traffic
            // screens; EmailSequences/CallLogs/Scoring are left as a
            // documented residual gap (see CLAUDE.md's Chantier 32.15 entry).
            'CRM'                 => ['create_contact', 'view_dashboard', 'create_opportunity', 'view_contacts_list', 'manage_leads', 'manage_opportunities_kanban', 'manage_quotes', 'manage_territories', 'view_sales_forecast'],
            // 'view_income_statement'/'import_treasury' added Chantier 30 (bulk
            // treasury/cash import assistance, IncomeStatement.vue AI panel).
            'Accounting'          => ['post_invoice', 'reconcile', 'view_balance_sheet', 'view_income_statement', 'ohada_report', 'invoice_approval', 'view_payment_schedule', 'cost_analysis', 'import_treasury'],
            'HR'                  => ['create_employee', 'approve_leave', 'run_payroll', 'onboarding'],
            // 'import_stock' added Chantier 30 (bulk stock import assistance).
            'Inventory'           => ['receive_stock', 'create_product', 'low_stock_alert', 'import_stock'],
            // 'manage_deposit_balance'/'manage_recurring_orders'/
            // 'manage_sales_objectives' added Chantier 32.16 (Sales deep
            // 14-layer audit): 3 of the module's 4 real Vue pages
            // (Orders/Show.vue, RecurringOrders/Index.vue, Objectives/
            // Index.vue) never called useAiAssistant() at all — the same
            // "N of M real pages never wired" pattern already found and
            // fixed for Strategy (Chantier 30) and Validation (Chantier
            // 32.7).
            'Sales'               => ['create_order', 'confirm_order', 'create_quotation', 'manage_deposit_balance', 'manage_recurring_orders', 'manage_sales_objectives'],
            'POS'                 => ['open_session', 'process_payment', 'close_session'],
            // Chantier 32.10 (Setup deep 14-layer audit): the 6-step
            // onboarding wizard (SetupWizard.vue) had ZERO AI-assist
            // integration at all — only the import sub-flow (SetupIndex.vue)
            // was wired, matching the exact "N of M real pages never call
            // useAiAssistant()" pattern already found and fixed for
            // Strategy at Chantier 30. wizard_company/admin/modules/
            // workflows/apps/complete cover the 6 real steps.
            'Setup'               => ['import_file', 'map_columns', 'execute_import', 'wizard_company', 'wizard_admin', 'wizard_modules', 'wizard_workflows', 'wizard_apps', 'wizard_complete'],
            'Achats'              => ['create_order', 'approve_order', 'receive_goods', 'three_way_match', 'view_dashboard'],
            'Projects'            => ['create_project', 'assign_task', 'update_progress', 'close_project', 'estimate_task'],
            'Manufacturing'       => ['production_dashboard', 'create_production_order', 'start_production', 'record_output', 'quality_check', 'track_bom_items', 'manage_bom', 'sample_request', 'qqcd_calendar', 'import_component'],
            'Quality'             => ['quality_control', 'inspect_component', 'iso_compliance', 'iso_textile', 'iso_construction'],
            'PLM'                 => ['view_dashboard', 'configure_product', 'cpq_index', 'cpq_configure', 'cpq_summary'],
            'Ecommerce'           => ['add_product', 'process_order', 'manage_returns', 'view_analytics'],
            'Logistics'           => ['create_shipment', 'track_delivery', 'manage_carrier', 'warehouse_receipt'],
            'Contracts'           => ['create_contract', 'activate_contract', 'renew_contract', 'expiry_alert'],
            'Assets'              => ['add_asset', 'post_depreciation', 'schedule_maintenance', 'dispose_asset'],
            'Reporting'           => ['create_report', 'schedule_report', 'export_report', 'interpret_results', 'view_dashboard'],
            'BI'                  => ['analyze_data'],
            // Chantier 32.2: 'view_dashboard' added — resources/js/Pages/Helpdesk/
            // Tickets/Show.vue (the real ticket-detail screen) calls
            // useAiAssistant('Helpdesk', 'view_dashboard'), which is not the same
            // (module, action) pair as 'route_ticket' below, so it always
            // resolved to an empty guidance shell.
            'Helpdesk'            => ['route_ticket', 'view_dashboard'],
            'Documents'           => ['ocr_classify'],
            'Timesheets'          => ['view_dashboard'],
            'Planning'            => ['view_dashboard'],
            'CustomerService'     => ['view_dashboard'],
            'Workflow'            => ['view_dashboard'],
            'MarketingAutomation' => ['view_dashboard'],
            // Chantier 32.2: 'calendar_integrations'/'team_calendar'/'view_event'
            // added — 3 real, mounted Calendar pages (Integrations.vue, Teams.vue,
            // Event/Show.vue) each call useAiAssistant('Calendar', <their own
            // action>), none of which matched any key already registered here.
            'Calendar'            => ['view_calendar', 'calendar_settings', 'create_event', 'calendar_integrations', 'team_calendar', 'view_event'],
            // Phase 52 modules
            'SMS'              => ['view_dashboard', 'send_campaign', 'configure_provider'],
            'Payroll'          => ['view_dashboard', 'generate_payslips', 'approve_payroll', 'export_payroll'],
            'Notes'            => ['view_notes', 'create_note', 'search_notes'],
            'SmartTable'       => ['view_dashboard', 'create_base', 'manage_table'],
            'AuditLog'         => ['view_audit_log', 'export_audit', 'filter_events'],
            'Settings'         => ['configure_settings', 'manage_integrations', 'notification_preferences'],
            'Shared'           => ['view_dashboard'],
            // Chantier 30: 'Strategy' was called from 2 real pages (Index.vue,
            // Cascade/Index.vue via useAiAssistant('Strategy', ...)) but was
            // never registered here and had zero fallback map entries — every
            // call silently resolved to emptyGuidance() (enabled:false, every
            // field blank) instead of real guidance text, and the other 7
            // Strategy pages had no AI assistant call at all. Both fixed.
            'Strategy'         => ['view_dashboard', 'view_cascade_map', 'view_ratios', 'view_plans', 'view_plan_detail', 'view_benchmarks', 'view_correlations', 'view_objectives', 'view_sector_kpi'],
            // Chantier 32.2 (14-layer deep audit of Modules\AI): a systematic
            // grep of every real `useAiAssistant(module, action)` call site
            // across the whole app (root `resources/js` + every
            // `Modules/*/resources/js`) found 3 entire modules called from a
            // real, mounted page but never registered here at all — the exact
            // same "silently resolves to an empty guidance shell" bug class
            // Chantier 30 already found and fixed for Strategy, still present
            // elsewhere. 'Analytics' is called from `Modules/Analytics/resources
            // /js/Pages/Index.vue` (the forecasting hub) and `CashflowForecast/
            // Index.vue`. 'Integration' is called from `IntegrationsIndex.vue`.
            // 'Security' is called from `Modules/Security/resources/js/Pages/
            // Index.vue`.
            'Analytics'        => ['view_dashboard'],
            'Integration'      => ['view_dashboard'],
            'Security'         => ['view_dashboard'],
            // Chantier 32.7 (14-layer deep audit of Modules\Validation): the
            // module's own ValidationAiAssistController (POST
            // /api/v1/validation/ai/assist, delegating here with
            // module:'Validation') has existed since an earlier chantier,
            // but 'Validation' was never registered in this map, AND none
            // of its 5 real Vue pages ever called useAiAssistant() at all —
            // the exact same double-gap Chantier 30 found and fixed for
            // 'Strategy'. Confirmed via grep across
            // Modules/Validation/resources/js before adding the calls.
            'Validation'       => ['view_approval_dashboard', 'view_approval_request', 'manage_workflows', 'build_workflow', 'manage_validation_rules'],
        ];
    }

    // -------------------------------------------------------------------------
    // Private — AI provider call (Anthropic/OpenAI/DeepSeek, via AIService)
    // -------------------------------------------------------------------------

    private function callClaude(
        string $module,
        string $action,
        array  $context,
        string $locale,
        string $userRole,
    ): array {
        $langName     = $this->localeName($locale);
        $systemPrompt = $this->buildSystemPrompt($langName);

        $userMessage = json_encode([
            'module'    => $module,
            'action'    => $action,
            'context'   => $context,
            'user_role' => $userRole,
        ]);

        try {
            // 'cache_system' is Anthropic-specific prompt caching
            // (cache_control: ephemeral) — AnthropicProvider applies it when
            // present, other providers ignore it, so this system prompt
            // (identical across every call for a given locale) is still
            // cached provider-side when the active provider supports it.
            $text = $this->aiService->forModule('AI')->chat(
                [['role' => 'user', 'content' => $userMessage]],
                [
                    'system'       => $systemPrompt,
                    'max_tokens'   => 800,
                    'cache_system' => true,
                ]
            );
        } catch (\Throwable $e) {
            return $this->fallbackGuidance($module, $action, $locale);
        }

        return $this->parseResponse($text, $module, $action, $locale);
    }

    private function buildSystemPrompt(string $langName): string
    {
        return <<<PROMPT
You are WideHalo's embedded AI business assistant, always replying in {$langName}.
You receive a JSON object with: module, action, context, user_role.
You MUST reply with a JSON object (no markdown) with these exact keys:
{
  "what_to_do": "one-sentence guidance for this action",
  "how_to_do": ["step 1", "step 2", "step 3"],
  "decision_indicators": [{"label": "...", "value": "...", "status": "ok|warning|critical"}],
  "warnings": ["warning 1 if any"],
  "next_actions": [{"label": "...", "action": "...", "module": "..."}],
  "tips": ["short tip 1", "short tip 2"]
}
Keep responses concise. Use plain business language, no ERP jargon. Max 3 items per array.
For Africa/Asia contexts: suggest mobile money, OHADA compliance checks, and local tax reminders when relevant.
PROMPT;
    }

    private function parseResponse(string $text, string $module, string $action, string $locale): array
    {
        // Strip markdown fences if present
        $text = preg_replace('/^```json\s*/m', '', $text ?? '');
        $text = preg_replace('/^```\s*/m', '', $text ?? '');

        $parsed = json_decode(trim($text), true);

        if (!is_array($parsed)) {
            return $this->fallbackGuidance($module, $action, $locale);
        }

        return array_merge($this->fallbackGuidance($module, $action, $locale), $parsed, ['enabled' => true]);
    }

    // -------------------------------------------------------------------------
    // Public fallback — static guidance when API unavailable
    // -------------------------------------------------------------------------

    /**
     * Static guidance when the API is unavailable.
     * Covers every supported module/action pair in French and English.
     */
    public function fallbackGuidance(string $module, string $action, string $locale = 'fr'): array
    {
        $useFr = ($locale !== 'en');

        $map = $this->getStaticMap($useFr);

        $key      = $module . '.' . $action;
        $defaults = $this->emptyGuidance();

        $entry = $map[$key] ?? null;

        if ($entry === null) {
            return $defaults;
        }

        return array_merge($defaults, $entry, ['enabled' => false]);
    }

    // -------------------------------------------------------------------------
    // Static guidance map (fr + en)
    // -------------------------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    private function getStaticMap(bool $fr): array
    {
        return $fr
            ? $this->frenchMap()
            : $this->englishMap();
    }

    /** @return array<string, array<string, mixed>> */
    private function frenchMap(): array
    {
        return array_merge($this->frenchMapCore(), $this->frenchMapExtended(), $this->frenchMapPhase52(), $this->frenchMapChantier30(), $this->frenchMapChantier32(), $this->frenchMapChantier327(), $this->frenchMapChantier3215(), $this->frenchMapChantier3216());
    }

    /** @return array<string, array<string, mixed>> */
    private function frenchMapCore(): array
    {
        return [
            // ------------------------------------------------------------------
            // CRM
            // ------------------------------------------------------------------
            'CRM.create_contact' => [
                'what_to_do'          => 'Créez un nouveau contact en renseignant les informations essentielles.',
                'how_to_do'           => [
                    'Saisissez le nom complet et l\'email du contact.',
                    'Indiquez le téléphone et le pays pour activer Mobile Money.',
                    'Associez le contact à une entreprise ou opportunité existante.',
                ],
                'decision_indicators' => [
                    ['label' => 'Doublons détectés', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer une opportunité', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Un numéro de téléphone valide est nécessaire pour Orange Money / Wave.',
                    'Vérifiez les doublons avant d\'enregistrer.',
                ],
            ],
            'CRM.view_dashboard' => [
                'what_to_do'          => 'Consultez vos indicateurs CRM en temps réel pour piloter votre activité commerciale.',
                'how_to_do'           => [
                    'Filtrez par période (jour, semaine, mois).',
                    'Identifiez les opportunités à relancer en rouge.',
                    'Exportez le rapport si nécessaire.',
                ],
                'decision_indicators' => [
                    ['label' => 'Opportunités en cours', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Taux de conversion', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer une opportunité', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Relancez les prospects non contactés depuis plus de 7 jours.',
                ],
            ],
            'CRM.create_opportunity' => [
                'what_to_do'          => 'Créez une opportunité commerciale pour suivre un prospect jusqu\'à la vente.',
                'how_to_do'           => [
                    'Sélectionnez ou créez le contact associé.',
                    'Renseignez le montant estimé et la date de clôture.',
                    'Choisissez l\'étape du pipeline (prospect, négociation, gagné…).',
                ],
                'decision_indicators' => [
                    ['label' => 'Valeur pipeline', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un devis', 'action' => 'create_quotation', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Définissez une date de relance pour ne pas oublier le suivi.',
                ],
            ],

            // ------------------------------------------------------------------
            // Accounting
            // ------------------------------------------------------------------
            'Accounting.post_invoice' => [
                'what_to_do'          => 'Enregistrez une facture client ou fournisseur dans le journal comptable.',
                'how_to_do'           => [
                    'Vérifiez le numéro de facture et le tiers concerné.',
                    'Contrôlez les montants HT, TVA et TTC avant validation.',
                    'Sélectionnez le compte OHADA approprié (classe 4 pour les tiers).',
                ],
                'decision_indicators' => [
                    ['label' => 'TVA applicable', 'value' => '18 %', 'status' => 'ok'],
                    ['label' => 'Balance tiers', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Vérifiez la conformité OHADA avant de valider la pièce comptable.',
                ],
                'next_actions'        => [
                    ['label' => 'Rapprocher les paiements', 'action' => 'reconcile', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Le plan SYSCOHADA impose l\'imputation sur les comptes de classe 6/7.',
                    'Archivez la pièce justificative numérique avant validation.',
                ],
            ],
            'Accounting.reconcile' => [
                'what_to_do'          => 'Rapprochez les écritures bancaires avec les paiements enregistrés.',
                'how_to_do'           => [
                    'Importez le relevé bancaire (CSV ou OFX).',
                    'Associez chaque ligne bancaire à une écriture comptable.',
                    'Validez le rapprochement et clôturez la période.',
                ],
                'decision_indicators' => [
                    ['label' => 'Écritures non rapprochées', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Tout écart non justifié doit être analysé avant la clôture mensuelle.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir le bilan', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Rapprochez chaque semaine pour détecter les anomalies rapidement.',
                ],
            ],
            'Accounting.view_balance_sheet' => [
                'what_to_do'          => 'Consultez le bilan comptable pour évaluer la santé financière de l\'entreprise.',
                'how_to_do'           => [
                    'Sélectionnez la période de référence.',
                    'Comparez avec la période précédente.',
                    'Exportez en PDF ou Excel pour le commissaire aux comptes.',
                ],
                'decision_indicators' => [
                    ['label' => 'Résultat net', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Capitaux propres', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Générer rapport OHADA', 'action' => 'ohada_report', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Le bilan SYSCOHADA doit être déposé avant le 30 avril de l\'exercice suivant.',
                ],
            ],
            'Accounting.ohada_report' => [
                'what_to_do'          => 'Générez les états financiers conformes au référentiel OHADA/SYSCOHADA.',
                'how_to_do'           => [
                    'Vérifiez que toutes les écritures de l\'exercice sont validées.',
                    'Lancez la génération des états (bilan, compte de résultat, TAFIRE).',
                    'Téléchargez le fichier PDF ou XML pour transmission aux autorités.',
                ],
                'decision_indicators' => [
                    ['label' => 'Conformité SYSCOHADA', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Assurez-vous d\'utiliser le plan comptable SYSCOHADA révisé 2017.',
                    'Le dépôt légal est obligatoire dans les 17 pays membres de l\'OHADA.',
                ],
                'next_actions'        => [
                    ['label' => 'Exporter le bilan', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Contrôlez les comptes de régularisation (classe 4) avant la génération.',
                ],
            ],

            // ------------------------------------------------------------------
            // HR
            // ------------------------------------------------------------------
            'HR.create_employee' => [
                'what_to_do'          => 'Ajoutez un nouvel employé dans le système RH.',
                'how_to_do'           => [
                    'Renseignez l\'identité, le contrat et le poste.',
                    'Configurez les paramètres de paie (salaire brut, CNSS, IR).',
                    'Enregistrez les coordonnées bancaires ou Mobile Money pour la paie.',
                ],
                'decision_indicators' => [
                    ['label' => 'Effectif total', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Lancer la paie', 'action' => 'run_payroll', 'module' => 'HR'],
                ],
                'tips'                => [
                    'Vérifiez les plafonds CNSS selon le pays de l\'employé.',
                    'Un RIB ou numéro Mobile Money est requis pour le virement de salaire.',
                ],
            ],
            'HR.approve_leave' => [
                'what_to_do'          => 'Approuvez ou refusez une demande de congé en attente.',
                'how_to_do'           => [
                    'Vérifiez le solde de congés disponible de l\'employé.',
                    'Consultez le planning d\'équipe pour éviter les conflits.',
                    'Validez ou refusez avec un commentaire si nécessaire.',
                ],
                'decision_indicators' => [
                    ['label' => 'Congés en attente', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Traitez les demandes sous 48 h pour respecter les délais légaux.',
                ],
            ],
            'HR.run_payroll' => [
                'what_to_do'          => 'Lancez le traitement de la paie pour le mois en cours.',
                'how_to_do'           => [
                    'Vérifiez les présences et heures supplémentaires.',
                    'Contrôlez les éléments variables (primes, absences).',
                    'Validez la paie et générez les bulletins de salaire.',
                ],
                'decision_indicators' => [
                    ['label' => 'Bulletins à générer', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Masse salariale', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Vérifiez les déclarations CNSS et IR avant envoi aux organismes.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Archivez les bulletins signés pour la durée légale (5 ans minimum).',
                ],
            ],

            // ------------------------------------------------------------------
            // Inventory
            // ------------------------------------------------------------------
            'Inventory.receive_stock' => [
                'what_to_do'          => 'Enregistrez une réception de marchandises dans le stock.',
                'how_to_do'           => [
                    'Scannez ou sélectionnez les produits reçus.',
                    'Vérifiez les quantités par rapport au bon de commande.',
                    'Validez la réception pour mettre à jour le stock.',
                ],
                'decision_indicators' => [
                    ['label' => 'Écarts de réception', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un produit manquant', 'action' => 'create_product', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Signalez immédiatement tout colis endommagé au fournisseur.',
                ],
            ],
            'Inventory.create_product' => [
                'what_to_do'          => 'Créez une nouvelle fiche produit dans le catalogue.',
                'how_to_do'           => [
                    'Renseignez le nom, la référence et la catégorie.',
                    'Définissez le prix de vente et le coût d\'achat.',
                    'Configurez le seuil de réapprovisionnement.',
                ],
                'decision_indicators' => [
                    ['label' => 'Produits actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Réceptionner du stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Un code-barres ou SKU unique facilite les inventaires.',
                ],
            ],
            'Inventory.low_stock_alert' => [
                'what_to_do'          => 'Des produits sont en dessous du seuil minimum — prenez action immédiatement.',
                'how_to_do'           => [
                    'Identifiez les produits en rupture imminente.',
                    'Créez une commande fournisseur pour les produits critiques.',
                    'Ajustez les seuils de réapprovisionnement si nécessaire.',
                ],
                'decision_indicators' => [
                    ['label' => 'Produits en alerte', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'Des ruptures de stock peuvent bloquer les ventes et la production.',
                ],
                'next_actions'        => [
                    ['label' => 'Créer commande fournisseur', 'action' => 'create_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Activez les alertes email automatiques pour les produits critiques.',
                ],
            ],

            // ------------------------------------------------------------------
            // Sales
            // ------------------------------------------------------------------
            'Sales.create_order' => [
                'what_to_do'          => 'Créez un bon de commande client ou fournisseur.',
                'how_to_do'           => [
                    'Sélectionnez le client ou fournisseur.',
                    'Ajoutez les lignes de produits avec quantités et prix.',
                    'Vérifiez la disponibilité du stock avant confirmation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Stock disponible', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Confirmer la commande', 'action' => 'confirm_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Vérifiez les conditions de paiement convenues avec le client.',
                ],
            ],
            'Sales.confirm_order' => [
                // Chantier 32.16 (Sales deep 14-layer audit): la version
                // précédente affirmait qu'une facture était générée
                // automatiquement à la confirmation — faux, confirmé en
                // lisant SalesService::confirmOrder() (transition de statut
                // uniquement, aucune facturation) : la vraie facturation
                // passe par le cycle acompte/solde explicite du Chantier 22
                // (voir manage_deposit_balance), jamais automatique.
                'what_to_do'          => 'Confirmez la commande (brouillon → confirmée) pour démarrer sa préparation.',
                'how_to_do'           => [
                    'Vérifiez les lignes et le total avant de confirmer — une commande confirmée n\'est plus modifiable.',
                    'Confirmez les conditions de livraison et délais.',
                    'Une fois confirmée, demandez l\'acompte depuis la fiche détail de la commande.',
                ],
                'decision_indicators' => [
                    ['label' => 'Commandes en attente', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'La confirmation ne génère aucune facture automatiquement — utilisez le cycle acompte/solde sur la fiche de la commande.',
                ],
                'next_actions'        => [
                    ['label' => 'Gérer acompte/solde', 'action' => 'manage_deposit_balance', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Envoyez une confirmation par email ou WhatsApp au client.',
                ],
            ],
            'Sales.manage_deposit_balance' => [
                'what_to_do'          => 'Suivez et encaissez l\'acompte puis le solde d\'une commande confirmée depuis sa fiche détail.',
                'how_to_do'           => [
                    'Demandez un acompte (pourcentage du total) — une vraie facture liée est créée automatiquement.',
                    'Enregistrez le paiement de l\'acompte reçu (Mvola, virement, espèces…) une fois encaissé.',
                    'Une fois l\'acompte payé, demandez le solde restant, puis enregistrez son paiement à la livraison.',
                ],
                'decision_indicators' => [
                    ['label' => 'Étape du cycle', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un acompte ou un solde ne peut être demandé qu\'une seule fois par commande — le montant est figé à la demande, pas recalculé automatiquement si la commande change ensuite.',
                    'Un paiement qui dépasserait le montant de la facture liée est rejeté par le serveur.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Chaque paiement encaissé génère une vraie écriture comptable équilibrée (comptes OHADA 419 pour l\'acompte, 411 pour le solde).',
                ],
            ],
            'Sales.create_quotation' => [
                'what_to_do'          => 'Créez un devis commercial à envoyer à un prospect ou client.',
                'how_to_do'           => [
                    'Sélectionnez le contact ou l\'opportunité liée.',
                    'Ajoutez les produits/services avec prix négociés.',
                    'Définissez la date de validité et les conditions.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux de conversion devis', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Convertir en commande', 'action' => 'create_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Incluez les conditions de paiement Mobile Money pour les clients africains.',
                ],
            ],

            // ------------------------------------------------------------------
            // POS
            // ------------------------------------------------------------------
            'POS.open_session' => [
                'what_to_do'          => 'Ouvrez une session de caisse pour démarrer la journée de vente.',
                'how_to_do'           => [
                    'Vérifiez le fond de caisse initial.',
                    'Sélectionnez le mode de paiement actif (espèces, Mobile Money, carte).',
                    'Validez l\'ouverture de session.',
                ],
                'decision_indicators' => [
                    ['label' => 'Fond de caisse', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Traiter un paiement', 'action' => 'process_payment', 'module' => 'POS'],
                ],
                'tips'                => [
                    'Comptez physiquement le fond de caisse avant chaque ouverture.',
                ],
            ],
            'POS.process_payment' => [
                'what_to_do'          => 'Encaissez le paiement d\'un client pour finaliser la vente.',
                'how_to_do'           => [
                    'Scannez les produits ou sélectionnez-les manuellement.',
                    'Choisissez le mode de paiement (espèces, Wave, Orange Money, carte).',
                    'Imprimez ou envoyez le reçu au client.',
                ],
                'decision_indicators' => [
                    ['label' => 'Total panier', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Wave et Orange Money sont acceptés sans frais supplémentaires.',
                ],
            ],
            'POS.close_session' => [
                'what_to_do'          => 'Clôturez la session de caisse en fin de journée.',
                'how_to_do'           => [
                    'Comptez physiquement la caisse.',
                    'Comparez avec le total calculé par le système.',
                    'Validez et archivez le rapport de caisse.',
                ],
                'decision_indicators' => [
                    ['label' => 'Écart de caisse', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Tout écart doit être justifié avant la clôture.',
                ],
                'next_actions'        => [
                    ['label' => 'Enregistrer les recettes', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Clôturez la caisse chaque soir pour éviter les erreurs d\'inventaire.',
                ],
            ],

            // ------------------------------------------------------------------
            // Setup (onboarding wizard)
            // ------------------------------------------------------------------
            'Setup.import_file' => [
                'what_to_do'          => 'Importez votre fichier de données (Excel, CSV ou PDF) dans WideHalo.',
                'how_to_do'           => [
                    'Glissez-déposez ou sélectionnez votre fichier (max 50 Mo).',
                    'Vérifiez le format détecté automatiquement.',
                    'Passez à l\'étape de mapping des colonnes.',
                ],
                'decision_indicators' => [
                    ['label' => 'Format détecté', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Lignes détectées', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Mapper les colonnes', 'action' => 'map_columns', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'Les fichiers Excel (.xlsx) et CSV UTF-8 sont recommandés.',
                    'Supprimez les lignes d\'en-tête superflues avant l\'import.',
                ],
            ],
            'Setup.map_columns' => [
                'what_to_do'          => 'Associez les colonnes de votre fichier aux champs WideHalo.',
                'how_to_do'           => [
                    'L\'IA suggère automatiquement les correspondances les plus probables.',
                    'Vérifiez et corrigez les associations proposées.',
                    'Ignorez les colonnes non pertinentes.',
                ],
                'decision_indicators' => [
                    ['label' => 'Colonnes mappées', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Colonnes non mappées', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Les champs obligatoires (nom, email) doivent être associés avant de continuer.',
                ],
                'next_actions'        => [
                    ['label' => 'Lancer l\'import', 'action' => 'execute_import', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'Sauvegardez votre configuration de mapping pour les prochains imports.',
                ],
            ],
            'Setup.execute_import' => [
                'what_to_do'          => 'Lancez l\'import des données pour les intégrer dans WideHalo.',
                'how_to_do'           => [
                    'Vérifiez le résumé de l\'import (lignes valides vs erreurs).',
                    'Choisissez le mode : ajout seul, mise à jour ou les deux.',
                    'Confirmez l\'import — les données seront chargées en arrière-plan.',
                ],
                'decision_indicators' => [
                    ['label' => 'Lignes valides', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Lignes en erreur', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Téléchargez le rapport d\'erreurs pour corriger les lignes rejetées.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Un import test sur 10 lignes est recommandé avant le chargement complet.',
                ],
            ],

            // Chantier 32.10: the 6-step onboarding wizard itself (distinct
            // from the import sub-flow above).
            'Setup.wizard_company' => [
                'what_to_do'          => 'Renseignez les informations légales de votre société.',
                'how_to_do'           => [
                    'Indiquez le nom commercial et, si différent, la raison sociale.',
                    'Choisissez le pays — la devise et le fuseau horaire seront suggérés automatiquement.',
                    'Passez à l\'étape suivante pour créer votre profil administrateur.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Le pays sélectionné détermine les règles fiscales (TVA) et comptables (OHADA) appliquées par défaut.',
                ],
                'next_actions'        => [
                    ['label' => 'Profil administrateur', 'action' => 'wizard_admin', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_admin' => [
                'what_to_do'          => 'Confirmez votre profil administrateur (nom, langue, fuseau horaire).',
                'how_to_do'           => [
                    'Vérifiez le nom affiché pour votre compte.',
                    'Choisissez la langue de l\'interface.',
                    'Validez pour passer à la sélection des modules.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Sélection des modules', 'action' => 'wizard_modules', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_modules' => [
                'what_to_do'          => 'Activez les modules dont votre société a besoin.',
                'how_to_do'           => [
                    'Cochez les modules à activer immédiatement — vous pourrez en activer d\'autres plus tard.',
                    'Chaque module activé devient visible dans la navigation principale.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Désactiver un module en cours d\'usage peut masquer des données déjà saisies — elles ne sont jamais supprimées.',
                ],
                'next_actions'        => [
                    ['label' => 'Configuration des workflows', 'action' => 'wizard_workflows', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_workflows' => [
                'what_to_do'          => 'Configurez les règles d\'approbation et les canaux de notification.',
                'how_to_do'           => [
                    'Activez l\'approbation obligatoire si les décisions doivent être validées par un responsable.',
                    'Choisissez les canaux de notification (email, SMS, WhatsApp, push).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Applications à activer', 'action' => 'wizard_apps', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_apps' => [
                'what_to_do'          => 'Choisissez les applications (web, mobile, API) que votre équipe utilisera.',
                'how_to_do'           => [
                    'Activez l\'application web pour un accès depuis un navigateur.',
                    'Activez l\'API si vous prévoyez d\'intégrer WideHalo à d\'autres systèmes.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Finaliser la configuration', 'action' => 'wizard_complete', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_complete' => [
                'what_to_do'          => 'Finalisez la configuration pour démarrer avec WideHalo.',
                'how_to_do'           => [
                    'Vérifiez le résumé de votre configuration.',
                    'Cliquez sur Terminer pour activer votre espace de travail.',
                    'Vous pourrez ensuite importer vos données existantes (clients, produits, factures).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Importer des données', 'action' => 'import_file', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'Toute la configuration reste modifiable après coup depuis les réglages administrateur.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function frenchMapExtended(): array
    {
        return [
            // ------------------------------------------------------------------
            // Achats
            // ------------------------------------------------------------------
            'Achats.create_order' => [
                'what_to_do'          => 'Créez un bon de commande fournisseur pour déclencher le processus d\'achat.',
                'how_to_do'           => [
                    'Sélectionnez le fournisseur et les produits à commander.',
                    'Renseignez les quantités et les prix négociés.',
                    'Soumettez à validation selon le workflow d\'approbation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Commandes en attente', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Budget disponible', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Approuver la commande', 'action' => 'approve_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Comparez les devis de plusieurs fournisseurs avant de valider.',
                    'Vérifiez les délais de livraison pour anticiper les besoins.',
                ],
            ],
            'Achats.approve_order' => [
                'what_to_do'          => 'Approuvez le bon de commande pour autoriser l\'achat auprès du fournisseur.',
                'how_to_do'           => [
                    'Vérifiez les détails de la commande (produits, quantités, prix).',
                    'Contrôlez le budget disponible sur la ligne budgétaire concernée.',
                    'Approuvez ou rejetez avec un commentaire justificatif.',
                ],
                'decision_indicators' => [
                    ['label' => 'Montant total', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Seuil d\'approbation', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Toute commande dépassant le seuil autorisé requiert une double approbation.',
                ],
                'next_actions'        => [
                    ['label' => 'Réceptionner les marchandises', 'action' => 'receive_goods', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Conservez la trace des approbations pour l\'audit OHADA.',
                ],
            ],
            'Achats.receive_goods' => [
                'what_to_do'          => 'Enregistrez la réception des marchandises commandées chez le fournisseur.',
                'how_to_do'           => [
                    'Associez la livraison au bon de commande d\'origine.',
                    'Vérifiez les quantités et l\'état des marchandises reçues.',
                    'Validez la réception pour mettre à jour le stock.',
                ],
                'decision_indicators' => [
                    ['label' => 'Écarts de quantité', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Rapprochement 3 voies', 'action' => 'three_way_match', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Photographiez les marchandises endommagées pour réclamation.',
                ],
            ],
            'Achats.three_way_match' => [
                'what_to_do'          => 'Effectuez le rapprochement tripartite entre commande, réception et facture.',
                'how_to_do'           => [
                    'Comparez le bon de commande, le bon de réception et la facture fournisseur.',
                    'Identifiez et justifiez tout écart de prix ou de quantité.',
                    'Validez pour autoriser le paiement fournisseur.',
                ],
                'decision_indicators' => [
                    ['label' => 'Documents correspondants', 'value' => '3/3', 'status' => 'ok'],
                    ['label' => 'Écarts détectés', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Ne payez pas une facture sans rapprochement 3 voies validé.',
                ],
                'next_actions'        => [
                    ['label' => 'Enregistrer la facture', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Le rapprochement 3 voies est une bonne pratique exigée en OHADA.',
                ],
            ],

            // ------------------------------------------------------------------
            // Projects
            // ------------------------------------------------------------------
            'Projects.create_project' => [
                'what_to_do'          => 'Créez un nouveau projet et définissez ses paramètres de base.',
                'how_to_do'           => [
                    'Renseignez le nom, les dates de début et de fin et le responsable.',
                    'Définissez le budget et les jalons principaux.',
                    'Invitez les membres de l\'équipe projet.',
                ],
                'decision_indicators' => [
                    ['label' => 'Projets actifs', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Budget alloué', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Assigner des tâches', 'action' => 'assign_task', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Définissez des jalons clairs pour suivre l\'avancement facilement.',
                ],
            ],
            'Projects.assign_task' => [
                'what_to_do'          => 'Assignez une tâche à un membre de l\'équipe avec une date d\'échéance.',
                'how_to_do'           => [
                    'Sélectionnez le projet et créez ou choisissez une tâche existante.',
                    'Attribuez la tâche à un membre de l\'équipe disponible.',
                    'Définissez la priorité et la date limite.',
                ],
                'decision_indicators' => [
                    ['label' => 'Tâches non assignées', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Mettre à jour l\'avancement', 'action' => 'update_progress', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Évitez de surcharger un même collaborateur avec trop de tâches parallèles.',
                ],
            ],
            'Projects.update_progress' => [
                'what_to_do'          => 'Mettez à jour l\'avancement du projet pour refléter l\'état actuel.',
                'how_to_do'           => [
                    'Sélectionnez les tâches complétées et mettez-les à "Terminé".',
                    'Ajustez le pourcentage d\'avancement du projet.',
                    'Ajoutez un commentaire de suivi si nécessaire.',
                ],
                'decision_indicators' => [
                    ['label' => 'Avancement global', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Tâches en retard', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Mettez à jour l\'avancement chaque semaine pour détecter les blocages tôt.',
                ],
            ],
            'Projects.close_project' => [
                'what_to_do'          => 'Clôturez le projet après validation de tous les livrables.',
                'how_to_do'           => [
                    'Vérifiez que toutes les tâches sont marquées comme terminées.',
                    'Clôturez les budgets et archivez les documents du projet.',
                    'Rédigez le bilan de projet et partagez-le avec l\'équipe.',
                ],
                'decision_indicators' => [
                    ['label' => 'Tâches restantes', 'value' => '0', 'status' => 'ok'],
                    ['label' => 'Budget consommé', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Assurez-vous que tous les livrables ont été acceptés par le client avant la clôture.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Documentez les leçons apprises pour améliorer les prochains projets.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing
            // ------------------------------------------------------------------
            'Manufacturing.production_dashboard' => [
                'what_to_do'          => 'Pilotez votre tableau de bord de production pour suivre l\'avancement des ordres de fabrication en temps réel.',
                'how_to_do'           => [
                    'Consultez le planning des postes de charge pour la semaine en cours.',
                    'Identifiez les ordres en retard ou à risque et replanifiez si nécessaire.',
                    'Glissez-déposez les ordres pour optimiser l\'affectation des ressources.',
                ],
                'decision_indicators' => [
                    ['label' => 'Ordres en cours', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Ordres en retard', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Taux de charge', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un ordre de fabrication', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                    ['label' => 'Contrôle qualité', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Un ordre en rouge indique un dépassement de délai — agissez rapidement.',
                    'Replanifiez en priorité les ordres liés aux commandes clients urgentes.',
                ],
            ],
            'Manufacturing.create_production_order' => [
                'what_to_do'          => 'Créez un ordre de fabrication pour lancer la production d\'un article.',
                'how_to_do'           => [
                    'Sélectionnez l\'article à produire et la quantité souhaitée.',
                    'Vérifiez la disponibilité des matières premières (nomenclature).',
                    'Planifiez la date de lancement et la capacité machine disponible.',
                ],
                'decision_indicators' => [
                    ['label' => 'Matières disponibles', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Capacité disponible', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Démarrer la production', 'action' => 'start_production', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Vérifiez la nomenclature (BOM) avant de créer l\'ordre de fabrication.',
                ],
            ],
            'Manufacturing.start_production' => [
                'what_to_do'          => 'Démarrez l\'ordre de fabrication et lancez le suivi en temps réel.',
                'how_to_do'           => [
                    'Confirmez la disponibilité des opérateurs et des machines.',
                    'Déclenchez l\'ordre de fabrication et prélevez les matières premières.',
                    'Enregistrez le début de production avec l\'heure de démarrage.',
                ],
                'decision_indicators' => [
                    ['label' => 'Opérateurs disponibles', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Machines opérationnelles', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Enregistrer la production', 'action' => 'record_output', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Assurez-vous que les équipements de sécurité sont en place avant le démarrage.',
                ],
            ],
            'Manufacturing.record_output' => [
                'what_to_do'          => 'Enregistrez les quantités produites et les éventuels rebuts.',
                'how_to_do'           => [
                    'Saisissez la quantité produite conforme et les rebuts.',
                    'Indiquez les causes des rebuts pour l\'analyse qualité.',
                    'Validez pour mettre à jour le stock de produits finis.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux de rebut', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Quantité produite', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Contrôle qualité', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Un taux de rebut > 5 % nécessite une analyse des causes racines.',
                ],
            ],
            'Manufacturing.quality_check' => [
                'what_to_do'          => 'Effectuez le contrôle qualité sur les articles produits avant expédition.',
                'how_to_do'           => [
                    'Prélevez un échantillon selon le plan de contrôle défini.',
                    'Enregistrez les résultats des tests qualité.',
                    'Approuvez ou bloquez le lot selon les critères de conformité.',
                ],
                'decision_indicators' => [
                    ['label' => 'Lots conformes', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Lots bloqués', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un lot non conforme ne doit pas être expédié sans dérogation approuvée.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Documentez tous les contrôles pour la traçabilité ISO.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality
            // ------------------------------------------------------------------
            'Quality.quality_control' => [
                'what_to_do'          => 'Gérez les inspections et non-conformités pour maintenir un taux de conformité élevé.',
                'how_to_do'           => [
                    'Lancez une nouvelle inspection en sélectionnant le produit et l\'ordre de fabrication concerné.',
                    'Enregistrez le résultat (conforme / non-conforme) et les observations.',
                    'Traitez les non-conformités ouvertes avant leur date d\'échéance.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux de conformité', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'NC ouvertes', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Inspections ce mois', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Les non-conformités critiques doivent être traitées sous 24 h.',
                ],
                'next_actions'        => [
                    ['label' => 'Contrôle qualité production', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Analysez les causes racines des NC récurrentes pour éviter leur répétition.',
                    'Un taux de conformité < 95 % nécessite une revue du processus qualité.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — BOM Cross-Module Tracking
            // ------------------------------------------------------------------
            'Manufacturing.track_bom_items' => [
                'what_to_do'          => 'Suivez l\'avancement de chaque composant du BOM depuis la commande jusqu\'à la consommation.',
                'how_to_do'           => [
                    'Vérifiez les composants en rupture ou à commander (statut rouge).',
                    'Déclenchez les bons de commande manquants via le module Achats.',
                    'Validez les réceptions avec le contrôle qualité avant de réserver pour la production.',
                ],
                'decision_indicators' => [
                    ['label' => 'Composants disponibles', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'En attente de commande', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Contrôle qualité', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un composant en rupture bloque le lancement de la production.',
                ],
                'next_actions'        => [
                    ['label' => 'Créer bon de commande', 'action' => 'create_order', 'module' => 'Achats'],
                    ['label' => 'Lancer contrôle qualité', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'Initialisez le suivi dès la confirmation de l\'ordre de fabrication.',
                    'Planifiez les dates de livraison pour anticiper les ruptures.',
                ],
            ],
            'Manufacturing.manage_bom' => [
                'what_to_do'          => 'Gérez vos nomenclatures (BOM) et les composants nécessaires à la production.',
                'how_to_do'           => [
                    'Créez ou mettez à jour les nomenclatures avec les composants et quantités requises.',
                    'Associez chaque composant à un SKU du catalogue produits.',
                    'Approuvez la nomenclature avant de l\'utiliser dans un ordre de fabrication.',
                ],
                'decision_indicators' => [
                    ['label' => 'BOM actives', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'En attente d\'approbation', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un ordre de fabrication', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Explosez la BOM pour vérifier la disponibilité des composants avant de confirmer l\'ordre.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — QQCD Sample Requests
            // ------------------------------------------------------------------
            'Manufacturing.sample_request' => [
                'what_to_do'          => 'Demandez des échantillons à plusieurs fournisseurs avant de passer commande.',
                'how_to_do'           => [
                    'Définissez vos critères QQCD cibles (quantité, qualité, coût, délai).',
                    'Envoyez la demande à 3-5 fournisseurs simultanément.',
                    'Évaluez les réponses avec la matrice QQCD et validez le meilleur fournisseur.',
                ],
                'decision_indicators' => [
                    ['label' => 'Demandes en cours', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Fournisseurs validés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un fournisseur non évalué peut bloquer la production en cas de non-conformité.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir le calendrier QQCD', 'action' => 'qqcd_calendar', 'module' => 'Manufacturing'],
                    ['label' => 'Créer bon de commande', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Pondérez la qualité à 35% pour les composants critiques.',
                    'Ajoutez un buffer de 20% sur le délai pour les imports internationaux.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — QQCD Calendar
            // ------------------------------------------------------------------
            'Manufacturing.qqcd_calendar' => [
                'what_to_do'          => 'Planifiez les demandes d\'échantillons en avance pour ne pas bloquer la production.',
                'how_to_do'           => [
                    'Identifiez les BOM des 3 prochains mois nécessitant des commandes.',
                    'Calculez les délais backward depuis la date de production requise.',
                    'Déclenchez les demandes d\'échantillons avec le buffer nécessaire (min. 14 jours avant commande).',
                ],
                'decision_indicators' => [
                    ['label' => 'Composants à commander', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'En retard sur planning', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'Un retard dans la demande d\'échantillons décale toute la chaîne d\'approvisionnement.',
                ],
                'next_actions'        => [
                    ['label' => 'Demander échantillons', 'action' => 'sample_request', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Planifiez les demandes au moins 3 semaines avant la date de commande souhaitée.',
                    'Regroupez les demandes par fournisseur pour accélérer l\'évaluation.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — Import international
            // ------------------------------------------------------------------
            'Manufacturing.import_component' => [
                'what_to_do'          => 'Gérez le processus d\'importation du composant en anticipant douanes et transit.',
                'how_to_do'           => [
                    'Vérifiez le code HS et les droits de douane applicables dans le pays de destination.',
                    'Créez la timeline import depuis la date de besoin (backward planning).',
                    'Préparez les documents requis en avance (certificats, licences, déclarations).',
                ],
                'decision_indicators' => [
                    ['label' => 'Composants importés', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Documents manquants', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Un document manquant au dédouanement peut immobiliser la marchandise plusieurs jours.',
                ],
                'next_actions'        => [
                    ['label' => 'Calendrier QQCD', 'action' => 'qqcd_calendar', 'module' => 'Manufacturing'],
                    ['label' => 'Calculer le coût rendu', 'action' => 'import_component', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Prévoyez 5 jours de buffer supplémentaires pour le dédouanement en haute saison.',
                    'Vérifiez la validité des certificats (origine, qualité) avant chaque expédition.',
                ],
            ],

                        // ------------------------------------------------------------------
            // Quality — BOM component inspection
            // ------------------------------------------------------------------
            'Quality.inspect_component' => [
                'what_to_do'          => 'Effectuez le contrôle qualité d\'un composant reçu dans le cadre d\'un ordre de fabrication.',
                'how_to_do'           => [
                    'Vérifiez le composant reçu par rapport au bon de commande (quantité, référence, état).',
                    'Consultez la checklist ISO générée pour ce composant.',
                    'Enregistrez le résultat du contrôle (conforme / non-conforme) : si conforme, marquez le composant "Disponible" ; si non-conforme, déclenchez le retour fournisseur.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contrôles en attente', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Taux de conformité', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Certifications ISO vérifiées', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un composant non conforme ne doit pas être utilisé en production.',
                    'Vérifiez les certificats du fournisseur avant de lancer l\'inspection.',
                ],
                'next_actions'        => [
                    ['label' => 'Passer à Disponible', 'action' => 'track_bom_items', 'module' => 'Manufacturing'],
                    ['label' => 'Voir conformité ISO', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Recommander', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Documentez les photos de réception pour les litiges fournisseur.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — ISO Compliance
            // ------------------------------------------------------------------
            'Quality.iso_compliance' => [
                'what_to_do'          => 'Vérifiez la conformité ISO de chaque fournisseur avant de valider un échantillon.',
                'how_to_do'           => [
                    'Consultez la matrice de certifications fournisseurs.',
                    'Identifiez les normes manquantes ou expirées.',
                    'Bloquez la validation QQCD si une norme obligatoire est absente.',
                ],
                'decision_indicators' => [
                    ['label' => 'Normes actives', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Certifications expirées', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Gaps obligatoires', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Une certification expirée équivaut à une certification absente.',
                    'Les composants RoHS/REACH requièrent des tests en laboratoire accrédité.',
                ],
                'next_actions'        => [
                    ['label' => 'Matrice certifications', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Fiche fournisseur', 'action' => 'receive_goods', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Intégrez une clause de renouvellement automatique dans vos contrats fournisseurs.',
                    'Planifiez les audits ISO 6 mois avant l\'expiration.',
                ],
            ],

            // ------------------------------------------------------------------
            // PLM
            // ------------------------------------------------------------------
            'PLM.view_dashboard' => [
                'what_to_do'          => 'Consultez et gérez les modèles d\'articles et leur cycle de vie produit.',
                'how_to_do'           => [
                    'Recherchez un modèle existant ou créez-en un nouveau.',
                    'Vérifiez l\'état du cycle de vie (Brouillon, En révision, Approuvé, Obsolète).',
                    'Gérez les variantes et les nomenclatures associées à chaque modèle.',
                ],
                'decision_indicators' => [
                    ['label' => 'Modèles actifs', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'En révision', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Variantes totales', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Validez les modèles en révision avant de les utiliser en production.',
                ],
                'next_actions'        => [
                    ['label' => 'Créer un ordre de fabrication', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Archivez les modèles obsolètes pour garder le catalogue lisible.',
                    'Associez chaque modèle à une nomenclature (BOM) pour faciliter la planification.',
                ],
            ],

            // ------------------------------------------------------------------
            // PLM — CPQ Configure, Price, Quote
            // ------------------------------------------------------------------
            'PLM.configure_product' => [
                'what_to_do'          => 'Configurez le produit étape par étape pour générer automatiquement le BOM, les coûts et le devis client.',
                'how_to_do'           => [
                    'Sélectionnez le domaine (Textile / Construction) et le template adapté.',
                    'Complétez les attributs — le système calcule le prix et le BOM en temps réel.',
                    'Vérifiez les normes ISO déclenchées avant de générer le devis.',
                ],
                'decision_indicators' => [
                    ['label' => 'Configurations en cours', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Devis générés ce mois',   'value' => '—', 'status' => 'ok'],
                    ['label' => 'ISO non conformes',        'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Un BOM incomplet bloque la génération de l\'ordre de production.',
                    'Les certifications ISO manquantes bloquent la validation QQCD fournisseur.',
                ],
                'next_actions'        => [
                    ['label' => 'Démarrer une configuration', 'action' => 'cpq_configure', 'module' => 'PLM'],
                    ['label' => 'Créer un devis',             'action' => 'create_quotation', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Textile : le FINEX augmente significativement pour les imports > 8 semaines.',
                    'Construction : la classe de feu (EN 13501) REI 120 impacte jusqu\'à 25 % du coût structure.',
                    'Liez la session à une opportunité CRM pour un suivi pipeline complet.',
                ],
            ],
            'PLM.cpq_index' => [
                'what_to_do'          => 'Choisissez un template CPQ pour démarrer une nouvelle configuration produit.',
                'how_to_do'           => [
                    'Filtrez par domaine : Textile, Construction ou Générique.',
                    'Cliquez sur « Configurer » pour lancer l\'assistant étape par étape.',
                    'Reprenez une configuration existante depuis le tableau « Récentes ».',
                ],
                'decision_indicators' => [
                    ['label' => 'Templates disponibles', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Brouillons en attente', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Démarrer Textile',      'action' => 'cpq_configure', 'module' => 'PLM'],
                    ['label' => 'Démarrer Construction', 'action' => 'cpq_configure', 'module' => 'PLM'],
                ],
                'tips'                => [
                    'Chaque configuration génère automatiquement un BOM et une estimation CAPEX/OPEX/FINEX/RISKEX.',
                ],
            ],
            'PLM.cpq_configure' => [
                'what_to_do'          => 'Remplissez chaque étape du configurateur — le prix et le BOM se calculent en temps réel.',
                'how_to_do'           => [
                    'Naviguez étape par étape via le panneau de gauche.',
                    'Les champs rouges sont obligatoires — les warnings jaunes signalent des normes ISO déclenchées.',
                    'Le panneau droit affiche le prix estimé et le BOM préliminaire à chaque saisie.',
                ],
                'decision_indicators' => [
                    ['label' => 'Étapes complètes',   'value' => '—', 'status' => 'ok'],
                    ['label' => 'Normes ISO actives', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'BOM généré',         'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Vérifiez que les certifications des fournisseurs couvrent les normes ISO déclenchées.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir le récapitulatif', 'action' => 'cpq_summary', 'module' => 'PLM'],
                ],
                'tips'                => [
                    'Utilisez « Suggestions IA » pour obtenir les valeurs optimales pour chaque étape.',
                ],
            ],
            'PLM.cpq_summary' => [
                'what_to_do'          => 'Vérifiez la configuration finale, ajustez la marge et générez le devis client.',
                'how_to_do'           => [
                    'Vérifiez le BOM et la décomposition CAPEX/OPEX/FINEX/RISKEX.',
                    'Ajustez la marge via le curseur (défaut 30 %).',
                    'Cliquez sur « Générer le devis Sales » pour créer le devis et l\'opportunité CRM.',
                ],
                'decision_indicators' => [
                    ['label' => 'Marge suggérée',        'value' => '30 %', 'status' => 'ok'],
                    ['label' => 'ISO conformes',          'value' => '—',    'status' => 'ok'],
                    ['label' => 'BOM complet',            'value' => '—',    'status' => 'ok'],
                ],
                'warnings'            => [
                    'Une norme ISO manquante bloque la création de l\'ordre de production.',
                ],
                'next_actions'        => [
                    ['label' => 'Créer le devis',              'action' => 'create_quotation',       'module' => 'Sales'],
                    ['label' => 'Créer l\'ordre de production', 'action' => 'create_production_order','module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Le devis généré est automatiquement lié à l\'opportunité CRM sélectionnée.',
                ],
            ],

            // ------------------------------------------------------------------
            // Ecommerce
            // ------------------------------------------------------------------
            'Ecommerce.add_product' => [
                'what_to_do'          => 'Ajoutez un produit à votre boutique en ligne avec toutes ses informations.',
                'how_to_do'           => [
                    'Renseignez le titre, la description et les photos du produit.',
                    'Définissez le prix de vente et la gestion du stock.',
                    'Publiez le produit sur la boutique.',
                ],
                'decision_indicators' => [
                    ['label' => 'Produits publiés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Traiter une commande', 'action' => 'process_order', 'module' => 'Ecommerce'],
                ],
                'tips'                => [
                    'Des photos de qualité augmentent le taux de conversion de 30 %.',
                    'Optimisez les descriptions pour le référencement (SEO).',
                ],
            ],
            'Ecommerce.process_order' => [
                'what_to_do'          => 'Traitez une commande en ligne et déclenchez l\'expédition.',
                'how_to_do'           => [
                    'Vérifiez le paiement reçu (Mobile Money, carte, virement).',
                    'Préparez la commande et générez le bon de livraison.',
                    'Informez le client par email ou SMS de l\'expédition.',
                ],
                'decision_indicators' => [
                    ['label' => 'Commandes à traiter', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Paiements confirmés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Gérer les retours', 'action' => 'manage_returns', 'module' => 'Ecommerce'],
                ],
                'tips'                => [
                    'Traitez les commandes sous 24 h pour améliorer la satisfaction client.',
                ],
            ],
            'Ecommerce.manage_returns' => [
                'what_to_do'          => 'Gérez les retours produits et remboursements clients.',
                'how_to_do'           => [
                    'Vérifiez le motif du retour et l\'état du produit retourné.',
                    'Choisissez entre remboursement, échange ou avoir commercial.',
                    'Enregistrez le retour en stock après inspection.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux de retour', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Le délai légal de remboursement est de 14 jours dans la plupart des pays.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Analysez les motifs de retour pour améliorer vos produits.',
                ],
            ],
            'Ecommerce.view_analytics' => [
                'what_to_do'          => 'Consultez les statistiques de votre boutique en ligne pour optimiser les ventes.',
                'how_to_do'           => [
                    'Sélectionnez la période d\'analyse.',
                    'Identifiez les produits les plus vendus et les paniers abandonnés.',
                    'Exportez les données pour un rapport détaillé.',
                ],
                'decision_indicators' => [
                    ['label' => 'Chiffre d\'affaires', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Taux de conversion', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Un taux de conversion > 3 % est un bon indicateur pour l\'e-commerce.',
                ],
            ],

            // ------------------------------------------------------------------
            // Logistics
            // ------------------------------------------------------------------
            'Logistics.create_shipment' => [
                'what_to_do'          => 'Créez un bon d\'expédition pour préparer l\'envoi des marchandises.',
                'how_to_do'           => [
                    'Sélectionnez les commandes à inclure dans l\'expédition.',
                    'Choisissez le transporteur et le mode de livraison.',
                    'Générez les étiquettes et les documents d\'expédition.',
                ],
                'decision_indicators' => [
                    ['label' => 'Commandes prêtes', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Suivre la livraison', 'action' => 'track_delivery', 'module' => 'Logistics'],
                ],
                'tips'                => [
                    'Vérifiez les réglementations douanières pour les exportations.',
                ],
            ],
            'Logistics.track_delivery' => [
                'what_to_do'          => 'Suivez l\'acheminement de vos expéditions en temps réel.',
                'how_to_do'           => [
                    'Saisissez le numéro de suivi ou sélectionnez l\'expédition.',
                    'Consultez les étapes de livraison et l\'ETA actualisée.',
                    'Alertez le client en cas de retard.',
                ],
                'decision_indicators' => [
                    ['label' => 'Livraisons en cours', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Livraisons en retard', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Activez les notifications automatiques pour informer vos clients.',
                ],
            ],
            'Logistics.manage_carrier' => [
                'what_to_do'          => 'Gérez les transporteurs partenaires et leurs tarifs.',
                'how_to_do'           => [
                    'Ajoutez ou mettez à jour les informations du transporteur.',
                    'Configurez les grilles tarifaires et zones de couverture.',
                    'Activez ou désactivez les transporteurs selon la disponibilité.',
                ],
                'decision_indicators' => [
                    ['label' => 'Transporteurs actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Comparez les performances des transporteurs (délai, coût) chaque trimestre.',
                ],
            ],
            'Logistics.warehouse_receipt' => [
                'what_to_do'          => 'Enregistrez la réception de marchandises dans l\'entrepôt.',
                'how_to_do'           => [
                    'Identifiez les marchandises reçues avec le bon de livraison.',
                    'Vérifiez les quantités et l\'état des articles.',
                    'Rangez les articles dans leurs emplacements dédiés.',
                ],
                'decision_indicators' => [
                    ['label' => 'Emplacements disponibles', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Réceptionner en stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Utilisez le scan de codes-barres pour accélérer la réception.',
                ],
            ],

            // ------------------------------------------------------------------
            // Contracts
            // ------------------------------------------------------------------
            'Contracts.create_contract' => [
                'what_to_do'          => 'Créez un contrat commercial ou prestataire dans le système.',
                'how_to_do'           => [
                    'Renseignez les parties contractantes et l\'objet du contrat.',
                    'Définissez les dates de début, de fin et les conditions financières.',
                    'Joignez le document contractuel signé.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contrats actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Activer le contrat', 'action' => 'activate_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Configurez les alertes d\'expiration pour anticiper les renouvellements.',
                ],
            ],
            'Contracts.activate_contract' => [
                'what_to_do'          => 'Activez un contrat pour démarrer son suivi et sa facturation.',
                'how_to_do'           => [
                    'Vérifiez que toutes les clauses sont correctement renseignées.',
                    'Confirmez la signature des deux parties.',
                    'Activez le contrat pour déclencher le suivi automatique.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contrats en attente', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Un contrat ne peut être activé sans documents justificatifs joints.',
                ],
                'next_actions'        => [
                    ['label' => 'Planifier le renouvellement', 'action' => 'renew_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Notifiez toutes les parties de l\'activation par email.',
                ],
            ],
            'Contracts.renew_contract' => [
                'what_to_do'          => 'Renouvelez un contrat arrivant à expiration.',
                'how_to_do'           => [
                    'Vérifiez les conditions du contrat existant à renouveler.',
                    'Négociez et mettez à jour les nouvelles conditions si nécessaire.',
                    'Créez le nouveau contrat et archivez l\'ancien.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contrats à renouveler', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Anticipez le renouvellement au moins 30 jours avant l\'expiration.',
                ],
                'next_actions'        => [
                    ['label' => 'Activer le nouveau contrat', 'action' => 'activate_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Profitez du renouvellement pour renégocier les tarifs.',
                ],
            ],
            'Contracts.expiry_alert' => [
                'what_to_do'          => 'Des contrats arrivent bientôt à expiration — prenez action rapidement.',
                'how_to_do'           => [
                    'Consultez la liste des contrats expirant dans les 30 prochains jours.',
                    'Contactez les parties concernées pour initier le renouvellement.',
                    'Mettez à jour le statut de chaque contrat traité.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contrats expirant sous 30 j', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'Un contrat expiré non renouvelé peut entraîner des interruptions de service.',
                ],
                'next_actions'        => [
                    ['label' => 'Renouveler le contrat', 'action' => 'renew_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Configurez des alertes automatiques à J-60 et J-30 avant expiration.',
                ],
            ],

            // ------------------------------------------------------------------
            // Assets
            // ------------------------------------------------------------------
            'Assets.add_asset' => [
                'what_to_do'          => 'Enregistrez un nouvel actif immobilisé dans le registre des immobilisations.',
                'how_to_do'           => [
                    'Renseignez la désignation, la catégorie et la valeur d\'acquisition.',
                    'Configurez la durée de vie et la méthode d\'amortissement.',
                    'Associez l\'actif à un centre de coût ou un département.',
                ],
                'decision_indicators' => [
                    ['label' => 'Valeur nette comptable', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Actifs en service', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Calculer l\'amortissement', 'action' => 'post_depreciation', 'module' => 'Assets'],
                ],
                'tips'                => [
                    'Le plan SYSCOHADA impose des durées d\'amortissement spécifiques par catégorie.',
                ],
            ],
            'Assets.post_depreciation' => [
                'what_to_do'          => 'Calculez et comptabilisez les dotations aux amortissements de la période.',
                'how_to_do'           => [
                    'Sélectionnez la période d\'amortissement (mensuelle ou annuelle).',
                    'Vérifiez les actifs éligibles et les taux appliqués.',
                    'Validez pour générer les écritures comptables d\'amortissement.',
                ],
                'decision_indicators' => [
                    ['label' => 'Dotation de la période', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Actifs totalement amortis', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Vérifiez la conformité des taux d\'amortissement avec le référentiel OHADA.',
                ],
                'next_actions'        => [
                    ['label' => 'Planifier maintenance', 'action' => 'schedule_maintenance', 'module' => 'Assets'],
                ],
                'tips'                => [
                    'Archivez les dotations mensuelles pour la réconciliation de fin d\'exercice.',
                ],
            ],
            'Assets.schedule_maintenance' => [
                'what_to_do'          => 'Planifiez une intervention de maintenance préventive ou corrective.',
                'how_to_do'           => [
                    'Sélectionnez l\'actif et le type de maintenance (préventive/corrective).',
                    'Planifiez la date et affectez le technicien responsable.',
                    'Renseignez le coût estimé et le temps d\'immobilisation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Maintenances planifiées', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Actifs en panne', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'La maintenance préventive réduit les pannes de 40 % en moyenne.',
                ],
            ],
            'Assets.dispose_asset' => [
                'what_to_do'          => 'Enregistrez la sortie définitive d\'un actif (cession, mise au rebut).',
                'how_to_do'           => [
                    'Sélectionnez l\'actif à sortir et le motif de cession.',
                    'Indiquez la valeur de cession éventuelle et la date de sortie.',
                    'Validez pour générer les écritures de sortie d\'immobilisation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Plus ou moins-value', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'La cession d\'immobilisation a un impact fiscal — consultez votre comptable.',
                ],
                'next_actions'        => [
                    ['label' => 'Enregistrer la facture de cession', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Conservez les documents de cession (acte de vente) pour l\'audit.',
                ],
            ],

            // ------------------------------------------------------------------
            // BI
            // ------------------------------------------------------------------
            'BI.analyze_data' => [
                'what_to_do'          => 'Analysez vos données métier avec des tableaux de bord interactifs.',
                'how_to_do'           => [
                    'Sélectionnez la source de données.',
                    'Choisissez le type de visualisation.',
                    'Appliquez les filtres et périodes.',
                ],
                'decision_indicators' => [
                    ['label' => 'Données à jour', 'value' => 'Oui', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Exporter le rapport', 'action' => 'export', 'module' => 'BI'],
                ],
                'tips'                => [
                    'Utilisez les segments pour comparer différentes périodes.',
                ],
            ],

            // ------------------------------------------------------------------
            // Helpdesk
            // ------------------------------------------------------------------
            'Helpdesk.route_ticket' => [
                'what_to_do'          => 'Traitez et routez les tickets de support clients efficacement.',
                'how_to_do'           => [
                    'Évaluez la priorité du ticket.',
                    'Assignez à l\'agent compétent.',
                    'Définissez une date d\'échéance SLA.',
                ],
                'decision_indicators' => [
                    ['label' => 'Tickets ouverts', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Vérifiez les SLA avant d\'assigner'],
                'next_actions'        => [
                    ['label' => 'Voir tous les tickets', 'action' => 'index', 'module' => 'Helpdesk'],
                ],
                'tips'                => [
                    'Les tickets critiques doivent être traités en moins de 2h.',
                ],
            ],

            // ------------------------------------------------------------------
            // Documents
            // ------------------------------------------------------------------
            'Documents.ocr_classify' => [
                'what_to_do'          => 'Importez et classifiez des documents avec reconnaissance automatique.',
                'how_to_do'           => [
                    'Glissez le document dans la zone d\'import.',
                    'L\'IA extrait les métadonnées clés.',
                    'Vérifiez et validez la classification.',
                ],
                'decision_indicators' => [
                    ['label' => 'OCR disponible', 'value' => 'Oui', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Importer un document', 'action' => 'import', 'module' => 'Documents'],
                ],
                'tips'                => [
                    'Les PDFs nativement numériques ont une meilleure précision.',
                ],
            ],

            // ------------------------------------------------------------------
            // Projects (new action)
            // ------------------------------------------------------------------
            'Projects.estimate_task' => [
                'what_to_do'          => 'Estimez les tâches et suivez l\'avancement du projet.',
                'how_to_do'           => [
                    'Décomposez en sous-tâches.',
                    'Estimez la durée en heures.',
                    'Assignez aux membres de l\'équipe.',
                ],
                'decision_indicators' => [
                    ['label' => 'Avancement', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Prévoyez 20% de marge pour les imprévus'],
                'next_actions'        => [
                    ['label' => 'Voir le Gantt', 'action' => 'gantt', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Utilisez les templates de projet pour les projets récurrents.',
                ],
            ],

            // ------------------------------------------------------------------
            // Timesheets
            // ------------------------------------------------------------------
            'Timesheets.view_dashboard' => [
                'what_to_do'          => 'Saisissez et validez les heures travaillées par projet.',
                'how_to_do'           => [
                    'Sélectionnez le projet et la tâche.',
                    'Saisissez les heures par jour.',
                    'Soumettez pour validation manager.',
                ],
                'decision_indicators' => [
                    ['label' => 'Heures cette semaine', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Soumettre la semaine', 'action' => 'submit', 'module' => 'Timesheets'],
                ],
                'tips'                => [
                    'Remplissez les feuilles de temps chaque vendredi.',
                ],
            ],

            // ------------------------------------------------------------------
            // Achats (new action)
            // ------------------------------------------------------------------
            'Achats.view_dashboard' => [
                'what_to_do'          => 'Gérez vos achats et appels d\'offres fournisseurs.',
                'how_to_do'           => [
                    'Créez un appel d\'offres.',
                    'Comparez les offres reçues.',
                    'Émettez la commande d\'achat.',
                ],
                'decision_indicators' => [
                    ['label' => 'Commandes en attente', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Nouvelle commande', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Regroupez les commandes fournisseur pour négocier de meilleurs prix.',
                ],
            ],

            // ------------------------------------------------------------------
            // Planning
            // ------------------------------------------------------------------
            'Planning.view_dashboard' => [
                'what_to_do'          => 'Planifiez vos ressources et suivez les jalons de projet.',
                'how_to_do'           => [
                    'Créez un plan de production.',
                    'Allouez les ressources disponibles.',
                    'Identifiez les goulots d\'étranglement.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux d\'utilisation', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Nouveau plan', 'action' => 'create', 'module' => 'Planning'],
                ],
                'tips'                => [
                    'Vérifiez les conflits de ressources avant de finaliser le plan.',
                ],
            ],

            // ------------------------------------------------------------------
            // CustomerService
            // ------------------------------------------------------------------
            'CustomerService.view_dashboard' => [
                'what_to_do'          => 'Suivez les tickets clients et respectez les SLA.',
                'how_to_do'           => [
                    'Vérifiez les tickets critiques en premier.',
                    'Assignez aux bons agents.',
                    'Répondez avant la date limite SLA.',
                ],
                'decision_indicators' => [
                    ['label' => 'SLA respecté', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Vérifiez les tickets avec SLA en danger'],
                'next_actions'        => [
                    ['label' => 'Voir les tickets critiques', 'action' => 'critical_tickets', 'module' => 'CustomerService'],
                ],
                'tips'                => [
                    'Configurez des réponses automatiques pour les questions fréquentes.',
                ],
            ],

            // ------------------------------------------------------------------
            // Reporting (new action)
            // ------------------------------------------------------------------
            'Reporting.view_dashboard' => [
                'what_to_do'          => 'Créez et exécutez des rapports pour piloter votre activité.',
                'how_to_do'           => [
                    'Sélectionnez le module source.',
                    'Configurez les colonnes et filtres.',
                    'Exécutez et exportez.',
                ],
                'decision_indicators' => [
                    ['label' => 'Rapports disponibles', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un rapport', 'action' => 'create', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Planifiez des rapports hebdomadaires pour votre équipe de direction.',
                ],
            ],

            // ------------------------------------------------------------------
            // Workflow
            // ------------------------------------------------------------------
            'Workflow.view_dashboard' => [
                'what_to_do'          => 'Automatisez vos processus métier avec des workflows.',
                'how_to_do'           => [
                    'Définissez le déclencheur (événement, planification).',
                    'Configurez les conditions.',
                    'Ajoutez les actions à exécuter.',
                ],
                'decision_indicators' => [
                    ['label' => 'Workflows actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Nouveau workflow', 'action' => 'create', 'module' => 'Workflow'],
                ],
                'tips'                => [
                    'Testez les workflows en mode simulation avant activation.',
                ],
            ],

            // ------------------------------------------------------------------
            // MarketingAutomation
            // ------------------------------------------------------------------
            'MarketingAutomation.view_dashboard' => [
                'what_to_do'          => 'Lancez des campagnes marketing ciblées et automatisées.',
                'how_to_do'           => [
                    'Définissez votre segment cible.',
                    'Choisissez le canal (email, SMS, WhatsApp).',
                    'Planifiez l\'envoi.',
                ],
                'decision_indicators' => [
                    ['label' => 'Campagnes actives', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Nouvelle campagne', 'action' => 'create', 'module' => 'MarketingAutomation'],
                ],
                'tips'                => [
                    'Segmentez par score de lead pour cibler les prospects chauds en priorité.',
                ],
            ],

            // ------------------------------------------------------------------
            // Calendar
            // ------------------------------------------------------------------
            'Calendar.view_calendar' => [
                'what_to_do'          => 'Visualisez et gérez tous vos événements depuis un calendrier centralisé.',
                'how_to_do'           => [
                    'Sélectionnez la vue (Mois, Semaine, Jour ou Agenda) selon votre besoin.',
                    'Activez les sources de modules (RH, Projets, Helpdesk) pour voir tous vos événements.',
                    'Connectez Google Calendar, Outlook ou iCloud pour synchroniser vos agendas.',
                ],
                'decision_indicators' => [
                    ['label' => 'Événements à venir', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Sync active', 'value' => 'Vérifier', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un événement', 'action' => 'create_event', 'module' => 'Calendar'],
                    ['label' => 'Paramètres de sync', 'action' => 'calendar_settings', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'La vue Agenda est idéale pour avoir une vue d\'ensemble des 30 prochains jours.',
                    'Activez les rappels pour ne jamais manquer une échéance importante.',
                ],
            ],
            'Calendar.calendar_settings' => [
                'what_to_do'          => 'Connectez vos calendriers externes et configurez les sources de données.',
                'how_to_do'           => [
                    'Cliquez sur "Connecter Google Calendar" ou "Connecter Outlook" pour autoriser la sync.',
                    'Pour Apple Calendar, utilisez un mot de passe spécifique à l\'app (pas votre mot de passe Apple ID).',
                    'Activez les sources de modules souhaitées (Congés RH, SLA Helpdesk, etc.).',
                ],
                'decision_indicators' => [
                    ['label' => 'Providers connectés', 'value' => '0/3', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Pour Apple iCloud, créez un mot de passe spécifique à l\'app sur appleid.apple.com.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir le calendrier', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'La synchronisation se fait automatiquement toutes les 15 minutes.',
                    'Exportez en .ics pour partager votre calendrier avec des collègues.',
                ],
            ],
            'Calendar.create_event' => [
                'what_to_do'          => 'Créez un nouvel événement et invitez des participants.',
                'how_to_do'           => [
                    'Renseignez le titre, la date/heure et le calendrier cible.',
                    'Ajoutez des participants en saisissant leur email ou nom.',
                    'Configurez un rappel (popup, email ou push) selon vos préférences.',
                ],
                'decision_indicators' => [
                    ['label' => 'Calendriers disponibles', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir le calendrier', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'Liez un événement à une tâche Projet ou un jalon Stratégie pour un suivi centralisé.',
                ],
            ],

            // ------------------------------------------------------------------
            // Reporting
            // ------------------------------------------------------------------
            'Reporting.create_report' => [
                'what_to_do'          => 'Créez un rapport personnalisé pour analyser vos données métier.',
                'how_to_do'           => [
                    'Sélectionnez le module source et les indicateurs à inclure.',
                    'Configurez les filtres, regroupements et la période d\'analyse.',
                    'Prévisualisez et sauvegardez le modèle de rapport.',
                ],
                'decision_indicators' => [
                    ['label' => 'Rapports sauvegardés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Planifier le rapport', 'action' => 'schedule_report', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Décrivez votre besoin en langage naturel — l\'IA peut générer le rapport automatiquement.',
                ],
            ],
            'Reporting.schedule_report' => [
                'what_to_do'          => 'Planifiez l\'envoi automatique d\'un rapport à une fréquence définie.',
                'how_to_do'           => [
                    'Sélectionnez le rapport à planifier.',
                    'Choisissez la fréquence (quotidien, hebdomadaire, mensuel).',
                    'Renseignez les destinataires par email.',
                ],
                'decision_indicators' => [
                    ['label' => 'Rapports planifiés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Exporter un rapport', 'action' => 'export_report', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Les rapports hebdomadaires sont idéaux pour le pilotage opérationnel.',
                ],
            ],
            'Reporting.export_report' => [
                'what_to_do'          => 'Exportez un rapport dans le format souhaité (PDF, Excel, CSV).',
                'how_to_do'           => [
                    'Sélectionnez le rapport à exporter.',
                    'Choisissez le format d\'export et les options de mise en page.',
                    'Téléchargez ou envoyez par email.',
                ],
                'decision_indicators' => [
                    ['label' => 'Format disponible', 'value' => 'PDF/Excel/CSV', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Le format Excel est recommandé pour les analyses avancées.',
                ],
            ],
            'Reporting.interpret_results' => [
                'what_to_do'          => 'Utilisez l\'IA pour interpréter les résultats de votre rapport en langage naturel.',
                'how_to_do'           => [
                    'Ouvrez un rapport existant et cliquez sur "Interpréter avec l\'IA".',
                    'L\'IA génère un résumé narratif des tendances et anomalies détectées.',
                    'Partagez l\'interprétation avec votre équipe.',
                ],
                'decision_indicators' => [
                    ['label' => 'Tendances détectées', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'L\'interprétation IA est disponible en français, anglais et arabe.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — Normes ISO secteur Textile
            // ------------------------------------------------------------------
            'Quality.iso_textile' => [
                'what_to_do'          => 'Vérifiez les normes OEKO-TEX, REACH et ISO 105 pour les composants textiles.',
                'how_to_do'           => [
                    'Exigez le certificat OEKO-TEX 100 pour tout composant en contact avec la peau.',
                    'Vérifiez la conformité REACH CE 1907/2006 pour les colorants et traitements chimiques.',
                    'Testez la solidité des coloris selon ISO 105-C06 (lavage) et ISO 105-X12 (frottement).',
                ],
                'decision_indicators' => [
                    ['label' => 'Normes textile actives', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'OEKO-TEX certifiés', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Non-conformités REACH', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'REACH interdit 197 substances — vérifiez les fiches de données sécurité fournisseurs.',
                    'GOTS requiert une certification de toute la chaîne (du champ au produit fini).',
                ],
                'next_actions'        => [
                    ['label' => 'Vérifier certifications fournisseurs', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Lancer inspection composant', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'Pour les vêtements enfants, exigez la classe 1 OEKO-TEX (seuils plus stricts).',
                    'Les EPI textiles (vêtements de travail) nécessitent une certification CE obligatoire.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — Normes ISO secteur Construction / BTP
            // ------------------------------------------------------------------
            'Quality.iso_construction' => [
                'what_to_do'          => 'Vérifiez les normes EN/Eurocode pour les matériaux de construction et leur conformité CE.',
                'how_to_do'           => [
                    'Pour le béton : exigez EN 206 et les PV d\'essais EN 12390 (résistance à la compression).',
                    'Pour l\'acier : exigez EN 10025 + marquage CE + certificat EN 1090 (classe EXC 2 minimum).',
                    'Pour l\'isolation : vérifiez la classe de réaction au feu EN 13501 selon l\'usage.',
                ],
                'decision_indicators' => [
                    ['label' => 'Normes BTP actives', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Marquage CE vérifié', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Certificats EN 1090', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Le marquage CE est obligatoire pour tous les produits de construction en Europe.',
                    'Les Eurocodes sont la référence de calcul — vérifiez la classe d\'exposition béton.',
                ],
                'next_actions'        => [
                    ['label' => 'Vérifier certifications fournisseurs', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Inspecter composant reçu', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'Pour les câbles électriques en ERP, exiger la classe de réaction au feu Cca minimum.',
                    'BIM ISO 19650 : numérisez les données matériaux dès la commande pour le dossier d\'ouvrage exécuté.',
                ],
            ],

            // ------------------------------------------------------------------
            // HR — Onboarding Workflow
            // ------------------------------------------------------------------
            'HR.onboarding' => [
                'what_to_do'          => 'Suivez l\'intégration du nouvel employé étape par étape en coordinant tous les départements.',
                'how_to_do'           => [
                    'Collectez les documents administratifs dans les 48h suivant l\'arrivée.',
                    'Coordinatez avec IT pour la création du compte ERP et de l\'e-mail.',
                    'Planifiez les formations obligatoires dans le Calendrier avant la fin de la 1ère semaine.',
                ],
                'decision_indicators' => [
                    ['label' => 'Onboardings en cours',     'value' => '—', 'status' => 'ok'],
                    ['label' => 'Documents manquants',      'value' => '—', 'status' => 'warning'],
                    ['label' => 'Étapes en retard',         'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un onboarding raté coûte en moyenne 3× le salaire mensuel en perte de productivité.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir les onboardings',     'action' => 'onboarding',         'module' => 'HR'],
                    ['label' => 'Créer un employé',         'action' => 'create_employee',    'module' => 'HR'],
                ],
                'tips'                => [
                    'Assignez un mentor dès le 1er jour pour accélérer l\'intégration.',
                    'Le suivi cross-module garantit qu\'aucune étape ne soit oubliée.',
                ],
            ],

            // ------------------------------------------------------------------
            // Accounting — Invoice Approval Workflow
            // ------------------------------------------------------------------
            'Accounting.invoice_approval' => [
                'what_to_do'          => 'Validez les factures en attente selon la chaîne d\'approbation OHADA.',
                'how_to_do'           => [
                    'Vérifiez la conformité de la facture (mentions légales obligatoires OHADA).',
                    'Contrôlez la disponibilité budgétaire avant d\'approuver.',
                    'Planifiez le paiement dans les délais contractuels après approbation complète.',
                ],
                'decision_indicators' => [
                    ['label' => 'Factures en attente',      'value' => '—', 'status' => 'warning'],
                    ['label' => 'Montant total en attente', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'En retard (>3j)',           'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Délai légal OHADA : 30 jours pour les factures fournisseurs (art. 277 OHADA).',
                    'Les factures >500 000 XOF requièrent 3 niveaux d\'approbation.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir les approbations',    'action' => 'invoice_approval',         'module' => 'Accounting'],
                    ['label' => 'Calendrier paiements',     'action' => 'view_payment_schedule',    'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Configurez des seuils d\'approbation par délégation dans les paramètres Comptabilité.',
                    'Les factures fournisseurs africains doivent mentionner le NIF et le régime fiscal.',
                ],
            ],
            'Accounting.view_payment_schedule' => [
                'what_to_do'          => 'Planifiez et suivez toutes les échéances de paiement pour éviter les pénalités de retard.',
                'how_to_do'           => [
                    'Consultez la vue calendrier pour identifier les paiements dus cette semaine.',
                    'Déclenchez les virements ou Mobile Money depuis le bouton Payer.',
                    'Envoyez les rappels automatiques aux fournisseurs 3 jours avant l\'échéance.',
                ],
                'decision_indicators' => [
                    ['label' => 'Paiements en retard',      'value' => '—', 'status' => 'ok'],
                    ['label' => 'Échéances cette semaine',  'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Les paiements en retard aux fournisseurs OHADA peuvent entraîner des pénalités de 18%/an.',
                ],
                'next_actions'        => [
                    ['label' => 'Approuver des factures',   'action' => 'invoice_approval',   'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Activez les rappels par SMS/WhatsApp pour les paiements importants.',
                    'Privilégiez Mobile Money (Orange Money, Wave) pour les paiements rapides.',
                ],
            ],

            // ─── Cost Engine ─────────────────────────────────────────────────────
            'Accounting.cost_analysis' => [
                'what_to_do'          => 'Analysez la structure CAPEX/OPEX/FINEX/RISKEX pour identifier les leviers de réduction des coûts et améliorer la rentabilité.',
                'how_to_do'           => [
                    'Comparez votre structure de coûts au benchmark sectoriel (onglet Benchmark).',
                    'Identifiez les composants BOM avec les coûts FINEX les plus élevés — signe de sur-financement import.',
                    'Surveillez le RISKEX — un taux >8% signale des problèmes qualité systémiques.',
                ],
                'decision_indicators' => [
                    ['label' => 'FINEX acceptable',    'value' => '<8% coût total',                         'status' => 'ok'],
                    ['label' => 'RISKEX acceptable',   'value' => '<5% coût total',                         'status' => 'ok'],
                    ['label' => 'Marge nette cible',   'value' => '>15% (textile) / >12% (construction)',   'status' => 'ok'],
                ],
                'warnings'            => [
                    'FINEX >12% du coût total suggère un BFR (Besoin en Fonds de Roulement) mal maîtrisé.',
                    'RISKEX >8% signale des problèmes qualité récurrents — auditer les fournisseurs.',
                ],
                'next_actions'        => [
                    ['label' => 'Voir coûts BOM',       'action' => 'cost_analysis', 'module' => 'Accounting'],
                    ['label' => 'Analyse rentabilité',  'action' => 'cost_analysis', 'module' => 'Accounting'],
                    ['label' => 'Rapport OHADA',        'action' => 'ohada_report',  'module' => 'Accounting'],
                ],
                'tips'                => [
                    'OHADA: classez CAPEX en Classe 2, OPEX en Classe 6, FINEX en Classe 67, RISKEX en Classe 69.',
                    'Un FINEX élevé sur un produit importé peut être réduit en refinançant via Lettre de Crédit.',
                    'Activez le rollup automatique après chaque clôture mensuelle.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMap(): array
    {
        return array_merge($this->englishMapCore(), $this->englishMapExtended(), $this->englishMapPhase52(), $this->englishMapChantier30(), $this->englishMapChantier32(), $this->englishMapChantier327(), $this->englishMapChantier3215(), $this->englishMapChantier3216());
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapCore(): array
    {
        return [
            // ------------------------------------------------------------------
            // CRM
            // ------------------------------------------------------------------
            'CRM.create_contact' => [
                'what_to_do'          => 'Create a new contact by filling in the key details.',
                'how_to_do'           => [
                    'Enter the full name and email address.',
                    'Add a phone number and country to enable Mobile Money.',
                    'Link the contact to an existing company or opportunity.',
                ],
                'decision_indicators' => [
                    ['label' => 'Duplicates detected', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create opportunity', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'A valid phone number is required for Orange Money / Wave payments.',
                    'Check for duplicates before saving.',
                ],
            ],
            'CRM.view_dashboard' => [
                'what_to_do'          => 'Monitor your real-time CRM metrics to drive commercial activity.',
                'how_to_do'           => [
                    'Filter by period (day, week, month).',
                    'Identify opportunities flagged in red for follow-up.',
                    'Export the report if needed.',
                ],
                'decision_indicators' => [
                    ['label' => 'Open opportunities', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Conversion rate', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create opportunity', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Follow up on prospects not contacted in the last 7 days.',
                ],
            ],
            'CRM.create_opportunity' => [
                'what_to_do'          => 'Create a sales opportunity to track a prospect through to closing.',
                'how_to_do'           => [
                    'Select or create the associated contact.',
                    'Enter the estimated amount and expected close date.',
                    'Set the pipeline stage (prospect, negotiation, won…).',
                ],
                'decision_indicators' => [
                    ['label' => 'Pipeline value', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create quotation', 'action' => 'create_quotation', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Set a follow-up reminder to avoid missing the next step.',
                ],
            ],

            // ------------------------------------------------------------------
            // Accounting
            // ------------------------------------------------------------------
            'Accounting.post_invoice' => [
                'what_to_do'          => 'Record a customer or supplier invoice in the accounting journal.',
                'how_to_do'           => [
                    'Verify the invoice number and the counterparty.',
                    'Check net, VAT and gross amounts before posting.',
                    'Select the appropriate OHADA account (class 4 for third parties).',
                ],
                'decision_indicators' => [
                    ['label' => 'Applicable VAT', 'value' => '18 %', 'status' => 'ok'],
                    ['label' => 'Third-party balance', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Check OHADA compliance before validating the accounting entry.',
                ],
                'next_actions'        => [
                    ['label' => 'Reconcile payments', 'action' => 'reconcile', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'SYSCOHADA requires posting to class 6/7 accounts.',
                    'Archive the supporting document before posting.',
                ],
            ],
            'Accounting.reconcile' => [
                'what_to_do'          => 'Match bank transactions with recorded payments.',
                'how_to_do'           => [
                    'Import the bank statement (CSV or OFX).',
                    'Match each bank line to an accounting entry.',
                    'Confirm the reconciliation and close the period.',
                ],
                'decision_indicators' => [
                    ['label' => 'Unreconciled entries', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Any unexplained difference must be investigated before monthly close.',
                ],
                'next_actions'        => [
                    ['label' => 'View balance sheet', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Reconcile weekly to catch anomalies early.',
                ],
            ],
            'Accounting.view_balance_sheet' => [
                'what_to_do'          => 'Review the balance sheet to assess the financial health of the company.',
                'how_to_do'           => [
                    'Select the reference period.',
                    'Compare with the previous period.',
                    'Export as PDF or Excel for the auditor.',
                ],
                'decision_indicators' => [
                    ['label' => 'Net profit', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Equity', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Generate OHADA report', 'action' => 'ohada_report', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'SYSCOHADA financial statements must be filed by 30 April of the following year.',
                ],
            ],
            'Accounting.ohada_report' => [
                'what_to_do'          => 'Generate financial statements compliant with OHADA/SYSCOHADA standards.',
                'how_to_do'           => [
                    'Ensure all entries for the fiscal year are posted.',
                    'Run the report generator (balance sheet, income statement, TAFIRE).',
                    'Download the PDF or XML file for submission to authorities.',
                ],
                'decision_indicators' => [
                    ['label' => 'SYSCOHADA compliance', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Use the revised SYSCOHADA 2017 chart of accounts.',
                    'Legal filing is mandatory in all 17 OHADA member states.',
                ],
                'next_actions'        => [
                    ['label' => 'Export balance sheet', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Check accrual accounts (class 4) before generating the report.',
                ],
            ],

            // ------------------------------------------------------------------
            // HR
            // ------------------------------------------------------------------
            'HR.create_employee' => [
                'what_to_do'          => 'Add a new employee to the HR system.',
                'how_to_do'           => [
                    'Fill in identity, contract type and job position.',
                    'Set payroll parameters (gross salary, social security, income tax).',
                    'Enter bank details or Mobile Money number for salary payment.',
                ],
                'decision_indicators' => [
                    ['label' => 'Total headcount', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Run payroll', 'action' => 'run_payroll', 'module' => 'HR'],
                ],
                'tips'                => [
                    'Check social security contribution ceilings for the employee\'s country.',
                    'A bank account or Mobile Money number is required for payroll.',
                ],
            ],
            'HR.approve_leave' => [
                'what_to_do'          => 'Approve or reject a pending leave request.',
                'how_to_do'           => [
                    'Check the employee\'s available leave balance.',
                    'Review the team schedule to avoid conflicts.',
                    'Approve or reject with a comment if necessary.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending leave requests', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Process requests within 48 hours to meet legal deadlines.',
                ],
            ],
            'HR.run_payroll' => [
                'what_to_do'          => 'Run payroll processing for the current month.',
                'how_to_do'           => [
                    'Verify attendance records and overtime hours.',
                    'Check variable pay elements (bonuses, absences).',
                    'Validate payroll and generate payslips.',
                ],
                'decision_indicators' => [
                    ['label' => 'Payslips to generate', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Total payroll cost', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Verify social security and income tax filings before submission.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Archive signed payslips for the minimum legal retention period (5 years).',
                ],
            ],

            // ------------------------------------------------------------------
            // Inventory
            // ------------------------------------------------------------------
            'Inventory.receive_stock' => [
                'what_to_do'          => 'Record a stock receipt and update inventory levels.',
                'how_to_do'           => [
                    'Scan or select the products being received.',
                    'Compare quantities against the purchase order.',
                    'Validate the receipt to update stock.',
                ],
                'decision_indicators' => [
                    ['label' => 'Receipt discrepancies', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create missing product', 'action' => 'create_product', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Report any damaged goods to the supplier immediately.',
                ],
            ],
            'Inventory.create_product' => [
                'what_to_do'          => 'Create a new product record in the catalogue.',
                'how_to_do'           => [
                    'Enter the name, reference code and category.',
                    'Set the selling price and purchase cost.',
                    'Configure the reorder threshold.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active products', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Receive stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'A unique barcode or SKU simplifies stock counts.',
                ],
            ],
            'Inventory.low_stock_alert' => [
                'what_to_do'          => 'Products are below the minimum threshold — take action immediately.',
                'how_to_do'           => [
                    'Identify products at critical stock levels.',
                    'Create a purchase order for the critical items.',
                    'Adjust reorder thresholds if needed.',
                ],
                'decision_indicators' => [
                    ['label' => 'Products on alert', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'Stock-outs can block sales and production.',
                ],
                'next_actions'        => [
                    ['label' => 'Create purchase order', 'action' => 'create_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Enable automatic email alerts for critical products.',
                ],
            ],

            // ------------------------------------------------------------------
            // Sales
            // ------------------------------------------------------------------
            'Sales.create_order' => [
                'what_to_do'          => 'Create a customer or supplier order.',
                'how_to_do'           => [
                    'Select the customer or supplier.',
                    'Add product lines with quantities and prices.',
                    'Check stock availability before confirming.',
                ],
                'decision_indicators' => [
                    ['label' => 'Available stock', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Confirm order', 'action' => 'confirm_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Verify the payment terms agreed with the customer.',
                ],
            ],
            'Sales.confirm_order' => [
                // Chantier 32.16 (Sales deep 14-layer audit): the previous
                // text claimed an invoice was generated automatically on
                // confirmation — false (SalesService::confirmOrder() only
                // transitions the status); real invoicing goes through the
                // explicit deposit/balance cycle (see
                // manage_deposit_balance), never automatic.
                'what_to_do'          => 'Confirm the order (draft → confirmed) to start its preparation.',
                'how_to_do'           => [
                    'Check the lines and total before confirming — a confirmed order can no longer be edited.',
                    'Confirm delivery conditions and lead time.',
                    'Once confirmed, request the deposit from the order\'s detail page.',
                ],
                'decision_indicators' => [
                    ['label' => 'Orders pending', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Confirming never generates an invoice automatically — use the deposit/balance cycle on the order\'s detail page.',
                ],
                'next_actions'        => [
                    ['label' => 'Manage deposit/balance', 'action' => 'manage_deposit_balance', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Send an order confirmation by email or WhatsApp to the customer.',
                ],
            ],
            'Sales.manage_deposit_balance' => [
                'what_to_do'          => 'Track and collect the deposit, then the balance, of a confirmed order from its detail page.',
                'how_to_do'           => [
                    'Request a deposit (percentage of the total) — a real linked invoice is created automatically.',
                    'Record the deposit payment once received (mobile money, bank transfer, cash…).',
                    'Once the deposit is paid, request the remaining balance, then record its payment on delivery.',
                ],
                'decision_indicators' => [
                    ['label' => 'Cycle stage', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A deposit or balance can only be requested once per order — the amount is fixed at request time, not recalculated if the order changes afterward.',
                    'A payment that would exceed the linked invoice\'s total is rejected by the server.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Every collected payment posts a real balanced accounting entry (OHADA account 419 for the deposit, 411 for the balance).',
                ],
            ],
            'Sales.create_quotation' => [
                'what_to_do'          => 'Create a commercial quotation to send to a prospect or customer.',
                'how_to_do'           => [
                    'Select the related contact or opportunity.',
                    'Add products/services with negotiated prices.',
                    'Set the validity date and payment terms.',
                ],
                'decision_indicators' => [
                    ['label' => 'Quotation conversion rate', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Convert to order', 'action' => 'create_order', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Include Mobile Money payment options for African customers.',
                ],
            ],

            // ------------------------------------------------------------------
            // POS
            // ------------------------------------------------------------------
            'POS.open_session' => [
                'what_to_do'          => 'Open a cash register session to start the day\'s sales.',
                'how_to_do'           => [
                    'Verify the opening float amount.',
                    'Select active payment methods (cash, Mobile Money, card).',
                    'Confirm session opening.',
                ],
                'decision_indicators' => [
                    ['label' => 'Opening float', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Process payment', 'action' => 'process_payment', 'module' => 'POS'],
                ],
                'tips'                => [
                    'Physically count the float before every opening.',
                ],
            ],
            'POS.process_payment' => [
                'what_to_do'          => 'Process a customer payment to finalise the sale.',
                'how_to_do'           => [
                    'Scan products or select them manually.',
                    'Choose the payment method (cash, Wave, Orange Money, card).',
                    'Print or send a receipt to the customer.',
                ],
                'decision_indicators' => [
                    ['label' => 'Cart total', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Wave and Orange Money are accepted with no additional fees.',
                ],
            ],
            'POS.close_session' => [
                'what_to_do'          => 'Close the cash register session at the end of the day.',
                'how_to_do'           => [
                    'Physically count the cash.',
                    'Compare with the system-calculated total.',
                    'Validate and archive the cash report.',
                ],
                'decision_indicators' => [
                    ['label' => 'Cash discrepancy', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Any discrepancy must be explained before closing.',
                ],
                'next_actions'        => [
                    ['label' => 'Post daily revenue', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Close the register every evening to avoid inventory errors.',
                ],
            ],

            // ------------------------------------------------------------------
            // Setup
            // ------------------------------------------------------------------
            'Setup.import_file' => [
                'what_to_do'          => 'Upload your data file (Excel, CSV or PDF) into WideHalo.',
                'how_to_do'           => [
                    'Drag and drop or select your file (max 50 MB).',
                    'Verify the auto-detected format.',
                    'Proceed to the column mapping step.',
                ],
                'decision_indicators' => [
                    ['label' => 'Detected format', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Detected rows', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Map columns', 'action' => 'map_columns', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'Excel (.xlsx) and UTF-8 CSV files are recommended.',
                    'Remove extra header rows before importing.',
                ],
            ],
            'Setup.map_columns' => [
                'what_to_do'          => 'Map your file columns to WideHalo fields.',
                'how_to_do'           => [
                    'AI automatically suggests the most likely column matches.',
                    'Review and correct the proposed mappings.',
                    'Ignore columns that are not relevant.',
                ],
                'decision_indicators' => [
                    ['label' => 'Columns mapped', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Unmapped columns', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Required fields (name, email) must be mapped before continuing.',
                ],
                'next_actions'        => [
                    ['label' => 'Execute import', 'action' => 'execute_import', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'Save your mapping configuration for future imports.',
                ],
            ],
            'Setup.execute_import' => [
                'what_to_do'          => 'Run the data import to load your records into WideHalo.',
                'how_to_do'           => [
                    'Review the import summary (valid rows vs errors).',
                    'Choose the import mode: add only, update only, or both.',
                    'Confirm — data will be loaded in the background.',
                ],
                'decision_indicators' => [
                    ['label' => 'Valid rows', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Rows with errors', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Download the error report to fix rejected rows.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'A test import of 10 rows is recommended before a full load.',
                ],
            ],

            // Chantier 32.10: the 6-step onboarding wizard itself (distinct
            // from the import sub-flow above).
            'Setup.wizard_company' => [
                'what_to_do'          => 'Enter your company\'s legal information.',
                'how_to_do'           => [
                    'Provide the trading name and, if different, the registered legal name.',
                    'Pick the country — currency and timezone will be suggested automatically.',
                    'Continue to create your administrator profile.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'The country you select determines the default tax (VAT) and accounting (OHADA) rules applied.',
                ],
                'next_actions'        => [
                    ['label' => 'Administrator profile', 'action' => 'wizard_admin', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_admin' => [
                'what_to_do'          => 'Confirm your administrator profile (name, language, timezone).',
                'how_to_do'           => [
                    'Check the display name for your account.',
                    'Choose the interface language.',
                    'Confirm to move on to module selection.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Module selection', 'action' => 'wizard_modules', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_modules' => [
                'what_to_do'          => 'Enable the modules your company needs.',
                'how_to_do'           => [
                    'Tick the modules to enable right away — others can be enabled later.',
                    'Every enabled module becomes visible in the main navigation.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Disabling a module already in use can hide already-entered data — it is never deleted.',
                ],
                'next_actions'        => [
                    ['label' => 'Workflow configuration', 'action' => 'wizard_workflows', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_workflows' => [
                'what_to_do'          => 'Configure approval rules and notification channels.',
                'how_to_do'           => [
                    'Enable mandatory approval if decisions must be validated by a manager.',
                    'Choose notification channels (email, SMS, WhatsApp, push).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Apps to enable', 'action' => 'wizard_apps', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_apps' => [
                'what_to_do'          => 'Choose the apps (web, mobile, API) your team will use.',
                'how_to_do'           => [
                    'Enable the web app for browser access.',
                    'Enable the API if you plan to integrate WideHalo with other systems.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Finish setup', 'action' => 'wizard_complete', 'module' => 'Setup'],
                ],
                'tips'                => [],
            ],
            'Setup.wizard_complete' => [
                'what_to_do'          => 'Finish setup to start using WideHalo.',
                'how_to_do'           => [
                    'Review your configuration summary.',
                    'Click Finish to activate your workspace.',
                    'You can then import your existing data (customers, products, invoices).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Import data', 'action' => 'import_file', 'module' => 'Setup'],
                ],
                'tips'                => [
                    'All settings remain editable afterwards from the admin settings.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapExtended(): array
    {
        return [
            // ------------------------------------------------------------------
            // Achats
            // ------------------------------------------------------------------
            'Achats.create_order' => [
                'what_to_do'          => 'Create a purchase order to initiate the procurement process.',
                'how_to_do'           => [
                    'Select the supplier and the products to order.',
                    'Enter quantities and negotiated prices.',
                    'Submit for approval according to the approval workflow.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending orders', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Available budget', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Approve order', 'action' => 'approve_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Compare quotes from multiple suppliers before committing.',
                    'Check delivery lead times to anticipate needs.',
                ],
            ],
            'Achats.approve_order' => [
                'what_to_do'          => 'Approve the purchase order to authorise procurement from the supplier.',
                'how_to_do'           => [
                    'Review order details (products, quantities, prices).',
                    'Check the available budget on the relevant budget line.',
                    'Approve or reject with a justification comment.',
                ],
                'decision_indicators' => [
                    ['label' => 'Total amount', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Approval threshold', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Orders exceeding the authorised threshold require dual approval.',
                ],
                'next_actions'        => [
                    ['label' => 'Receive goods', 'action' => 'receive_goods', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Keep a record of approvals for OHADA audit compliance.',
                ],
            ],
            'Achats.receive_goods' => [
                'what_to_do'          => 'Record the receipt of goods ordered from the supplier.',
                'how_to_do'           => [
                    'Link the delivery to the original purchase order.',
                    'Verify quantities and condition of received goods.',
                    'Validate the receipt to update stock levels.',
                ],
                'decision_indicators' => [
                    ['label' => 'Quantity discrepancies', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Three-way match', 'action' => 'three_way_match', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Photograph damaged goods immediately for supplier claims.',
                ],
            ],
            'Achats.three_way_match' => [
                'what_to_do'          => 'Perform a three-way match between purchase order, receipt and supplier invoice.',
                'how_to_do'           => [
                    'Compare the purchase order, goods receipt and supplier invoice.',
                    'Identify and justify any price or quantity discrepancy.',
                    'Validate to authorise supplier payment.',
                ],
                'decision_indicators' => [
                    ['label' => 'Matching documents', 'value' => '3/3', 'status' => 'ok'],
                    ['label' => 'Discrepancies', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Never pay a supplier invoice without a validated three-way match.',
                ],
                'next_actions'        => [
                    ['label' => 'Post supplier invoice', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Three-way matching is a best practice required under OHADA standards.',
                ],
            ],

            // ------------------------------------------------------------------
            // Projects
            // ------------------------------------------------------------------
            'Projects.create_project' => [
                'what_to_do'          => 'Create a new project and define its core parameters.',
                'how_to_do'           => [
                    'Enter the name, start/end dates and project manager.',
                    'Define the budget and key milestones.',
                    'Invite team members to the project.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active projects', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Allocated budget', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Assign tasks', 'action' => 'assign_task', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Define clear milestones to track progress effectively.',
                ],
            ],
            'Projects.assign_task' => [
                'what_to_do'          => 'Assign a task to a team member with a due date.',
                'how_to_do'           => [
                    'Select the project and create or choose an existing task.',
                    'Assign the task to an available team member.',
                    'Set the priority and deadline.',
                ],
                'decision_indicators' => [
                    ['label' => 'Unassigned tasks', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Update progress', 'action' => 'update_progress', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Avoid overloading a single team member with too many parallel tasks.',
                ],
            ],
            'Projects.update_progress' => [
                'what_to_do'          => 'Update project progress to reflect the current state.',
                'how_to_do'           => [
                    'Mark completed tasks as "Done".',
                    'Adjust the project completion percentage.',
                    'Add a progress note if needed.',
                ],
                'decision_indicators' => [
                    ['label' => 'Overall progress', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Overdue tasks', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Update progress weekly to detect blockers early.',
                ],
            ],
            'Projects.close_project' => [
                'what_to_do'          => 'Close the project after all deliverables have been validated.',
                'how_to_do'           => [
                    'Verify all tasks are marked as completed.',
                    'Close budgets and archive project documents.',
                    'Write a project retrospective and share with the team.',
                ],
                'decision_indicators' => [
                    ['label' => 'Remaining tasks', 'value' => '0', 'status' => 'ok'],
                    ['label' => 'Budget consumed', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Ensure all deliverables have been accepted by the client before closing.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Document lessons learned to improve future projects.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing
            // ------------------------------------------------------------------
            'Manufacturing.production_dashboard' => [
                'what_to_do'          => 'Monitor your production dashboard to track manufacturing orders in real time.',
                'how_to_do'           => [
                    'Review the work-centre schedule for the current week.',
                    'Identify overdue or at-risk orders and reschedule as needed.',
                    'Drag and drop orders to optimise resource assignment.',
                ],
                'decision_indicators' => [
                    ['label' => 'Orders in progress', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Overdue orders', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Capacity load', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create production order', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                    ['label' => 'Quality check', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'A red order card indicates a deadline breach — act immediately.',
                    'Prioritise rescheduling orders linked to urgent customer sales orders.',
                ],
            ],
            'Manufacturing.create_production_order' => [
                'what_to_do'          => 'Create a production order to manufacture an item.',
                'how_to_do'           => [
                    'Select the item to produce and the required quantity.',
                    'Verify raw material availability (bill of materials).',
                    'Schedule the start date and check available machine capacity.',
                ],
                'decision_indicators' => [
                    ['label' => 'Materials available', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Capacity available', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Start production', 'action' => 'start_production', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Verify the bill of materials (BOM) before creating the production order.',
                ],
            ],
            'Manufacturing.start_production' => [
                'what_to_do'          => 'Start the production order and begin real-time tracking.',
                'how_to_do'           => [
                    'Confirm operator and machine availability.',
                    'Trigger the order and issue raw materials from stock.',
                    'Record the production start time.',
                ],
                'decision_indicators' => [
                    ['label' => 'Operators available', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Machines operational', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Record output', 'action' => 'record_output', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Ensure all safety equipment is in place before starting.',
                ],
            ],
            'Manufacturing.record_output' => [
                'what_to_do'          => 'Record quantities produced and any scrap or defects.',
                'how_to_do'           => [
                    'Enter the quantity of conforming output and scrap.',
                    'Note the causes of scrap for quality analysis.',
                    'Validate to update the finished goods inventory.',
                ],
                'decision_indicators' => [
                    ['label' => 'Scrap rate', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Quantity produced', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Quality check', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'A scrap rate above 5% requires root cause analysis.',
                ],
            ],
            'Manufacturing.quality_check' => [
                'what_to_do'          => 'Perform quality inspection on produced items before shipment.',
                'how_to_do'           => [
                    'Take a sample according to the defined inspection plan.',
                    'Record the quality test results.',
                    'Approve or block the batch based on conformance criteria.',
                ],
                'decision_indicators' => [
                    ['label' => 'Conformant batches', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Blocked batches', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A non-conformant batch must not be shipped without an approved waiver.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Document all inspections for ISO traceability requirements.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality
            // ------------------------------------------------------------------
            'Quality.quality_control' => [
                'what_to_do'          => 'Manage inspections and non-conformances to maintain a high conformance rate.',
                'how_to_do'           => [
                    'Start a new inspection by selecting the relevant product and production order.',
                    'Record the result (conformant / non-conformant) and any observations.',
                    'Process open non-conformances before their due dates.',
                ],
                'decision_indicators' => [
                    ['label' => 'Conformance rate', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Open NCs', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Inspections this month', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Critical non-conformances must be addressed within 24 hours.',
                ],
                'next_actions'        => [
                    ['label' => 'Production quality check', 'action' => 'quality_check', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Analyse root causes of recurring NCs to prevent recurrence.',
                    'A conformance rate below 95% requires a quality process review.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — BOM Cross-Module Tracking (English)
            // ------------------------------------------------------------------
            'Manufacturing.track_bom_items' => [
                'what_to_do'          => 'Track each BOM component from ordering to consumption across all departments.',
                'how_to_do'           => [
                    'Check components in shortage or needing ordering (red status).',
                    'Trigger missing purchase orders via the Achats module.',
                    'Validate receipts with quality control before reserving for production.',
                ],
                'decision_indicators' => [
                    ['label' => 'Available components', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Pending order', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Quality check', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A component in shortage will block production launch.',
                ],
                'next_actions'        => [
                    ['label' => 'Create purchase order', 'action' => 'create_order', 'module' => 'Achats'],
                    ['label' => 'Start quality check', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'Initialize tracking as soon as the production order is confirmed.',
                    'Schedule delivery dates to anticipate shortages.',
                ],
            ],
            'Manufacturing.manage_bom' => [
                'what_to_do'          => 'Manage your Bills of Materials and the components required for production.',
                'how_to_do'           => [
                    'Create or update BOMs with the required components and quantities.',
                    'Link each component to a SKU in the product catalogue.',
                    'Approve the BOM before using it in a production order.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active BOMs', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Pending approval', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create production order', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Explode the BOM to check component availability before confirming the order.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — QQCD Sample Requests (English)
            // ------------------------------------------------------------------
            'Manufacturing.sample_request' => [
                'what_to_do'          => 'Request samples from multiple suppliers before placing an order.',
                'how_to_do'           => [
                    'Define your QQCD target criteria (quantity, quality, cost, lead time).',
                    'Send the request to 3-5 suppliers simultaneously.',
                    'Evaluate responses with the QQCD matrix and validate the best supplier.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending requests', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Validated suppliers', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'An unevaluated supplier can block production in case of non-conformance.',
                ],
                'next_actions'        => [
                    ['label' => 'View QQCD calendar', 'action' => 'qqcd_calendar', 'module' => 'Manufacturing'],
                    ['label' => 'Create purchase order', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Weight quality at 35% for critical components.',
                    'Add a 20% buffer on lead time for international imports.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — QQCD Calendar (English)
            // ------------------------------------------------------------------
            'Manufacturing.qqcd_calendar' => [
                'what_to_do'          => 'Schedule sample requests in advance to avoid blocking production.',
                'how_to_do'           => [
                    'Identify BOMs for the next 3 months requiring orders.',
                    'Calculate lead times backward from the required production date.',
                    'Trigger sample requests with the necessary buffer (min. 14 days before ordering).',
                ],
                'decision_indicators' => [
                    ['label' => 'Components to order', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Behind schedule', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'A delay in sample requests shifts the entire supply chain.',
                ],
                'next_actions'        => [
                    ['label' => 'Request samples', 'action' => 'sample_request', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Schedule requests at least 3 weeks before the desired order date.',
                    'Group requests by supplier to speed up evaluation.',
                ],
            ],

            // ------------------------------------------------------------------
            // Manufacturing — International Import (English)
            // ------------------------------------------------------------------
            'Manufacturing.import_component' => [
                'what_to_do'          => 'Manage the import process for the component, anticipating customs and transit delays.',
                'how_to_do'           => [
                    'Check the HS code and applicable customs duties for the destination country.',
                    'Generate the import timeline by backward planning from the required date.',
                    'Prepare required documents in advance (certificates, licenses, declarations).',
                ],
                'decision_indicators' => [
                    ['label' => 'Imported components', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Missing documents', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'A missing document at customs can hold goods for several days.',
                ],
                'next_actions'        => [
                    ['label' => 'QQCD calendar', 'action' => 'qqcd_calendar', 'module' => 'Manufacturing'],
                    ['label' => 'Calculate landed cost', 'action' => 'import_component', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Add 5 extra buffer days for customs clearance during peak season.',
                    'Verify certificate validity (origin, quality) before each shipment.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — BOM component inspection (English)
            // ------------------------------------------------------------------
            'Quality.inspect_component' => [
                'what_to_do'          => 'Perform a quality check on a received component for a production order.',
                'how_to_do'           => [
                    'Check the ISO-generated checklist for this component.',
                    'Verify the received component against the purchase order (qty, reference, condition).',
                    'Record the inspection result (pass / fail): if passed, mark the component as Available; if failed, trigger supplier return.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending checks', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Conformance rate', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'ISO certs verified', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A failed component must not be used in production.',
                    'Verify supplier certificates before starting the inspection.',
                ],
                'next_actions'        => [
                    ['label' => 'Mark as Available', 'action' => 'track_bom_items', 'module' => 'Manufacturing'],
                    ['label' => 'View ISO compliance', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Reorder from supplier', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Document receipt photos for potential supplier disputes.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — ISO Compliance (English)
            // ------------------------------------------------------------------
            'Quality.iso_compliance' => [
                'what_to_do'          => 'Verify ISO compliance for each supplier before approving a sample.',
                'how_to_do'           => [
                    'Review the supplier certifications matrix.',
                    'Identify missing or expired certifications.',
                    'Block QQCD validation if a mandatory standard is missing.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active standards', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Expired certs', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Mandatory gaps', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'An expired certification is equivalent to a missing one.',
                    'RoHS/REACH components require testing by an accredited laboratory.',
                ],
                'next_actions'        => [
                    ['label' => 'Supplier cert matrix', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Supplier profile', 'action' => 'receive_goods', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Include auto-renewal clauses in supplier contracts.',
                    'Schedule ISO audits 6 months before expiry.',
                ],
            ],

            // ------------------------------------------------------------------
            // PLM
            // ------------------------------------------------------------------
            'PLM.view_dashboard' => [
                'what_to_do'          => 'Browse and manage item templates and their product lifecycle states.',
                'how_to_do'           => [
                    'Search for an existing template or create a new one.',
                    'Check the lifecycle state (Draft, In Review, Released, Obsolete).',
                    'Manage variants and bills of materials linked to each template.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active templates', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'In review', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Total variants', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Validate templates under review before using them in production.',
                ],
                'next_actions'        => [
                    ['label' => 'Create production order', 'action' => 'create_production_order', 'module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'Archive obsolete templates to keep the catalogue clean.',
                    'Link each template to a BOM to facilitate production planning.',
                ],
            ],

            // ------------------------------------------------------------------
            // PLM — CPQ (English)
            // ------------------------------------------------------------------
            'PLM.configure_product' => [
                'what_to_do'          => 'Configure the product step by step to automatically generate the BOM, costs, and customer quote.',
                'how_to_do'           => [
                    'Select the domain (Textile / Construction) and the appropriate template.',
                    'Complete attributes — the system calculates price and BOM in real time.',
                    'Check triggered ISO standards before generating the quote.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active sessions',    'value' => '—', 'status' => 'ok'],
                    ['label' => 'Quotes this month',  'value' => '—', 'status' => 'ok'],
                    ['label' => 'ISO gaps',           'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'An incomplete BOM blocks production order creation.',
                    'Missing ISO certifications block QQCD supplier validation.',
                ],
                'next_actions'        => [
                    ['label' => 'Start configuration', 'action' => 'cpq_configure', 'module' => 'PLM'],
                    ['label' => 'Create quotation',    'action' => 'create_quotation', 'module' => 'Sales'],
                ],
                'tips'                => [
                    'Textile: FINEX increases significantly for imports > 8 weeks.',
                    'Construction: fire class REI 120 (EN 13501) adds up to 25% to structure cost.',
                ],
            ],
            'PLM.cpq_index' => [
                'what_to_do'          => 'Choose a CPQ template to start a new product configuration.',
                'how_to_do'           => [
                    'Filter by domain: Textile, Construction, or Generic.',
                    'Click "Configure" to launch the step-by-step wizard.',
                    'Resume an existing configuration from the "Recent" table.',
                ],
                'decision_indicators' => [
                    ['label' => 'Available templates', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Pending drafts',      'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Start Textile',      'action' => 'cpq_configure', 'module' => 'PLM'],
                    ['label' => 'Start Construction', 'action' => 'cpq_configure', 'module' => 'PLM'],
                ],
                'tips'                => [
                    'Each configuration automatically generates a BOM and a CAPEX/OPEX/FINEX/RISKEX breakdown.',
                ],
            ],
            'PLM.cpq_configure' => [
                'what_to_do'          => 'Fill each wizard step — price and BOM update in real time.',
                'how_to_do'           => [
                    'Navigate step by step using the left panel.',
                    'Red fields are required — yellow warnings signal triggered ISO standards.',
                    'The right panel shows the estimated price and preliminary BOM as you fill in the form.',
                ],
                'decision_indicators' => [
                    ['label' => 'Steps complete',   'value' => '—', 'status' => 'ok'],
                    ['label' => 'Active ISO norms', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'BOM generated',    'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Check that supplier certifications cover all triggered ISO standards.',
                ],
                'next_actions'        => [
                    ['label' => 'View summary', 'action' => 'cpq_summary', 'module' => 'PLM'],
                ],
                'tips'                => [
                    'Use "AI Suggestions" to get optimal values for each step.',
                ],
            ],
            'PLM.cpq_summary' => [
                'what_to_do'          => 'Review the final configuration, adjust the margin, and generate the customer quote.',
                'how_to_do'           => [
                    'Review the BOM and CAPEX/OPEX/FINEX/RISKEX cost breakdown.',
                    'Adjust the margin using the slider (default 30%).',
                    'Click "Generate Sales Quote" to create the quote and update the CRM opportunity.',
                ],
                'decision_indicators' => [
                    ['label' => 'Suggested margin', 'value' => '30%', 'status' => 'ok'],
                    ['label' => 'ISO compliant',    'value' => '—',   'status' => 'ok'],
                    ['label' => 'BOM complete',     'value' => '—',   'status' => 'ok'],
                ],
                'warnings'            => [
                    'A missing ISO standard blocks production order creation.',
                ],
                'next_actions'        => [
                    ['label' => 'Create quotation',      'action' => 'create_quotation',       'module' => 'Sales'],
                    ['label' => 'Create production order','action' => 'create_production_order','module' => 'Manufacturing'],
                ],
                'tips'                => [
                    'The generated quote is automatically linked to the selected CRM opportunity.',
                ],
            ],

            // ------------------------------------------------------------------
            // Ecommerce
            // ------------------------------------------------------------------
            'Ecommerce.add_product' => [
                'what_to_do'          => 'Add a product to your online store with full details.',
                'how_to_do'           => [
                    'Fill in the title, description and product images.',
                    'Set the selling price and stock management options.',
                    'Publish the product to the storefront.',
                ],
                'decision_indicators' => [
                    ['label' => 'Published products', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Process order', 'action' => 'process_order', 'module' => 'Ecommerce'],
                ],
                'tips'                => [
                    'High-quality images increase conversion rates by up to 30%.',
                    'Optimise descriptions for search engine visibility (SEO).',
                ],
            ],
            'Ecommerce.process_order' => [
                'what_to_do'          => 'Process an online order and trigger fulfilment.',
                'how_to_do'           => [
                    'Verify payment received (Mobile Money, card, bank transfer).',
                    'Pick and pack the order and generate the delivery note.',
                    'Notify the customer by email or SMS of the shipment.',
                ],
                'decision_indicators' => [
                    ['label' => 'Orders to process', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Confirmed payments', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Manage returns', 'action' => 'manage_returns', 'module' => 'Ecommerce'],
                ],
                'tips'                => [
                    'Process orders within 24 hours to improve customer satisfaction.',
                ],
            ],
            'Ecommerce.manage_returns' => [
                'what_to_do'          => 'Handle product returns and customer refunds.',
                'how_to_do'           => [
                    'Verify the return reason and condition of the returned product.',
                    'Choose between refund, exchange or store credit.',
                    'Return the item to stock after inspection.',
                ],
                'decision_indicators' => [
                    ['label' => 'Return rate', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'The legal refund period is 14 days in most jurisdictions.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Analyse return reasons to improve your products.',
                ],
            ],
            'Ecommerce.view_analytics' => [
                'what_to_do'          => 'Review your online store statistics to optimise sales.',
                'how_to_do'           => [
                    'Select the analysis period.',
                    'Identify top-selling products and abandoned carts.',
                    'Export data for a detailed report.',
                ],
                'decision_indicators' => [
                    ['label' => 'Revenue', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Conversion rate', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'A conversion rate above 3% is a strong e-commerce benchmark.',
                ],
            ],

            // ------------------------------------------------------------------
            // Logistics
            // ------------------------------------------------------------------
            'Logistics.create_shipment' => [
                'what_to_do'          => 'Create a shipment record to prepare goods for dispatch.',
                'how_to_do'           => [
                    'Select the orders to include in the shipment.',
                    'Choose the carrier and delivery method.',
                    'Generate shipping labels and documentation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Orders ready', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Track delivery', 'action' => 'track_delivery', 'module' => 'Logistics'],
                ],
                'tips'                => [
                    'Verify customs regulations for international shipments.',
                ],
            ],
            'Logistics.track_delivery' => [
                'what_to_do'          => 'Track your shipments in real time.',
                'how_to_do'           => [
                    'Enter the tracking number or select the shipment.',
                    'View delivery milestones and updated ETA.',
                    'Alert the customer in case of delay.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active deliveries', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Delayed deliveries', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Enable automatic notifications to keep customers informed.',
                ],
            ],
            'Logistics.manage_carrier' => [
                'what_to_do'          => 'Manage partner carriers and their tariff structures.',
                'how_to_do'           => [
                    'Add or update carrier information.',
                    'Configure rate grids and coverage zones.',
                    'Activate or deactivate carriers based on availability.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active carriers', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Compare carrier performance (speed, cost) quarterly.',
                ],
            ],
            'Logistics.warehouse_receipt' => [
                'what_to_do'          => 'Record the receipt of goods into the warehouse.',
                'how_to_do'           => [
                    'Identify received goods against the delivery note.',
                    'Verify quantities and condition of items.',
                    'Place items in their designated storage locations.',
                ],
                'decision_indicators' => [
                    ['label' => 'Available locations', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Update stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Use barcode scanning to speed up the receiving process.',
                ],
            ],

            // ------------------------------------------------------------------
            // Contracts
            // ------------------------------------------------------------------
            'Contracts.create_contract' => [
                'what_to_do'          => 'Create a commercial or service contract in the system.',
                'how_to_do'           => [
                    'Fill in the contracting parties and the contract subject.',
                    'Set the start/end dates and financial terms.',
                    'Attach the signed contract document.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active contracts', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Activate contract', 'action' => 'activate_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Configure expiry alerts to anticipate renewals.',
                ],
            ],
            'Contracts.activate_contract' => [
                'what_to_do'          => 'Activate a contract to start tracking and invoicing.',
                'how_to_do'           => [
                    'Verify all contract terms are correctly entered.',
                    'Confirm signatures from both parties.',
                    'Activate the contract to trigger automatic monitoring.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contracts pending', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'A contract cannot be activated without supporting documents attached.',
                ],
                'next_actions'        => [
                    ['label' => 'Schedule renewal', 'action' => 'renew_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Notify all parties of activation by email.',
                ],
            ],
            'Contracts.renew_contract' => [
                'what_to_do'          => 'Renew an expiring contract before it lapses.',
                'how_to_do'           => [
                    'Review the terms of the existing contract.',
                    'Negotiate and update new terms if necessary.',
                    'Create the new contract and archive the previous one.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contracts to renew', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'Initiate renewal at least 30 days before the expiry date.',
                ],
                'next_actions'        => [
                    ['label' => 'Activate new contract', 'action' => 'activate_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Use renewal as an opportunity to renegotiate rates.',
                ],
            ],
            'Contracts.expiry_alert' => [
                'what_to_do'          => 'Contracts are expiring soon — take action immediately.',
                'how_to_do'           => [
                    'Review contracts expiring within the next 30 days.',
                    'Contact the relevant parties to initiate renewal.',
                    'Update the status of each contract handled.',
                ],
                'decision_indicators' => [
                    ['label' => 'Expiring within 30 days', 'value' => '—', 'status' => 'critical'],
                ],
                'warnings'            => [
                    'An expired contract that is not renewed may cause service interruption.',
                ],
                'next_actions'        => [
                    ['label' => 'Renew contract', 'action' => 'renew_contract', 'module' => 'Contracts'],
                ],
                'tips'                => [
                    'Set automatic alerts at D-60 and D-30 before contract expiry.',
                ],
            ],

            // ------------------------------------------------------------------
            // Assets
            // ------------------------------------------------------------------
            'Assets.add_asset' => [
                'what_to_do'          => 'Register a new fixed asset in the asset register.',
                'how_to_do'           => [
                    'Enter the asset name, category and acquisition value.',
                    'Set the useful life and depreciation method.',
                    'Link the asset to a cost centre or department.',
                ],
                'decision_indicators' => [
                    ['label' => 'Net book value', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Assets in service', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Post depreciation', 'action' => 'post_depreciation', 'module' => 'Assets'],
                ],
                'tips'                => [
                    'SYSCOHADA prescribes specific depreciation periods by asset category.',
                ],
            ],
            'Assets.post_depreciation' => [
                'what_to_do'          => 'Calculate and post depreciation charges for the period.',
                'how_to_do'           => [
                    'Select the depreciation period (monthly or annual).',
                    'Verify eligible assets and the applied rates.',
                    'Validate to generate the depreciation journal entries.',
                ],
                'decision_indicators' => [
                    ['label' => 'Period depreciation', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Fully depreciated assets', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Verify depreciation rates comply with OHADA standards.',
                ],
                'next_actions'        => [
                    ['label' => 'Schedule maintenance', 'action' => 'schedule_maintenance', 'module' => 'Assets'],
                ],
                'tips'                => [
                    'Archive monthly depreciation entries for year-end reconciliation.',
                ],
            ],
            'Assets.schedule_maintenance' => [
                'what_to_do'          => 'Schedule a preventive or corrective maintenance intervention.',
                'how_to_do'           => [
                    'Select the asset and the type of maintenance (preventive/corrective).',
                    'Schedule the date and assign the responsible technician.',
                    'Enter the estimated cost and downtime.',
                ],
                'decision_indicators' => [
                    ['label' => 'Scheduled maintenances', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Assets down', 'value' => '0', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Preventive maintenance reduces breakdowns by up to 40% on average.',
                ],
            ],
            'Assets.dispose_asset' => [
                'what_to_do'          => 'Record the permanent disposal of an asset (sale or write-off).',
                'how_to_do'           => [
                    'Select the asset to dispose of and the disposal reason.',
                    'Enter the disposal value (if sold) and the disposal date.',
                    'Validate to generate the asset disposal journal entries.',
                ],
                'decision_indicators' => [
                    ['label' => 'Gain or loss on disposal', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Asset disposal has tax implications — consult your accountant.',
                ],
                'next_actions'        => [
                    ['label' => 'Post disposal invoice', 'action' => 'post_invoice', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Keep disposal documents (sale deed) for audit purposes.',
                ],
            ],

            // ------------------------------------------------------------------
            // BI
            // ------------------------------------------------------------------
            'BI.analyze_data' => [
                'what_to_do'          => 'Analyze your business data with interactive dashboards.',
                'how_to_do'           => [
                    'Select the data source.',
                    'Choose visualization type.',
                    'Apply filters and periods.',
                ],
                'decision_indicators' => [
                    ['label' => 'Data fresh', 'value' => 'Yes', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Export report', 'action' => 'export', 'module' => 'BI'],
                ],
                'tips'                => [
                    'Use segments to compare different time periods.',
                ],
            ],

            // ------------------------------------------------------------------
            // Helpdesk
            // ------------------------------------------------------------------
            'Helpdesk.route_ticket' => [
                'what_to_do'          => 'Handle and route customer support tickets efficiently.',
                'how_to_do'           => [
                    'Evaluate ticket priority.',
                    'Assign to the right agent.',
                    'Set SLA deadline.',
                ],
                'decision_indicators' => [
                    ['label' => 'Open tickets', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Check SLA before assigning'],
                'next_actions'        => [
                    ['label' => 'View all tickets', 'action' => 'index', 'module' => 'Helpdesk'],
                ],
                'tips'                => [
                    'Critical tickets must be handled within 2 hours.',
                ],
            ],

            // ------------------------------------------------------------------
            // Documents
            // ------------------------------------------------------------------
            'Documents.ocr_classify' => [
                'what_to_do'          => 'Import and classify documents with automatic recognition.',
                'how_to_do'           => [
                    'Drop document in import zone.',
                    'AI extracts key metadata.',
                    'Verify and confirm classification.',
                ],
                'decision_indicators' => [
                    ['label' => 'OCR available', 'value' => 'Yes', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Import document', 'action' => 'import', 'module' => 'Documents'],
                ],
                'tips'                => [
                    'Native digital PDFs have better OCR accuracy.',
                ],
            ],

            // ------------------------------------------------------------------
            // Projects (new action)
            // ------------------------------------------------------------------
            'Projects.estimate_task' => [
                'what_to_do'          => 'Estimate tasks and track project progress.',
                'how_to_do'           => [
                    'Break into subtasks.',
                    'Estimate duration in hours.',
                    'Assign to team members.',
                ],
                'decision_indicators' => [
                    ['label' => 'Progress', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Add 20% buffer for unexpected tasks'],
                'next_actions'        => [
                    ['label' => 'View Gantt', 'action' => 'gantt', 'module' => 'Projects'],
                ],
                'tips'                => [
                    'Use project templates for recurring project types.',
                ],
            ],

            // ------------------------------------------------------------------
            // Timesheets
            // ------------------------------------------------------------------
            'Timesheets.view_dashboard' => [
                'what_to_do'          => 'Enter and validate hours worked by project.',
                'how_to_do'           => [
                    'Select project and task.',
                    'Enter hours per day.',
                    'Submit for manager approval.',
                ],
                'decision_indicators' => [
                    ['label' => 'Hours this week', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Submit week', 'action' => 'submit', 'module' => 'Timesheets'],
                ],
                'tips'                => [
                    'Fill timesheets every Friday.',
                ],
            ],

            // ------------------------------------------------------------------
            // Achats (new action)
            // ------------------------------------------------------------------
            'Achats.view_dashboard' => [
                'what_to_do'          => 'Manage your purchases and supplier RFQs.',
                'how_to_do'           => [
                    'Create a request for quotation.',
                    'Compare received quotes.',
                    'Issue the purchase order.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending orders', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'New order', 'action' => 'create_order', 'module' => 'Achats'],
                ],
                'tips'                => [
                    'Consolidate supplier orders to negotiate better prices.',
                ],
            ],

            // ------------------------------------------------------------------
            // Planning
            // ------------------------------------------------------------------
            'Planning.view_dashboard' => [
                'what_to_do'          => 'Plan your resources and track project milestones.',
                'how_to_do'           => [
                    'Create a production plan.',
                    'Allocate available resources.',
                    'Identify bottlenecks.',
                ],
                'decision_indicators' => [
                    ['label' => 'Utilization rate', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'New plan', 'action' => 'create', 'module' => 'Planning'],
                ],
                'tips'                => [
                    'Check resource conflicts before finalizing the plan.',
                ],
            ],

            // ------------------------------------------------------------------
            // CustomerService
            // ------------------------------------------------------------------
            'CustomerService.view_dashboard' => [
                'what_to_do'          => 'Track customer tickets and meet SLA commitments.',
                'how_to_do'           => [
                    'Check critical tickets first.',
                    'Assign to right agents.',
                    'Respond before SLA deadline.',
                ],
                'decision_indicators' => [
                    ['label' => 'SLA met', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Check tickets with SLA at risk'],
                'next_actions'        => [
                    ['label' => 'View critical tickets', 'action' => 'critical_tickets', 'module' => 'CustomerService'],
                ],
                'tips'                => [
                    'Set up automatic replies for frequently asked questions.',
                ],
            ],

            // ------------------------------------------------------------------
            // Reporting (new action)
            // ------------------------------------------------------------------
            'Reporting.view_dashboard' => [
                'what_to_do'          => 'Create and run reports to drive your business.',
                'how_to_do'           => [
                    'Select source module.',
                    'Configure columns and filters.',
                    'Run and export.',
                ],
                'decision_indicators' => [
                    ['label' => 'Reports available', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create report', 'action' => 'create', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Schedule weekly reports for your management team.',
                ],
            ],

            // ------------------------------------------------------------------
            // Workflow
            // ------------------------------------------------------------------
            'Workflow.view_dashboard' => [
                'what_to_do'          => 'Automate your business processes with workflows.',
                'how_to_do'           => [
                    'Define the trigger (event, schedule).',
                    'Set conditions.',
                    'Add actions to execute.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active workflows', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'New workflow', 'action' => 'create', 'module' => 'Workflow'],
                ],
                'tips'                => [
                    'Test workflows in simulation mode before activation.',
                ],
            ],

            // ------------------------------------------------------------------
            // MarketingAutomation
            // ------------------------------------------------------------------
            'MarketingAutomation.view_dashboard' => [
                'what_to_do'          => 'Launch targeted and automated marketing campaigns.',
                'how_to_do'           => [
                    'Define your target segment.',
                    'Choose channel (email, SMS, WhatsApp).',
                    'Schedule sending.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active campaigns', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'New campaign', 'action' => 'create', 'module' => 'MarketingAutomation'],
                ],
                'tips'                => [
                    'Segment by lead score to prioritize hot prospects.',
                ],
            ],

            // ------------------------------------------------------------------
            // Calendar (English)
            // ------------------------------------------------------------------
            'Calendar.view_calendar' => [
                'what_to_do'          => 'View and manage all your events from a centralized calendar.',
                'how_to_do'           => [
                    'Select the view (Month, Week, Day or Agenda) that suits your needs.',
                    'Enable module sources (HR, Projects, Helpdesk) to see all relevant events.',
                    'Connect Google Calendar, Outlook or iCloud to sync your external agendas.',
                ],
                'decision_indicators' => [
                    ['label' => 'Upcoming events', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Sync status', 'value' => 'Check', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create event', 'action' => 'create_event', 'module' => 'Calendar'],
                    ['label' => 'Sync settings', 'action' => 'calendar_settings', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'The Agenda view is ideal for a 30-day overview.',
                    'Set reminders so you never miss an important deadline.',
                ],
            ],
            'Calendar.calendar_settings' => [
                'what_to_do'          => 'Connect external calendars and configure module event sources.',
                'how_to_do'           => [
                    'Click "Connect Google Calendar" or "Connect Outlook" to authorize sync.',
                    'For Apple Calendar, use an app-specific password (not your regular Apple ID password).',
                    'Enable module sources (HR Leaves, Helpdesk SLA, etc.) as needed.',
                ],
                'decision_indicators' => [
                    ['label' => 'Connected providers', 'value' => '0/3', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'For Apple iCloud, create an app-specific password at appleid.apple.com.',
                ],
                'next_actions'        => [
                    ['label' => 'View calendar', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'Sync runs automatically every 15 minutes.',
                    'Export as .ics to share your calendar with colleagues.',
                ],
            ],
            'Calendar.create_event' => [
                'what_to_do'          => 'Create a new event and invite attendees.',
                'how_to_do'           => [
                    'Enter the title, date/time, and target calendar.',
                    'Add attendees by typing their email or name.',
                    'Set a reminder (popup, email or push notification).',
                ],
                'decision_indicators' => [
                    ['label' => 'Available calendars', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View calendar', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [
                    'Link an event to a Project task or Strategy milestone for unified tracking.',
                ],
            ],

            // ------------------------------------------------------------------
            // Reporting
            // ------------------------------------------------------------------
            'Reporting.create_report' => [
                'what_to_do'          => 'Create a custom report to analyse your business data.',
                'how_to_do'           => [
                    'Select the source module and the KPIs to include.',
                    'Configure filters, groupings and the analysis period.',
                    'Preview and save the report template.',
                ],
                'decision_indicators' => [
                    ['label' => 'Saved reports', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Schedule report', 'action' => 'schedule_report', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Describe your need in plain language — AI can generate the report automatically.',
                ],
            ],
            'Reporting.schedule_report' => [
                'what_to_do'          => 'Schedule a report to be sent automatically at a defined frequency.',
                'how_to_do'           => [
                    'Select the report to schedule.',
                    'Choose the frequency (daily, weekly, monthly).',
                    'Enter the email recipients.',
                ],
                'decision_indicators' => [
                    ['label' => 'Scheduled reports', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Export report', 'action' => 'export_report', 'module' => 'Reporting'],
                ],
                'tips'                => [
                    'Weekly reports are ideal for operational management.',
                ],
            ],
            'Reporting.export_report' => [
                'what_to_do'          => 'Export a report in the desired format (PDF, Excel, CSV).',
                'how_to_do'           => [
                    'Select the report to export.',
                    'Choose the export format and layout options.',
                    'Download or send by email.',
                ],
                'decision_indicators' => [
                    ['label' => 'Available formats', 'value' => 'PDF/Excel/CSV', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Excel format is recommended for advanced data analysis.',
                ],
            ],
            'Reporting.interpret_results' => [
                'what_to_do'          => 'Use AI to interpret your report results in plain language.',
                'how_to_do'           => [
                    'Open an existing report and click "Interpret with AI".',
                    'AI generates a narrative summary of trends and detected anomalies.',
                    'Share the interpretation with your team.',
                ],
                'decision_indicators' => [
                    ['label' => 'Trends detected', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'AI interpretation is available in French, English and Arabic.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — ISO standards for Textile sector (English)
            // ------------------------------------------------------------------
            'Quality.iso_textile' => [
                'what_to_do'          => 'Verify OEKO-TEX, REACH and ISO 105 standards for textile components.',
                'how_to_do'           => [
                    'Require an OEKO-TEX Standard 100 certificate for any component in direct skin contact.',
                    'Check REACH CE 1907/2006 compliance for dyes and chemical treatments.',
                    'Test colour fastness per ISO 105-C06 (washing) and ISO 105-X12 (rubbing).',
                ],
                'decision_indicators' => [
                    ['label' => 'Active textile standards', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'OEKO-TEX certified', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'REACH non-conformances', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'REACH restricts 197 substances — verify supplier safety data sheets (SDS).',
                    'GOTS requires full supply-chain certification from field to finished product.',
                ],
                'next_actions'        => [
                    ['label' => 'Check supplier certifications', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Start component inspection', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'For children\'s garments, require OEKO-TEX Class 1 (stricter limits).',
                    'Protective textile clothing (PPE) requires mandatory CE certification.',
                ],
            ],

            // ------------------------------------------------------------------
            // Quality — ISO standards for Construction / BTP sector (English)
            // ------------------------------------------------------------------
            'Quality.iso_construction' => [
                'what_to_do'          => 'Verify EN/Eurocode standards for construction materials and their CE marking.',
                'how_to_do'           => [
                    'For concrete: require EN 206 and EN 12390 test reports (compressive strength).',
                    'For steel: require EN 10025 + CE marking + EN 1090 certificate (EXC 2 minimum).',
                    'For insulation: verify fire reaction class per EN 13501 based on intended use.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active construction standards', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'CE marking verified', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'EN 1090 certificates', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [
                    'CE marking is mandatory for all construction products sold in Europe.',
                    'Eurocodes are the reference calculation method — verify concrete exposure class.',
                ],
                'next_actions'        => [
                    ['label' => 'Check supplier certifications', 'action' => 'iso_compliance', 'module' => 'Quality'],
                    ['label' => 'Inspect received component', 'action' => 'inspect_component', 'module' => 'Quality'],
                ],
                'tips'                => [
                    'For electrical cables in ERP buildings, require minimum Cca fire reaction class.',
                    'BIM ISO 19650: digitise material data at order stage for the as-built record.',
                ],
            ],

            // ------------------------------------------------------------------
            // HR — Onboarding Workflow
            // ------------------------------------------------------------------
            'HR.onboarding' => [
                'what_to_do'          => 'Track new employee onboarding step-by-step, coordinating all departments.',
                'how_to_do'           => [
                    'Collect administrative documents within 48h of the start date.',
                    'Coordinate with IT to create the ERP account and email address.',
                    'Schedule mandatory training sessions before the end of the first week.',
                ],
                'decision_indicators' => [
                    ['label' => 'Onboardings in progress', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Missing documents',       'value' => '—', 'status' => 'warning'],
                    ['label' => 'Overdue steps',           'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A failed onboarding costs on average 3× the monthly salary in lost productivity.',
                ],
                'next_actions'        => [
                    ['label' => 'View onboardings',    'action' => 'onboarding',       'module' => 'HR'],
                    ['label' => 'Create employee',     'action' => 'create_employee',  'module' => 'HR'],
                ],
                'tips'                => [
                    'Assign a buddy on day 1 to accelerate integration.',
                    'Cross-module tracking ensures no step is overlooked.',
                ],
            ],

            // ------------------------------------------------------------------
            // Accounting — Invoice Approval Workflow
            // ------------------------------------------------------------------
            'Accounting.invoice_approval' => [
                'what_to_do'          => 'Validate pending invoices following the OHADA-compliant approval chain.',
                'how_to_do'           => [
                    'Check invoice compliance (mandatory OHADA legal mentions).',
                    'Verify budget availability before approving.',
                    'Schedule payment within contractual terms after full approval.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending invoices',        'value' => '—', 'status' => 'warning'],
                    ['label' => 'Total amount pending',    'value' => '—', 'status' => 'ok'],
                    ['label' => 'Overdue (>3 days)',        'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'OHADA legal deadline: 30 days for supplier invoices (art. 277 OHADA).',
                    'Invoices over 500,000 XOF require 3 approval levels.',
                ],
                'next_actions'        => [
                    ['label' => 'View approvals',          'action' => 'invoice_approval',         'module' => 'Accounting'],
                    ['label' => 'Payment calendar',        'action' => 'view_payment_schedule',    'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Configure approval thresholds by delegation in Accounting settings.',
                    'African supplier invoices must include the TIN and tax regime.',
                ],
            ],
            'Accounting.view_payment_schedule' => [
                'what_to_do'          => 'Plan and track all payment deadlines to avoid late penalties.',
                'how_to_do'           => [
                    'Use the calendar view to identify payments due this week.',
                    'Trigger bank transfers or Mobile Money from the Pay button.',
                    'Send automatic reminders to suppliers 3 days before the due date.',
                ],
                'decision_indicators' => [
                    ['label' => 'Overdue payments',        'value' => '—', 'status' => 'ok'],
                    ['label' => 'Due this week',           'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Late payments to OHADA suppliers may incur 18%/year penalties.',
                ],
                'next_actions'        => [
                    ['label' => 'Approve invoices',        'action' => 'invoice_approval',   'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Enable SMS/WhatsApp reminders for large payments.',
                    'Use Mobile Money (Orange Money, Wave) for fast payments.',
                ],
            ],

            // ─── Cost Engine ─────────────────────────────────────────────────────
            'Accounting.cost_analysis' => [
                'what_to_do'          => 'Analyse the CAPEX/OPEX/FINEX/RISKEX cost structure to identify cost reduction levers and improve profitability.',
                'how_to_do'           => [
                    'Compare your cost structure to the industry benchmark (Benchmark tab).',
                    'Identify BOM components with the highest FINEX costs — a sign of over-financing.',
                    'Monitor RISKEX — a rate >8% signals systemic quality problems.',
                ],
                'decision_indicators' => [
                    ['label' => 'FINEX threshold',   'value' => '<8% of total cost',                          'status' => 'ok'],
                    ['label' => 'RISKEX threshold',  'value' => '<5% of total cost',                          'status' => 'ok'],
                    ['label' => 'Net margin target', 'value' => '>15% (textile) / >12% (construction)',       'status' => 'ok'],
                ],
                'warnings'            => [
                    'FINEX >12% of total cost suggests a working capital (BFR) problem.',
                    'RISKEX >8% signals recurring quality issues — audit your suppliers.',
                ],
                'next_actions'        => [
                    ['label' => 'View BOM costs',       'action' => 'cost_analysis', 'module' => 'Accounting'],
                    ['label' => 'Profitability report', 'action' => 'cost_analysis', 'module' => 'Accounting'],
                    ['label' => 'OHADA report',         'action' => 'ohada_report',  'module' => 'Accounting'],
                ],
                'tips'                => [
                    'OHADA: classify CAPEX as Class 2, OPEX as Class 6, FINEX as Class 67, RISKEX as Class 69.',
                    'High FINEX on an imported product can be reduced by refinancing via Letter of Credit.',
                    'Trigger automatic rollup after each monthly close.',
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function emptyGuidance(): array
    {
        return [
            'enabled'             => false,
            'what_to_do'          => '',
            'how_to_do'           => [],
            'decision_indicators' => [],
            'warnings'            => [],
            'next_actions'        => [],
            'tips'                => [],
        ];
    }

    /**
     * Fallback guidance written for modules added to supportedModules() but
     * never merged into frenchMap()/englishMap() -- they previously sat as
     * dead extra keys inside emptyGuidance(), never reached by the
     * frenchMap()/englishMap() lookup in fallbackGuidance(), and leaking
     * into every single fallback response as extra array keys.
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapPhase52(): array
    {
        return [
            // ─── SMS ───────────────────────────────────────────────────────
            'SMS.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Gérez vos campagnes SMS et l\'état de vos envois.',
                'how_to_do'           => [
                    'Consultez le tableau de bord pour le taux de livraison et les coûts.',
                    'Créez une campagne en sélectionnant les destinataires depuis le CRM.',
                    'Choisissez le fournisseur (Orange/MTN/Airtel) selon la région cible.',
                ],
                'decision_indicators' => [
                    ['label' => 'Taux de livraison', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Messages envoyés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Vérifiez les opt-outs GDPR avant chaque campagne.'],
                'next_actions'        => [
                    ['label' => 'Nouvelle campagne', 'action' => 'send_campaign', 'module' => 'SMS'],
                    ['label' => 'Configurer fournisseur', 'action' => 'configure_provider', 'module' => 'SMS'],
                ],
                'tips'                => ['Les SMS en XOF sur Orange SN ont le meilleur taux de livraison (99%).'],
            ],
            'SMS.send_campaign' => [
                'enabled'             => true,
                'what_to_do'          => 'Envoyez une campagne SMS à votre audience cible.',
                'how_to_do'           => [
                    'Sélectionnez ou importez la liste de destinataires (CRM / CSV).',
                    'Rédigez le message (max 160 caractères par crédit SMS).',
                    'Planifiez l\'heure d\'envoi pour maximiser l\'ouverture.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Respectez la réglementation UEMOA sur les messages commerciaux.'],
                'next_actions'        => [
                    ['label' => 'Voir résultats', 'action' => 'view_dashboard', 'module' => 'SMS'],
                ],
                'tips'                => ['Taux d\'ouverture SMS moyen : 98% dans les 3 minutes.'],
            ],

            // ─── Payroll ───────────────────────────────────────────────────
            'Payroll.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Supervisez la paie du mois et l\'état des fiches de paie.',
                'how_to_do'           => [
                    'Vérifiez que tous les employés actifs sont inclus dans la période.',
                    'Contrôlez les ajustements (congés, absences, heures sup).',
                    'Lancez la génération des fiches de paie puis soumettez pour approbation.',
                ],
                'decision_indicators' => [
                    ['label' => 'Fiches générées', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'En attente approbation', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Masse salariale', 'value' => '— XOF', 'status' => 'ok'],
                ],
                'warnings'            => ['La paie OHADA doit être versée au plus tard le dernier jour ouvré du mois.'],
                'next_actions'        => [
                    ['label' => 'Générer les fiches', 'action' => 'generate_payslips', 'module' => 'Payroll'],
                    ['label' => 'Approuver la paie', 'action' => 'approve_payroll', 'module' => 'Payroll'],
                ],
                'tips'                => ['Activez l\'IA de détection d\'anomalies (écarts > 15% vs mois précédent).'],
            ],
            'Payroll.generate_payslips' => [
                'enabled'             => true,
                'what_to_do'          => 'Générez les fiches de paie pour la période sélectionnée.',
                'how_to_do'           => [
                    'Sélectionnez la période (AAAA-MM).',
                    'Vérifiez les règles fiscales (CNSS, IRPP, TRIMF) pour le pays.',
                    'Lancez le calcul — les fiches passent en statut « Brouillon ».',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Vérifiez les cotisations CNSS avant validation (taux 2026 Sénégal : 14%).'],
                'next_actions'        => [
                    ['label' => 'Approuver', 'action' => 'approve_payroll', 'module' => 'Payroll'],
                ],
                'tips'                => [],
            ],

            // ─── Notes ─────────────────────────────────────────────────────
            'Notes.view_notes' => [
                'enabled'             => true,
                'what_to_do'          => 'Consultez et organisez vos notes et pages wiki partagées.',
                'how_to_do'           => [
                    'Parcourez les pages par espace de travail ou utilisez la recherche.',
                    'Les notes privées ne sont visibles que par leur auteur.',
                    'Créez des pages structurées avec titres, listes et blocs de code.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer une note', 'action' => 'create_note', 'module' => 'Notes'],
                    ['label' => 'Rechercher', 'action' => 'search_notes', 'module' => 'Notes'],
                ],
                'tips'                => ['Taggez vos notes avec les modules ERP pour les retrouver rapidement.'],
            ],

            // ─── SmartTable ────────────────────────────────────────────────
            'SmartTable.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Gérez vos bases de données métier no-code de type Airtable.',
                'how_to_do'           => [
                    'Créez une base depuis un modèle (CRM, Inventaire, OKR…) ou de zéro.',
                    'Ajoutez des colonnes typées (texte, date, liste, relation).',
                    'Partagez la base avec votre équipe et filtrez/triez les données.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Nouvelle base', 'action' => 'create_base', 'module' => 'SmartTable'],
                ],
                'tips'                => ['Synchronisez vos SmartTables avec le CRM pour créer des vues personnalisées.'],
            ],

            // ─── AuditLog ──────────────────────────────────────────────────
            'AuditLog.view_audit_log' => [
                'enabled'             => true,
                'what_to_do'          => 'Consultez le journal d\'audit pour la traçabilité et la conformité RGPD.',
                'how_to_do'           => [
                    'Filtrez par module, utilisateur, action ou plage de dates.',
                    'Exportez le journal en CSV ou PDF pour les audits réglementaires.',
                    'Configurez les alertes pour les actions sensibles (DELETE, PERMISSION_CHANGE).',
                ],
                'decision_indicators' => [
                    ['label' => 'Événements critiques', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Conservez les journaux d\'audit minimum 5 ans (exigence PDPL/RGPD).'],
                'next_actions'        => [
                    ['label' => 'Exporter CSV', 'action' => 'export_audit', 'module' => 'AuditLog'],
                ],
                'tips'                => ['Activez les alertes temps réel pour les connexions hors des heures ouvrées.'],
            ],

            // ─── Settings ──────────────────────────────────────────────────
            'Settings.configure_settings' => [
                'enabled'             => true,
                'what_to_do'          => 'Configurez les paramètres globaux de votre entreprise.',
                'how_to_do'           => [
                    'Renseignez les informations de l\'entreprise (nom, devise, fuseau horaire).',
                    'Configurez les modules actifs et les intégrations tierces.',
                    'Définissez les préférences de notification par rôle.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Toute modification de devise affecte les rapports OHADA en cours.'],
                'next_actions'        => [
                    ['label' => 'Gérer les intégrations', 'action' => 'manage_integrations', 'module' => 'Settings'],
                ],
                'tips'                => ['Activez l\'authentification 2FA pour tous les rôles admin.'],
            ],

            // ─── Shared ────────────────────────────────────────────────────
            'Shared.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Accédez aux ressources partagées et aux utilitaires communs.',
                'how_to_do'           => [
                    'Les préférences partagées s\'appliquent à tous les modules.',
                    'Consultez les helpers et composants disponibles pour votre équipe.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],

            'SMS.configure_provider' => [
                'enabled'             => true,
                'what_to_do'          => 'Configurez le fournisseur SMS à utiliser pour vos envois.',
                'how_to_do'           => [
                    'Choisissez un fournisseur régional (Orange, MTN, Airtel) selon votre marché.',
                    'Renseignez la clé API et le sender ID fournis par l\'opérateur.',
                    'Envoyez un SMS de test avant d\'activer le fournisseur en production.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Un sender ID non validé par l\'opérateur peut bloquer tous les envois.'],
                'next_actions'        => [
                    ['label' => 'Voir tableau de bord', 'action' => 'view_dashboard', 'module' => 'SMS'],
                ],
                'tips'                => [],
            ],
            'Payroll.approve_payroll' => [
                'enabled'             => true,
                'what_to_do'          => 'Validez la paie générée avant versement des salaires.',
                'how_to_do'           => [
                    'Vérifiez le total de la masse salariale par rapport au budget.',
                    'Contrôlez les anomalies signalées (écarts, doublons).',
                    'Approuvez pour déclencher le versement et l\'écriture comptable.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Une fois approuvée, la paie ne peut plus être modifiée pour cette période.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Payroll.export_payroll' => [
                'enabled'             => true,
                'what_to_do'          => 'Exportez les données de paie pour la déclaration ou l\'archivage.',
                'how_to_do'           => [
                    'Sélectionnez la période et le format (CSV, PDF, déclaration CNSS).',
                    'Vérifiez que la paie a bien été approuvée avant export.',
                    'Téléchargez le fichier ou envoyez-le directement à l\'organisme concerné.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Notes.create_note' => [
                'enabled'             => true,
                'what_to_do'          => 'Créez une nouvelle note ou page wiki.',
                'how_to_do'           => [
                    'Choisissez l\'espace de travail et donnez un titre clair.',
                    'Rédigez le contenu avec titres, listes et blocs de code si besoin.',
                    'Partagez la note avec votre équipe ou gardez-la privée.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Rechercher', 'action' => 'search_notes', 'module' => 'Notes'],
                ],
                'tips'                => [],
            ],
            'Notes.search_notes' => [
                'enabled'             => true,
                'what_to_do'          => 'Recherchez une note ou une page wiki existante.',
                'how_to_do'           => [
                    'Utilisez des mots-clés du titre ou du contenu.',
                    'Filtrez par espace de travail ou par auteur si besoin.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'SmartTable.create_base' => [
                'enabled'             => true,
                'what_to_do'          => 'Créez une nouvelle base de données métier.',
                'how_to_do'           => [
                    'Partez d\'un modèle (CRM, Inventaire, OKR…) ou d\'une base vide.',
                    'Ajoutez des colonnes typées et définissez les relations entre tables.',
                    'Partagez la base avec votre équipe.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Gérer la table', 'action' => 'manage_table', 'module' => 'SmartTable'],
                ],
                'tips'                => [],
            ],
            'SmartTable.manage_table' => [
                'enabled'             => true,
                'what_to_do'          => 'Gérez la structure et les permissions d\'une base existante.',
                'how_to_do'           => [
                    'Ajoutez, renommez ou supprimez des colonnes selon vos besoins.',
                    'Définissez les permissions de lecture/écriture par membre d\'équipe.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Supprimer une colonne efface définitivement les données associées.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'AuditLog.export_audit' => [
                'enabled'             => true,
                'what_to_do'          => 'Exportez le journal d\'audit pour un contrôle réglementaire.',
                'how_to_do'           => [
                    'Filtrez par module, utilisateur ou plage de dates.',
                    'Choisissez le format d\'export (CSV ou PDF).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'AuditLog.filter_events' => [
                'enabled'             => true,
                'what_to_do'          => 'Filtrez les événements du journal d\'audit.',
                'how_to_do'           => [
                    'Combinez les filtres module, utilisateur, action et date.',
                    'Enregistrez un filtre fréquent pour y accéder rapidement.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Exporter', 'action' => 'export_audit', 'module' => 'AuditLog'],
                ],
                'tips'                => [],
            ],
            'Settings.manage_integrations' => [
                'enabled'             => true,
                'what_to_do'          => 'Gérez les intégrations tierces connectées à l\'ERP.',
                'how_to_do'           => [
                    'Activez ou désactivez une intégration depuis la liste disponible.',
                    'Renseignez les clés API requises par chaque fournisseur.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Désactiver une intégration active peut interrompre des synchronisations en cours.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Settings.notification_preferences' => [
                'enabled'             => true,
                'what_to_do'          => 'Définissez les préférences de notification par rôle.',
                'how_to_do'           => [
                    'Choisissez les canaux (email, in-app) par type d\'événement.',
                    'Ajustez les préférences par rôle ou par utilisateur.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapPhase52(): array
    {
        return [
            'SMS.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Manage your SMS campaigns and delivery status.',
                'how_to_do'           => [
                    'Check the dashboard for delivery rate and costs.',
                    'Create a campaign by selecting recipients from CRM.',
                    'Choose the provider (Orange/MTN/Airtel) based on the target region.',
                ],
                'decision_indicators' => [
                    ['label' => 'Delivery rate', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Messages sent', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Check GDPR opt-outs before each campaign.'],
                'next_actions'        => [
                    ['label' => 'New campaign', 'action' => 'send_campaign', 'module' => 'SMS'],
                    ['label' => 'Configure provider', 'action' => 'configure_provider', 'module' => 'SMS'],
                ],
                'tips'                => ['SMS in XOF via Orange SN has the best delivery rate (99%).'],
            ],
            'SMS.send_campaign' => [
                'enabled'             => true,
                'what_to_do'          => 'Send an SMS campaign to your target audience.',
                'how_to_do'           => [
                    'Select or import the recipient list (CRM / CSV).',
                    'Write the message (max 160 characters per SMS credit).',
                    'Schedule the send time to maximize open rate.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Comply with UEMOA regulations on commercial messages.'],
                'next_actions'        => [
                    ['label' => 'View results', 'action' => 'view_dashboard', 'module' => 'SMS'],
                ],
                'tips'                => ['Average SMS open rate: 98% within 3 minutes.'],
            ],
            'SMS.configure_provider' => [
                'enabled'             => true,
                'what_to_do'          => 'Configure the SMS provider used for your campaigns.',
                'how_to_do'           => [
                    'Choose a regional provider (Orange, MTN, Airtel) based on your market.',
                    'Enter the API key and sender ID provided by the operator.',
                    'Send a test SMS before enabling the provider in production.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['A sender ID not approved by the operator can block all sends.'],
                'next_actions'        => [
                    ['label' => 'View dashboard', 'action' => 'view_dashboard', 'module' => 'SMS'],
                ],
                'tips'                => [],
            ],
            'Payroll.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Oversee this month\'s payroll and payslip status.',
                'how_to_do'           => [
                    'Verify all active employees are included for the period.',
                    'Review adjustments (leave, absences, overtime).',
                    'Generate payslips then submit for approval.',
                ],
                'decision_indicators' => [
                    ['label' => 'Payslips generated', 'value' => '—', 'status' => 'ok'],
                    ['label' => 'Pending approval', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Total payroll', 'value' => '— XOF', 'status' => 'ok'],
                ],
                'warnings'            => ['OHADA payroll must be paid by the last business day of the month.'],
                'next_actions'        => [
                    ['label' => 'Generate payslips', 'action' => 'generate_payslips', 'module' => 'Payroll'],
                    ['label' => 'Approve payroll', 'action' => 'approve_payroll', 'module' => 'Payroll'],
                ],
                'tips'                => ['Enable AI anomaly detection (variances > 15% vs previous month).'],
            ],
            'Payroll.generate_payslips' => [
                'enabled'             => true,
                'what_to_do'          => 'Generate payslips for the selected period.',
                'how_to_do'           => [
                    'Select the period (YYYY-MM).',
                    'Check tax rules (CNSS, IRPP, TRIMF) for the country.',
                    'Run the calculation — payslips move to "Draft" status.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Verify CNSS contributions before validation (2026 Senegal rate: 14%).'],
                'next_actions'        => [
                    ['label' => 'Approve', 'action' => 'approve_payroll', 'module' => 'Payroll'],
                ],
                'tips'                => [],
            ],
            'Payroll.approve_payroll' => [
                'enabled'             => true,
                'what_to_do'          => 'Approve the generated payroll before disbursement.',
                'how_to_do'           => [
                    'Verify the total payroll amount against budget.',
                    'Review flagged anomalies (variances, duplicates).',
                    'Approve to trigger disbursement and the accounting entry.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Once approved, payroll can no longer be modified for this period.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Payroll.export_payroll' => [
                'enabled'             => true,
                'what_to_do'          => 'Export payroll data for filing or archiving.',
                'how_to_do'           => [
                    'Select the period and format (CSV, PDF, CNSS filing).',
                    'Verify payroll has been approved before exporting.',
                    'Download the file or send it directly to the relevant authority.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Notes.view_notes' => [
                'enabled'             => true,
                'what_to_do'          => 'View and organize your notes and shared wiki pages.',
                'how_to_do'           => [
                    'Browse pages by workspace or use search.',
                    'Private notes are only visible to their author.',
                    'Create structured pages with headings, lists, and code blocks.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create note', 'action' => 'create_note', 'module' => 'Notes'],
                    ['label' => 'Search', 'action' => 'search_notes', 'module' => 'Notes'],
                ],
                'tips'                => ['Tag your notes with ERP modules to find them quickly.'],
            ],
            'Notes.create_note' => [
                'enabled'             => true,
                'what_to_do'          => 'Create a new note or wiki page.',
                'how_to_do'           => [
                    'Choose the workspace and give it a clear title.',
                    'Write the content with headings, lists, and code blocks as needed.',
                    'Share the note with your team or keep it private.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Search', 'action' => 'search_notes', 'module' => 'Notes'],
                ],
                'tips'                => [],
            ],
            'Notes.search_notes' => [
                'enabled'             => true,
                'what_to_do'          => 'Search for an existing note or wiki page.',
                'how_to_do'           => [
                    'Use keywords from the title or content.',
                    'Filter by workspace or author if needed.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'SmartTable.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Manage your Airtable-style no-code business databases.',
                'how_to_do'           => [
                    'Create a base from a template (CRM, Inventory, OKR…) or from scratch.',
                    'Add typed columns (text, date, list, relation).',
                    'Share the base with your team and filter/sort the data.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'New base', 'action' => 'create_base', 'module' => 'SmartTable'],
                ],
                'tips'                => ['Sync your SmartTables with CRM to build custom views.'],
            ],
            'SmartTable.create_base' => [
                'enabled'             => true,
                'what_to_do'          => 'Create a new business database.',
                'how_to_do'           => [
                    'Start from a template (CRM, Inventory, OKR…) or an empty base.',
                    'Add typed columns and define relations between tables.',
                    'Share the base with your team.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Manage table', 'action' => 'manage_table', 'module' => 'SmartTable'],
                ],
                'tips'                => [],
            ],
            'SmartTable.manage_table' => [
                'enabled'             => true,
                'what_to_do'          => 'Manage the structure and permissions of an existing base.',
                'how_to_do'           => [
                    'Add, rename, or remove columns as needed.',
                    'Set read/write permissions per team member.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Deleting a column permanently erases its associated data.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'AuditLog.view_audit_log' => [
                'enabled'             => true,
                'what_to_do'          => 'View the audit log for traceability and GDPR compliance.',
                'how_to_do'           => [
                    'Filter by module, user, action, or date range.',
                    'Export the log as CSV or PDF for regulatory audits.',
                    'Configure alerts for sensitive actions (DELETE, PERMISSION_CHANGE).',
                ],
                'decision_indicators' => [
                    ['label' => 'Critical events', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => ['Retain audit logs for at least 5 years (PDPL/GDPR requirement).'],
                'next_actions'        => [
                    ['label' => 'Export CSV', 'action' => 'export_audit', 'module' => 'AuditLog'],
                ],
                'tips'                => ['Enable real-time alerts for logins outside business hours.'],
            ],
            'AuditLog.export_audit' => [
                'enabled'             => true,
                'what_to_do'          => 'Export the audit log for a regulatory review.',
                'how_to_do'           => [
                    'Filter by module, user, or date range.',
                    'Choose the export format (CSV or PDF).',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'AuditLog.filter_events' => [
                'enabled'             => true,
                'what_to_do'          => 'Filter events in the audit log.',
                'how_to_do'           => [
                    'Combine module, user, action, and date filters.',
                    'Save a frequent filter for quick access.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Export', 'action' => 'export_audit', 'module' => 'AuditLog'],
                ],
                'tips'                => [],
            ],
            'Settings.configure_settings' => [
                'enabled'             => true,
                'what_to_do'          => 'Configure your company\'s global settings.',
                'how_to_do'           => [
                    'Fill in company information (name, currency, timezone).',
                    'Configure active modules and third-party integrations.',
                    'Set notification preferences per role.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Any currency change affects in-progress OHADA reports.'],
                'next_actions'        => [
                    ['label' => 'Manage integrations', 'action' => 'manage_integrations', 'module' => 'Settings'],
                ],
                'tips'                => ['Enable 2FA for all admin roles.'],
            ],
            'Settings.manage_integrations' => [
                'enabled'             => true,
                'what_to_do'          => 'Manage third-party integrations connected to the ERP.',
                'how_to_do'           => [
                    'Enable or disable an integration from the available list.',
                    'Enter the API keys required by each provider.',
                ],
                'decision_indicators' => [],
                'warnings'            => ['Disabling an active integration can interrupt ongoing syncs.'],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Settings.notification_preferences' => [
                'enabled'             => true,
                'what_to_do'          => 'Set notification preferences per role.',
                'how_to_do'           => [
                    'Choose channels (email, in-app) per event type.',
                    'Adjust preferences per role or per user.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Shared.view_dashboard' => [
                'enabled'             => true,
                'what_to_do'          => 'Access shared resources and common utilities.',
                'how_to_do'           => [
                    'Shared preferences apply to all modules.',
                    'Check the helpers and components available to your team.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
        ];
    }

    /**
     * Chantier 30 — closes 2 real gaps found while confirming the AI-assist
     * layer: (1) 'Strategy' was never registered here at all despite 2 real
     * pages already calling useAiAssistant('Strategy', ...), and 7 more
     * Strategy pages had no AI-assist call whatsoever; (2) the two real
     * bulk-data-import flows outside Setup (Inventory's Stock/Import.vue —
     * Chantier 16 — and Accounting's TreasuryImport/Index.vue — Chantier 15)
     * had no contextual guidance either, unlike Setup's own import wizard.
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapChantier30(): array
    {
        return [
            'Strategy.view_dashboard' => [
                'what_to_do'          => 'Consultez le cockpit stratégique : santé des ratios, plans, alertes, OKR et recommandations IA.',
                'how_to_do'           => [
                    'Repérez les ratios en statut critique (rouge) en priorité.',
                    'Consultez les recommandations IA et les corrélations associées.',
                    'Exportez un rapport de pilotage complet (PDF ou Excel) avant un comité de direction.',
                ],
                'decision_indicators' => [
                    ['label' => 'Ratios en alerte', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir tous les ratios', 'action' => 'view_ratios', 'module' => 'Strategy'],
                    ['label' => 'Voir les OKR', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'Le mode hors-ligne (badge gris) signifie que les recommandations viennent du calcul local, pas de l\'IA en direct — toujours basées sur vos vraies données.',
                ],
            ],
            'Strategy.view_cascade_map' => [
                'what_to_do'          => 'Vérifiez l\'alignement entre les objectifs d\'entreprise et les objectifs opérationnels de chaque équipe.',
                'how_to_do'           => [
                    'Repérez un objectif opérationnel non relié à un objectif d\'entreprise.',
                    'Reliez-le depuis l\'écran Objectifs.',
                    'Suivez la progression cumulée par niveau.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Gérer les OKR', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'Un objectif orphelin (non relié) ne remonte dans aucun indicateur de cockpit.',
                ],
            ],
            'Strategy.view_ratios' => [
                'what_to_do'          => 'Comparez chaque ratio de pilotage à son repère de secteur (P25/Médiane/P75).',
                'how_to_do'           => [
                    'Repérez les ratios sous le P25 (en dessous du secteur).',
                    'Consultez la tendance (sparkline) pour distinguer une dérive d\'un accident ponctuel.',
                    'Filtrez par module (finance, commercial, stock, RH, ventes, support) pour cibler une équipe.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir les benchmarks détaillés', 'action' => 'view_benchmarks', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'Plus de 30 ratios couvrent tous les métiers — inutile de tous les surveiller en continu, concentrez-vous sur ceux en alerte.',
                ],
            ],
            'Strategy.view_plans' => [
                'what_to_do'          => 'Suivez l\'avancement de vos plans stratégiques et leur score de santé.',
                'how_to_do'           => [
                    'Identifiez le plan avec le score de santé le plus bas.',
                    'Ouvrez son détail pour voir l\'arborescence Objectifs → Résultats clés.',
                    'Dupliquez un plan existant pour démarrer le cycle suivant.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir les OKR liés', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_plan_detail' => [
                'what_to_do'          => 'Explorez l\'arborescence complète de ce plan : objectifs, résultats clés et progression.',
                'how_to_do'           => [
                    'Développez chaque objectif pour voir ses résultats clés.',
                    'Vérifiez que chaque résultat clé est bien relié à un ratio réel.',
                    'Mettez à jour la progression dès qu\'une donnée change.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Strategy.view_benchmarks' => [
                'what_to_do'          => 'Comparez la performance de l\'entreprise aux repères de référence du secteur pour chaque ratio.',
                'how_to_do'           => [
                    'Filtrez par pays ou par secteur si plusieurs repères sont disponibles.',
                    'Repérez les ratios sous le P25 en priorité.',
                    'Croisez avec l\'écran Corrélations pour comprendre les causes possibles.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir les corrélations', 'action' => 'view_correlations', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_correlations' => [
                'what_to_do'          => 'Identifiez les liens statistiques entre vos indicateurs pour prioriser les actions à fort effet de levier.',
                'how_to_do'           => [
                    'Repérez les corrélations les plus fortes (proches de 1 ou -1).',
                    'Lisez l\'interprétation automatique associée à chaque paire d\'indicateurs.',
                    'Utilisez la matrice complète pour explorer des liens moins évidents.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Une corrélation ne prouve pas une causalité — croisez toujours avec votre connaissance métier.',
                ],
            ],
            'Strategy.view_objectives' => [
                'what_to_do'          => 'Gérez l\'arborescence Objectifs → Résultats clés et reliez-la aux ratios de pilotage réels.',
                'how_to_do'           => [
                    'Créez un objectif rattaché à un plan stratégique.',
                    'Ajoutez ses résultats clés mesurables.',
                    'Reliez chaque résultat clé à un ratio réel pour un suivi automatique.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir la carte de cascade', 'action' => 'view_cascade_map', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_sector_kpi' => [
                'what_to_do'          => 'Consultez les indicateurs propres au métier de la confection : marge, coût de revient, délais de sous-traitance.',
                'how_to_do'           => [
                    'Comparez la marge moyenne par famille de produit.',
                    'Repérez les sous-traitants avec un taux de respect des délais faible.',
                    'Surveillez l\'écart entre le coût matière chiffré et les prix réellement observés chez les fournisseurs.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Ces indicateurs sont calculés en direct sur vos fiches de chiffrage et commandes de production réelles — aucun chiffre n\'est inventé.',
                ],
            ],
            'Inventory.import_stock' => [
                'what_to_do'          => 'Importez en masse des mouvements de stock (entrée/sortie) depuis un fichier CSV ou Excel.',
                'how_to_do'           => [
                    'Choisissez l\'entrepôt concerné et déposez le fichier.',
                    'Vérifiez la prévisualisation ligne par ligne — chaque produit est identifié par SKU ou par nom.',
                    'Validez : tout produit du fichier absent du catalogue est créé automatiquement.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Un stock insuffisant sur une seule ligne annule tout le lot importé — corrigez la ligne concernée et réessayez.',
                ],
                'next_actions'        => [
                    ['label' => 'Réceptionner du stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Utile pour reprendre un stock existant tenu jusqu\'ici sur un tableur.',
                ],
            ],
            'Accounting.import_treasury' => [
                'what_to_do'          => 'Importez en masse des opérations de caisse ou de relevé bancaire, avec suggestion automatique du modèle comptable.',
                'how_to_do'           => [
                    'Choisissez le compte de trésorerie (caisse, banque, Mvola, Airtel Money) et déposez le fichier CSV ou Excel.',
                    'Vérifiez la suggestion de modèle comptable proposée pour chaque ligne — modifiez-la si besoin.',
                    'Validez : une écriture comptable équilibrée est créée pour chaque ligne.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Rapprochement bancaire', 'action' => 'reconcile', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Une ligne liée à un compte bancaire est automatiquement pré-rapprochée sur l\'écran de rapprochement.',
                ],
            ],
            'Accounting.view_income_statement' => [
                'what_to_do'          => 'Consultez le compte de résultat pour suivre chiffre d\'affaires, marge et résultat net.',
                'how_to_do'           => [
                    'Sélectionnez la période de référence.',
                    'Comparez avec la période précédente pour repérer une dérive.',
                    'Exportez en PDF ou Excel pour le commissaire aux comptes ou un comité de direction.',
                ],
                'decision_indicators' => [
                    ['label' => 'Résultat net', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir le bilan', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapChantier30(): array
    {
        return [
            'Strategy.view_dashboard' => [
                'what_to_do'          => 'Review the strategy cockpit: ratio health, plans, alerts, OKRs and AI recommendations.',
                'how_to_do'           => [
                    'Look at ratios in critical (red) status first.',
                    'Review AI recommendations and their related correlations.',
                    'Export a full executive report (PDF or Excel) ahead of a leadership meeting.',
                ],
                'decision_indicators' => [
                    ['label' => 'Ratios in alert', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View all ratios', 'action' => 'view_ratios', 'module' => 'Strategy'],
                    ['label' => 'View OKRs', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'The offline badge (grey) means recommendations come from the local calculation, not live AI — still based on your real data.',
                ],
            ],
            'Strategy.view_cascade_map' => [
                'what_to_do'          => 'Check the alignment between company objectives and each team\'s operational objectives.',
                'how_to_do'           => [
                    'Spot an operational objective not linked to a company objective.',
                    'Link it from the Objectives screen.',
                    'Track cumulative progress by level.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Manage OKRs', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'An orphaned (unlinked) objective doesn\'t roll up into any cockpit indicator.',
                ],
            ],
            'Strategy.view_ratios' => [
                'what_to_do'          => 'Compare each steering ratio against its industry benchmark (P25/Median/P75).',
                'how_to_do'           => [
                    'Spot ratios below P25 (below the industry).',
                    'Check the trend sparkline to tell a drift from a one-off incident.',
                    'Filter by module (finance, sales, stock, HR, support) to target one team.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View detailed benchmarks', 'action' => 'view_benchmarks', 'module' => 'Strategy'],
                ],
                'tips'                => [
                    'Over 30 ratios cover every function — no need to watch them all continuously, focus on the ones in alert.',
                ],
            ],
            'Strategy.view_plans' => [
                'what_to_do'          => 'Track the progress of your strategic plans and their health score.',
                'how_to_do'           => [
                    'Identify the plan with the lowest health score.',
                    'Open its detail to see the Objectives → Key Results tree.',
                    'Duplicate an existing plan to start the next cycle.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View linked OKRs', 'action' => 'view_objectives', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_plan_detail' => [
                'what_to_do'          => 'Explore the full tree for this plan: objectives, key results and progress.',
                'how_to_do'           => [
                    'Expand each objective to see its key results.',
                    'Confirm each key result is genuinely linked to a real ratio.',
                    'Update progress as soon as new data comes in.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Strategy.view_benchmarks' => [
                'what_to_do'          => 'Compare company performance against industry benchmarks for each ratio.',
                'how_to_do'           => [
                    'Filter by country or industry when several benchmarks are available.',
                    'Spot ratios below P25 first.',
                    'Cross-check with the Correlations screen to understand possible causes.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View correlations', 'action' => 'view_correlations', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_correlations' => [
                'what_to_do'          => 'Identify statistical links between your indicators to prioritize high-leverage actions.',
                'how_to_do'           => [
                    'Spot the strongest correlations (close to 1 or -1).',
                    'Read the automatic interpretation for each indicator pair.',
                    'Use the full matrix to explore less obvious links.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'A correlation doesn\'t prove causation — always cross-check with your business knowledge.',
                ],
            ],
            'Strategy.view_objectives' => [
                'what_to_do'          => 'Manage the Objectives → Key Results tree and link it to real steering ratios.',
                'how_to_do'           => [
                    'Create an objective attached to a strategic plan.',
                    'Add its measurable key results.',
                    'Link each key result to a real ratio for automatic tracking.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View the cascade map', 'action' => 'view_cascade_map', 'module' => 'Strategy'],
                ],
                'tips'                => [],
            ],
            'Strategy.view_sector_kpi' => [
                'what_to_do'          => 'Review the KPIs specific to the apparel/PPE business: margin, cost of goods, subcontracting lead times.',
                'how_to_do'           => [
                    'Compare average margin by product family.',
                    'Spot subcontractors with a low on-time rate.',
                    'Watch the gap between the costed material price and prices actually observed at suppliers.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'These indicators are computed live from your real costing sheets and production orders — nothing is invented.',
                ],
            ],
            'Inventory.import_stock' => [
                'what_to_do'          => 'Bulk-import stock movements (in/out) from a CSV or Excel file.',
                'how_to_do'           => [
                    'Pick the warehouse and drop the file.',
                    'Review the row-by-row preview — each product is matched by SKU or name.',
                    'Confirm: any product in the file not yet in the catalogue is created automatically.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Insufficient stock on a single line cancels the whole batch — fix that line and retry.',
                ],
                'next_actions'        => [
                    ['label' => 'Receive stock', 'action' => 'receive_stock', 'module' => 'Inventory'],
                ],
                'tips'                => [
                    'Useful for taking over stock previously tracked in a spreadsheet.',
                ],
            ],
            'Accounting.import_treasury' => [
                'what_to_do'          => 'Bulk-import cash register or bank statement operations, with an automatic accounting template suggestion.',
                'how_to_do'           => [
                    'Pick the treasury account (cash, bank, Mvola, Airtel Money) and drop the CSV or Excel file.',
                    'Review the suggested accounting template for each row — adjust if needed.',
                    'Confirm: a balanced accounting entry is created for each row.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Bank reconciliation', 'action' => 'reconcile', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'A row linked to a bank account is automatically pre-matched on the reconciliation screen.',
                ],
            ],
            'Accounting.view_income_statement' => [
                'what_to_do'          => 'Review the income statement to track revenue, margin and net result.',
                'how_to_do'           => [
                    'Select the reference period.',
                    'Compare with the previous period to spot a drift.',
                    'Export as PDF or Excel for the auditor or a leadership meeting.',
                ],
                'decision_indicators' => [
                    ['label' => 'Net result', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View balance sheet', 'action' => 'view_balance_sheet', 'module' => 'Accounting'],
                ],
                'tips'                => [],
            ],
        ];
    }

    /**
     * Chantier 32.2 (14-layer deep audit of Modules\AI) — closes 3 modules
     * (Analytics, Integration, Security) called from real, mounted Vue pages
     * but never registered in supportedModules()/the fallback map at all,
     * plus 4 actions on 2 already-registered modules (Helpdesk.view_dashboard,
     * Calendar.calendar_integrations/team_calendar/view_event) called from
     * real pages but missing from those modules' action lists — the exact
     * "silently resolves to an empty guidance shell" bug class Chantier 30
     * already found and fixed for Strategy. See supportedModules()'s own
     * comments for exactly which real Vue file calls each pair.
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapChantier32(): array
    {
        return [
            'Analytics.view_dashboard' => [
                'what_to_do'          => 'Consultez le centre de prévisions IA : modèles prédictifs, anomalies détectées et alertes proactives.',
                'how_to_do'           => [
                    'Consultez l\'onglet Prévisions pour les modèles de prévision de la demande, de trésorerie et de production actifs.',
                    'Repérez les anomalies signalées (rupture de stock, dérive de trésorerie) en priorité.',
                    'Ouvrez la prévision de trésorerie pour un horizon détaillé (30/60/90/180 jours) et exportez-la en PDF/Excel.',
                ],
                'decision_indicators' => [
                    ['label' => 'Alertes actives', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Voir la prévision de trésorerie', 'action' => 'view_dashboard', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Les prévisions se basent sur votre historique réel de mouvements — plus vous avez d\'historique, plus elles sont fiables.',
                ],
            ],
            'Integration.view_dashboard' => [
                'what_to_do'          => 'Connectez WideHalo à vos outils externes (paiement mobile, comptabilité bancaire, partenaires fédérés).',
                'how_to_do'           => [
                    'Consultez l\'onglet Connecteurs actifs pour voir ce qui est déjà relié.',
                    'Explorez le catalogue pour ajouter une nouvelle connexion (Orange Money, MTN MoMo, open banking…).',
                    'Vérifiez le statut de chaque webhook après une nouvelle connexion.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Une connexion externe peut nécessiter des identifiants sensibles — ne les partagez qu\'avec un administrateur.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Security.view_dashboard' => [
                'what_to_do'          => 'Surveillez les incidents de sécurité, les indicateurs de menace et le taux de conformité de l\'entreprise.',
                'how_to_do'           => [
                    'Traitez en priorité les incidents ouverts de sévérité critique ou élevée.',
                    'Vérifiez le taux d\'échec d\'authentification des dernières 24 heures.',
                    'Consultez le score de conformité pour repérer les contrôles non encore implémentés.',
                ],
                'decision_indicators' => [
                    ['label' => 'Incidents ouverts', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Score de conformité', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Un pic soudain d\'échecs d\'authentification peut signaler une tentative d\'intrusion — vérifiez l\'origine des tentatives.',
                ],
            ],
            'Helpdesk.view_dashboard' => [
                'what_to_do'          => 'Suivez le cycle de vie complet d\'un ticket, de son ouverture jusqu\'à sa clôture.',
                'how_to_do'           => [
                    'Vérifiez le statut actuel dans le fil (ouvert → en cours → résolu → clôturé).',
                    'Ajoutez une note interne si l\'information n\'est destinée qu\'aux agents.',
                    'Changez l\'assignation si le ticket concerne une autre équipe.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Router un nouveau ticket', 'action' => 'route_ticket', 'module' => 'Helpdesk'],
                ],
                'tips'                => [
                    'Un ticket lié à un SLA affiche son échéance — surveillez-la pour éviter un dépassement.',
                ],
            ],
            'Calendar.calendar_integrations' => [
                'what_to_do'          => 'Synchronisez votre calendrier WideHalo avec Google Calendar, Outlook ou Apple Calendar (iCloud).',
                'how_to_do'           => [
                    'Choisissez le fournisseur à connecter (Google, Outlook ou Apple).',
                    'Autorisez la connexion depuis la fenêtre d\'authentification du fournisseur.',
                    'Vérifiez la date de dernière synchronisation pour confirmer que la connexion est active.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'La synchronisation est bidirectionnelle — un événement supprimé côté fournisseur externe peut être supprimé ici aussi.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Calendar.team_calendar' => [
                'what_to_do'          => 'Visualisez les disponibilités et les événements de toute l\'équipe sur une même vue semaine/jour.',
                'how_to_do'           => [
                    'Sélectionnez la vue semaine ou jour selon le niveau de détail souhaité.',
                    'Filtrez sur un membre en particulier pour voir uniquement son planning.',
                    'Repérez les créneaux libres communs avant de proposer une réunion.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un événement', 'action' => 'create_event', 'module' => 'Calendar'],
                ],
                'tips'                => [],
            ],
            'Calendar.view_event' => [
                'what_to_do'          => 'Consultez le détail d\'un événement : date, participants et lien éventuel avec un autre module.',
                'how_to_do'           => [
                    'Vérifiez la date et l\'heure avant de confirmer votre présence.',
                    'Consultez la liste des participants pour savoir qui d\'autre est convié.',
                    'Supprimez l\'événement uniquement si vous en êtes l\'organisateur.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'La suppression d\'un événement est définitive et ne peut pas être annulée.',
                ],
                'next_actions'        => [
                    ['label' => 'Retour au calendrier', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapChantier32(): array
    {
        return [
            'Analytics.view_dashboard' => [
                'what_to_do'          => 'Review the AI forecasting hub: predictive models, detected anomalies and proactive alerts.',
                'how_to_do'           => [
                    'Check the Forecasts tab for active demand, cashflow and production forecast models.',
                    'Look at flagged anomalies (stockout risk, cashflow drift) first.',
                    'Open the cashflow forecast for a detailed horizon (30/60/90/180 days) and export it as PDF/Excel.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active alerts', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'View cashflow forecast', 'action' => 'view_dashboard', 'module' => 'Accounting'],
                ],
                'tips'                => [
                    'Forecasts are based on your real transaction history — the more history you have, the more reliable they are.',
                ],
            ],
            'Integration.view_dashboard' => [
                'what_to_do'          => 'Connect WideHalo to your external tools (mobile payment, open banking, federation partners).',
                'how_to_do'           => [
                    'Check the Active connectors tab to see what is already linked.',
                    'Browse the catalog to add a new connection (Orange Money, MTN MoMo, open banking…).',
                    'Verify each webhook\'s status after a new connection.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'An external connection may require sensitive credentials — only share them with an administrator.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Security.view_dashboard' => [
                'what_to_do'          => 'Monitor security incidents, threat indicators and the company\'s compliance rate.',
                'how_to_do'           => [
                    'Handle open critical/high-severity incidents first.',
                    'Check the authentication failure rate over the last 24 hours.',
                    'Review the compliance score to spot controls not yet implemented.',
                ],
                'decision_indicators' => [
                    ['label' => 'Open incidents', 'value' => '—', 'status' => 'warning'],
                    ['label' => 'Compliance score', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'A sudden spike in authentication failures can signal an intrusion attempt — check where the attempts come from.',
                ],
            ],
            'Helpdesk.view_dashboard' => [
                'what_to_do'          => 'Track a ticket\'s full lifecycle, from opening through to closure.',
                'how_to_do'           => [
                    'Check its current status in the timeline (open → in progress → resolved → closed).',
                    'Add an internal note if the information is for agents only.',
                    'Reassign the ticket if it belongs to a different team.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Route a new ticket', 'action' => 'route_ticket', 'module' => 'Helpdesk'],
                ],
                'tips'                => [
                    'A ticket linked to an SLA shows its due date — watch it to avoid a breach.',
                ],
            ],
            'Calendar.calendar_integrations' => [
                'what_to_do'          => 'Sync your WideHalo calendar with Google Calendar, Outlook, or Apple Calendar (iCloud).',
                'how_to_do'           => [
                    'Choose the provider to connect (Google, Outlook, or Apple).',
                    'Authorize the connection from the provider\'s sign-in window.',
                    'Check the last-synced date to confirm the connection is active.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Sync is two-way — an event deleted on the external provider side may be deleted here too.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Calendar.team_calendar' => [
                'what_to_do'          => 'See the whole team\'s availability and events in one week/day view.',
                'how_to_do'           => [
                    'Pick the week or day view depending on the level of detail needed.',
                    'Filter to one member to see only their schedule.',
                    'Spot common free slots before proposing a meeting.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create an event', 'action' => 'create_event', 'module' => 'Calendar'],
                ],
                'tips'                => [],
            ],
            'Calendar.view_event' => [
                'what_to_do'          => 'Review an event\'s detail: date, attendees, and any link to another module.',
                'how_to_do'           => [
                    'Check the date and time before confirming your attendance.',
                    'Review the attendee list to see who else is invited.',
                    'Only delete the event if you are its organizer.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Deleting an event is permanent and cannot be undone.',
                ],
                'next_actions'        => [
                    ['label' => 'Back to calendar', 'action' => 'view_calendar', 'module' => 'Calendar'],
                ],
                'tips'                => [],
            ],
        ];
    }

    /**
     * Chantier 32.7 (14-layer deep audit of Modules\Validation): 'Validation'
     * was never registered in supportedModules() at all, and none of the
     * module's 5 real Vue pages ever called useAiAssistant() — both fixed
     * here and in each page.
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapChantier327(): array
    {
        return [
            'Validation.view_approval_dashboard' => [
                'what_to_do'          => 'Consultez les demandes d\'approbation en attente et celles qui vous attendent en priorité.',
                'how_to_do'           => [
                    'Filtrez par statut ou par module pour retrouver une demande précise.',
                    'Traitez en priorité les demandes marquées "en attente de votre décision".',
                    'Ouvrez une demande pour voir son historique complet avant d\'approuver ou de rejeter.',
                ],
                'decision_indicators' => [
                    ['label' => 'En attente de votre décision', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Une demande à plusieurs niveaux avance d\'un niveau à la fois — approuver ne finalise pas toujours la demande.',
                ],
            ],
            'Validation.view_approval_request' => [
                'what_to_do'          => 'Examinez le détail d\'une demande d\'approbation avant de décider.',
                'how_to_do'           => [
                    'Vérifiez le workflow et le niveau d\'avancement actuel.',
                    'Consultez l\'historique des décisions déjà prises sur cette demande.',
                    'Approuvez, rejetez ou déléguez selon votre rôle — un commentaire justificatif est recommandé.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Déléguer transfère la décision à un autre utilisateur de la même société — cette action n\'est pas réversible sans une nouvelle délégation.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Validation.manage_workflows' => [
                'what_to_do'          => 'Gérez les workflows d\'approbation par module (Achats, Comptabilité, RH…).',
                'how_to_do'           => [
                    'Filtrez par module pour retrouver le workflow concerné.',
                    'Activez ou désactivez un workflow selon vos besoins actuels.',
                    'Utilisez un modèle de démarrage rapide pour créer un nouveau workflow standard.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Désactiver un workflow ne supprime pas les demandes déjà en cours — seules les nouvelles soumissions cessent d\'être routées.',
                ],
                'next_actions'        => [
                    ['label' => 'Créer un workflow', 'action' => 'build_workflow', 'module' => 'Validation'],
                ],
                'tips'                => [],
            ],
            'Validation.build_workflow' => [
                'what_to_do'          => 'Composez un workflow d\'approbation : nom, module concerné, puis une liste ordonnée de règles.',
                'how_to_do'           => [
                    'Ajoutez une règle par seuil ou condition (ex : montant, catégorie).',
                    'Choisissez le mode d\'approbation (séquentiel ou parallèle) et le nombre d\'approbateurs requis.',
                    'Associez une hiérarchie d\'approbateurs à la règle si vous en avez déjà configuré une.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Une règle sans hiérarchie associée sera routée vers la hiérarchie générique du module, pas vers un circuit dédié.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Les modèles de démarrage rapide pré-remplissent un point de départ — vous pouvez toujours ajuster chaque règle ensuite.',
                ],
            ],
            'Validation.manage_validation_rules' => [
                'what_to_do'          => 'Configurez le moteur générique de validation de données : champ, type de règle, paramètres.',
                'how_to_do'           => [
                    'Choisissez le champ concerné et le type de règle (obligatoire, email, regex, plage de valeurs…).',
                    'Renseignez les paramètres au format JSON pour les types qui en ont besoin.',
                    'Ajoutez un message d\'erreur personnalisé pour guider l\'utilisateur final.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Ces règles ne s\'appliquent que lorsqu\'un appelant les invoque explicitement — elles ne sont pas branchées automatiquement sur tous les formulaires de l\'application.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapChantier327(): array
    {
        return [
            'Validation.view_approval_dashboard' => [
                'what_to_do'          => 'Review pending approval requests and the ones waiting on your own decision.',
                'how_to_do'           => [
                    'Filter by status or module to find a specific request.',
                    'Handle requests marked "awaiting your decision" first.',
                    'Open a request to see its full history before approving or rejecting.',
                ],
                'decision_indicators' => [
                    ['label' => 'Awaiting your decision', 'value' => '—', 'status' => 'warning'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'A multi-level request advances one level at a time — approving doesn\'t always finalize the request.',
                ],
            ],
            'Validation.view_approval_request' => [
                'what_to_do'          => 'Review a single approval request\'s detail before deciding.',
                'how_to_do'           => [
                    'Check the workflow and the current progress level.',
                    'Review the history of decisions already made on this request.',
                    'Approve, reject, or delegate based on your role — a comment is recommended.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Delegating hands the decision to another user in the same company — this cannot be undone without a new delegation.',
                ],
                'next_actions'        => [],
                'tips'                => [],
            ],
            'Validation.manage_workflows' => [
                'what_to_do'          => 'Manage approval workflows per module (Achats, Accounting, HR…).',
                'how_to_do'           => [
                    'Filter by module to find the relevant workflow.',
                    'Activate or deactivate a workflow as needed.',
                    'Use a quick-start template to create a standard new workflow.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'Deactivating a workflow doesn\'t affect requests already in progress — only new submissions stop being routed.',
                ],
                'next_actions'        => [
                    ['label' => 'Create a workflow', 'action' => 'build_workflow', 'module' => 'Validation'],
                ],
                'tips'                => [],
            ],
            'Validation.build_workflow' => [
                'what_to_do'          => 'Compose an approval workflow: name, target module, then an ordered list of rules.',
                'how_to_do'           => [
                    'Add one rule per threshold or condition (e.g. amount, category).',
                    'Choose the approval mode (sequential or parallel) and how many approvers are required.',
                    'Attach an approver hierarchy to the rule if you have one already configured.',
                ],
                'decision_indicators' => [],
                'warnings'            => [
                    'A rule with no attached hierarchy will be routed to the module\'s generic hierarchy, not a dedicated chain.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Quick-start templates pre-fill a starting point — you can still tweak every rule afterwards.',
                ],
            ],
            'Validation.manage_validation_rules' => [
                'what_to_do'          => 'Configure the generic data-validation rule engine: field, rule type, parameters.',
                'how_to_do'           => [
                    'Pick the target field and rule type (required, email, regex, range, …).',
                    'Fill in the parameters as JSON for rule types that need them.',
                    'Add a custom error message to guide the end user.',
                ],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'These rules only apply when a caller explicitly invokes them — they are not automatically wired into every form in the app.',
                ],
            ],
        ];
    }

    /**
     * Chantier 32.16 (Sales deep 14-layer audit): guidance for
     * RecurringOrders/Index.vue and Objectives/Index.vue — the 2 remaining
     * real Sales pages that never called useAiAssistant() at all before
     * this fix (manage_deposit_balance was added inline next to
     * confirm_order above, since it directly supersedes that entry's
     * inaccurate "auto-invoicing" claim).
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapChantier3216(): array
    {
        return [
            'Sales.manage_recurring_orders' => [
                'what_to_do'          => 'Créez des modèles de commande récurrente pour vos clients réguliers — plus besoin de ressaisir la même commande à chaque cycle.',
                'how_to_do'           => [
                    'Créez un modèle : client, périodicité (hebdomadaire/mensuelle/trimestrielle) et lignes de produits.',
                    'Cliquez "Générer maintenant" pour créer une vraie commande immédiatement, indépendamment de l\'échéance.',
                    'Sinon, laissez le modèle générer automatiquement une commande à chaque échéance via la tâche planifiée quotidienne.',
                ],
                'decision_indicators' => [
                    ['label' => 'Modèles actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    '"Générer maintenant" crée toujours une nouvelle commande réelle, même si vous cliquez plusieurs fois de suite — évitez les doubles clics.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Désactivez un modèle plutôt que de le supprimer si vous voulez juste suspendre temporairement la génération automatique.',
                ],
            ],
            'Sales.manage_sales_objectives' => [
                'what_to_do'          => 'Proposez et validez des objectifs de chiffre d\'affaires par équipe, commercial, client ou catégorie de produits.',
                'how_to_do'           => [
                    'Choisissez le périmètre (toute l\'équipe, un commercial, un client, ou une catégorie) et la période cible.',
                    'L\'application calcule 3 propositions (conservateur/modéré/ambitieux) à partir de votre historique réel des 6 derniers mois — jamais inventées.',
                    'Ajustez si besoin le montant proposé, puis validez une seule proposition — les autres sont automatiquement rejetées.',
                ],
                'decision_indicators' => [
                    ['label' => 'Objectifs validés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'Un objectif déjà validé ne peut plus être modifié ni supprimé.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Sans historique de commandes confirmées sur la période de référence, les 3 propositions démarrent à 0 — ce n\'est pas une erreur.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapChantier3216(): array
    {
        return [
            'Sales.manage_recurring_orders' => [
                'what_to_do'          => 'Create recurring order templates for your repeat customers — no need to re-enter the same order every cycle.',
                'how_to_do'           => [
                    'Create a template: customer, recurrence (weekly/monthly/quarterly), and product lines.',
                    'Click "Generate now" to create a real order immediately, independently of the due date.',
                    'Otherwise, let the template generate an order automatically on each due date via the daily scheduled job.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active templates', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    '"Generate now" always creates a new real order, even on repeated clicks — avoid double-clicking.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Deactivate a template instead of deleting it if you only want to pause automatic generation temporarily.',
                ],
            ],
            'Sales.manage_sales_objectives' => [
                'what_to_do'          => 'Propose and validate revenue targets by team, sales rep, customer, or product category.',
                'how_to_do'           => [
                    'Choose the scope (whole team, a rep, a customer, or a category) and the target period.',
                    'The app computes 3 proposals (conservative/moderate/ambitious) from your real historical data over the last 6 months — never invented.',
                    'Adjust the proposed amount if needed, then validate a single proposal — the others are automatically rejected.',
                ],
                'decision_indicators' => [
                    ['label' => 'Validated objectives', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'A validated objective can no longer be edited or deleted.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'With no confirmed order history over the reference period, all 3 proposals start at 0 — that\'s not an error.',
                ],
            ],
        ];
    }

    /**
     * Chantier 32.15 (CRM deep 14-layer audit): guidance for the 6 real,
     * routed CRM screens that never called useAiAssistant() at all before
     * this fix — confirmed via grep that zero of the module's ~13 real Vue
     * pages were wired, the same "real pages, no AI guidance" pattern
     * already found and fixed for Strategy (Chantier 30), Validation
     * (Chantier 32.7), and Sales (Chantier 32.16). These 6 cover the
     * module's highest-traffic screens (contact list, lead pipeline,
     * opportunity Kanban, quotes/CPQ, territory management, forecast
     * dashboard) — EmailSequences/CallLogs/Scoring/Campaigns are a
     * documented residual gap (see CLAUDE.md's Chantier 32.15 entry).
     *
     * @return array<string, array<string, mixed>>
     */
    private function frenchMapChantier3215(): array
    {
        return [
            'CRM.view_contacts_list' => [
                'what_to_do'          => 'Parcourez, filtrez et gérez la liste de vos contacts CRM.',
                'how_to_do'           => [
                    'Utilisez la recherche/les filtres pour retrouver un contact par nom, email ou statut.',
                    'Cliquez sur un contact pour voir sa fiche complète et son historique.',
                    'Utilisez "Détecter les doublons" sur une fiche pour repérer des contacts similaires avant d\'en créer un nouveau.',
                ],
                'decision_indicators' => [
                    ['label' => 'Contacts actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer un contact', 'action' => 'create_contact', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Seuls les contacts de votre propre société sont visibles ici, même si vous avez un rôle administrateur.',
                ],
            ],
            'CRM.manage_leads' => [
                'what_to_do'          => 'Suivez vos prospects (leads) depuis leur création jusqu\'à leur conversion en opportunité.',
                'how_to_do'           => [
                    'Un lead peut être créé manuellement, ou automatiquement via un formulaire web public.',
                    'Qualifiez le lead (source, statut) puis convertissez-le en contact/opportunité une fois prêt.',
                    'Vérifiez que l\'email/téléphone du lead sont bien renseignés avant conversion.',
                ],
                'decision_indicators' => [
                    ['label' => 'Leads non qualifiés', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Créer une opportunité', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Un lead créé via un formulaire web public a déjà été rattaché à votre société automatiquement — pas besoin de le réassigner.',
                ],
            ],
            'CRM.manage_opportunities_kanban' => [
                'what_to_do'          => 'Faites glisser vos opportunités entre les étapes du pipeline pour suivre leur avancement.',
                'how_to_do'           => [
                    'Glissez-déposez une carte d\'une colonne à l\'autre pour changer son étape.',
                    'Chaque déplacement est validé côté serveur — une étape inconnue ou hors du pipeline sélectionné sera rejetée.',
                    'Cliquez sur une carte pour voir/modifier les détails complets de l\'opportunité.',
                ],
                'decision_indicators' => [
                    ['label' => 'Opportunités en cours', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Les libellés d\'étapes affichés viennent de la configuration réelle du pipeline choisi — ils peuvent varier d\'un pipeline à l\'autre.',
                ],
            ],
            'CRM.manage_quotes' => [
                'what_to_do'          => 'Créez et gérez des devis chiffrés (CPQ) pour vos opportunités commerciales.',
                'how_to_do'           => [
                    'Créez un devis depuis une opportunité, ajoutez des lignes de produits/services.',
                    'Générez le PDF du devis une fois les montants validés.',
                    'Dupliquez un devis existant pour créer rapidement une variante sans repartir de zéro.',
                ],
                'decision_indicators' => [
                    ['label' => 'Devis en attente', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Les montants du PDF sont affichés en Ariary (Ar) — la devise de référence de l\'application.',
                ],
            ],
            'CRM.manage_territories' => [
                'what_to_do'          => 'Organisez vos secteurs commerciaux (territoires) et suivez les quotas/objectifs par équipe.',
                'how_to_do'           => [
                    'Assignez des comptes/opportunités à un territoire selon votre logique de découpage (géographie, secteur, etc.).',
                    'Consultez les quotas d\'équipe et le taux d\'atteinte par territoire.',
                    'Utilisez "Rééquilibrer" pour obtenir une suggestion de redistribution entre territoires.',
                ],
                'decision_indicators' => [
                    ['label' => 'Territoires actifs', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'La couverture et les quotas d\'équipe sont calculés uniquement sur les données de votre propre société.',
                ],
            ],
            'CRM.view_sales_forecast' => [
                'what_to_do'          => 'Consultez la prévision de chiffre d\'affaires calculée à partir de votre pipeline d\'opportunités réel.',
                'how_to_do'           => [
                    'Le montant pondéré par étape (weighted forecast) est recalculé automatiquement selon les probabilités de chaque étape.',
                    'Filtrez par commercial pour voir la prévision individuelle plutôt que l\'ensemble de l\'équipe.',
                    'Utilisez l\'ajustement "what-if" pour simuler l\'impact d\'un facteur de croissance sans rien modifier réellement.',
                ],
                'decision_indicators' => [
                    ['label' => 'Prévision pondérée', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'L\'ajustement "what-if" est une simulation non persistée — rien n\'est enregistré tant que vous ne créez pas d\'objectif validé.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'La prévision par produit n\'est pas disponible — ce module ne modélise pas de ligne produit sur les opportunités.',
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function englishMapChantier3215(): array
    {
        return [
            'CRM.view_contacts_list' => [
                'what_to_do'          => 'Browse, filter, and manage your CRM contact list.',
                'how_to_do'           => [
                    'Use search/filters to find a contact by name, email, or status.',
                    'Click a contact to see their full profile and history.',
                    'Use "Detect duplicates" on a contact record to spot similar contacts before creating a new one.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active contacts', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create a contact', 'action' => 'create_contact', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'Only your own company\'s contacts are visible here, even with an admin role.',
                ],
            ],
            'CRM.manage_leads' => [
                'what_to_do'          => 'Track your leads from creation through conversion into an opportunity.',
                'how_to_do'           => [
                    'A lead can be created manually, or automatically via a public web form.',
                    'Qualify the lead (source, status) then convert it into a contact/opportunity once ready.',
                    'Confirm the lead\'s email/phone are set before converting it.',
                ],
                'decision_indicators' => [
                    ['label' => 'Unqualified leads', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Create an opportunity', 'action' => 'create_opportunity', 'module' => 'CRM'],
                ],
                'tips'                => [
                    'A lead created via a public web form is already tagged to your company automatically — no need to reassign it.',
                ],
            ],
            'CRM.manage_opportunities_kanban' => [
                'what_to_do'          => 'Drag your opportunities between pipeline stages to track progress.',
                'how_to_do'           => [
                    'Drag-and-drop a card from one column to another to change its stage.',
                    'Every move is validated server-side — an unknown stage or one outside the selected pipeline will be rejected.',
                    'Click a card to see/edit the opportunity\'s full details.',
                ],
                'decision_indicators' => [
                    ['label' => 'Open opportunities', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'The stage labels shown come from the selected pipeline\'s real configuration — they can differ between pipelines.',
                ],
            ],
            'CRM.manage_quotes' => [
                'what_to_do'          => 'Create and manage priced quotes (CPQ) for your sales opportunities.',
                'how_to_do'           => [
                    'Create a quote from an opportunity, add product/service lines.',
                    'Generate the quote PDF once amounts are confirmed.',
                    'Duplicate an existing quote to quickly create a variant without starting from scratch.',
                ],
                'decision_indicators' => [
                    ['label' => 'Pending quotes', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'PDF amounts are shown in Ariary (Ar) — this app\'s base currency.',
                ],
            ],
            'CRM.manage_territories' => [
                'what_to_do'          => 'Organize your sales territories and track team quotas/attainment.',
                'how_to_do'           => [
                    'Assign accounts/opportunities to a territory following your own segmentation logic (geography, sector, etc.).',
                    'Review team quotas and attainment rate per territory.',
                    'Use "Rebalance" to get a suggested redistribution across territories.',
                ],
                'decision_indicators' => [
                    ['label' => 'Active territories', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [
                    'Coverage and team quotas are computed only from your own company\'s data.',
                ],
            ],
            'CRM.view_sales_forecast' => [
                'what_to_do'          => 'View the revenue forecast computed from your real opportunity pipeline.',
                'how_to_do'           => [
                    'The stage-weighted forecast is automatically recalculated from each stage\'s probability.',
                    'Filter by sales rep to see an individual forecast instead of the whole team\'s.',
                    'Use the "what-if" adjustment to simulate a growth factor\'s impact without changing anything for real.',
                ],
                'decision_indicators' => [
                    ['label' => 'Weighted forecast', 'value' => '—', 'status' => 'ok'],
                ],
                'warnings'            => [
                    'The "what-if" adjustment is a non-persisted simulation — nothing is saved unless you create a validated objective.',
                ],
                'next_actions'        => [],
                'tips'                => [
                    'Per-product forecasting isn\'t available — this module has no product line dimension on opportunities.',
                ],
            ],
        ];
    }

    private function localeName(string $code): string
    {
        return match ($code) {
            'fr'    => 'French',
            'en'    => 'English',
            'ar'    => 'Arabic',
            'sw'    => 'Swahili',
            'mg'    => 'Malagasy',
            'ha'    => 'Hausa',
            'zh'    => 'Chinese (Simplified)',
            'hi'    => 'Hindi',
            'es'    => 'Spanish',
            'pt'    => 'Portuguese',
            default => 'French',
        };
    }
}
