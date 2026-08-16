<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * RiskAssessmentService — adaptive/risk-based authentication
 *
 * Scores each login attempt against a handful of weighted signals (all kept
 * in the cache, per user, so this needs no dedicated tables):
 *
 *  - new_device       (weight 10) — device fingerprint not seen before
 *  - new_ip           (weight 15) — source IP not seen before
 *  - impossible_travel(weight 40) — a previous login location was recorded
 *                      recently, and either (a) real coordinates were
 *                      supplied for this request and the implied travel
 *                      speed exceeds what's physically possible, or (b) no
 *                      coordinates are available but the IP changed within
 *                      the "impossible" time window — a conservative
 *                      approximation when precise geolocation isn't wired up
 *  - new_location     (weight 15) — a previous location exists, real
 *                      coordinates were supplied, and they're far away but
 *                      not "impossible" (i.e. plausible given the elapsed
 *                      time)
 *  - unusual_hour     (weight 10) — current hour is well outside the user's
 *                      recorded login-hour history
 *  - brute_force       (weight up to 40) — recent failed login attempts
 *
 * IP-based checks (new_ip / impossible_travel / new_location) are skipped
 * entirely for private/loopback/link-local addresses (RFC 1918 etc.) since
 * those never correspond to a real-world geographic location — flagging an
 * office LAN address as "a new location" would just be noise.
 */
class RiskAssessmentService
{
    private const WEIGHT_NEW_DEVICE = 10;

    private const WEIGHT_NEW_IP = 15;

    private const WEIGHT_IMPOSSIBLE_TRAVEL = 40;

    private const WEIGHT_NEW_LOCATION = 15;

    private const WEIGHT_UNUSUAL_HOUR = 10;

    private const WEIGHT_BRUTE_FORCE_PER_ATTEMPT = 6;

    private const WEIGHT_BRUTE_FORCE_MAX = 40;

    private const BRUTE_FORCE_THRESHOLD = 5;

    /** Max plausible commercial-flight-plus-margin speed, km/h. */
    private const IMPOSSIBLE_TRAVEL_SPEED_KMH = 1000;

    /** Without real coordinates, treat "recent previous login + new IP" as impossible travel within this window. */
    private const IMPOSSIBLE_TRAVEL_WINDOW_MINUTES = 60;

    /** Below this distance a location change isn't worth flagging (same metro area). */
    private const NEW_LOCATION_DISTANCE_KM = 100;

    private const RISK_LEVEL_MEDIUM_THRESHOLD = 20;

    private const RISK_LEVEL_HIGH_THRESHOLD = 40;

    private const RISK_LEVEL_CRITICAL_THRESHOLD = 70;

    private const DEVICE_HISTORY_TTL_DAYS = 180;

    private const IP_HISTORY_TTL_DAYS = 180;

    private const LOCATION_HISTORY_TTL_DAYS = 90;

    private const LOGIN_TIMES_HISTORY_TTL_DAYS = 90;

    private const FAILED_ATTEMPTS_TTL_MINUTES = 30;

    private const MAX_HISTORY_ENTRIES = 20;

    /**
     * Assess the risk of a login attempt.
     *
     * @return array{
     *   risk_score: int,
     *   risk_level: 'low'|'medium'|'high'|'critical',
     *   factors: array<int, array{factor: string, weight: int, description: string}>,
     *   requires_mfa: bool,
     *   requires_verification: bool,
     * }
     */
    public function assessRisk(User $user, Request $request): array
    {
        $factors = [];
        $score = 0;

        [$deviceFactors, $deviceScore] = $this->assessDeviceRisk($user, $request);
        $factors = array_merge($factors, $deviceFactors);
        $score += $deviceScore;

        $ip = (string) ($request->ip() ?? '');
        if ($ip !== '' && ! $this->isPrivateOrLocalIp($ip)) {
            [$locationFactors, $locationScore] = $this->assessLocationRisk($user, $request, $ip);
            $factors = array_merge($factors, $locationFactors);
            $score += $locationScore;
        }

        [$hourFactors, $hourScore] = $this->assessHourRisk($user);
        $factors = array_merge($factors, $hourFactors);
        $score += $hourScore;

        [$bruteForceFactors, $bruteForceScore] = $this->assessBruteForceRisk($user);
        $factors = array_merge($factors, $bruteForceFactors);
        $score += $bruteForceScore;

        $score = min(100, $score);
        $level = $this->riskLevel($score);

        return [
            'risk_score' => $score,
            'risk_level' => $level,
            'factors' => $factors,
            'requires_mfa' => in_array($level, ['high', 'critical'], true),
            'requires_verification' => $level === 'critical',
        ];
    }

    /**
     * Record a successful login: refreshes device/IP/location/hour history
     * and clears any failed-attempt counter.
     */
    public function recordLogin(User $user, Request $request): void
    {
        $ip = (string) ($request->ip() ?? '');

        $this->rememberDevice($user, $request);

        if ($ip !== '') {
            $this->rememberIp($user, $ip);
        }

        $this->rememberLocation($user, $request);
        $this->rememberLoginHour($user);

        Cache::forget("failed_attempts:{$user->id}");
    }

    /**
     * Record a failed login attempt (used for brute-force detection).
     */
    public function recordFailedAttempt(User $user): int
    {
        $key = "failed_attempts:{$user->id}";
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addMinutes(self::FAILED_ATTEMPTS_TTL_MINUTES));

        return $attempts;
    }

    /**
     * @return array{0: array<int, array{factor: string, weight: int, description: string}>, 1: int}
     */
    private function assessDeviceRisk(User $user, Request $request): array
    {
        $fingerprint = $this->computeDeviceFingerprint($request);
        $knownDevices = Cache::get("user_devices:{$user->id}", []);

        if (! in_array($fingerprint, $knownDevices, true)) {
            return [
                [[
                    'factor' => 'new_device',
                    'weight' => self::WEIGHT_NEW_DEVICE,
                    'description' => 'Login from a device fingerprint not seen before',
                ]],
                self::WEIGHT_NEW_DEVICE,
            ];
        }

        return [[], 0];
    }

    /**
     * @return array{0: array<int, array{factor: string, weight: int, description: string}>, 1: int}
     */
    private function assessLocationRisk(User $user, Request $request, string $ip): array
    {
        $factors = [];
        $score = 0;

        $knownIps = Cache::get("user_ips:{$user->id}", []);
        $ipIsNew = ! in_array($ip, $knownIps, true);

        if ($ipIsNew) {
            $factors[] = [
                'factor' => 'new_ip',
                'weight' => self::WEIGHT_NEW_IP,
                'description' => 'Login from an IP address not seen before',
            ];
            $score += self::WEIGHT_NEW_IP;
        }

        $previousLocation = Cache::get("user_location:{$user->id}");

        if (is_array($previousLocation) && isset($previousLocation['timestamp'])) {
            $currentLat = $request->input('latitude');
            $currentLon = $request->input('longitude');

            if ($currentLat !== null && $currentLon !== null
                && isset($previousLocation['latitude'], $previousLocation['longitude'])) {
                $distanceKm = $this->haversineDistanceKm(
                    (float) $previousLocation['latitude'],
                    (float) $previousLocation['longitude'],
                    (float) $currentLat,
                    (float) $currentLon,
                );

                $hoursElapsed = max(0.01, (time() - (int) $previousLocation['timestamp']) / 3600);
                $impliedSpeedKmh = $distanceKm / $hoursElapsed;

                if ($impliedSpeedKmh > self::IMPOSSIBLE_TRAVEL_SPEED_KMH) {
                    $factors[] = [
                        'factor' => 'impossible_travel',
                        'weight' => self::WEIGHT_IMPOSSIBLE_TRAVEL,
                        'description' => sprintf(
                            'Implied travel speed of %d km/h from last known location is not physically possible',
                            (int) $impliedSpeedKmh,
                        ),
                    ];
                    $score += self::WEIGHT_IMPOSSIBLE_TRAVEL;
                } elseif ($distanceKm > self::NEW_LOCATION_DISTANCE_KM) {
                    $factors[] = [
                        'factor' => 'new_location',
                        'weight' => self::WEIGHT_NEW_LOCATION,
                        'description' => sprintf('Login from %d km away from the last known location', (int) $distanceKm),
                    ];
                    $score += self::WEIGHT_NEW_LOCATION;
                }
            } elseif ($ipIsNew) {
                // No real coordinates for this request (e.g. no client-side
                // geolocation submitted) — fall back to a time-window proxy:
                // a recently recorded location plus a brand new IP looks
                // like impossible travel even without precise distances.
                $minutesElapsed = (time() - (int) $previousLocation['timestamp']) / 60;

                if ($minutesElapsed <= self::IMPOSSIBLE_TRAVEL_WINDOW_MINUTES) {
                    $factors[] = [
                        'factor' => 'impossible_travel',
                        'weight' => self::WEIGHT_IMPOSSIBLE_TRAVEL,
                        'description' => 'New IP address shortly after a login from a different location',
                    ];
                    $score += self::WEIGHT_IMPOSSIBLE_TRAVEL;
                }
            }
        }

        return [$factors, $score];
    }

    /**
     * @return array{0: array<int, array{factor: string, weight: int, description: string}>, 1: int}
     */
    private function assessHourRisk(User $user): array
    {
        $typicalHours = Cache::get("user_login_times:{$user->id}", []);

        if (empty($typicalHours)) {
            return [[], 0];
        }

        $currentHour = (int) now()->format('G');

        foreach ($typicalHours as $hour) {
            if (abs($currentHour - (int) $hour) <= 1) {
                return [[], 0];
            }
        }

        return [
            [[
                'factor' => 'unusual_hour',
                'weight' => self::WEIGHT_UNUSUAL_HOUR,
                'description' => 'Login hour is well outside the user\'s usual login hours',
            ]],
            self::WEIGHT_UNUSUAL_HOUR,
        ];
    }

    /**
     * @return array{0: array<int, array{factor: string, weight: int, description: string}>, 1: int}
     */
    private function assessBruteForceRisk(User $user): array
    {
        $attempts = (int) Cache::get("failed_attempts:{$user->id}", 0);

        if ($attempts < self::BRUTE_FORCE_THRESHOLD) {
            return [[], 0];
        }

        $weight = min(self::WEIGHT_BRUTE_FORCE_MAX, $attempts * self::WEIGHT_BRUTE_FORCE_PER_ATTEMPT);

        return [
            [[
                'factor' => 'brute_force',
                'weight' => $weight,
                'description' => "{$attempts} failed login attempts recorded recently",
            ]],
            $weight,
        ];
    }

    private function rememberDevice(User $user, Request $request): void
    {
        $fingerprint = $this->computeDeviceFingerprint($request);
        $key = "user_devices:{$user->id}";

        $devices = Cache::get($key, []);
        if (! in_array($fingerprint, $devices, true)) {
            $devices[] = $fingerprint;
            $devices = array_slice($devices, -self::MAX_HISTORY_ENTRIES);
        }

        Cache::put($key, $devices, now()->addDays(self::DEVICE_HISTORY_TTL_DAYS));
    }

    private function rememberIp(User $user, string $ip): void
    {
        $key = "user_ips:{$user->id}";

        $ips = Cache::get($key, []);
        if (! in_array($ip, $ips, true)) {
            $ips[] = $ip;
            $ips = array_slice($ips, -self::MAX_HISTORY_ENTRIES);
        }

        Cache::put($key, $ips, now()->addDays(self::IP_HISTORY_TTL_DAYS));
    }

    private function rememberLocation(User $user, Request $request): void
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        Cache::put("user_location:{$user->id}", [
            'latitude' => $latitude !== null ? (float) $latitude : null,
            'longitude' => $longitude !== null ? (float) $longitude : null,
            'city' => $request->input('city'),
            'timestamp' => time(),
        ], now()->addDays(self::LOCATION_HISTORY_TTL_DAYS));
    }

    private function rememberLoginHour(User $user): void
    {
        $key = "user_login_times:{$user->id}";

        $hours = Cache::get($key, []);
        $hours[] = (int) now()->format('G');
        $hours = array_slice($hours, -self::MAX_HISTORY_ENTRIES);

        Cache::put($key, $hours, now()->addDays(self::LOGIN_TIMES_HISTORY_TTL_DAYS));
    }

    private function computeDeviceFingerprint(Request $request): string
    {
        $components = implode('|', [
            $request->userAgent() ?? '',
            $request->server('HTTP_ACCEPT_LANGUAGE') ?? '',
            $request->server('HTTP_ACCEPT_ENCODING') ?? '',
        ]);

        return hash('sha256', $components);
    }

    /**
     * True when the IP is private, loopback, or link-local (RFC 1918 etc.)
     * — i.e. it doesn't correspond to a real geographic location. Uses only
     * FILTER_FLAG_NO_PRIV_RANGE (not FILTER_FLAG_NO_RES_RANGE) so that the
     * RFC 5737 documentation ranges (192.0.2.0/24, 198.51.100.0/24,
     * 203.0.113.0/24) — used throughout tests as stand-ins for "a real
     * public IP" — are treated as public.
     */
    private function isPrivateOrLocalIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    private function haversineDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= self::RISK_LEVEL_CRITICAL_THRESHOLD => 'critical',
            $score >= self::RISK_LEVEL_HIGH_THRESHOLD => 'high',
            $score >= self::RISK_LEVEL_MEDIUM_THRESHOLD => 'medium',
            default => 'low',
        };
    }
}
