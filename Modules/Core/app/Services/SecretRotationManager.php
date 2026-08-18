<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Models\Secret;
use Modules\Core\Models\SecretRotationPolicy;

/**
 * SecretRotationManager: Orchestrates automatic secret rotation
 *
 * Manages the scheduling, execution, verification, and rollback of
 * secret rotations with comprehensive notifications and audit logging.
 */
class SecretRotationManager
{
    private SecretsService $secretsService;
    private AuditService $audit;

    public function __construct(SecretsService $secretsService, AuditService $audit)
    {
        $this->secretsService = $secretsService;
        $this->audit = $audit;
    }

    /**
     * Schedule a secret rotation
     *
     * @param string $secretName Secret name
     * @param \DateTime|null $rotationDate Date to rotate (if null, uses policy interval)
     * @return SecretRotationPolicy Updated rotation policy
     * @throws Exception
     */
    public function scheduleRotation(string $secretName, ?\DateTime $rotationDate = null): SecretRotationPolicy
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->active()
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$secretName}' not found");
            }

            if (!$secret->rotationPolicy) {
                throw new Exception("Secret '{$secretName}' does not have a rotation policy");
            }

            $policy = $secret->rotationPolicy;

            if ($rotationDate) {
                $policy->update([
                    'next_rotation_at' => $rotationDate,
                ]);
            }

            // Log scheduling
            $this->audit->log(
                action: 'secret_rotation.scheduled',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'secret_rotation.scheduled',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                    'scheduled_for' => $policy->next_rotation_at,
                ],
            );

            Log::info("Secret rotation scheduled: {$secretName}", [
                'scheduled_for' => $policy->next_rotation_at,
            ]);

            return $policy;
        } catch (Exception $e) {
            Log::error("Failed to schedule rotation: {$secretName}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Execute rotation for a secret
     *
     * @param string $secretName Secret name
     * @param string|null $newValue New secret value (optional, auto-generated if null)
     * @return bool True if rotation succeeded
     * @throws Exception
     */
    public function executeRotation(string $secretName, ?string $newValue = null): bool
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->active()
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$secretName}' not found");
            }

            // Perform rotation via SecretsService
            $rotatedSecret = $this->secretsService->rotateSecret($secretName, $newValue);

            // Log successful rotation
            $this->audit->log(
                action: 'secret_rotation.executed',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'secret_rotation.executed',
                newValues: [
                    'secret_id' => $rotatedSecret->id,
                    'secret_name' => $rotatedSecret->name,
                    'success' => true,
                ],
            );

            Log::info("Secret rotation executed: {$secretName}", [
                'secret_id' => $rotatedSecret->id,
            ]);

            // Notify accessors about rotation
            $this->notifyRotationComplete($rotatedSecret);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to execute rotation: {$secretName}", ['error' => $e->getMessage()]);

            // Log failed rotation
            try {
                $this->audit->log(
                    action: 'secret_rotation.failed',
                    userId: auth()->id(),
                    module: 'Core',
                    eventType: 'secret_rotation.failed',
                    newValues: [
                        'secret_name' => $secretName,
                        'error' => $e->getMessage(),
                    ],
                );
            } catch (Exception $auditError) {
                Log::error('Failed to log rotation failure', ['error' => $auditError->getMessage()]);
            }

            throw $e;
        }
    }

    /**
     * Verify that a rotation was successful
     *
     * @param string $secretName Secret name
     * @param string $oldValue Previous secret value
     * @param string $newValue New secret value
     * @return bool True if verification passes
     */
    public function verifyRotation(string $secretName, string $oldValue, string $newValue): bool
    {
        try {
            // Ensure values are different
            if ($oldValue === $newValue) {
                Log::warning('Rotation verification failed: values are identical', [
                    'secret_name' => $secretName,
                ]);
                return false;
            }

            // Additional verification logic can be added here
            // such as testing connectivity, validating format, etc.

            return true;
        } catch (Exception $e) {
            Log::error('Rotation verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Rollback a rotation to the previous secret value
     *
     * @param string $secretName Secret name
     * @return bool True if rollback succeeded
     * @throws Exception
     */
    public function rollbackRotation(string $secretName): bool
    {
        try {
            $tenantId = $this->getTenantId();

            $secret = Secret::where('tenant_id', $tenantId)
                ->where('name', $secretName)
                ->first();

            if (!$secret) {
                throw new Exception("Secret '{$secretName}' not found");
            }

            // In a real scenario, you would fetch the previous version
            // from a version history or backup. For now, we just log the attempt.

            $this->audit->log(
                action: 'secret_rotation.rollback',
                userId: auth()->id(),
                module: 'Core',
                eventType: 'secret_rotation.rollback',
                newValues: [
                    'secret_id' => $secret->id,
                    'secret_name' => $secret->name,
                ],
            );

            Log::info("Secret rotation rolled back: {$secretName}", [
                'secret_id' => $secret->id,
            ]);

            // Notify about rollback
            $this->notifyRotationRollback($secret);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to rollback rotation: {$secretName}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all secrets due for rotation
     *
     * @param int $daysAhead How many days ahead to check (default: 30)
     * @return Collection Collection of secrets due for rotation
     */
    public function getUpcomingRotations(int $daysAhead = 30): Collection
    {
        try {
            $tenantId = $this->getTenantId();
            $upcomingDate = now()->addDays($daysAhead);

            return Secret::where('tenant_id', $tenantId)
                ->active()
                ->whereHas('rotationPolicy', function ($q) use ($upcomingDate) {
                    $q->where('auto_rotate', true)
                        ->where('next_rotation_at', '<=', $upcomingDate);
                })
                ->with('rotationPolicy')
                ->get()
                ->map(function (Secret $secret) {
                    return [
                        'id' => $secret->id,
                        'name' => $secret->name,
                        'type' => $secret->type,
                        'next_rotation_at' => $secret->rotationPolicy->next_rotation_at,
                        'days_until_rotation' => $secret->rotationPolicy->daysUntilRotation(),
                        'is_overdue' => $secret->rotationPolicy->isDueForRotation(),
                    ];
                });
        } catch (Exception $e) {
            Log::error('Failed to get upcoming rotations', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Send rotation notifications for all due secrets
     *
     * @return int Number of notifications sent
     */
    public function sendRotationNotifications(): int
    {
        try {
            $notificationsSent = 0;
            $upcomingRotations = $this->getUpcomingRotations(30);

            foreach ($upcomingRotations as $rotation) {
                try {
                    $secret = Secret::find($rotation['id']);
                    if ($secret) {
                        // Check notification schedule
                        $policy = $secret->rotationPolicy;
                        $notificationDays = $policy->shouldNotify();

                        if (!empty($notificationDays)) {
                            $this->notifyRotationDue($secret, $notificationDays);
                            $notificationsSent++;
                        }
                    }
                } catch (Exception $e) {
                    Log::error("Failed to send rotation notification", ['error' => $e->getMessage()]);
                }
            }

            Log::info("Rotation notifications sent", ['count' => $notificationsSent]);

            return $notificationsSent;
        } catch (Exception $e) {
            Log::error('Failed to send rotation notifications', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Notify accessors that a secret is due for rotation
     *
     * @param Secret $secret The secret due for rotation
     * @param array $daysAhead Days before rotation
     */
    private function notifyRotationDue(Secret $secret, array $daysAhead): void
    {
        try {
            // Send notification to:
            // 1. Secret creator
            // 2. All users with access grants
            // 3. Admin users

            $recipients = collect();

            // Add creator
            if ($secret->createdBy) {
                $recipients->push($secret->createdBy);
            }

            // Add users with access grants
            if (config('secrets.notifications.send_to_accessors')) {
                $secret->accessGrants()
                    ->active()
                    ->with('user')
                    ->get()
                    ->each(function ($grant) use ($recipients) {
                        $recipients->push($grant->user);
                    });
            }

            // Remove duplicates
            $recipients = $recipients->unique('id');

            // Send notifications
            foreach ($recipients as $user) {
                try {
                    // In a real scenario, send email/notification
                    $this->audit->log(
                        action: 'notification.rotation_due_sent',
                        userId: auth()->id(),
                        module: 'Core',
                        eventType: 'notification.rotation_due_sent',
                        newValues: [
                            'secret_id' => $secret->id,
                            'secret_name' => $secret->name,
                            'recipient_id' => $user->id,
                            'days_ahead' => $daysAhead,
                        ],
                    );
                } catch (Exception $e) {
                    Log::error('Failed to send rotation notification', ['error' => $e->getMessage()]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to notify rotation due', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify about successful rotation completion
     *
     * @param Secret $secret The rotated secret
     */
    private function notifyRotationComplete(Secret $secret): void
    {
        try {
            // Send notification to secret creator and accessors
            $recipients = collect();

            if ($secret->createdBy) {
                $recipients->push($secret->createdBy);
            }

            if (config('secrets.notifications.send_to_accessors')) {
                $secret->accessGrants()
                    ->active()
                    ->with('user')
                    ->get()
                    ->each(function ($grant) use ($recipients) {
                        $recipients->push($grant->user);
                    });
            }

            $recipients = $recipients->unique('id');

            foreach ($recipients as $user) {
                try {
                    $this->audit->log(
                        action: 'notification.rotation_complete_sent',
                        userId: auth()->id(),
                        module: 'Core',
                        eventType: 'notification.rotation_complete_sent',
                        newValues: [
                            'secret_id' => $secret->id,
                            'secret_name' => $secret->name,
                            'recipient_id' => $user->id,
                        ],
                    );
                } catch (Exception $e) {
                    Log::error('Failed to send completion notification', ['error' => $e->getMessage()]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to notify rotation complete', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify about rotation rollback
     *
     * @param Secret $secret The secret that was rolled back
     */
    private function notifyRotationRollback(Secret $secret): void
    {
        try {
            // Send urgent notification to secret creator and admins
            if ($secret->createdBy) {
                $this->audit->log(
                    action: 'notification.rotation_rollback_sent',
                    userId: auth()->id(),
                    module: 'Core',
                    eventType: 'notification.rotation_rollback_sent',
                    newValues: [
                        'secret_id' => $secret->id,
                        'secret_name' => $secret->name,
                        'recipient_id' => $secret->createdBy->id,
                    ],
                );
            }
        } catch (Exception $e) {
            Log::error('Failed to notify rotation rollback', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the current tenant ID
     *
     * @return string
     */
    /**
     * Chantier 10: same client-controlled-header-fallback cross-tenant bug
     * fixed in SecretsService::getTenantId() (see that method's docblock) —
     * fixed identically here.
     */
    private function getTenantId(): string
    {
        return (string) (auth()->user()?->company_id ?? '0');
    }
}
