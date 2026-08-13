<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Modules\Workflow\Exceptions\WorkflowDslParseException;

/**
 * WorkflowDslParser
 * -----------------
 * Parses a French-language DSL into internal workflow definition arrays.
 *
 * Supported syntax:
 *   SI <condition> [ET|OU <condition>]* ALORS <action> [ET <action>]* [SINON <action>]
 *   QUAND <trigger_name> [SI <condition>]* ALORS <action> [ET <action>]* [SINON <action>]
 *   CHAQUE_JOUR SI <condition> ALORS <action> [ET <action>]*
 *
 * Examples:
 *   SI montant > 500000 ALORS approuver_3_niveaux ET notifier_manager
 *   QUAND opportunite_gagnee SI montant > 0 ALORS creer_commande
 *   CHAQUE_JOUR SI contrat_expire_dans <= 30 ALORS alerter_rh
 */
class WorkflowDslParser
{
    // ─── Trigger name mapping (French → internal key) ─────────────────────────

    private const TRIGGER_MAP = [
        'opportunite_gagnee'     => 'crm.opportunity.won',
        'facture_recue'          => 'achats.invoice_received',
        'stock_critique'         => 'inventory.stock_below_reorder_point',
        'conge_approuve'         => 'hr.leave_approved',
        'contrat_expire_dans'    => 'hr.contract_expiry_check',
        'commande_creee'         => 'sales.order.created',
        'paiement_recu'          => 'accounting.payment_received',
        'client_cree'            => 'crm.contact.created',
        'devis_envoye'           => 'sales.quotation.sent',
        'livraison_recue'        => 'inventory.delivery_received',
        'tache_terminee'         => 'projects.task.completed',
        'employe_embauche'       => 'hr.employee.hired',
        'facture_approuvee'      => 'accounting.invoice.approved',
        'reappro_declenchee'     => 'inventory.reorder.triggered',
    ];

    // ─── Scheduled/implicit trigger keyword ───────────────────────────────────

    private const SCHEDULED_TRIGGER = 'hr.contract_expiry_check';

    // ─── Action name mapping (French → internal key + params) ─────────────────

    private const ACTION_MAP = [
        'approuver_3_niveaux'          => ['key' => 'approval.request_multi_level', 'params' => ['levels' => 3]],
        'approuver_2_niveaux'          => ['key' => 'approval.request_multi_level', 'params' => ['levels' => 2]],
        'approbation_1_niveau'         => ['key' => 'approval.request_single_level', 'params' => ['levels' => 1]],
        'notifier_manager'             => ['key' => 'notify.in_app', 'params' => ['role' => 'manager']],
        'notifier_rh'                  => ['key' => 'notify.in_app', 'params' => ['role' => 'hr_manager']],
        'notifier_directeur'           => ['key' => 'notify.in_app', 'params' => ['role' => 'director']],
        'creer_bon_commande'           => ['key' => 'achats.auto_create_po', 'params' => []],
        'creer_commande'               => ['key' => 'sales.create_order_from_opportunity', 'params' => []],
        'comptabiliser_direct'         => ['key' => 'accounting.book_purchase_invoice', 'params' => []],
        'ajuster_paie'                 => ['key' => 'payroll.adjust_for_leave', 'params' => []],
        'alerter_rh'                   => ['key' => 'hr.notify_contract_expiry', 'params' => []],
        'planifier_echeancier'         => ['key' => 'accounting.generate_payment_schedule', 'params' => ['installments' => 3]],
        'envoyer_email'                => ['key' => 'notify.send_email', 'params' => []],
        'envoyer_sms'                  => ['key' => 'notify.send_sms', 'params' => []],
        'creer_tache'                  => ['key' => 'projects.create_task', 'params' => []],
        'cloturer_opportunite'         => ['key' => 'crm.close_opportunity', 'params' => []],
        'mettre_a_jour_stock'          => ['key' => 'inventory.update_stock', 'params' => []],
        'generer_rapport'              => ['key' => 'reporting.generate_report', 'params' => []],
        'escalader_support'            => ['key' => 'helpdesk.escalate_ticket', 'params' => []],
        'bloquer_fournisseur'          => ['key' => 'achats.block_supplier', 'params' => []],
    ];

    // ─── Operator mapping (DSL symbol → internal key) ─────────────────────────

    private const OPERATOR_MAP = [
        '>='  => 'greater_than_or_equal',
        '<='  => 'less_than_or_equal',
        '!='  => 'not_equal',
        '>'   => 'greater_than',
        '<'   => 'less_than',
        '='   => 'equal',
    ];

    // ─── Field name normalisation (French → internal) ─────────────────────────

    private const FIELD_MAP = [
        'montant'              => 'amount',
        'total'                => 'total_amount',
        'stock'                => 'stock_level',
        'seuil_reappro'        => 'reorder_point',
        'reappro_auto'         => 'auto_reorder',
        'type_conge'           => 'leave_type',
        'contrat_expire_dans'  => 'days_until_expiry',
        'jours_retard'         => 'days_overdue',
        'quantite'             => 'quantity',
        'priorite'             => 'priority',
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Parse DSL text (possibly multi-line) into an array of workflow definitions.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WorkflowDslParseException
     */
    public function parse(string $dslText): array
    {
        $lines       = $this->splitLines($dslText);
        $definitions = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue; // blank or comment
            }

            $def = $this->parseLine($line, $lineNumber + 1);

            // Add convenience aliases for single-condition/single-action DSL
            if (count($def['conditions'] ?? []) === 1) {
                $def['condition'] = $def['conditions'][0];
            } elseif (!empty($def['conditions'])) {
                $def['condition'] = [
                    'logic'      => $def['condition_logic'] ?? 'AND',
                    'conditions' => $def['conditions'],
                ];
            } else {
                $def['condition'] = null;
            }

            if (count($def['actions'] ?? []) === 1) {
                $def['action'] = $def['actions'][0]['key'] ?? null;
            } elseif (!empty($def['actions'])) {
                $def['action'] = array_column($def['actions'], 'key');
            } else {
                $def['action'] = null;
            }

            $definitions[] = $def;
        }

        return $definitions;
    }

    /**
     * Validate DSL text and return a result with errors (never throws).
     *
     * @return array{valid: bool, errors: string[]}
     */
    /**
     * Evaluate a parsed condition node against a context.
     *
     * @param array<string,mixed>|null $condition
     */
    public function evaluate(?array $condition, array $context): bool
    {
        if ($condition === null) {
            return true;
        }

        // Compound condition (AND/OR)
        if (isset($condition['conditions'])) {
            $logic      = strtoupper($condition['logic'] ?? 'AND');
            $conditions = $condition['conditions'];
            if ($logic === 'OR') {
                foreach ($conditions as $sub) {
                    if ($this->evaluate($sub, $context)) {
                        return true;
                    }
                }
                return false;
            }
            // AND
            foreach ($conditions as $sub) {
                if (!$this->evaluate($sub, $context)) {
                    return false;
                }
            }
            return true;
        }

        // Simple condition
        $field    = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '=';
        $value    = $condition['value'] ?? null;

        if ($field === null) {
            return true;
        }

        $actual = $context[$field] ?? null;

        return match ($operator) {
            'greater_than'             => $actual !== null && $actual > $value,
            'greater_than_or_equal'    => $actual !== null && $actual >= $value,
            'less_than'                => $actual !== null && $actual < $value,
            'less_than_or_equal'       => $actual !== null && $actual <= $value,
            'equal', '='               => $actual == $value,
            'not_equal', '!='          => $actual != $value,
            default                    => $actual == $value,
        };
    }

    /**
     * Validate DSL text and return true if valid (simplified for test compatibility).
     */
    public function validateFull(string $dslText): array
    {
        $errors = [];
        $lines  = $this->splitLines($dslText);

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            try {
                $this->parseLine($line, $lineNumber + 1);
            } catch (WorkflowDslParseException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'valid'  => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Validate DSL and return a boolean (true = valid).
     * Used by tests and the old validate() contract.
     */
    public function validate(string $dslText): array
    {
        return $this->validateFull($dslText);
    }

    /**
     * Convert DSL text to its JSON string representation.
     *
     * @throws WorkflowDslParseException
     */
    public function toJson(string $dslText): string
    {
        $definitions = $this->parse($dslText);

        return json_encode(count($definitions) === 1 ? $definitions[0] : $definitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert an internal workflow definition array back to a DSL text line.
     */
    public function fromJson(array $definition): string
    {
        $parts = [];

        // ── Trigger ───────────────────────────────────────────────────────────
        $triggerKey   = $definition['trigger_key'] ?? '';
        $triggerLabel = $this->reverseTrigger($triggerKey);

        if ($triggerKey === self::SCHEDULED_TRIGGER) {
            $parts[] = 'CHAQUE_JOUR';
        } elseif ($triggerLabel !== null) {
            $parts[] = 'QUAND ' . $triggerLabel;
        }

        // ── Conditions ────────────────────────────────────────────────────────
        $conditions = $definition['conditions'] ?? [];
        if (! empty($conditions)) {
            $condLogic = strtoupper($definition['condition_logic'] ?? 'ET');
            $condParts = [];
            foreach ($conditions as $cond) {
                $condParts[] = $this->reverseCondition($cond);
            }
            $parts[] = 'SI ' . implode(" $condLogic ", $condParts);
        }

        // ── Actions ───────────────────────────────────────────────────────────
        $actions     = $definition['actions'] ?? [];
        $elseActions = $definition['else_actions'] ?? [];

        if (! empty($actions)) {
            $actionParts = array_map(fn ($a) => $this->reverseAction($a), $actions);
            $parts[]     = 'ALORS ' . implode(' ET ', $actionParts);
        }

        if (! empty($elseActions)) {
            $elseActionParts = array_map(fn ($a) => $this->reverseAction($a), $elseActions);
            $parts[]         = 'SINON ' . implode(' ET ', $elseActionParts);
        }

        return implode(' ', $parts);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Parse a single DSL line into a definition.
     *
     * @throws WorkflowDslParseException
     */
    private function parseLine(string $line, int $lineNumber): array
    {
        $tokens = $this->tokenise($line);

        if (empty($tokens)) {
            throw new WorkflowDslParseException('Ligne vide ou non reconnue.', $lineNumber, $line);
        }

        $definition = [
            'trigger_key'     => null,
            'conditions'      => [],
            'condition_logic' => 'ET',
            'actions'         => [],
            'else_actions'    => [],
        ];

        $pos = 0;

        // ── Trigger keyword (optional) ─────────────────────────────────────────
        if (in_array(strtoupper($tokens[0]), ['QUAND', 'CHAQUE_JOUR'], true)) {
            $keyword = strtoupper($tokens[$pos]);
            $pos++;

            if ($keyword === 'CHAQUE_JOUR') {
                $definition['trigger_key'] = self::SCHEDULED_TRIGGER;
            } else {
                // QUAND <trigger_name>
                if (! isset($tokens[$pos])) {
                    throw new WorkflowDslParseException("Nom de déclencheur manquant après QUAND.", $lineNumber, $line);
                }
                $triggerName = $tokens[$pos];
                $pos++;
                $definition['trigger_key'] = $this->mapTrigger($triggerName, $lineNumber, $line);
            }
        } else {
            // Default implicit trigger (SI-only rules trigger on any matching context)
            $definition['trigger_key'] = 'generic.condition_check';
        }

        // ── Condition block (SI) ───────────────────────────────────────────────
        if (isset($tokens[$pos]) && strtoupper($tokens[$pos]) === 'SI') {
            $pos++;
            // If next token is immediately ALORS, condition is empty — invalid syntax
            if (!isset($tokens[$pos]) || strtoupper($tokens[$pos]) === 'ALORS') {
                throw new WorkflowDslParseException("Condition manquante après SI.", $lineNumber, $line);
            }
            [$conditions, $logic, $pos] = $this->parseConditionBlock($tokens, $pos, $lineNumber, $line);
            $definition['conditions']      = $conditions;
            $definition['condition_logic'] = $logic;
        }

        // ── Action block (ALORS) ───────────────────────────────────────────────
        if (! isset($tokens[$pos]) || strtoupper($tokens[$pos]) !== 'ALORS') {
            throw new WorkflowDslParseException("Mot-clé ALORS manquant.", $lineNumber, $line);
        }
        $pos++;

        [$actions, $pos] = $this->parseActionBlock($tokens, $pos, $lineNumber, $line);
        $definition['actions'] = $actions;

        // ── Else block (SINON) ─────────────────────────────────────────────────
        if (isset($tokens[$pos]) && strtoupper($tokens[$pos]) === 'SINON') {
            $pos++;
            [$elseActions, $pos] = $this->parseActionBlock($tokens, $pos, $lineNumber, $line);
            $definition['else_actions'] = $elseActions;
        }

        return $definition;
    }

    /**
     * Parse one or more conditions (separated by ET/OU) after the SI keyword.
     *
     * @return array{0: array, 1: string, 2: int}
     *
     * @throws WorkflowDslParseException
     */
    private function parseConditionBlock(array $tokens, int $pos, int $lineNumber, string $line): array
    {
        $conditions = [];
        $logic      = 'ET'; // default

        // Collect tokens until ALORS or end
        $condTokens = [];
        while (isset($tokens[$pos]) && ! in_array(strtoupper($tokens[$pos]), ['ALORS', 'SINON'], true)) {
            $condTokens[] = $tokens[$pos];
            $pos++;
        }

        // Split on ET/OU logic keywords, preserving logic operator
        $rawConditions = [];
        $current       = [];
        foreach ($condTokens as $tok) {
            if (in_array(strtoupper($tok), ['ET', 'OU'], true)) {
                if (! empty($current)) {
                    $rawConditions[] = $current;
                    $current         = [];
                }
                $logic = strtoupper($tok); // last logic operator wins
            } else {
                $current[] = $tok;
            }
        }
        if (! empty($current)) {
            $rawConditions[] = $current;
        }

        foreach ($rawConditions as $condTokenGroup) {
            $condStr      = implode(' ', $condTokenGroup);
            $conditions[] = $this->parseCondition($condStr, $lineNumber, $line);
        }

        return [$conditions, $logic, $pos];
    }

    /**
     * Parse one or more actions (separated by ET) from the token stream.
     *
     * @return array{0: array, 1: int}
     *
     * @throws WorkflowDslParseException
     */
    private function parseActionBlock(array $tokens, int $pos, int $lineNumber, string $line): array
    {
        $actions     = [];
        $actionNames = [];
        $current     = [];

        while (isset($tokens[$pos]) && ! in_array(strtoupper($tokens[$pos]), ['SINON'], true)) {
            if (strtoupper($tokens[$pos]) === 'ET') {
                if (! empty($current)) {
                    $actionNames[] = implode('_', $current);
                    $current       = [];
                }
            } else {
                $current[] = $tokens[$pos];
            }
            $pos++;
        }
        if (! empty($current)) {
            $actionNames[] = implode('_', $current);
        }

        foreach ($actionNames as $name) {
            $actions[] = $this->parseAction($name, $lineNumber, $line);
        }

        return [$actions, $pos];
    }

    /**
     * Convert a condition string like "montant > 500000" to internal format.
     *
     * @throws WorkflowDslParseException
     */
    private function parseCondition(string $condStr, int $lineNumber, string $line): array
    {
        $condStr = trim($condStr);

        // Try each operator from longest to shortest
        foreach (self::OPERATOR_MAP as $symbol => $opKey) {
            if (str_contains($condStr, $symbol)) {
                [$rawField, $rawValue] = explode($symbol, $condStr, 2);
                $rawField              = trim($rawField);
                $rawValue              = trim($rawValue);

                $field = self::FIELD_MAP[$rawField] ?? $rawField;
                $value = $this->castValue($rawValue);

                return [
                    'field'    => $field,
                    'operator' => $opKey,
                    'value'    => $value,
                ];
            }
        }

        // Accept plain identifier as a truthy passthrough condition (for tests/simple DSL)
        if (preg_match('/^\w+$/', $condStr)) {
            return [
                'field'    => $condStr,
                'operator' => 'truthy',
                'value'    => true,
            ];
        }

        throw new WorkflowDslParseException("Condition non reconnue: \"$condStr\"", $lineNumber, $line);
    }

    /**
     * Map a French action name to its internal action definition.
     *
     * @throws WorkflowDslParseException
     */
    private function parseAction(string $actionStr, int $lineNumber, string $line): array
    {
        $actionStr = trim($actionStr);
        $lower     = strtolower($actionStr);

        if (isset(self::ACTION_MAP[$lower])) {
            return [
                'key'    => self::ACTION_MAP[$lower]['key'],
                'params' => self::ACTION_MAP[$lower]['params'],
            ];
        }

        throw new WorkflowDslParseException("Action non reconnue: \"$actionStr\"", $lineNumber, $line);
    }

    /**
     * Map a French trigger name to its internal key.
     *
     * @throws WorkflowDslParseException
     */
    private function mapTrigger(string $triggerName, int $lineNumber, string $line): string
    {
        $lower = strtolower($triggerName);
        if (isset(self::TRIGGER_MAP[$lower])) {
            return self::TRIGGER_MAP[$lower];
        }

        throw new WorkflowDslParseException("Déclencheur non reconnu: \"$triggerName\"", $lineNumber, $line);
    }

    /**
     * Reverse-lookup: internal trigger key → French name.
     */
    private function reverseTrigger(string $key): ?string
    {
        $flipped = array_flip(self::TRIGGER_MAP);

        return $flipped[$key] ?? null;
    }

    /**
     * Convert an internal condition back to DSL text.
     */
    private function reverseCondition(array $cond): string
    {
        $fieldMap    = array_flip(self::FIELD_MAP);
        $operatorMap = array_flip(self::OPERATOR_MAP);

        $field    = $fieldMap[$cond['field']] ?? $cond['field'];
        $operator = $operatorMap[$cond['operator']] ?? '=';
        $value    = is_string($cond['value']) ? "'{$cond['value']}'" : (string) $cond['value'];

        return "$field $operator $value";
    }

    /**
     * Convert an internal action back to DSL text.
     */
    private function reverseAction(array $action): string
    {
        foreach (self::ACTION_MAP as $frName => $def) {
            if ($def['key'] === $action['key']) {
                return $frName;
            }
        }

        return str_replace('.', '_', $action['key']);
    }

    /**
     * Tokenise a DSL line by splitting on whitespace but keeping quoted strings together.
     *
     * @return string[]
     */
    private function tokenise(string $line): array
    {
        $tokens = [];
        // Match quoted strings or non-whitespace sequences
        preg_match_all("/'[^']*'|\"[^\"]*\"|\S+/u", $line, $matches);

        return $matches[0] ?? [];
    }

    /**
     * Split DSL text into individual non-empty lines.
     *
     * @return string[]
     */
    private function splitLines(string $text): array
    {
        return explode("\n", str_replace("\r\n", "\n", $text));
    }

    /**
     * Cast a raw string value to its appropriate PHP type.
     */
    private function castValue(string $raw): mixed
    {
        // Quoted string
        if (preg_match("/^'(.+)'$/", $raw, $m) || preg_match('/^"(.+)"$/', $raw, $m)) {
            return $m[1];
        }
        // Boolean
        if (strtolower($raw) === 'vrai') {
            return true;
        }
        if (strtolower($raw) === 'faux') {
            return false;
        }
        // Numeric
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }
}
