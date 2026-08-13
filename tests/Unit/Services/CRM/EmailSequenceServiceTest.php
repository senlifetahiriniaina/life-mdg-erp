<?php

declare(strict_types=1);

namespace Tests\Unit\Services\CRM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\SequenceStep;
use Modules\CRM\Services\EmailSequenceService;
use Tests\TestCase;

class EmailSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailSequenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailSequenceService();
    }

    public function test_can_create_sequence_with_valid_data(): void
    {
        $data = [
            'name' => 'Welcome Series',
            'description' => 'New contact welcome emails',
            'trigger' => 'manual',
            'enabled' => true,
        ];

        $sequence = $this->service->create($data);

        $this->assertInstanceOf(EmailSequence::class, $sequence);
        $this->assertEquals('Welcome Series', $sequence->name);
        $this->assertTrue($sequence->enabled);
    }

    public function test_create_sequence_validates_required_fields(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->create(['trigger' => 'manual']);
    }

    public function test_can_add_step_to_sequence(): void
    {
        $sequence = EmailSequence::factory()->create();

        $step = $this->service->addStep($sequence, [
            'step_order' => 1,
            'delay_hours' => 0,
            'subject' => 'Welcome!',
            'body_html' => '<p>Welcome to our service</p>',
        ]);

        $this->assertInstanceOf(SequenceStep::class, $step);
        $this->assertEquals(1, $step->step_order);
        $this->assertEquals('Welcome!', $step->subject);
    }

    public function test_can_update_sequence(): void
    {
        $sequence = EmailSequence::factory()->create(['name' => 'Original Name']);

        $updated = $this->service->update($sequence, ['name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated Name', $sequence->refresh()->name);
    }

    public function test_can_delete_sequence(): void
    {
        $sequence = EmailSequence::factory()->create();
        $id = $sequence->id;

        $result = $this->service->delete($sequence);

        $this->assertTrue($result);
        $this->assertNull(EmailSequence::find($id));
    }

    public function test_cannot_add_step_with_duplicate_order(): void
    {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->create(['sequence_id' => $sequence->id, 'step_order' => 1]);

        $this->expectException(\Exception::class);
        $this->service->addStep($sequence, [
            'step_order' => 1,
            'delay_hours' => 24,
            'subject' => 'Duplicate',
            'body_html' => '<p>Duplicate step</p>',
        ]);
    }

    public function test_can_get_sequence_with_steps(): void
    {
        $sequence = EmailSequence::factory()->create();
        SequenceStep::factory()->count(3)->create(['sequence_id' => $sequence->id]);

        $loaded = $this->service->getWithSteps($sequence->id);

        $this->assertEquals(3, $loaded->steps()->count());
    }

    public function test_enable_sequence(): void
    {
        $sequence = EmailSequence::factory()->create(['enabled' => false]);

        $result = $this->service->enable($sequence);

        $this->assertTrue($result);
        $this->assertTrue($sequence->refresh()->enabled);
    }

    public function test_disable_sequence(): void
    {
        $sequence = EmailSequence::factory()->create(['enabled' => true]);

        $result = $this->service->disable($sequence);

        $this->assertTrue($result);
        $this->assertFalse($sequence->refresh()->enabled);
    }
}
