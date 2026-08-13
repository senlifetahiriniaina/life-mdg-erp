<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Shared\Services\BaseService;

class RealTimeAlertService extends BaseService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CONDITION_OPERATORS = ['greater_than', 'less_than', 'equals', 'range', 'custom'];
    private const NOTIFICATION_CHANNELS = ['email', 'sms', 'slack', 'in_app'];
    private const ALERT_STATUSES = ['triggered', 'acknowledged', 'resolved', 'snoozed'];
    private const ESCALATION_LEVELS = ['none', 'manager', 'director', 'executive'];

    /**
     * Create an alert rule with conditions.
     *
     * @param  array{name: string, metric: string, operator: string, threshold: float, enabled?: bool, company_id: int, created_by: int, description?: string}  $ruleData
     * @return array{id: int, name: string, metric: string, operator: string, threshold: float, enabled: bool}
     */
    public function createAlertRule(array $ruleData): array
    {
        try {
            if (!in_array($ruleData['operator'], self::CONDITION_OPERATORS)) {
                throw new \InvalidArgumentException("Invalid operator: {$ruleData['operator']}");
            }

            $ruleId = DB::table('bi_alert_rules')->insertGetId([
                'name'           => $ruleData['name'],
                'metric'         => $ruleData['metric'],
                'operator'       => $ruleData['operator'],
                'threshold'      => $ruleData['threshold'],
                'enabled'        => $ruleData['enabled'] ?? true,
                'company_id'     => $ruleData['company_id'],
                'created_by'     => $ruleData['created_by'],
                'description'    => $ruleData['description'] ?? null,
                'last_evaluated' => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Log::info('Alert rule created', [
                'rule_id'  => $ruleId,
                'name'     => $ruleData['name'],
                'operator' => $ruleData['operator'],
            ]);

            return [
                'id'        => $ruleId,
                'name'      => $ruleData['name'],
                'metric'    => $ruleData['metric'],
                'operator'  => $ruleData['operator'],
                'threshold' => $ruleData['threshold'],
                'enabled'   => $ruleData['enabled'] ?? true,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create alert rule', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Evaluate if metric meets alert condition threshold.
     *
     * @param  int  $ruleId
     * @param  float  $metricValue
     * @return array{triggered: bool, reason: string|null}
     */
    public function evaluateAlertRule(int $ruleId, float $metricValue): array
    {
        try {
            $rule = DB::table('bi_alert_rules')->find($ruleId);
            if (!$rule) {
                throw new \InvalidArgumentException("Rule {$ruleId} not found");
            }

            if (!$rule->enabled) {
                return ['triggered' => false, 'reason' => 'Rule is disabled'];
            }

            $triggered = $this->evaluateCondition($rule->operator, $metricValue, (float) $rule->threshold);

            $reason = null;
            if ($triggered) {
                $reason = sprintf(
                    "Metric %s (%f) meets condition %s threshold (%f)",
                    $rule->metric,
                    $metricValue,
                    $rule->operator,
                    $rule->threshold
                );
            }

            // Update last evaluated timestamp
            DB::table('bi_alert_rules')
                ->where('id', $ruleId)
                ->update(['last_evaluated' => now()]);

            Log::debug('Alert rule evaluated', [
                'rule_id'  => $ruleId,
                'metric'   => $rule->metric,
                'triggered' => $triggered,
            ]);

            return ['triggered' => $triggered, 'reason' => $reason];
        } catch (\Throwable $e) {
            Log::error('Failed to evaluate alert rule', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Evaluate multiple conditions with AND/OR logic.
     *
     * @param  array<array{ruleId: int, metricValue: float}>  $conditions
     * @param  string  $logic  'AND' or 'OR'
     * @return array{triggered: bool, details: array}
     */
    public function evaluateMultipleConditions(array $conditions, string $logic = 'AND'): array
    {
        try {
            $results = [];

            foreach ($conditions as $condition) {
                $evaluation = $this->evaluateAlertRule($condition['ruleId'], $condition['metricValue']);
                $results[] = $evaluation;
            }

            $triggered = match (strtoupper($logic)) {
                'AND' => collect($results)->every(fn ($r) => $r['triggered']),
                'OR'  => collect($results)->some(fn ($r) => $r['triggered']),
                default => false,
            };

            Log::debug('Multiple conditions evaluated', [
                'condition_count' => count($conditions),
                'logic'           => $logic,
                'triggered'       => $triggered,
            ]);

            return [
                'triggered' => $triggered,
                'details'   => $results,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to evaluate multiple conditions', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect threshold breach and monitor value changes.
     *
     * @param  string  $metricName
     * @param  float  $currentValue
     * @param  float  $threshold
     * @param  string  $operator
     * @return array{breached: bool, previousValue: float|null, changePercent: float}
     */
    public function detectThresholdBreach(
        string $metricName,
        float $currentValue,
        float $threshold,
        string $operator = 'greater_than'
    ): array {
        try {
            $cacheKey    = "metric:{$metricName}:last";
            $previousValue = Cache::get($cacheKey);

            $breached = $this->evaluateCondition($operator, $currentValue, $threshold);

            $changePercent = 0;
            if ($previousValue !== null) {
                $changePercent = (($currentValue - $previousValue) / abs($previousValue)) * 100;
            }

            // Store current value
            Cache::put($cacheKey, $currentValue, 86400); // 24 hours

            Log::debug('Threshold breach detected', [
                'metric'      => $metricName,
                'breached'    => $breached,
                'current'     => $currentValue,
                'threshold'   => $threshold,
                'change_pct'  => $changePercent,
            ]);

            return [
                'breached'      => $breached,
                'previousValue' => $previousValue,
                'changePercent' => round($changePercent, 2),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to detect threshold breach', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Trigger alert and queue notifications.
     *
     * @param  int  $ruleId
     * @param  array{metricValue: float, entityType?: string, entityId?: int, context?: array}  $alertData
     * @return array{alertId: int, status: string, recipientCount: int}
     */
    public function triggerAlert(int $ruleId, array $alertData): array
    {
        try {
            $rule = DB::table('bi_alert_rules')->find($ruleId);
            if (!$rule) {
                throw new \InvalidArgumentException("Rule {$ruleId} not found");
            }

            // Check if duplicate alert already exists
            $recentAlert = DB::table('bi_alerts')
                ->where('alert_rule_id', $ruleId)
                ->where('status', 'triggered')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if ($recentAlert) {
                Log::debug('Duplicate alert suppressed', ['rule_id' => $ruleId]);
                return [
                    'alertId'        => $recentAlert->id,
                    'status'         => 'duplicate_suppressed',
                    'recipientCount' => 0,
                ];
            }

            // Create alert record
            $alertId = DB::table('bi_alerts')->insertGetId([
                'alert_rule_id'  => $ruleId,
                'metric_value'   => $alertData['metricValue'],
                'status'         => 'triggered',
                'entity_type'    => $alertData['entityType'] ?? null,
                'entity_id'      => $alertData['entityId'] ?? null,
                'context'        => json_encode($alertData['context'] ?? []),
                'triggered_at'   => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // Get recipients and queue notifications
            $recipients = $this->getAlertRecipients($ruleId);
            $recipientCount = count($recipients);

            foreach ($recipients as $recipient) {
                Queue::push(new \Modules\BI\Jobs\SendAlertNotificationJob(
                    $alertId,
                    $recipient['channel'],
                    $recipient['address']
                ));
            }

            // Initialize escalation policy
            $escalationPolicy = DB::table('bi_escalation_policies')
                ->where('alert_rule_id', $ruleId)
                ->first();

            if ($escalationPolicy) {
                DB::table('bi_alerts')
                    ->where('id', $alertId)
                    ->update([
                        'escalation_policy_id' => $escalationPolicy->id,
                        'next_escalation_at'   => now()->addMinutes($escalationPolicy->initial_wait_minutes),
                    ]);
            }

            Log::info('Alert triggered', [
                'alert_id'        => $alertId,
                'rule_id'         => $ruleId,
                'recipient_count' => $recipientCount,
            ]);

            return [
                'alertId'        => $alertId,
                'status'         => 'triggered',
                'recipientCount' => $recipientCount,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to trigger alert', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Escalate unacknowledged alert to higher authority.
     *
     * @param  int  $alertId
     * @param  array{reason?: string, escalationLevel?: string}  $options
     * @return array{escalated: bool, newLevel: string, recipients: array}
     */
    public function escalateAlert(int $alertId, array $options = []): array
    {
        try {
            $alert = DB::table('bi_alerts')->find($alertId);
            if (!$alert) {
                throw new \InvalidArgumentException("Alert {$alertId} not found");
            }

            if ($alert->status !== 'triggered') {
                return [
                    'escalated' => false,
                    'newLevel'  => $alert->escalation_level ?? 'none',
                    'recipients' => [],
                ];
            }

            $escalationPolicy = DB::table('bi_escalation_policies')
                ->find($alert->escalation_policy_id);

            if (!$escalationPolicy) {
                return [
                    'escalated' => false,
                    'newLevel'  => 'none',
                    'recipients' => [],
                ];
            }

            // Determine next escalation level
            $currentLevel = $alert->escalation_level ?? 'none';
            $levels       = ['none', 'manager', 'director', 'executive'];
            $currentIndex = array_search($currentLevel, $levels);
            $nextIndex    = $currentIndex + 1;

            if ($nextIndex >= count($levels)) {
                Log::warning('Alert already at max escalation level', ['alert_id' => $alertId]);
                return [
                    'escalated' => false,
                    'newLevel'  => 'executive',
                    'recipients' => [],
                ];
            }

            $newLevel = $levels[$nextIndex];

            // Update alert
            DB::table('bi_alerts')
                ->where('id', $alertId)
                ->update([
                    'escalation_level'   => $newLevel,
                    'escalation_reason'  => $options['reason'] ?? 'Automatic escalation - no acknowledgment',
                    'escalation_count'   => ($alert->escalation_count ?? 0) + 1,
                    'last_escalated_at'  => now(),
                    'next_escalation_at' => now()->addMinutes($escalationPolicy->escalation_wait_minutes),
                ]);

            // Get recipients for new level
            $recipients = $this->getAlertRecipientsForLevel($alert->alert_rule_id, $newLevel);

            // Queue notifications
            foreach ($recipients as $recipient) {
                Queue::push(new \Modules\BI\Jobs\SendAlertNotificationJob(
                    $alertId,
                    $recipient['channel'],
                    $recipient['address']
                ));
            }

            Log::info('Alert escalated', [
                'alert_id'   => $alertId,
                'new_level'  => $newLevel,
                'recipients' => count($recipients),
            ]);

            return [
                'escalated'  => true,
                'newLevel'   => $newLevel,
                'recipients' => $recipients,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to escalate alert', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Acknowledge alert with optional user notes.
     *
     * @param  int  $alertId
     * @param  array{acknowledgedBy: int, notes?: string}  $data
     * @return array{alertId: int, status: string, acknowledgedAt: string}
     */
    public function acknowledgeAlert(int $alertId, array $data): array
    {
        try {
            $alert = DB::table('bi_alerts')->find($alertId);
            if (!$alert) {
                throw new \InvalidArgumentException("Alert {$alertId} not found");
            }

            DB::table('bi_alerts')
                ->where('id', $alertId)
                ->update([
                    'status'              => 'acknowledged',
                    'acknowledged_by'     => $data['acknowledgedBy'],
                    'acknowledged_at'     => now(),
                    'acknowledgment_notes' => $data['notes'] ?? null,
                    'updated_at'          => now(),
                ]);

            Log::info('Alert acknowledged', [
                'alert_id'        => $alertId,
                'acknowledged_by' => $data['acknowledgedBy'],
            ]);

            return [
                'alertId'        => $alertId,
                'status'         => 'acknowledged',
                'acknowledgedAt' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to acknowledge alert', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Snooze alert (temporarily suppress) for specified duration.
     *
     * @param  int  $alertId
     * @param  array{snoozeMinutes: int, snoozedBy: int, reason?: string}  $data
     * @return array{alertId: int, status: string, snoozeUntil: string}
     */
    public function snoozeAlert(int $alertId, array $data): array
    {
        try {
            $alert = DB::table('bi_alerts')->find($alertId);
            if (!$alert) {
                throw new \InvalidArgumentException("Alert {$alertId} not found");
            }

            $snoozeUntil = now()->addMinutes($data['snoozeMinutes']);

            DB::table('bi_alerts')
                ->where('id', $alertId)
                ->update([
                    'status'         => 'snoozed',
                    'snoozed_by'     => $data['snoozedBy'],
                    'snoozed_at'     => now(),
                    'snooze_until'   => $snoozeUntil,
                    'snooze_reason'  => $data['reason'] ?? null,
                    'updated_at'     => now(),
                ]);

            Log::info('Alert snoozed', [
                'alert_id'       => $alertId,
                'snooze_minutes' => $data['snoozeMinutes'],
                'snooze_until'   => $snoozeUntil,
            ]);

            return [
                'alertId'     => $alertId,
                'status'      => 'snoozed',
                'snoozeUntil' => $snoozeUntil->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to snooze alert', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get alert recipients based on rule configuration (by role, team, individual).
     *
     * @param  int  $ruleId
     * @return array<array{channel: string, address: string, userId?: int}>
     */
    public function getAlertRecipients(int $ruleId): array
    {
        try {
            $recipients = [];

            $recipientConfigs = DB::table('bi_alert_recipients')
                ->where('alert_rule_id', $ruleId)
                ->get();

            foreach ($recipientConfigs as $config) {
                if ($config->recipient_type === 'user') {
                    $user = DB::table('users')->find($config->recipient_id);
                    if ($user) {
                        if ($config->channel === 'email' && $user->email) {
                            $recipients[] = [
                                'channel' => 'email',
                                'address' => $user->email,
                                'userId'  => $user->id,
                            ];
                        }
                    }
                } elseif ($config->recipient_type === 'role') {
                    // Get users with this role
                    $users = DB::table('users')
                        ->whereHas('roles', fn ($q) => $q->where('name', $config->recipient_id))
                        ->get();

                    foreach ($users as $user) {
                        if ($user->email) {
                            $recipients[] = [
                                'channel' => $config->channel,
                                'address' => $user->email,
                                'userId'  => $user->id,
                            ];
                        }
                    }
                } elseif ($config->recipient_type === 'webhook') {
                    $recipients[] = [
                        'channel' => 'webhook',
                        'address' => $config->recipient_id,
                    ];
                }
            }

            return array_unique($recipients, SORT_REGULAR);
        } catch (\Throwable $e) {
            Log::error('Failed to get alert recipients', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get alert recipients for specific escalation level.
     *
     * @param  int  $ruleId
     * @param  string  $level
     * @return array<array{channel: string, address: string}>
     */
    public function getAlertRecipientsForLevel(int $ruleId, string $level): array
    {
        try {
            $recipients = [];

            $escalationConfig = DB::table('bi_escalation_policies')
                ->where('alert_rule_id', $ruleId)
                ->first();

            if (!$escalationConfig) {
                return [];
            }

            $escalationRoles = json_decode($escalationConfig->escalation_roles, true) ?? [];

            if (isset($escalationRoles[$level])) {
                $roles = (array) $escalationRoles[$level];
                foreach ($roles as $role) {
                    $users = DB::table('users')
                        ->whereHas('roles', fn ($q) => $q->where('name', $role))
                        ->get();

                    foreach ($users as $user) {
                        if ($user->email) {
                            $recipients[] = [
                                'channel' => 'email',
                                'address' => $user->email,
                            ];
                        }
                    }
                }
            }

            return $recipients;
        } catch (\Throwable $e) {
            Log::error('Failed to get recipients for escalation level', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Apply DND (Do Not Disturb) schedule to skip alerts during quiet hours.
     *
     * @param  int  $ruleId
     * @param  array{startTime: string, endTime: string, timezone: string, daysOfWeek?: array<int>}  $schedule
     * @return array{scheduled: bool, startTime: string, endTime: string}
     */
    public function applyDoNotDisturbSchedule(int $ruleId, array $schedule): array
    {
        try {
            DB::table('bi_alert_rules')
                ->where('id', $ruleId)
                ->update([
                    'dnd_start_time' => $schedule['startTime'],
                    'dnd_end_time'   => $schedule['endTime'],
                    'dnd_timezone'   => $schedule['timezone'],
                    'dnd_days'       => json_encode($schedule['daysOfWeek'] ?? [0, 1, 2, 3, 4, 5, 6]),
                ]);

            Log::info('DND schedule applied', [
                'rule_id'   => $ruleId,
                'start_time' => $schedule['startTime'],
                'end_time'  => $schedule['endTime'],
            ]);

            return [
                'scheduled' => true,
                'startTime' => $schedule['startTime'],
                'endTime'   => $schedule['endTime'],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to apply DND schedule', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Deduplicate alerts to prevent spam by grouping similar alerts.
     *
     * @param  string  $metricName
     * @param  int  $groupingWindowMinutes
     * @return array{deduplicated: int, groups: array}
     */
    public function deduplicateAlerts(string $metricName, int $groupingWindowMinutes = 5): array
    {
        try {
            $cutoff = now()->subMinutes($groupingWindowMinutes);

            // Get recent alerts for this metric
            $alerts = DB::table('bi_alerts')
                ->join('bi_alert_rules', 'bi_alerts.alert_rule_id', '=', 'bi_alert_rules.id')
                ->where('bi_alert_rules.metric', $metricName)
                ->where('bi_alerts.created_at', '>=', $cutoff)
                ->where('bi_alerts.status', 'triggered')
                ->get();

            // Group by rule and suppress duplicates
            $groups = [];
            $deduplicatedCount = 0;

            foreach ($alerts->groupBy('alert_rule_id') as $ruleId => $ruleAlerts) {
                if ($ruleAlerts->count() > 1) {
                    $primaryAlert = $ruleAlerts->first();
                    $duplicates   = $ruleAlerts->skip(1);

                    // Mark duplicates as suppressed
                    DB::table('bi_alerts')
                        ->whereIn('id', $duplicates->pluck('id')->all())
                        ->update(['status' => 'deduplicated']);

                    $deduplicatedCount += $duplicates->count();

                    $groups[] = [
                        'rule_id'    => $ruleId,
                        'primary_id' => $primaryAlert->id,
                        'duplicates' => $duplicates->count(),
                    ];
                }
            }

            Log::info('Alerts deduplicated', [
                'metric'      => $metricName,
                'deduplicated' => $deduplicatedCount,
                'groups'      => count($groups),
            ]);

            return [
                'deduplicated' => $deduplicatedCount,
                'groups'       => $groups,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to deduplicate alerts', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send alert notification via specified channel.
     *
     * @param  int  $alertId
     * @param  string  $channel
     * @param  array  $notificationData
     * @return array{sent: bool, channel: string, messageId?: string}
     */
    public function sendAlertNotification(int $alertId, string $channel, array $notificationData = []): array
    {
        try {
            if (!in_array($channel, self::NOTIFICATION_CHANNELS)) {
                throw new \InvalidArgumentException("Unsupported notification channel: {$channel}");
            }

            $alert = DB::table('bi_alerts')->find($alertId);
            if (!$alert) {
                throw new \InvalidArgumentException("Alert {$alertId} not found");
            }

            $messageId = null;

            // Route to channel-specific handler
            $messageId = match ($channel) {
                'email'   => $this->sendEmailNotification($alert, $notificationData),
                'sms'     => $this->sendSmsNotification($alert, $notificationData),
                'slack'   => $this->sendSlackNotification($alert, $notificationData),
                'in_app'  => $this->sendInAppNotification($alert, $notificationData),
                default   => null,
            };

            Log::info('Alert notification sent', [
                'alert_id'  => $alertId,
                'channel'   => $channel,
                'message_id' => $messageId,
            ]);

            return [
                'sent'     => true,
                'channel'  => $channel,
                'messageId' => $messageId,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to send alert notification', ['error' => $e->getMessage()]);
            return [
                'sent'    => false,
                'channel' => $channel,
            ];
        }
    }

    /**
     * Track alert metrics (frequency, MTTR - mean time to resolution).
     *
     * @param  int  $ruleId
     * @return array{totalAlerts: int, avgMTTR: float, alertFrequency: array, acknowledgedCount: int}
     */
    public function trackAlertMetrics(int $ruleId): array
    {
        try {
            $alerts = DB::table('bi_alerts')
                ->where('alert_rule_id', $ruleId)
                ->where('created_at', '>=', now()->subDays(30))
                ->get();

            $totalAlerts = $alerts->count();

            // Calculate MTTR (time between trigger and acknowledgement)
            $mttrValues = [];
            foreach ($alerts as $alert) {
                if ($alert->acknowledged_at && $alert->triggered_at) {
                    $mttr = Carbon::parse($alert->acknowledged_at)
                        ->diffInMinutes(Carbon::parse($alert->triggered_at));
                    $mttrValues[] = $mttr;
                }
            }

            $avgMTTR = count($mttrValues) > 0 ? array_sum($mttrValues) / count($mttrValues) : 0;

            // Alert frequency (by day)
            $alertFrequency = [];
            foreach ($alerts->groupBy(fn ($a) => Carbon::parse($a->created_at)->toDateString()) as $date => $dayAlerts) {
                $alertFrequency[] = [
                    'date'  => $date,
                    'count' => $dayAlerts->count(),
                ];
            }

            $acknowledgedCount = $alerts->where('status', 'acknowledged')->count();

            $metrics = [
                'totalAlerts'       => $totalAlerts,
                'avgMTTR'           => round($avgMTTR, 2),
                'alertFrequency'    => $alertFrequency,
                'acknowledgedCount' => $acknowledgedCount,
            ];

            Log::debug('Alert metrics retrieved', [
                'rule_id'       => $ruleId,
                'total_alerts'  => $totalAlerts,
                'avg_mttr'      => $avgMTTR,
            ]);

            return $metrics;
        } catch (\Throwable $e) {
            Log::error('Failed to track alert metrics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get alert history with filtering options.
     *
     * @param  int  $ruleId
     * @param  array{status?: string, limit?: int, offset?: int}  $filters
     * @return array{total: int, alerts: array}
     */
    public function getAlertHistory(int $ruleId, array $filters = []): array
    {
        try {
            $query = DB::table('bi_alerts')
                ->where('alert_rule_id', $ruleId);

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $total = $query->count();

            $limit  = $filters['limit'] ?? 50;
            $offset = $filters['offset'] ?? 0;

            $alerts = $query->orderByDesc('created_at')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'status'     => $a->status,
                    'metric_value' => $a->metric_value,
                    'triggered_at' => $a->triggered_at,
                    'acknowledged_at' => $a->acknowledged_at,
                ])
                ->all();

            Log::debug('Alert history retrieved', [
                'rule_id' => $ruleId,
                'total'   => $total,
            ]);

            return [
                'total'  => $total,
                'alerts' => $alerts,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get alert history', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate alert condition syntax and threshold values.
     *
     * @param  string  $operator
     * @param  float  $threshold
     * @param  array  $constraints
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateAlertCondition(string $operator, float $threshold, array $constraints = []): array
    {
        try {
            $errors = [];

            if (!in_array($operator, self::CONDITION_OPERATORS)) {
                $errors[] = "Invalid operator: {$operator}";
            }

            if ($operator === 'range' && count($constraints) < 2) {
                $errors[] = "Range operator requires min and max constraints";
            }

            if ($operator === 'custom' && !isset($constraints['expression'])) {
                $errors[] = "Custom operator requires expression constraint";
            }

            Log::debug('Alert condition validated', [
                'operator' => $operator,
                'valid'    => empty($errors),
            ]);

            return [
                'valid'  => empty($errors),
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to validate alert condition', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create escalation policy defining escalation chain.
     *
     * @param  int  $ruleId
     * @param  array{initialWaitMinutes: int, escalationWaitMinutes: int, escalationRoles: array}  $policyData
     * @return array{id: int, ruleId: int, levels: array}
     */
    public function createEscalationPolicy(int $ruleId, array $policyData): array
    {
        try {
            $policyId = DB::table('bi_escalation_policies')->insertGetId([
                'alert_rule_id'        => $ruleId,
                'initial_wait_minutes' => $policyData['initialWaitMinutes'] ?? 5,
                'escalation_wait_minutes' => $policyData['escalationWaitMinutes'] ?? 15,
                'escalation_roles'     => json_encode($policyData['escalationRoles'] ?? []),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            Log::info('Escalation policy created', [
                'policy_id' => $policyId,
                'rule_id'   => $ruleId,
            ]);

            return [
                'id'     => $policyId,
                'ruleId' => $ruleId,
                'levels' => array_keys($policyData['escalationRoles'] ?? []),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create escalation policy', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get active (triggered, unacknowledged) alerts.
     *
     * @param  array{ruleId?: int, limit?: int}  $filters
     * @return array{total: int, alerts: array}
     */
    public function getActiveAlerts(array $filters = []): array
    {
        try {
            $query = DB::table('bi_alerts')
                ->where('status', 'triggered')
                ->orderByDesc('triggered_at');

            if (isset($filters['ruleId'])) {
                $query->where('alert_rule_id', $filters['ruleId']);
            }

            $limit = $filters['limit'] ?? 100;

            $total = $query->count();

            $alerts = $query->limit($limit)
                ->get()
                ->map(fn ($a) => [
                    'id'            => $a->id,
                    'rule_id'       => $a->alert_rule_id,
                    'metric_value'  => $a->metric_value,
                    'triggered_at'  => $a->triggered_at,
                    'escalation_level' => $a->escalation_level,
                ])
                ->all();

            Log::debug('Active alerts retrieved', ['total' => $total]);

            return [
                'total'  => $total,
                'alerts' => $alerts,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get active alerts', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function evaluateCondition(string $operator, float $value, float $threshold): bool
    {
        return match ($operator) {
            'greater_than' => $value > $threshold,
            'less_than'    => $value < $threshold,
            'equals'       => $value == $threshold,
            'range'        => false, // Requires array of bounds
            'custom'       => false, // Requires custom logic
            default        => false,
        };
    }

    private function sendEmailNotification(object $alert, array $data): ?string
    {
        // Simulate email sending
        $messageId = 'email_' . uniqid();
        Log::debug('Email notification queued', ['message_id' => $messageId]);
        return $messageId;
    }

    private function sendSmsNotification(object $alert, array $data): ?string
    {
        // Simulate SMS sending
        $messageId = 'sms_' . uniqid();
        Log::debug('SMS notification queued', ['message_id' => $messageId]);
        return $messageId;
    }

    private function sendSlackNotification(object $alert, array $data): ?string
    {
        // Simulate Slack webhook
        $messageId = 'slack_' . uniqid();
        Log::debug('Slack notification queued', ['message_id' => $messageId]);
        return $messageId;
    }

    private function sendInAppNotification(object $alert, array $data): ?string
    {
        // Create in-app notification record
        $notificationId = DB::table('notifications')->insertGetId([
            'type'          => 'alert',
            'data'          => json_encode(['alert_id' => $alert->id]),
            'created_at'    => now(),
        ]);

        Log::debug('In-app notification created', ['notification_id' => $notificationId]);
        return "inapp_{$notificationId}";
    }
}
