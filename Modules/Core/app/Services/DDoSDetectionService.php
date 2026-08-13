<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\DDoSIncident;

/**
 * DDoSDetectionService
 *
 * Detects DDoS attacks through pattern recognition, spike detection, and
 * botnet fingerprinting. Implements automated IP blocking and incident logging.
 */
class DDoSDetectionService
{
    private Repository $cache;
    private string $cacheStore;
    private array $config;

    public const RISK_LEVEL_LOW = 'low';
    public const RISK_LEVEL_MEDIUM = 'medium';
    public const RISK_LEVEL_HIGH = 'high';
    public const RISK_LEVEL_CRITICAL = 'critical';

    // Known botnet user-agent patterns
    private const BOTNET_PATTERNS = [
        '/bot[-_.]/i',
        '/crawler/i',
        '/spider/i',
        '/scraper/i',
        '/wget|curl|python|java(?!script)/i',
        '/nikto|masscan|nmap|sqlmap/i',
        '/havij|acunetix|nessus|openvas/i',
        '/slurp|bingbot|googlebot|baiduspider/i',
    ];

    // Known attack signatures
    private const ATTACK_SIGNATURES = [
        'sql_injection' => '/\bselect\b.+\bfrom\b|\bunion\s+select\b|\bdrop\s+(table|database|index|view)\b|\binsert\s+into\b|\bupdate\b.+\bset\b|\bdelete\s+from\b|\btruncate\s+table\b|\bwhere\b.+[=<>].+(\bor\b|\band\b)|\bor\b\s+\'[^\']*\'=\'|\'--\s*$|;\s*--/i',
        'xss_attempt' => '/<script|javascript:|onerror|onclick|onload/i',
        'path_traversal' => '/\.\.[\/\\\\]/i',
        'lfi_attempt' => '/(file|require|include|include_once|require_once)[\s\(]*=/i',
        'command_injection' => '/;\s*(cat|ls|rm|wget|curl|nc|bash|sh|python|perl|ruby|php)\b|\|\s*(nc|bash|sh|python|wget|curl|cmd)\b|&&\s*\w|`[^`]+`|\$\([^)]+\)|\bsystem\s*\(|\bexec\s*\(|\bpassthru\s*\(|\bshell_exec\s*\(/i',
    ];

    public function __construct()
    {
        $this->config = config('rate_limit', []);
        $this->cacheStore = $this->config['cache_store'] ?? config('cache.default', 'array');
        $this->cache = Cache::store($this->cacheStore);
    }

    /**
     * Analyze a request for DDoS risk.
     *
     * Returns a DDoSAnalysisResult with risk level and detection details.
     */
    public function analyzeRequest(Request $request): DDoSAnalysisResult
    {
        try {
            $ipAddress = $request->header('X-Forwarded-For') ? explode(',', $request->header('X-Forwarded-For'))[0] : ($request->ip() ?? 'unknown');
            $ipAddress = trim($ipAddress);
            $endpoint = $request->path();
            $userAgent = $request->userAgent() ?? '';

            $riskLevel = self::RISK_LEVEL_LOW;
            $reasons = [];
            $metrics = [];

            // Check for botnet patterns
            if ($this->isBotnetPattern($userAgent)) {
                $riskLevel = self::RISK_LEVEL_MEDIUM;
                $reasons[] = 'Botnet user-agent pattern detected';
                $metrics['botnet_pattern'] = true;
            }

            // Check for attack signatures
            $signatures = $this->detectAttackSignatures($request);
            if (!empty($signatures)) {
                $riskLevel = self::RISK_LEVEL_HIGH;
                foreach ($signatures as $sig) {
                    $reasons[] = 'Attack signature detected: ' . $sig;
                }
                $metrics['signatures'] = $signatures;
            }

            // Check for request rate spike
            $spikeDetection = $this->detectSpike($ipAddress, $endpoint);
            if ($spikeDetection['detected']) {
                if ($spikeDetection['rps'] > $this->getConfig('spike_threshold', 1000)) {
                    $riskLevel = self::RISK_LEVEL_CRITICAL;
                    $reasons[] = "Critical spike detected: {$spikeDetection['rps']} req/sec";
                } else {
                    $riskLevel = max($riskLevel, self::RISK_LEVEL_MEDIUM);
                    $reasons[] = "Spike detected: {$spikeDetection['rps']} req/sec";
                }
                $metrics = array_merge($metrics, $spikeDetection);
            }

            // Check for geographic anomalies
            $geoAnomaly = $this->detectGeoAnomaly($ipAddress);
            if ($geoAnomaly['detected']) {
                $riskLevel = max($riskLevel, self::RISK_LEVEL_MEDIUM);
                $reasons[] = 'Geographic anomaly detected';
                $metrics['geo_anomaly'] = $geoAnomaly;
            }

            // Determine if should block
            $shouldBlock = in_array($riskLevel, [self::RISK_LEVEL_HIGH, self::RISK_LEVEL_CRITICAL]);

            return new DDoSAnalysisResult(
                riskLevel: $riskLevel,
                shouldBlock: $shouldBlock,
                reasons: $reasons,
                metrics: $metrics,
                ipAddress: $ipAddress,
                endpoint: $endpoint,
            );
        } catch (\Exception $e) {
            \Log::warning('DDoSDetectionService error: ' . $e->getMessage());
            // Graceful degradation - allow request
            return DDoSAnalysisResult::safe();
        }
    }

    /**
     * Detect if anomalies exist for an endpoint.
     */
    public function detectAnomalies(string $endpoint): bool
    {
        try {
            $key = "ddos:anomalies:{$endpoint}";
            return (bool)$this->cache->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Determine if an IP should be blocked.
     */
    public function shouldBlockIp(string $ipAddress): bool
    {
        try {
            // Check if IP is in blocked list
            $key = "ddos:blocked_ip:{$ipAddress}";
            return (bool)$this->cache->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Block an IP address.
     */
    public function blockIp(string $ipAddress, int $durationSeconds = 3600, string $reason = '', bool $createIncident = true): void
    {
        try {
            $key = "ddos:blocked_ip:{$ipAddress}";
            $this->cache->put($key, true, $durationSeconds);

            if ($createIncident) {
                DDoSIncident::create([
                    'ip_address' => $ipAddress,
                    'risk_level' => self::RISK_LEVEL_CRITICAL,
                    'reason' => $reason ?: 'IP manually blocked by DDoS service',
                    'detected_at' => now(),
                    'blocked_until' => now()->addSeconds($durationSeconds),
                    'auto_blocked' => true,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to block IP {$ipAddress}: " . $e->getMessage());
        }
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(string $ipAddress): void
    {
        try {
            $key = "ddos:blocked_ip:{$ipAddress}";
            $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("Failed to unblock IP {$ipAddress}: " . $e->getMessage());
        }
    }

    /**
     * Record metrics for an IP and endpoint.
     */
    public function recordMetrics(string $ipAddress, string $endpoint, int $timestamp): void
    {
        try {
            $key = "ddos:metrics:{$ipAddress}:{$endpoint}";
            $current = json_decode($this->cache->get($key) ?? '[]', true);

            // Keep only recent timestamps (last 60 seconds)
            $cutoff = $timestamp - 60;
            $current = array_filter($current, fn($ts) => $ts > $cutoff);

            // Add new timestamp
            $current[] = $timestamp;

            // Store with 61-second TTL
            $this->cache->put($key, json_encode($current), 61);
        } catch (\Exception $e) {
            \Log::debug('Failed to record metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get active incidents for an IP.
     */
    public function getActiveIncidents(string $ipAddress): array
    {
        return DDoSIncident::forIp($ipAddress)
            ->active()
            ->recent(60)
            ->get()
            ->toArray();
    }

    /**
     * Create a new DDoS incident.
     */
    public function createIncident(
        string $ipAddress,
        string $riskLevel,
        string $reason,
        ?string $endpoint = null,
        array $metrics = []
    ): DDoSIncident {
        // Determine block duration based on risk level
        $blockDuration = match ($riskLevel) {
            self::RISK_LEVEL_LOW => 300, // 5 minutes
            self::RISK_LEVEL_MEDIUM => 900, // 15 minutes
            self::RISK_LEVEL_HIGH => 3600, // 1 hour
            self::RISK_LEVEL_CRITICAL => 86400, // 24 hours
            default => 300,
        };

        $incident = DDoSIncident::create([
            'ip_address' => $ipAddress,
            'endpoint' => $endpoint,
            'risk_level' => $riskLevel,
            'reason' => $reason,
            'detected_at' => now(),
            'blocked_until' => now()->addSeconds($blockDuration),
            'metrics' => $metrics,
            'auto_blocked' => true,
            'request_count' => $metrics['request_count'] ?? 0,
            'requests_per_second' => $metrics['rps'] ?? 0,
            'attack_signature' => $metrics['signatures'][0] ?? null,
        ]);

        // Block the IP immediately for critical/high-risk
        if (in_array($riskLevel, [self::RISK_LEVEL_HIGH, self::RISK_LEVEL_CRITICAL])) {
            $this->blockIp($ipAddress, $blockDuration, $reason, createIncident: false);
        }

        return $incident;
    }

    /**
     * Check if a user-agent matches botnet patterns.
     */
    private function isBotnetPattern(string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        foreach (self::BOTNET_PATTERNS as $pattern) {
            if (@preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect attack signatures in request parameters.
     */
    private function detectAttackSignatures(Request $request): array
    {
        $detected = [];
        $payload = [
            'query' => ($request->getQueryString() ?? '') . ' ' . ($request->server->get('QUERY_STRING', '')) . ' ' . json_encode($request->query->all()) . ' ' . urldecode($request->server->get('QUERY_STRING', '')),
            'body' => $request->getContent() ?? '',
            'headers' => json_encode($request->headers->all()),
        ];

        foreach (self::ATTACK_SIGNATURES as $signature => $pattern) {
            foreach ($payload as $source => $content) {
                if (@preg_match($pattern, $content)) {
                    $detected[] = $signature;
                    break;
                }
            }
        }

        return array_unique($detected);
    }

    /**
     * Detect sudden spike in request rate.
     */
    private function detectSpike(string $ipAddress, string $endpoint): array
    {
        try {
            $windowSize = $this->getConfig('window_size', 60);
            $spikeThreshold = $this->getConfig('spike_threshold', 1000);

            $key = "ddos:metrics:{$ipAddress}:{$endpoint}";
            $timestamps = json_decode($this->cache->get($key) ?? '[]', true);

            if (count($timestamps) < 10) {
                return ['detected' => false];
            }

            // Calculate requests per second
            $oldest = min($timestamps);
            $newest = max($timestamps);
            $timeWindow = max(1, $newest - $oldest);
            $rps = count($timestamps) / $timeWindow;

            $detected = $rps > ($spikeThreshold / 60);

            return [
                'detected' => $detected,
                'rps' => round($rps, 2),
                'window_size' => $timeWindow,
                'request_count' => count($timestamps),
            ];
        } catch (\Exception $e) {
            return ['detected' => false];
        }
    }

    /**
     * Detect geographic anomalies (same IP from multiple countries).
     */
    private function detectGeoAnomaly(string $ipAddress): array
    {
        try {
            // In a real implementation, you'd use GeoIP database
            // For now, we'll check if IP changed recently
            $key = "ddos:geo:{$ipAddress}";
            $lastIp = $this->cache->get($key);

            if ($lastIp && $lastIp !== $ipAddress) {
                return [
                    'detected' => true,
                    'previous_ip' => $lastIp,
                    'current_ip' => $ipAddress,
                ];
            }

            $this->cache->put($key, $ipAddress, 300);

            return ['detected' => false];
        } catch (\Exception $e) {
            return ['detected' => false];
        }
    }

    /**
     * Get configuration value with fallback.
     */
    private function getConfig(string $key, $default = null)
    {
        return config('rate_limit.ddos_detection.' . $key) ?? $default;
    }

    /**
     * Clean up old incidents.
     */
    public function cleanup(int $daysOld = 30): int
    {
        return DDoSIncident::where('detected_at', '<', now()->subDays($daysOld))->delete();
    }
}

/**
 * DDoSAnalysisResult DTO
 */
class DDoSAnalysisResult
{
    public function __construct(
        public string $riskLevel,
        public bool $shouldBlock,
        public array $reasons = [],
        public array $metrics = [],
        public string $ipAddress = '',
        public string $endpoint = '',
    ) {
    }

    public static function safe(): self
    {
        return new self(
            riskLevel: DDoSDetectionService::RISK_LEVEL_LOW,
            shouldBlock: false,
            reasons: [],
            metrics: [],
        );
    }

    public function isCritical(): bool
    {
        return $this->riskLevel === DDoSDetectionService::RISK_LEVEL_CRITICAL;
    }

    public function isHigh(): bool
    {
        return $this->riskLevel === DDoSDetectionService::RISK_LEVEL_HIGH;
    }
}
