<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Modules\Core\Models\CspViolation;
use Modules\Core\Services\CspViolationLogger;

uses(RefreshDatabase::class, WithFaker::class);

describe('CSP Violations', function () {
    describe('Violation Logging', function () {
        test('violation_is_logged_to_database', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            $report = [
                'document-uri' => 'https://example.com/dashboard',
                'violated-directive' => 'script-src',
                'effective-directive' => 'script-src',
                'original-policy' => "script-src 'self'",
                'disposition' => 'enforce',
                'blocked-uri' => 'https://malicious.com/script.js',
                'source-file' => 'https://example.com/app.js',
                'line-number' => 42,
                'column-number' => 10,
                'status-code' => 200,
            ];

            $violation = $logger->logViolation(
                $report,
                userId: $user->id,
                tenantId: $user->tenant_id,
                module: 'CRM'
            );

            expect($violation)->toBeInstanceOf(CspViolation::class);
            expect($violation->violated_directive)->toBe('script-src');
            expect($violation->user_id)->toBe($user->id);
            expect($violation->module)->toBe('CRM');

            // Verify in database
            $this->assertDatabaseHas('csp_violations', [
                'id' => $violation->id,
                'violated_directive' => 'script-src',
                'user_id' => $user->id,
            ]);
        });

        test('violation_severity_is_calculated', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Script-src violations should be critical
            $report = [
                'document-uri' => 'https://example.com',
                'violated-directive' => 'script-src',
            ];

            $violation = $logger->logViolation($report, userId: $user->id);

            expect($violation->severity)->toBe('critical');
        });

        test('style_src_violations_are_high_severity', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            $report = [
                'document-uri' => 'https://example.com',
                'violated-directive' => 'style-src',
            ];

            $violation = $logger->logViolation($report, userId: $user->id);

            expect($violation->severity)->toBe('high');
        });

        test('violations_are_logged_with_request_context', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            $this->actingAs($user, 'sanctum');

            $report = [
                'document-uri' => 'https://example.com',
                'violated-directive' => 'script-src',
            ];

            $violation = $logger->logViolation($report, userId: $user->id);

            expect($violation->ip_address)->not->toBeNull();
            expect($violation->user_agent)->not->toBeNull();
        });
    });

    describe('Violation Statistics', function () {
        test('violation_stats_are_accurate', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Create multiple violations
            for ($i = 0; $i < 5; $i++) {
                $logger->logViolation(
                    [
                        'document-uri' => 'https://example.com',
                        'violated-directive' => 'script-src',
                    ],
                    userId: $user->id
                );
            }

            // Create some high-severity violations
            for ($i = 0; $i < 3; $i++) {
                $logger->logViolation(
                    [
                        'document-uri' => 'https://example.com',
                        'violated-directive' => 'object-src',
                    ],
                    userId: $user->id
                );
            }

            $stats = $logger->getViolationStats(
                from: now()->subDay(),
                to: now(),
                tenantId: $user->tenant_id
            );

            expect($stats['total'])->toBe(8);
            expect($stats['by_severity'])->toHaveKey('critical');
            expect($stats['by_directive'])->toHaveKey('script-src');
        });

        test('unresolved_violations_are_counted', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Create unresolved violations
            $v1 = $logger->logViolation(
                ['document-uri' => 'https://example.com', 'violated-directive' => 'script-src'],
                userId: $user->id
            );

            $v2 = $logger->logViolation(
                ['document-uri' => 'https://example.com', 'violated-directive' => 'script-src'],
                userId: $user->id
            );

            // Resolve one
            $logger->resolveViolation($v1->id);

            $stats = $logger->getViolationStats(
                from: now()->subDay(),
                to: now()
            );

            expect($stats['total'])->toBe(2);
            expect($stats['unresolved'])->toBe(1);
        });

        test('statistics_can_be_filtered_by_date_range', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Create old violation
            $oldViolation = CspViolation::factory()->create([
                'created_at' => now()->subDays(10),
                'user_id' => $user->id,
            ]);

            // Create recent violation
            $recentViolation = $logger->logViolation(
                ['document-uri' => 'https://example.com', 'violated-directive' => 'script-src'],
                userId: $user->id
            );

            // Get stats for last 5 days
            $stats = $logger->getViolationStats(
                from: now()->subDays(5),
                to: now()
            );

            expect($stats['total'])->toBe(1); // Only recent violation
        });
    });

    describe('Top Violators', function () {
        test('top_violators_are_identified', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Create violations from different IPs
            for ($i = 0; $i < 5; $i++) {
                $logger->logViolation(
                    [
                        'document-uri' => 'https://example.com',
                        'violated-directive' => 'script-src',
                    ],
                    userId: $user->id
                );
            }

            $violators = $logger->getTopViolators(limit: 10, since: now()->subDay());

            expect($violators)->not->toBeEmpty();
            expect($violators->first())->toHaveKey('violation_count');
        });

        test('violators_are_sorted_by_count', function () {
            $logger = app(CspViolationLogger::class);
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            // Create 5 violations for user1
            for ($i = 0; $i < 5; $i++) {
                CspViolation::factory()->create([
                    'ip_address' => '192.168.1.1',
                    'user_id' => $user1->id,
                ]);
            }

            // Create 3 violations for user2
            for ($i = 0; $i < 3; $i++) {
                CspViolation::factory()->create([
                    'ip_address' => '192.168.1.2',
                    'user_id' => $user2->id,
                ]);
            }

            $violators = $logger->getTopViolators(limit: 10);

            expect($violators->first()->violation_count)->toBeGreaterThanOrEqual(
                $violators->last()->violation_count
            );
        });
    });

    describe('Violation Analysis', function () {
        test('violation_trends_are_tracked', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            // Create violations over multiple days
            for ($day = 0; $day < 3; $day++) {
                for ($i = 0; $i < 2; $i++) {
                    CspViolation::factory()->create([
                        'created_at' => now()->subDays($day),
                        'user_id' => $user->id,
                    ]);
                }
            }

            $trends = $logger->getViolationTrends(days: 7, tenantId: $user->tenant_id);

            expect($trends)->toBeArray();
            expect(count($trends))->toBeGreaterThan(0);
        });

        test('blocked_resources_are_analyzed', function () {
            $logger = app(CspViolationLogger::class);

            // Create violations with different blocked URIs
            CspViolation::factory()->create([
                'blocked_uri' => 'https://cdn.example.com/script1.js',
            ]);

            CspViolation::factory()->create([
                'blocked_uri' => 'https://cdn.example.com/script1.js',
            ]);

            CspViolation::factory()->create([
                'blocked_uri' => 'https://other.com/script.js',
            ]);

            $resources = $logger->getBlockedResources(limit: 10);

            expect($resources)->not->toBeEmpty();

            $topResource = $resources->first();
            expect($topResource->count)->toBeGreaterThanOrEqual(1);
        });

        test('pattern_analysis_identifies_repeated_directives', function () {
            $logger = app(CspViolationLogger::class);

            // Create many violations for same directive
            for ($i = 0; $i < 15; $i++) {
                CspViolation::factory()->create([
                    'violated_directive' => 'script-src',
                ]);
            }

            $patterns = $logger->analyzePatterns();

            expect($patterns['repeated_directives'])->toHaveKey('script-src');
            expect($patterns['repeated_directives']['script-src'])->toBeGreaterThan(10);
        });

        test('pattern_analysis_identifies_suspicious_ips', function () {
            $logger = app(CspViolationLogger::class);

            // Create many violations from same IP
            for ($i = 0; $i < 25; $i++) {
                CspViolation::factory()->create([
                    'ip_address' => '192.168.1.100',
                ]);
            }

            $patterns = $logger->analyzePatterns();

            expect($patterns['suspicious_ips'])->toHaveKey('192.168.1.100');
            expect($patterns['suspicious_ips']['192.168.1.100'])->toBeGreaterThan(20);
        });

        test('pattern_analysis_identifies_high_severity_directives', function () {
            $logger = app(CspViolationLogger::class);

            // Create critical violations
            for ($i = 0; $i < 5; $i++) {
                CspViolation::factory()->create([
                    'violated_directive' => 'script-src',
                    'severity' => 'critical',
                ]);
            }

            $patterns = $logger->analyzePatterns();

            expect($patterns['high_severity_directives'])->toHaveKey('script-src');
        });
    });

    describe('Violation Resolution', function () {
        test('violations_can_be_resolved', function () {
            $logger = app(CspViolationLogger::class);
            $user = User::factory()->create();

            $violation = $logger->logViolation(
                ['document-uri' => 'https://example.com', 'violated-directive' => 'script-src'],
                userId: $user->id
            );

            expect($violation->resolved_at)->toBeNull();

            $logger->resolveViolation($violation->id);

            $resolved = CspViolation::find($violation->id);

            expect($resolved->resolved_at)->not->toBeNull();
        });

        test('get_recent_critical_violations', function () {
            $logger = app(CspViolationLogger::class);

            // Create critical violations
            for ($i = 0; $i < 3; $i++) {
                CspViolation::factory()->create([
                    'severity' => 'critical',
                    'created_at' => now()->subMinutes($i),
                ]);
            }

            // Create non-critical
            CspViolation::factory()->create([
                'severity' => 'low',
            ]);

            $critical = $logger->getRecentCritical(limit: 10);

            expect($critical)->toHaveCount(3);
            expect($critical->first()->severity)->toBe('critical');
        });
    });

    describe('Database Model Scopes', function () {
        test('scope_by_directive', function () {
            CspViolation::factory()->create([
                'violated_directive' => 'script-src',
            ]);

            CspViolation::factory()->create([
                'violated_directive' => 'style-src',
            ]);

            $scriptViolations = CspViolation::byDirective('script-src')->get();

            expect($scriptViolations)->toHaveCount(1);
            expect($scriptViolations->first()->violated_directive)->toBe('script-src');
        });

        test('scope_by_severity', function () {
            CspViolation::factory()->create(['severity' => 'critical']);
            CspViolation::factory()->create(['severity' => 'high']);
            CspViolation::factory()->create(['severity' => 'medium']);

            $critical = CspViolation::bySeverity('critical')->get();

            expect($critical)->toHaveCount(1);
        });

        test('scope_unresolved', function () {
            $v1 = CspViolation::factory()->create(['resolved_at' => null]);
            $v2 = CspViolation::factory()->create(['resolved_at' => now()]);

            $unresolved = CspViolation::unresolved()->get();

            expect($unresolved)->toHaveCount(1);
            expect($unresolved->first()->id)->toBe($v1->id);
        });

        test('scope_for_user', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            CspViolation::factory()->create(['user_id' => $user1->id]);
            CspViolation::factory()->create(['user_id' => $user2->id]);

            $violations = CspViolation::forUser($user1->id)->get();

            expect($violations)->toHaveCount(1);
            expect($violations->first()->user_id)->toBe($user1->id);
        });

        test('scope_date_range', function () {
            CspViolation::factory()->create([
                'created_at' => now()->subDays(10),
            ]);

            CspViolation::factory()->create([
                'created_at' => now()->subDays(2),
            ]);

            CspViolation::factory()->create([
                'created_at' => now(),
            ]);

            $recent = CspViolation::dateRange(
                now()->subDays(5),
                now()
            )->get();

            expect($recent)->toHaveCount(2);
        });
    });
});
