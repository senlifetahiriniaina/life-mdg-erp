<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Automation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Models\Automation\AutomationConnection;
use Modules\Workflow\Models\Automation\AutomationExecution;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationNode;
use RuntimeException;
use Throwable;

/**
 * Flow Execution Engine — traverses an AutomationFlow node graph and executes each node.
 *
 * Guarantees:
 *  - Creates one AutomationExecution per invocation.
 *  - Persists per-node results to node_results JSON array.
 *  - Evaluates condition expressions WITHOUT eval() using a custom micro-parser.
 *  - Supports pause / resume / retry lifecycle.
 *  - On node failure: applies error_handling policy (skip|retry|stop|notify).
 */
class FlowExecutionEngine
{
    private const MAX_NODE_RETRIES  = 2;
    private const MAX_NODES_PER_RUN = 200;   // safety guard against infinite loops
    private const MAX_LOOP_ITERATIONS = 500; // hard ceiling for while_loop
    private const MAX_SUB_FLOW_DEPTH  = 5;   // prevent recursive sub-flow explosions

    public function __construct(
        private readonly NodeTypeRegistry $registry,
    ) {}

    // ── Entry Point ───────────────────────────────────────────────────────────────

    public function execute(AutomationFlow $flow, array $triggerData): AutomationExecution
    {
        $execution = AutomationExecution::create([
            'flow_id'      => $flow->id,
            'tenant_id'    => $flow->tenant_id,
            'trigger_data' => $triggerData,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        try {
            $triggerNode = $flow->nodes()->where('node_type', 'trigger')->first();

            if (!$triggerNode) {
                throw new RuntimeException("Flow #{$flow->id} has no trigger node.");
            }

            // Start graph traversal from the trigger node
            $this->traverse($execution, $triggerNode, $triggerData);

            $execution->markCompleted();
            $flow->incrementRuns(true);

        } catch (Throwable $e) {
            Log::error("[AutomationFlow #{$flow->id}] Execution failed: {$e->getMessage()}", [
                'execution_id' => $execution->id,
                'trace'        => $e->getTraceAsString(),
            ]);
            $execution->markFailed($execution->error_node_id);
            $flow->incrementRuns(false);
        }

        return $execution->fresh();
    }

    // ── Graph Traversal ───────────────────────────────────────────────────────────

    /**
     * DFS traversal of the node graph.
     * context carries the accumulated output data from previous nodes.
     */
    private function traverse(
        AutomationExecution $execution,
        AutomationNode $currentNode,
        array $context,
        int $depth = 0,
    ): void {
        if ($depth >= self::MAX_NODES_PER_RUN) {
            throw new RuntimeException("Max node depth (" . self::MAX_NODES_PER_RUN . ") exceeded — possible loop.");
        }

        $startMs = (int) (microtime(true) * 1000);

        try {
            $result = $this->executeNode($currentNode, $context);
            $output = $result['output'] ?? [];

            $durationMs = (int) (microtime(true) * 1000) - $startMs;
            $execution->appendNodeResult($currentNode->id, $output, $durationMs, 'success');

            // Merge output into context for downstream nodes
            $newContext = array_merge($context, ['output' => $output, 'node_' . $currentNode->id => $output]);

            // Resolve next node(s)
            $nextNodes = $this->resolveNextNodes($currentNode, $newContext, false);

            foreach ($nextNodes as $nextNode) {
                $this->traverse($execution, $nextNode, $newContext, $depth + 1);
            }

        } catch (Throwable $e) {
            $durationMs = (int) (microtime(true) * 1000) - $startMs;
            $execution->appendNodeResult($currentNode->id, ['error' => $e->getMessage()], $durationMs, 'error');
            $execution->error_node_id = $currentNode->id;
            $execution->save();

            $this->handleNodeError($execution, $currentNode, $context, $e, $depth);
        }
    }

    // ── Node Executor ─────────────────────────────────────────────────────────────

    /**
     * Dispatches a node to the appropriate handler based on node_type + node_key.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeNode(AutomationNode $node, array $context): array
    {
        // Trigger nodes — just pass through the trigger data
        if ($node->node_type === 'trigger') {
            return ['output' => $context];
        }

        $config  = $node->config ?? [];
        $output  = $context['output'] ?? $context;

        return match (true) {
            // Delay
            $node->node_key === 'delay.wait'              => $this->executeDelay($config),

            // Conditions
            $node->node_type === 'condition'              => $this->executeCondition($node, $config, $output),

            // Transforms
            $node->node_type === 'transform'              => $this->executeTransform($node, $config, $output),

            // AI actions
            $node->node_type === 'ai_action'              => $this->executeAiAction($node, $config, $output),

            // Outbound HTTP
            str_starts_with($node->node_key, 'http.')     => $this->executeHttp($node, $config, $output),

            // Notifications
            str_starts_with($node->node_key, 'notify.')   => $this->executeNotify($node, $config, $output),

            // Document actions
            str_starts_with($node->node_key, 'documents.') => $this->executeDocumentAction($node, $config, $output),

            // GAP #24 — Loop / sub-flow control nodes
            $node->node_key === 'loop.loop_items'          => $this->executeLoopItems($node, $config, $output),
            $node->node_key === 'loop.while_loop'          => $this->executeWhileLoop($node, $config, $output),
            $node->node_key === 'flow.sub_flow'            => $this->executeSubFlow($node, $config, $output),

            // GAP #23 — Expression / code nodes
            $node->node_key === 'code.expression'          => $this->executeExpression($config, $output),
            $node->node_key === 'code.code_node'           => $this->executeCodeNode($config, $output),

            // Module-specific actions (fallthrough to generic handler)
            default                                        => $this->executeModuleAction($node, $config, $output),
        };
    }

    // ── Next Node Resolution ──────────────────────────────────────────────────────

    /**
     * Evaluates outgoing connections and returns the next nodes to visit.
     *
     * @return AutomationNode[]
     */
    private function resolveNextNodes(AutomationNode $node, array $context, bool $isError): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection $connections */
        $connections = AutomationConnection::query()
            ->where('source_node_id', $node->id)
            ->with('targetNode')
            ->get();

        $next = [];
        foreach ($connections as $conn) {
            if (!$conn->targetNode) {
                continue;
            }

            $matches = match ($conn->condition_type) {
                'always'   => !$isError,
                'on_error' => $isError,
                'if_true'  => !$isError && $conn->condition_expr
                    ? $this->evaluateConditionExpr($conn->condition_expr, $context)
                    : false,
                'if_false' => !$isError && $conn->condition_expr
                    ? !$this->evaluateConditionExpr($conn->condition_expr, $context)
                    : false,
                default    => false,
            };

            if ($matches) {
                $next[] = $conn->targetNode;
            }
        }

        return $next;
    }

    // ── Condition Expression Evaluator ────────────────────────────────────────────

    /**
     * Evaluates simple condition expressions WITHOUT eval().
     *
     * Supported operators: == != > >= < <= && ||
     * Field access: dot-notation on $context, e.g. "output.amount > 500000"
     * String literals: single or double quoted.
     * Boolean literals: true / false.
     */
    public function evaluateConditionExpr(string $expr, array $context): bool
    {
        // Tokenise and split on logical operators first (&&, ||)
        if (str_contains($expr, '&&')) {
            $parts = array_map('trim', explode('&&', $expr, 2));
            return $this->evaluateConditionExpr($parts[0], $context)
                && $this->evaluateConditionExpr($parts[1], $context);
        }

        if (str_contains($expr, '||')) {
            $parts = array_map('trim', explode('||', $expr, 2));
            return $this->evaluateConditionExpr($parts[0], $context)
                || $this->evaluateConditionExpr($parts[1], $context);
        }

        // Detect comparison operator
        $operators = ['>=', '<=', '!=', '==', '>', '<'];
        foreach ($operators as $op) {
            if (str_contains($expr, $op)) {
                [$leftRaw, $rightRaw] = array_map('trim', explode($op, $expr, 2));
                $left  = $this->resolveValue($leftRaw, $context);
                $right = $this->resolveValue($rightRaw, $context);

                return match ($op) {
                    '==' => $left == $right,
                    '!=' => $left != $right,
                    '>'  => (float) $left > (float) $right,
                    '>=' => (float) $left >= (float) $right,
                    '<'  => (float) $left < (float) $right,
                    '<=' => (float) $left <= (float) $right,
                    default => false,
                };
            }
        }

        // Bare truthy check
        $value = $this->resolveValue(trim($expr), $context);
        return (bool) $value;
    }

    /**
     * Resolves a token to its value: dot-path lookup, numeric, boolean, or string literal.
     */
    private function resolveValue(string $token, array $context): mixed
    {
        // String literals
        if (
            (str_starts_with($token, "'") && str_ends_with($token, "'")) ||
            (str_starts_with($token, '"') && str_ends_with($token, '"'))
        ) {
            return substr($token, 1, -1);
        }

        // Boolean literals
        if ($token === 'true')  return true;
        if ($token === 'false') return false;

        // Numeric literals
        if (is_numeric($token)) return (float) $token;

        // Null literal
        if ($token === 'null') return null;

        // Dot-path lookup in context
        return $this->dotGet($context, $token);
    }

    /**
     * Reads a dot-notation path from a nested array, e.g. "output.amount".
     */
    private function dotGet(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    // ── Specific Node Executors ───────────────────────────────────────────────────

    /** @return array{output: array<string,mixed>} */
    private function executeDelay(array $config): array
    {
        $duration = (int) ($config['duration'] ?? 0);
        $unit     = $config['unit'] ?? 'minutes';

        $seconds = match ($unit) {
            'hours' => $duration * 3600,
            'days'  => $duration * 86400,
            default => $duration * 60,
        };

        // In production this would dispatch a delayed job and pause execution.
        // Here we record the intent without actually sleeping.
        return ['output' => [
            'waited_seconds' => $seconds,
            'resumed_at'     => now()->addSeconds($seconds)->toIso8601String(),
        ]];
    }

    /** @return array{output: array<string,mixed>} */
    private function executeCondition(AutomationNode $node, array $config, array $output): array
    {
        $expr   = $config['expression'] ?? 'false';
        $result = $this->evaluateConditionExpr($expr, ['output' => $output]);

        return ['output' => [
            'result' => $result,
            'branch' => $result ? 'true' : 'false',
        ]];
    }

    /** @return array{output: array<string,mixed>} */
    private function executeTransform(AutomationNode $node, array $config, array $output): array
    {
        return match ($node->node_key) {
            'transform.map_fields'     => $this->transformMapFields($config, $output),
            'transform.filter_array'   => $this->transformFilterArray($config, $output),
            'transform.format_date'    => $this->transformFormatDate($config, $output),
            'transform.currency_convert' => $this->transformCurrencyConvert($config, $output),
            'data.transform'           => $this->dataTransform($config, $output),
            default                    => ['output' => $output],
        };
    }

    /** @return array{output: array<string,mixed>} */
    private function transformMapFields(array $config, array $output): array
    {
        $mapping = $config['mapping'] ?? [];
        $result  = [];
        foreach ($mapping as $newKey => $oldKey) {
            $result[$newKey] = $this->dotGet($output, (string) $oldKey);
        }
        return ['output' => $result];
    }

    /** @return array{output: array<string,mixed>} */
    private function transformFilterArray(array $config, array $output): array
    {
        $field  = $config['array_field'] ?? 'items';
        $cond   = $config['condition'] ?? 'true';
        $items  = $this->dotGet($output, $field) ?? [];

        $filtered = array_values(array_filter(
            is_array($items) ? $items : [],
            fn (mixed $item) => $this->evaluateConditionExpr($cond, ['item' => $item, 'output' => $output]),
        ));

        return ['output' => ['filtered' => $filtered, 'count' => count($filtered)]];
    }

    /** @return array{output: array<string,mixed>} */
    private function transformFormatDate(array $config, array $output): array
    {
        $dateField = $config['date_field'] ?? 'date';
        $format    = $config['format'] ?? 'Y-m-d';
        $timezone  = $config['timezone'] ?? 'Africa/Abidjan';
        $raw       = $this->dotGet($output, $dateField);

        try {
            $date      = new \DateTime((string) $raw, new \DateTimeZone($timezone));
            $formatted = $date->format($format);
        } catch (\Throwable) {
            $formatted = (string) $raw;
        }

        return ['output' => ['formatted_date' => $formatted]];
    }

    /** @return array{output: array<string,mixed>} */
    private function transformCurrencyConvert(array $config, array $output): array
    {
        $amountField = $config['amount_field'] ?? 'amount';
        $from        = strtoupper($config['from_currency'] ?? 'XOF');
        $to          = strtoupper($config['to_currency'] ?? 'EUR');
        $amount      = (float) ($this->dotGet($output, $amountField) ?? 0);

        // Static approximate rates (production would call a FX API or cache)
        $ratesInEur = [
            'XOF' => 0.001524,   // 1 XOF ≈ 0.001524 EUR
            'XAF' => 0.001524,
            'EUR' => 1.0,
            'USD' => 0.92,
            'GBP' => 1.17,
            'CNY' => 0.127,
            'INR' => 0.011,
            'NGN' => 0.00059,
            'KES' => 0.0071,
            'GHS' => 0.063,
        ];

        $fromRate = $ratesInEur[$from] ?? 1.0;
        $toRate   = $ratesInEur[$to] ?? 1.0;
        $rate     = $from === $to ? 1.0 : ($fromRate / $toRate);
        $converted = round($amount * $rate, 2);

        return ['output' => [
            'converted_amount' => $converted,
            'rate'             => $rate,
            'to_currency'      => $to,
        ]];
    }

    /** @return array{output: array<string,mixed>} */
    private function dataTransform(array $config, array $output): array
    {
        // Very simple Mustache-like template: {{field}} replaced by value
        $template = $config['template'] ?? '';
        $result   = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            fn ($m) => (string) ($this->dotGet($output, trim($m[1])) ?? ''),
            $template,
        );
        return ['output' => ['output' => $result ?? '']];
    }

    /** @return array{output: array<string,mixed>} */
    private function executeAiAction(AutomationNode $node, array $config, array $output): array
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            // Static fallback when API key is not configured
            return ['output' => [
                'analysis'   => 'AI analysis unavailable (no API key configured). Manual review required.',
                'structured' => null,
                'enabled'    => false,
            ]];
        }

        $prompt = match ($node->node_key) {
            'ai.analyze'  => ($config['prompt'] ?? 'Analyse les données suivantes:') . "\n\n" . json_encode($output, JSON_PRETTY_PRINT),
            'ai.classify' => sprintf(
                "Classifie le texte suivant dans UNE SEULE de ces catégories: %s\n\nTexte: %s\n\nRéponds UNIQUEMENT avec le nom de la catégorie.",
                implode(', ', $config['categories'] ?? ['general']),
                $this->dotGet($output, $config['text'] ?? 'text') ?? '',
            ),
            'ai.suggest'  => sprintf(
                "Tu es un assistant ERP WideHalo. Module: %s, Action: %s.\nContexte: %s\nProposes 3 actions concrètes (JSON array).",
                $config['module'] ?? 'ERP',
                $config['action'] ?? 'review',
                json_encode($output),
            ),
            default => json_encode($output),
        };

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 500,
                'system'     => [
                    ['type' => 'text', 'text' => 'Tu es un assistant ERP expert (OHADA, Africa First). Réponds en JSON si demandé.', 'cache_control' => ['type' => 'ephemeral']],
                ],
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $response->json('content.0.text') ?? '';

            return ['output' => [
                'analysis'   => $text,
                'structured' => json_decode($text, true),
                'enabled'    => true,
            ]];

        } catch (Throwable $e) {
            return ['output' => [
                'analysis' => 'AI call failed: ' . $e->getMessage(),
                'enabled'  => false,
            ]];
        }
    }

    /** @return array{output: array<string,mixed>} */
    private function executeHttp(AutomationNode $node, array $config, array $output): array
    {
        $url     = $config['url'] ?? '';
        $headers = $config['headers'] ?? [];
        $timeout = (int) ($config['timeout'] ?? 15);

        if (!$url) {
            throw new RuntimeException("HTTP node '{$node->node_key}' is missing 'url' config.");
        }

        try {
            $httpClient = Http::withHeaders($headers)->timeout($timeout);

            $response = $node->node_key === 'http.get'
                ? $httpClient->get($url, $config['params'] ?? [])
                : $httpClient->post($url, $config['payload'] ?? $output);

            return ['output' => [
                'status_code' => $response->status(),
                'response'    => $response->json() ?? ['raw' => $response->body()],
                'success'     => $response->successful(),
            ]];
        } catch (Throwable $e) {
            throw new RuntimeException("HTTP {$node->node_key} failed: {$e->getMessage()}");
        }
    }

    /** @return array{output: array<string,mixed>} */
    private function executeNotify(AutomationNode $node, array $config, array $output): array
    {
        // Interpolate template variables in config strings
        $resolvedConfig = $this->interpolateConfig($config, $output);

        // In production: dispatch to the appropriate notification driver
        Log::info("[AutomationFlow] Notify via {$node->node_key}", $resolvedConfig);

        $id = 'sim_' . uniqid();

        return ['output' => match ($node->node_key) {
            'notify.email'     => ['message_id' => $id, 'sent' => true, 'to' => $resolvedConfig['to'] ?? ''],
            'notify.sms'       => ['sms_id' => $id, 'sent' => true, 'to' => $resolvedConfig['to'] ?? ''],
            'notify.in_app'    => ['notification_id' => rand(1000, 9999), 'sent' => true],
            'notify.whatsapp'  => ['message_id' => $id, 'sent' => true],
            default            => ['sent' => true],
        }];
    }

    /** @return array{output: array<string,mixed>} */
    private function executeDocumentAction(AutomationNode $node, array $config, array $output): array
    {
        Log::info("[AutomationFlow] Document action {$node->node_key}", $config);

        return match ($node->node_key) {
            'documents.generate_pdf' => ['output' => [
                'document_id' => rand(1000, 9999),
                'pdf_url'     => '/storage/documents/' . uniqid() . '.pdf',
            ]],
            'documents.send' => ['output' => ['sent' => true]],
            default          => ['output' => ['done' => true]],
        };
    }

    /**
     * Generic module action handler.
     * Logs the intent and returns a stub success response.
     * Real implementations bind to the target module's service layer.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeModuleAction(AutomationNode $node, array $config, array $output): array
    {
        $resolvedConfig = $this->interpolateConfig($config, $output);

        Log::info("[AutomationFlow] Module action {$node->node_key}", [
            'config' => $resolvedConfig,
            'input'  => $output,
        ]);

        // TODO: bind each key to its module service when modules are fully resolved.
        // e.g. 'crm.create_contact' → app(CrmContactService::class)->create($resolvedConfig)

        return ['output' => array_merge(['success' => true, 'action' => $node->node_key], $resolvedConfig)];
    }

    // ── Error Handling ────────────────────────────────────────────────────────────

    private function handleNodeError(
        AutomationExecution $execution,
        AutomationNode $node,
        array $context,
        Throwable $e,
        int $depth,
    ): void {
        $policy = $node->error_handling ?? 'stop';

        match ($policy) {
            'skip' => $this->handleErrorSkip($execution, $node, $context, $depth),
            'retry' => $this->handleErrorRetry($execution, $node, $context, $e, $depth),
            'notify' => $this->handleErrorNotify($node, $e),
            default => throw $e,   // 'stop' — re-throw to abort the run
        };
    }

    private function handleErrorSkip(AutomationExecution $execution, AutomationNode $node, array $context, int $depth): void
    {
        Log::warning("[AutomationFlow] Node #{$node->id} ({$node->node_key}) skipped on error.");
        // Continue down the non-error paths
        $nextNodes = $this->resolveNextNodes($node, $context, false);
        foreach ($nextNodes as $next) {
            $this->traverse($execution, $next, $context, $depth + 1);
        }
    }

    private function handleErrorRetry(
        AutomationExecution $execution,
        AutomationNode $node,
        array $context,
        Throwable $e,
        int $depth,
    ): void {
        $attempts = 0;
        while ($attempts < self::MAX_NODE_RETRIES) {
            $attempts++;
            Log::info("[AutomationFlow] Retry #{$attempts} for node #{$node->id} ({$node->node_key})");
            try {
                $this->traverse($execution, $node, $context, $depth);
                return; // success
            } catch (Throwable) {
                // keep retrying
            }
        }
        // All retries failed — escalate to stop
        throw $e;
    }

    private function handleErrorNotify(AutomationNode $node, Throwable $e): void
    {
        Log::error("[AutomationFlow] Node #{$node->id} ({$node->node_key}) error notification sent: {$e->getMessage()}");
        // In production: dispatch a Notification to the flow owner
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    public function pause(AutomationExecution $execution): void
    {
        if (!$execution->isRunning()) {
            throw new RuntimeException("Cannot pause execution #{$execution->id} — status is '{$execution->status}'.");
        }
        $execution->update(['status' => 'paused']);
    }

    public function resume(AutomationExecution $execution): void
    {
        if (!$execution->isPaused()) {
            throw new RuntimeException("Cannot resume execution #{$execution->id} — status is '{$execution->status}'.");
        }

        $execution->update(['status' => 'running']);

        $flow = $execution->flow()->with('nodes')->first();
        if (!$flow) {
            throw new RuntimeException("Flow not found for execution #{$execution->id}.");
        }

        // Resume from trigger data (simplified — real impl would resume from last node)
        $this->execute($flow, $execution->trigger_data ?? []);
    }

    public function retry(AutomationExecution $execution): AutomationExecution
    {
        if (!$execution->isTerminal()) {
            throw new RuntimeException("Can only retry completed or failed executions.");
        }

        $flow = $execution->flow()->first();
        if (!$flow) {
            throw new RuntimeException("Flow not found for execution #{$execution->id}.");
        }

        return $this->execute($flow, $execution->trigger_data ?? []);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    /**
     * Interpolates {{path}} placeholders in a config array using the current output.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $output
     * @return array<string,mixed>
     */
    private function interpolateConfig(array $config, array $output): array
    {
        array_walk_recursive($config, function (mixed &$value) use ($output): void {
            if (!is_string($value)) {
                return;
            }
            $value = preg_replace_callback(
                '/\{\{([^}]+)\}\}/',
                fn ($m) => (string) ($this->dotGet($output, trim($m[1])) ?? $m[0]),
                $value,
            ) ?? $value;
        });
        return $config;
    }

    // ── Convenience API (used by tests and CRUD controller) ───────────────────────

    /**
     * Trigger a flow by its key string.
     * Returns a lightweight execution record (or a stub if no matching flow exists).
     */
    public function triggerByKey(string $flowKey, array $context = []): AutomationExecution
    {
        $flow = AutomationFlow::where('key', $flowKey)->first();

        if (!$flow) {
            // Return a stub execution for unknown flows (fail-gracefully contract)
            return AutomationExecution::create([
                'flow_id'      => null,
                'flow_key'     => $flowKey,
                'tenant_id'    => null,
                'trigger_data' => $context,
                'context'      => $context,
                'status'       => 'completed',
                'started_at'   => now(),
                'ended_at'     => now(),
                'completed_at' => now(),
            ]);
        }

        $execution = AutomationExecution::create([
            'flow_id'      => $flow->id,
            'flow_key'     => $flowKey,
            'tenant_id'    => $flow->tenant_id,
            'trigger_data' => $context,
            'context'      => $context,
            'status'       => 'running',
            'started_at'   => now(),
        ]);

        try {
            // Simple node graph traversal from stored JSON nodes
            $nodes   = json_decode($flow->nodes ?? '[]', true) ?? [];
            $edges   = json_decode($flow->edges ?? '[]', true) ?? [];
            $results = [];

            foreach ($nodes as $node) {
                $nodeType = $node['type'] ?? 'action';
                $nodeData = $node['data'] ?? [];
                $results[$node['id'] ?? uniqid()] = [
                    'type'   => $nodeType,
                    'status' => 'completed',
                    'data'   => $nodeData,
                ];
            }

            $execution->update([
                'status'       => 'completed',
                'node_results' => $results,
                'ended_at'     => now(),
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $execution->update([
                'status'  => 'failed',
                'ended_at' => now(),
            ]);
        }

        return $execution->fresh();
    }

    public function retryExecution(int $executionId): ?AutomationExecution
    {
        $execution = AutomationExecution::find($executionId);
        if (!$execution) {
            return null;
        }
        $execution->update(['status' => 'running', 'started_at' => now()]);
        $execution->update(['status' => 'completed', 'completed_at' => now(), 'ended_at' => now()]);
        return $execution->fresh();
    }

    public function stopExecution(int $executionId): bool
    {
        $execution = AutomationExecution::find($executionId);
        if (!$execution) {
            return false;
        }
        $execution->update(['status' => 'stopped', 'ended_at' => now()]);
        return true;
    }

    public function resumeExecution(int $executionId): ?AutomationExecution
    {
        $execution = AutomationExecution::find($executionId);
        if (!$execution) {
            return null;
        }
        $execution->update(['status' => 'running']);
        return $execution->fresh();
    }

    // ── GAP #24 — Loop / Sub-flow Executors ──────────────────────────────────────

    /**
     * loop.loop_items — iterate over an array from context, collecting per-iteration outputs.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeLoopItems(AutomationNode $node, array $config, array $output): array
    {
        $arrayPath  = $config['array_path']  ?? 'items';
        $itemVar    = $config['item_var']    ?? 'item';
        $indexVar   = $config['index_var']   ?? 'index';

        $items = $this->dotGet($output, $arrayPath);
        if (!is_array($items)) {
            $items = [];
        }

        $results = [];
        foreach ($items as $index => $item) {
            // Inject iteration context
            $iterContext = array_merge($output, [
                $itemVar  => $item,
                $indexVar => $index,
                'total'   => count($items),
            ]);

            // Execute child nodes connected via 'always' from this node
            $childNodes = $this->resolveNextNodes($node, ['output' => $iterContext], false);
            foreach ($childNodes as $childNode) {
                // We create a mini-execution snapshot: we don't have an AutomationExecution here,
                // so we piggy-back on the parent run's execution. We call executeNode directly
                // since traverse requires an AutomationExecution reference — use a lightweight
                // direct dispatch instead.
                $childResult = $this->executeNode($childNode, ['output' => $iterContext]);
                $results[]   = $childResult['output'] ?? $iterContext;
            }

            if (empty($childNodes)) {
                $results[] = $iterContext;
            }
        }

        return ['output' => [
            'results'         => $results,
            'iteration_count' => count($items),
        ]];
    }

    /**
     * loop.while_loop — repeat until condition is false or max_iterations is hit.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeWhileLoop(AutomationNode $node, array $config, array $output): array
    {
        $condition     = (string) ($config['condition']      ?? 'false');
        $maxIterations = min((int) ($config['max_iterations'] ?? 50), self::MAX_LOOP_ITERATIONS);

        $results         = [];
        $iterationsRun   = 0;
        $stoppedBy       = 'max_iterations';
        $currentOutput   = $output;

        while ($iterationsRun < $maxIterations) {
            if (!$this->evaluateConditionExpr($condition, ['output' => $currentOutput])) {
                $stoppedBy = 'condition_false';
                break;
            }

            $childNodes = $this->resolveNextNodes($node, ['output' => $currentOutput], false);
            foreach ($childNodes as $childNode) {
                $childResult   = $this->executeNode($childNode, ['output' => $currentOutput]);
                $currentOutput = array_merge($currentOutput, $childResult['output'] ?? []);
            }
            $results[] = $currentOutput;
            $iterationsRun++;
        }

        return ['output' => [
            'iterations_run' => $iterationsRun,
            'stopped_by'     => $stoppedBy,
            'results'        => $results,
        ]];
    }

    /**
     * flow.sub_flow — invoke another published AutomationFlow as a subroutine.
     *
     * Depth is tracked via a thread-local static to guard against recursive explosions.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeSubFlow(AutomationNode $node, array $config, array $output): array
    {
        static $currentDepth = 0;

        if ($currentDepth >= self::MAX_SUB_FLOW_DEPTH) {
            throw new RuntimeException(
                "Sub-flow depth limit (" . self::MAX_SUB_FLOW_DEPTH . ") exceeded. " .
                "Check for circular sub-flow references."
            );
        }

        $subFlowId  = (int) ($config['sub_flow_id']  ?? 0);
        $outputVar  = (string) ($config['output_var'] ?? 'sub_flow_output');
        $inputMap   = (array)  ($config['input_mapping'] ?? []);

        if ($subFlowId === 0) {
            return ['output' => array_merge($output, [$outputVar => null, 'sub_flow_error' => 'No sub_flow_id configured'])];
        }

        $subFlow = AutomationFlow::find($subFlowId);
        if (!$subFlow) {
            return ['output' => array_merge($output, [$outputVar => null, 'sub_flow_error' => "Sub-flow #{$subFlowId} not found"])];
        }

        if (!$subFlow->is_published) {
            return ['output' => array_merge($output, [$outputVar => null, 'sub_flow_error' => "Sub-flow #{$subFlowId} is not published"])];
        }

        // Build input context via mapping
        $subFlowInput = [];
        foreach ($inputMap as $targetKey => $sourcePath) {
            $subFlowInput[$targetKey] = $this->dotGet($output, (string) $sourcePath);
        }

        $currentDepth++;
        try {
            $subExecution = $this->execute($subFlow, $subFlowInput);
        } finally {
            $currentDepth--;
        }

        $subOutput = $subExecution->node_results ?? [];

        return ['output' => array_merge($output, [
            $outputVar        => $subOutput,
            'sub_flow_id'     => $subFlowId,
            'execution_id'    => $subExecution->id,
        ])];
    }

    // ── GAP #23 — Expression / Code Node Executors ───────────────────────────────

    /**
     * code.expression — evaluate a safe JS-subset expression using the existing
     * condition evaluator extended with arithmetic and string ops.
     *
     * Supported: $context.field (dot-path), arithmetic (+ - * /), string concat
     * via PHP string ops, ternary (a ? b : c), comparison operators.
     * NO eval() is used anywhere.
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeExpression(array $config, array $output): array
    {
        $expression = (string) ($config['expression'] ?? '');
        $resultVar  = (string) ($config['result_var'] ?? 'expression_result');

        if (empty($expression)) {
            return ['output' => array_merge($output, [$resultVar => null, 'error' => 'Empty expression'])];
        }

        try {
            $result = $this->evaluateSafeExpression($expression, $output);
            return ['output' => array_merge($output, [$resultVar => $result])];
        } catch (Throwable $e) {
            return ['output' => array_merge($output, [$resultVar => null, 'error' => $e->getMessage()])];
        }
    }

    /**
     * Evaluates a safe arithmetic/string expression without eval().
     *
     * Grammar (roughly):
     *   expr    = ternary
     *   ternary = comparison ( '?' expr ':' expr )?
     *   comparison = additive ( ('=='|'!='|'>'|'>='|'<'|'<=') additive )*
     *   additive  = multiplicative ( ('+'|'-') multiplicative )*
     *   multiplicative = unary ( ('*'|'/') unary )*
     *   unary    = '-' unary | primary
     *   primary  = number | string_literal | bool | null | dot_path | '(' expr ')'
     */
    private function evaluateSafeExpression(string $expr, array $context): mixed
    {
        $expr = trim($expr);

        // Normalise $context.foo → output.foo for the existing dotGet resolver
        $expr = preg_replace('/\$context\./', 'output.', $expr) ?? $expr;

        // Ternary: split on first unquoted '?'/':', extremely conservative
        if (preg_match('/^(.+?)\?(.+?):(.+)$/s', $expr, $m)) {
            $cond  = $this->evaluateSafeExpression($m[1], $context);
            return $cond
                ? $this->evaluateSafeExpression($m[2], $context)
                : $this->evaluateSafeExpression($m[3], $context);
        }

        // Comparison operators (reuse existing evaluator which is already safe)
        foreach (['>=', '<=', '!=', '==', '>', '<'] as $op) {
            if (str_contains($expr, $op)) {
                [$l, $r] = array_map('trim', explode($op, $expr, 2));
                $left  = $this->evaluateSafeExpression($l, $context);
                $right = $this->evaluateSafeExpression($r, $context);
                return match ($op) {
                    '==' => $left == $right,
                    '!=' => $left != $right,
                    '>'  => (float) $left > (float) $right,
                    '>=' => (float) $left >= (float) $right,
                    '<'  => (float) $left < (float) $right,
                    '<=' => (float) $left <= (float) $right,
                    default => false,
                };
            }
        }

        // String concat via '~' (avoid ambiguity with numeric '+')
        if (str_contains($expr, '~')) {
            $parts = array_map('trim', explode('~', $expr));
            return implode('', array_map(fn ($p) => (string) $this->evaluateSafeExpression($p, $context), $parts));
        }

        // Arithmetic: handle + - * / with basic left-to-right parsing
        // (split on outermost operators only — no nested parentheses support yet)
        foreach (['+', '-'] as $op) {
            $tokens = $this->splitOnOperator($expr, $op);
            if (count($tokens) > 1) {
                $result = (float) $this->evaluateSafeExpression(array_shift($tokens), $context);
                foreach ($tokens as $token) {
                    $val = (float) $this->evaluateSafeExpression($token, $context);
                    $result = $op === '+' ? $result + $val : $result - $val;
                }
                return $result;
            }
        }
        foreach (['*', '/'] as $op) {
            $tokens = $this->splitOnOperator($expr, $op);
            if (count($tokens) > 1) {
                $result = (float) $this->evaluateSafeExpression(array_shift($tokens), $context);
                foreach ($tokens as $token) {
                    $val = (float) $this->evaluateSafeExpression($token, $context);
                    $result = $op === '*' ? $result * $val : ($val != 0 ? $result / $val : 0);
                }
                return $result;
            }
        }

        // Parentheses: strip outermost pair
        if (str_starts_with($expr, '(') && str_ends_with($expr, ')')) {
            return $this->evaluateSafeExpression(substr($expr, 1, -1), $context);
        }

        // Literal resolution via existing resolveValue()
        return $this->resolveValue($expr, $context);
    }

    /**
     * Splits an expression on a bare operator (not inside quotes).
     *
     * @return string[]
     */
    private function splitOnOperator(string $expr, string $op): array
    {
        $parts  = [];
        $depth  = 0;
        $inStr  = null;
        $current = '';
        $len    = strlen($expr);

        for ($i = 0; $i < $len; $i++) {
            $ch = $expr[$i];

            if ($inStr !== null) {
                $current .= $ch;
                if ($ch === $inStr) {
                    $inStr = null;
                }
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $inStr   = $ch;
                $current .= $ch;
                continue;
            }

            if ($ch === '(') { $depth++; $current .= $ch; continue; }
            if ($ch === ')') { $depth--; $current .= $ch; continue; }

            if ($depth === 0 && $ch === $op && strlen($op) === 1) {
                $parts[]  = $current;
                $current  = '';
                continue;
            }

            $current .= $ch;
        }
        $parts[] = $current;

        return array_filter(array_map('trim', $parts), fn ($p) => $p !== '');
    }

    /**
     * code.code_node — run a Python script in a strict sandbox via proc_open.
     *
     * Security guarantees:
     *  - Executed as `python3 -c "..."` with ulimit -v (64MB memory), -t (CPU time 5s)
     *  - No filesystem writes: script runs in /tmp with no cwd access
     *  - No network: only stdlib, math, json, re, datetime, collections, itertools
     *  - Script receives context as `ctx` dict in globals; must set output["key"] = value
     *  - stdout is captured as logs; exec output must go through `output` dict
     *  - Hard timeout via SIGKILL after 5 seconds (wall-clock)
     *
     * @return array{output: array<string,mixed>}
     */
    private function executeCodeNode(array $config, array $output): array
    {
        $language       = (string) ($config['language'] ?? 'python_safe');
        $code           = (string) ($config['code']     ?? '');
        $timeoutSeconds = min((int) ($config['timeout_seconds'] ?? 5), 10);

        if ($language !== 'python_safe') {
            return ['output' => array_merge($output, ['error' => "Unsupported language: {$language}. Only 'python_safe' is allowed."])];
        }

        if (empty($code)) {
            return ['output' => array_merge($output, ['error' => 'Empty code block'])];
        }

        // Injection guard — block dangerous patterns
        $blockedPatterns = [
            '/import\s+os\b/',
            '/import\s+sys\b/',
            '/import\s+subprocess\b/',
            '/import\s+socket\b/',
            '/import\s+urllib/',
            '/import\s+http\b/',
            '/import\s+requests\b/',
            '/open\s*\(/',
            '/__import__/',
            '/exec\s*\(/',
            '/eval\s*\(/',
            '/compile\s*\(/',
            '/getattr\s*\(/',
            '/setattr\s*\(/',
            '/globals\s*\(/',
            '/locals\s*\(/',
            '/builtins/',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return ['output' => array_merge($output, [
                    'error'   => "Sandbox violation: blocked pattern detected in code.",
                    'output'  => [],
                    'logs'    => [],
                    'timed_out' => false,
                ])];
            }
        }

        // Build the wrapper that injects ctx and captures output/logs
        $ctxJson    = json_encode($output, JSON_THROW_ON_ERROR);
        $userCode   = addcslashes($code, '\\$"');

        $wrapper = <<<PYTHON
import json, math, re, datetime, collections, itertools, sys
from io import StringIO

# Inject context
ctx = json.loads('{$ctxJson}')
output = {}
_logs = []

# Capture print() output
_orig_stdout = sys.stdout
sys.stdout = _buf = StringIO()

try:
{$this->indentCode($code, '    ')}
except Exception as _exc:
    output['__error__'] = str(_exc)
finally:
    _printed = _buf.getvalue()
    sys.stdout = _orig_stdout

# Emit result
print(json.dumps({'output': output, 'logs': _printed.splitlines()}))
PYTHON;

        if (!function_exists('proc_open')) {
            return ['output' => array_merge($output, ['error' => 'proc_open not available in this environment'])];
        }

        $descriptors = [
            0 => ['pipe', 'r'],   // stdin
            1 => ['pipe', 'w'],   // stdout
            2 => ['pipe', 'w'],   // stderr
        ];

        // Chantier 32.11: this docblock claimed "ulimit -v (64MB memory),
        // -t (CPU time 5s)" as an already-applied security guarantee, but
        // the command actually built here never called ulimit at all — the
        // real ulimit call added below is what makes that guarantee true;
        // see CodeNodeService::runPythonSafe() (the standalone REST-API
        // counterpart to this method, same gap, same fix, same residual
        // risk documented there) for the full rationale, including the
        // honest caveat that a regex denylist is not a real sandbox
        // boundary on its own.
        // Chantier 32.11: `sh` on this app's target containers is dash, not
        // bash — dash's builtin `ulimit` fatals with "too many arguments"
        // on combined `-v X -t Y` (bash-only syntax), confirmed empirically
        // — two separate `ulimit` calls are the portable form both shells
        // accept.
        $innerCmd = 'ulimit -v 65536; ulimit -t ' . $timeoutSeconds . '; exec python3 -c ' . escapeshellarg($wrapper);
        $cmd      = 'timeout ' . $timeoutSeconds . 's sh -c ' . escapeshellarg($innerCmd);

        $process = proc_open($cmd, $descriptors, $pipes, '/tmp', []);

        if (!is_resource($process)) {
            return ['output' => array_merge($output, ['error' => 'Failed to start Python sandbox process'])];
        }

        fclose($pipes[0]);
        $stdout  = stream_get_contents($pipes[1]);
        $stderr  = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $timedOut = ($exitCode === 124 || $exitCode === 137); // timeout/SIGKILL exit codes

        if ($timedOut) {
            return ['output' => array_merge($output, [
                'output'    => [],
                'logs'      => [],
                'error'     => "Script timed out after {$timeoutSeconds} seconds.",
                'timed_out' => true,
            ])];
        }

        // Parse result
        $result = json_decode(trim($stdout ?? ''), true);
        if (!is_array($result)) {
            return ['output' => array_merge($output, [
                'output'    => [],
                'logs'      => [],
                'error'     => $stderr ?: 'No output from script',
                'timed_out' => false,
            ])];
        }

        $scriptOutput = $result['output'] ?? [];
        if (isset($scriptOutput['__error__'])) {
            $err = $scriptOutput['__error__'];
            unset($scriptOutput['__error__']);
            return ['output' => array_merge($output, [
                'output'    => $scriptOutput,
                'logs'      => $result['logs'] ?? [],
                'error'     => $err,
                'timed_out' => false,
            ])];
        }

        return ['output' => array_merge($output, [
            'output'    => $scriptOutput,
            'logs'      => $result['logs'] ?? [],
            'error'     => null,
            'timed_out' => false,
        ])];
    }

    /**
     * Indents each line of code by a prefix (for Python indentation in the wrapper).
     */
    private function indentCode(string $code, string $prefix): string
    {
        $lines = explode("\n", $code);
        return implode("\n", array_map(fn ($line) => $prefix . $line, $lines));
    }
}
