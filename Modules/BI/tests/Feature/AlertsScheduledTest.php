<?php

declare(strict_types=1);

use App\Models\User;
use Modules\BI\Models\AlertEvent;
use Modules\BI\Models\KpiAlert;
use Modules\BI\Models\ScheduledReport;
use Modules\BI\Services\AlertService;

// ─── KpiAlert model ───────────────────────────────────────────────────────────
describe('KpiAlert model', function () {
    it('isActive returns true when is_active is true', function () {
        $alert = KpiAlert::factory()->create(['is_active' => true]);
        expect($alert->isActive())->toBeTrue();
    });

    it('isActive returns false when is_active is false', function () {
        $alert = KpiAlert::factory()->create(['is_active' => false]);
        expect($alert->isActive())->toBeFalse();
    });

    it('evaluate returns true when value is above threshold', function () {
        $alert = KpiAlert::factory()->create(['condition' => 'above', 'threshold' => 100]);
        expect($alert->evaluate(150.0))->toBeTrue();
        expect($alert->evaluate(50.0))->toBeFalse();
    });

    it('evaluate returns true when value is below threshold', function () {
        $alert = KpiAlert::factory()->create(['condition' => 'below', 'threshold' => 50]);
        expect($alert->evaluate(30.0))->toBeTrue();
        expect($alert->evaluate(80.0))->toBeFalse();
    });

    it('trigger increments trigger_count', function () {
        $alert = KpiAlert::factory()->create(['trigger_count' => 0]);
        $alert->trigger(200.0);
        expect($alert->fresh()->trigger_count)->toBe(1);
        expect($alert->fresh()->last_triggered_at)->not->toBeNull();
    });

    it('has events relation', function () {
        $alert = KpiAlert::factory()->create();
        AlertEvent::factory()->create(['alert_id' => $alert->id]);
        expect($alert->events)->toHaveCount(1);
    });
});

// ─── AlertEvent model ─────────────────────────────────────────────────────────
describe('AlertEvent model', function () {
    it('isAcknowledged returns false by default', function () {
        $event = AlertEvent::factory()->create(['acknowledged' => false]);
        expect($event->isAcknowledged())->toBeFalse();
    });

    it('acknowledge marks event acknowledged', function () {
        $user = User::factory()->create();
        $event = AlertEvent::factory()->create(['acknowledged' => false]);
        $event->acknowledge($user->id);
        $event->refresh();
        expect($event->isAcknowledged())->toBeTrue();
        expect($event->acknowledged_by)->toBe($user->id);
        expect($event->acknowledged_at)->not->toBeNull();
    });
});

// ─── ScheduledReport model ────────────────────────────────────────────────────
describe('ScheduledReport model', function () {
    it('isActive returns correct value', function () {
        $active = ScheduledReport::factory()->create(['is_active' => true]);
        $inactive = ScheduledReport::factory()->create(['is_active' => false]);
        expect($active->isActive())->toBeTrue();
        expect($inactive->isActive())->toBeFalse();
    });

    it('isDue returns true when next_send_at is in the past', function () {
        $due = ScheduledReport::factory()->due()->create();
        $soon = ScheduledReport::factory()->create(['next_send_at' => now()->addHour()]);
        expect($due->isDue())->toBeTrue();
        expect($soon->isDue())->toBeFalse();
    });

    it('computeNextSend returns correct future date', function () {
        $daily = ScheduledReport::factory()->create(['schedule' => 'daily']);
        $weekly = ScheduledReport::factory()->create(['schedule' => 'weekly']);
        $monthly = ScheduledReport::factory()->create(['schedule' => 'monthly']);
        $quarterly = ScheduledReport::factory()->create(['schedule' => 'quarterly']);

        expect($daily->computeNextSend()->isAfter(now()))->toBeTrue();
        expect($weekly->computeNextSend()->isAfter(now()->addDays(5)))->toBeTrue();
        expect($monthly->computeNextSend()->isAfter(now()->addDays(27)))->toBeTrue();
        expect($quarterly->computeNextSend()->isAfter(now()->addMonths(2)))->toBeTrue();
    });

    it('markSent increments send_count and updates next_send_at', function () {
        $report = ScheduledReport::factory()->create(['schedule' => 'daily', 'send_count' => 0]);
        $report->markSent();
        $report->refresh();
        expect($report->send_count)->toBe(1);
        expect($report->last_sent_at)->not->toBeNull();
        expect($report->next_send_at->isAfter(now()))->toBeTrue();
    });
});

// ─── AlertService ─────────────────────────────────────────────────────────────
describe('AlertService', function () {
    beforeEach(function () {
        $this->service = app(AlertService::class);
    });

    it('createAlert creates a KpiAlert', function () {
        $alert = $this->service->createAlert([
            'name' => 'Revenue Alert',
            'metric_name' => 'revenue',
            'condition' => 'above',
            'threshold' => 10000,
            'severity' => 'critical',
        ]);
        expect($alert)->toBeInstanceOf(KpiAlert::class);
        expect($alert->name)->toBe('Revenue Alert');
    });

    it('evaluateAlert creates event when threshold breached', function () {
        $alert = KpiAlert::factory()->create([
            'condition' => 'above',
            'threshold' => 100,
            'metric_name' => 'revenue',
            'is_active' => true,
        ]);
        $event = $this->service->evaluateAlert($alert, 150.0);
        expect($event)->toBeInstanceOf(AlertEvent::class);
        expect($event->alert_id)->toBe($alert->id);
        expect((float) $event->triggered_value)->toEqual(150.0);
    });

    it('evaluateAlert returns null when threshold not breached', function () {
        $alert = KpiAlert::factory()->create([
            'condition' => 'above',
            'threshold' => 100,
            'is_active' => true,
        ]);
        $event = $this->service->evaluateAlert($alert, 50.0);
        expect($event)->toBeNull();
    });

    it('evaluateAlert returns null for inactive alert', function () {
        $alert = KpiAlert::factory()->create([
            'condition' => 'above',
            'threshold' => 100,
            'is_active' => false,
        ]);
        $event = $this->service->evaluateAlert($alert, 200.0);
        expect($event)->toBeNull();
    });

    it('checkAlerts evaluates multiple metrics', function () {
        KpiAlert::factory()->create(['metric_name' => 'revenue', 'condition' => 'above', 'threshold' => 100, 'is_active' => true]);
        KpiAlert::factory()->create(['metric_name' => 'errors', 'condition' => 'above', 'threshold' => 10, 'is_active' => true]);
        $events = $this->service->checkAlerts(['revenue' => 200.0, 'errors' => 5.0]);
        expect($events)->toHaveCount(1);
    });

    it('acknowledgeEvent marks event acknowledged', function () {
        $user = User::factory()->create();
        $event = AlertEvent::factory()->create(['acknowledged' => false]);
        $this->service->acknowledgeEvent($event, $user->id);
        expect($event->fresh()->isAcknowledged())->toBeTrue();
    });

    it('getUnacknowledgedEvents returns only unacknowledged events', function () {
        AlertEvent::factory()->create(['acknowledged' => false]);
        AlertEvent::factory()->acknowledged()->create();
        expect($this->service->getUnacknowledgedEvents())->toHaveCount(1);
    });

    it('createScheduledReport sets next_send_at', function () {
        $report = $this->service->createScheduledReport([
            'name' => 'Weekly Sales',
            'schedule' => 'weekly',
            'format' => 'pdf',
            'recipients' => ['user@example.com'],
        ]);
        expect($report)->toBeInstanceOf(ScheduledReport::class);
        expect($report->next_send_at)->not->toBeNull();
    });

    it('processDueReports sends due reports only', function () {
        ScheduledReport::factory()->due()->create();
        ScheduledReport::factory()->create(['next_send_at' => now()->addHour()]);
        $sent = $this->service->processDueReports();
        expect($sent)->toHaveCount(1);
    });
});

// ─── KPI Alerts API ───────────────────────────────────────────────────────────
describe('KPI Alerts API', function () {
    beforeEach(function () {
        actingAsUser('manager');

    });

    it('GET /api/v1/bi/kpi-alerts returns list', function () {
        KpiAlert::factory(3)->create();
        $this->getJson('/api/v1/bi/kpi-alerts')->assertOk()->assertJsonCount(3, 'data');
    });

    it('POST /api/v1/bi/kpi-alerts creates alert', function () {
        $this->postJson('/api/v1/bi/kpi-alerts', [
            'name' => 'Test Alert',
            'metric_name' => 'revenue',
            'condition' => 'above',
            'threshold' => 5000,
            'severity' => 'warning',
        ])->assertCreated()->assertJsonPath('metric_name', 'revenue');
    });

    it('POST /api/v1/bi/kpi-alerts validates required fields', function () {
        $this->postJson('/api/v1/bi/kpi-alerts', [])->assertUnprocessable();
    });

    it('GET /api/v1/bi/kpi-alerts/{kpiAlert} shows alert with events', function () {
        $alert = KpiAlert::factory()->create();
        $this->getJson("/api/v1/bi/kpi-alerts/{$alert->id}")->assertOk()->assertJsonPath('id', $alert->id);
    });

    it('PUT /api/v1/bi/kpi-alerts/{kpiAlert} updates alert', function () {
        $alert = KpiAlert::factory()->create(['threshold' => 100]);
        $this->putJson("/api/v1/bi/kpi-alerts/{$alert->id}", ['threshold' => 200])
            ->assertOk()->assertJsonPath('threshold', '200.0000');
    });

    it('DELETE /api/v1/bi/kpi-alerts/{kpiAlert} deletes alert', function () {
        $alert = KpiAlert::factory()->create();
        $this->deleteJson("/api/v1/bi/kpi-alerts/{$alert->id}")->assertNoContent();
        $this->assertDatabaseMissing('bi_kpi_alerts', ['id' => $alert->id]);
    });

    it('POST /api/v1/bi/kpi-alerts/{kpiAlert}/evaluate evaluates alert', function () {
        $alert = KpiAlert::factory()->create(['condition' => 'above', 'threshold' => 100, 'is_active' => true]);
        $this->postJson("/api/v1/bi/kpi-alerts/{$alert->id}/evaluate", ['value' => 200])
            ->assertOk()->assertJsonPath('triggered', true);
    });

    it('POST /api/v1/bi/kpi-alerts/check checks multiple metrics', function () {
        KpiAlert::factory()->create(['metric_name' => 'sales', 'condition' => 'above', 'threshold' => 1000, 'is_active' => true]);
        $this->postJson('/api/v1/bi/kpi-alerts/check', ['metrics' => ['sales' => 2000]])
            ->assertOk()->assertJsonPath('triggered_count', 1);
    });

    it('GET /api/v1/bi/kpi-alerts/{kpiAlert}/events returns events', function () {
        $alert = KpiAlert::factory()->create();
        AlertEvent::factory(2)->create(['alert_id' => $alert->id]);
        $this->getJson("/api/v1/bi/kpi-alerts/{$alert->id}/events")->assertOk()->assertJsonCount(2, 'data');
    });

    it('GET /api/v1/bi/kpi-alerts/unacknowledged returns unacknowledged events', function () {
        AlertEvent::factory()->create(['acknowledged' => false]);
        AlertEvent::factory()->acknowledged()->create();
        $this->getJson('/api/v1/bi/kpi-alerts/unacknowledged')->assertOk();
    });

    it('POST /api/v1/bi/alert-events/{alertEvent}/acknowledge acknowledges event', function () {
        $event = AlertEvent::factory()->create(['acknowledged' => false]);
        $this->postJson("/api/v1/bi/alert-events/{$event->id}/acknowledge")
            ->assertOk()->assertJsonPath('acknowledged', true);
    });

});

it('unauthenticated request to kpi-alerts returns 401', function () {
    $this->getJson('/api/v1/bi/kpi-alerts')->assertUnauthorized();
});

// ─── Scheduled Reports API ────────────────────────────────────────────────────
describe('Scheduled Reports API', function () {
    beforeEach(function () {
        actingAsUser('manager');

    });

    it('GET /api/v1/bi/scheduled-reports returns list', function () {
        ScheduledReport::factory(2)->create();
        $this->getJson('/api/v1/bi/scheduled-reports')->assertOk()->assertJsonCount(2, 'data');
    });

    it('POST /api/v1/bi/scheduled-reports creates scheduled report', function () {
        $this->postJson('/api/v1/bi/scheduled-reports', [
            'name' => 'Weekly Report',
            'schedule' => 'weekly',
            'format' => 'pdf',
            'recipients' => ['user@example.com'],
        ])->assertCreated()->assertJsonPath('name', 'Weekly Report');
    });

    it('POST /api/v1/bi/scheduled-reports validates required fields', function () {
        $this->postJson('/api/v1/bi/scheduled-reports', [])->assertUnprocessable();
    });

    it('GET /api/v1/bi/scheduled-reports/{scheduledReport} shows scheduled report', function () {
        $report = ScheduledReport::factory()->create();
        $this->getJson("/api/v1/bi/scheduled-reports/{$report->id}")->assertOk()->assertJsonPath('id', $report->id);
    });

    it('PUT /api/v1/bi/scheduled-reports/{scheduledReport} updates scheduled report', function () {
        $report = ScheduledReport::factory()->create(['name' => 'Old Name']);
        $this->putJson("/api/v1/bi/scheduled-reports/{$report->id}", ['name' => 'New Name'])
            ->assertOk()->assertJsonPath('name', 'New Name');
    });

    it('DELETE /api/v1/bi/scheduled-reports/{scheduledReport} deletes scheduled report', function () {
        $report = ScheduledReport::factory()->create();
        $this->deleteJson("/api/v1/bi/scheduled-reports/{$report->id}")->assertNoContent();
        $this->assertDatabaseMissing('bi_scheduled_reports', ['id' => $report->id]);
    });

    it('POST /api/v1/bi/scheduled-reports/{scheduledReport}/send triggers send', function () {
        $report = ScheduledReport::factory()->create(['send_count' => 0]);
        $this->postJson("/api/v1/bi/scheduled-reports/{$report->id}/send")
            ->assertOk()->assertJsonPath('send_count', 1);
    });

    it('POST /api/v1/bi/scheduled-reports/process-due processes due reports', function () {
        ScheduledReport::factory()->due()->create();
        $this->postJson('/api/v1/bi/scheduled-reports/process-due')
            ->assertOk()->assertJsonPath('sent_count', 1);
    });
});
