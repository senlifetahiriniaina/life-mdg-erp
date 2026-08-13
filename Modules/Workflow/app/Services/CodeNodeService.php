<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Modules\Workflow\Services\Automation\FlowExecutionEngine;

/**
 * CodeNodeService — standalone service wrapping expression and sandboxed-code evaluation.
 *
 * Used by the CodeNodeController REST API.
 * Internally delegates to FlowExecutionEngine's private evaluators via reflection-free
 * re-implementation of the same safe logic (no circular dependency).
 */
class CodeNodeService
{
    private const BLOCKED_PATTERNS = [
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

    // ── Public API ───────────────────────────────────────────────────────────────

    /**
     * Validate a code/expression block.
     *
     * @param  string $language  "expression" | "python_safe"
     * @param  string $code
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(string $language, string $code): array
    {
        $errors = [];

        if (empty(trim($code))) {
            $errors[] = 'Code block is empty.';
            return ['valid' => false, 'errors' => $errors];
        }

        if ($language === 'python_safe') {
            foreach (self::BLOCKED_PATTERNS as $pattern) {
                if (preg_match($pattern, $code)) {
                    $errors[] = "Blocked pattern detected: {$pattern}";
                }
            }
        } elseif ($language === 'expression') {
            // Basic check: no PHP open tags, no eval, no shell escapes
            if (preg_match('/<\?php|eval\(|shell_exec|proc_open|system\s*\(/', $code)) {
                $errors[] = 'Expression contains forbidden PHP/shell keywords.';
            }
        } else {
            $errors[] = "Unsupported language: '{$language}'. Allowed: 'expression', 'python_safe'.";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Execute a code/expression node.
     *
     * @param  string               $language  "expression" | "python_safe"
     * @param  string               $code
     * @param  array<string,mixed>  $context   Input context (available as $context.field in expressions, ctx in Python)
     * @param  int                  $timeout   Seconds (max 10, ignored for expressions)
     * @return array{output: array, logs: array, error: string|null, timed_out: bool}
     */
    public function execute(string $language, string $code, array $context = [], int $timeout = 5): array
    {
        $validation = $this->validate($language, $code);
        if (!$validation['valid']) {
            return [
                'output'    => [],
                'logs'      => [],
                'error'     => implode('; ', $validation['errors']),
                'timed_out' => false,
            ];
        }

        if ($language === 'expression') {
            return $this->runExpression($code, $context);
        }

        return $this->runPythonSafe($code, $context, $timeout);
    }

    // ── Private Helpers ──────────────────────────────────────────────────────────

    /**
     * @return array{output: array, logs: array, error: string|null, timed_out: bool}
     */
    private function runExpression(string $expr, array $context): array
    {
        // Normalise $context.foo → context.foo
        $expr = preg_replace('/\$context\./', 'context.', $expr) ?? $expr;

        try {
            $result = $this->evalExpr(trim($expr), $context);
            return [
                'output'    => ['result' => $result],
                'logs'      => [],
                'error'     => null,
                'timed_out' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'output'    => [],
                'logs'      => [],
                'error'     => $e->getMessage(),
                'timed_out' => false,
            ];
        }
    }

    /**
     * Simple safe expression evaluator (mirrors FlowExecutionEngine logic).
     *
     * Supports: dot-path lookups, arithmetic, comparisons, ternary, string concat (~).
     */
    private function evalExpr(string $expr, array $context): mixed
    {
        $expr = trim($expr);

        // Ternary
        if (preg_match('/^(.+?)\?(.+?):(.+)$/s', $expr, $m)) {
            return $this->evalExpr($m[1], $context)
                ? $this->evalExpr($m[2], $context)
                : $this->evalExpr($m[3], $context);
        }

        // Comparisons
        foreach (['>=', '<=', '!=', '==', '>', '<'] as $op) {
            if (str_contains($expr, $op)) {
                [$l, $r] = array_map('trim', explode($op, $expr, 2));
                $left  = $this->evalExpr($l, $context);
                $right = $this->evalExpr($r, $context);
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

        // String concat via ~
        if (str_contains($expr, '~')) {
            $parts = array_map('trim', explode('~', $expr));
            return implode('', array_map(fn ($p) => (string) $this->evalExpr($p, $context), $parts));
        }

        // Addition / subtraction
        foreach (['+', '-'] as $op) {
            $tokens = $this->splitOn($expr, $op);
            if (count($tokens) > 1) {
                $result = (float) $this->evalExpr(array_shift($tokens), $context);
                foreach ($tokens as $t) {
                    $val = (float) $this->evalExpr($t, $context);
                    $result = $op === '+' ? $result + $val : $result - $val;
                }
                return $result;
            }
        }

        // Multiply / divide
        foreach (['*', '/'] as $op) {
            $tokens = $this->splitOn($expr, $op);
            if (count($tokens) > 1) {
                $result = (float) $this->evalExpr(array_shift($tokens), $context);
                foreach ($tokens as $t) {
                    $val = (float) $this->evalExpr($t, $context);
                    $result = $op === '*' ? $result * $val : ($val != 0 ? $result / $val : 0.0);
                }
                return $result;
            }
        }

        // Strip parentheses
        if (str_starts_with($expr, '(') && str_ends_with($expr, ')')) {
            return $this->evalExpr(substr($expr, 1, -1), $context);
        }

        return $this->resolveToken($expr, $context);
    }

    private function resolveToken(string $token, array $context): mixed
    {
        $token = trim($token);

        // String literal
        if (
            (str_starts_with($token, "'") && str_ends_with($token, "'")) ||
            (str_starts_with($token, '"') && str_ends_with($token, '"'))
        ) {
            return substr($token, 1, -1);
        }

        if ($token === 'true')  return true;
        if ($token === 'false') return false;
        if ($token === 'null')  return null;
        if (is_numeric($token)) return (float) $token;

        // Dot-path: look in $context['context'] first, then top-level
        $inContext = $this->dotGet($context['context'] ?? [], $token)
            ?? $this->dotGet($context, $token);
        return $inContext;
    }

    /**
     * @return string[]
     */
    private function splitOn(string $expr, string $op): array
    {
        $parts   = [];
        $depth   = 0;
        $inStr   = null;
        $current = '';

        for ($i = 0; $i < strlen($expr); $i++) {
            $ch = $expr[$i];

            if ($inStr !== null) {
                $current .= $ch;
                if ($ch === $inStr) $inStr = null;
                continue;
            }
            if ($ch === '"' || $ch === "'") { $inStr = $ch; $current .= $ch; continue; }
            if ($ch === '(') { $depth++; $current .= $ch; continue; }
            if ($ch === ')') { $depth--; $current .= $ch; continue; }

            if ($depth === 0 && $ch === $op) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $parts[] = $current;

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    private function dotGet(array $data, string $path): mixed
    {
        $keys    = explode('.', $path);
        $current = $data;
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * @return array{output: array, logs: array, error: string|null, timed_out: bool}
     */
    private function runPythonSafe(string $code, array $context, int $timeout): array
    {
        $timeout = min($timeout, 10);

        if (!function_exists('proc_open')) {
            return ['output' => [], 'logs' => [], 'error' => 'proc_open unavailable', 'timed_out' => false];
        }

        $ctxJson  = json_encode($context, JSON_THROW_ON_ERROR);
        $indented = $this->indentLines($code, '    ');

        $wrapper = <<<PYTHON
import json, math, re, datetime, collections, itertools, sys
from io import StringIO

ctx = json.loads(r'''{$ctxJson}''')
output = {}
_buf = StringIO()
sys.stdout = _buf

try:
{$indented}
except Exception as _e:
    output['__error__'] = str(_e)
finally:
    _logs = _buf.getvalue()
    sys.stdout = sys.__stdout__

print(json.dumps({'output': output, 'logs': _logs.splitlines()}))
PYTHON;

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd         = "timeout {$timeout}s python3 -c " . escapeshellarg($wrapper);
        $process     = proc_open($cmd, $descriptors, $pipes, '/tmp', []);

        if (!is_resource($process)) {
            return ['output' => [], 'logs' => [], 'error' => 'Failed to spawn sandbox process', 'timed_out' => false];
        }

        fclose($pipes[0]);
        $stdout   = stream_get_contents($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $timedOut = in_array($exitCode, [124, 137], true);
        if ($timedOut) {
            return ['output' => [], 'logs' => [], 'error' => "Timed out after {$timeout}s.", 'timed_out' => true];
        }

        $result = json_decode(trim((string) $stdout), true);
        if (!is_array($result)) {
            return ['output' => [], 'logs' => [], 'error' => (string) $stderr ?: 'No output', 'timed_out' => false];
        }

        $out = $result['output'] ?? [];
        $err = null;
        if (isset($out['__error__'])) {
            $err = $out['__error__'];
            unset($out['__error__']);
        }

        return [
            'output'    => $out,
            'logs'      => $result['logs'] ?? [],
            'error'     => $err,
            'timed_out' => false,
        ];
    }

    private function indentLines(string $code, string $prefix): string
    {
        return implode("\n", array_map(fn ($l) => $prefix . $l, explode("\n", $code)));
    }
}
