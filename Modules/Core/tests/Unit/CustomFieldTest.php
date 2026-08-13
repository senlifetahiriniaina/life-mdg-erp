<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\CustomField;
use Modules\Core\Models\CustomFieldValue;
use Modules\Core\Services\CustomFieldService;
use Tests\TestCase;

/**
 * CustomFieldTest — tests for CustomField model and CustomFieldService.
 *
 * Covers: getValidationRules(), castValue(), validateValue(),
 * getFieldsForEntity(), validateValues(), saveValues() via service.
 */
class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    private CustomFieldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomFieldService::class);
    }

    // ─── getValidationRules() ─────────────────────────────────────────────────

    public function test_required_field_includes_required_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'text',
            'is_required' => true,
        ]);

        $rules = $field->getValidationRules();

        $this->assertContains('required', $rules);
    }

    public function test_number_field_includes_integer_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'number',
            'is_required' => false,
        ]);

        $rules = $field->getValidationRules();

        $this->assertContains('integer', $rules);
    }

    public function test_decimal_field_includes_numeric_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'product',
            'field_type'  => 'decimal',
            'is_required' => false,
        ]);

        $this->assertContains('numeric', $field->getValidationRules());
    }

    public function test_email_field_includes_email_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'email',
            'is_required' => false,
        ]);

        $this->assertContains('email', $field->getValidationRules());
    }

    public function test_url_field_includes_url_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'url',
            'is_required' => false,
        ]);

        $this->assertContains('url', $field->getValidationRules());
    }

    public function test_date_field_includes_date_rule(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'event',
            'field_type'  => 'date',
            'is_required' => false,
        ]);

        $this->assertContains('date', $field->getValidationRules());
    }

    public function test_custom_validation_rules_are_merged(): void
    {
        $field = CustomField::factory()->create([
            'entity_type'      => 'contact',
            'field_type'       => 'text',
            'is_required'      => false,
            'validation_rules' => 'min:3|max:255',
        ]);

        $rules = $field->getValidationRules();

        $this->assertContains('min:3', $rules);
        $this->assertContains('max:255', $rules);
    }

    // ─── castValue() ─────────────────────────────────────────────────────────

    public function test_cast_value_converts_number(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'product',
            'field_type'  => 'number',
        ]);

        $this->assertSame(42, $field->castValue('42'));
    }

    public function test_cast_value_converts_decimal(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'product',
            'field_type'  => 'decimal',
        ]);

        $this->assertSame(3.14, $field->castValue('3.14'));
    }

    public function test_cast_value_converts_boolean_true(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'boolean',
        ]);

        $this->assertTrue($field->castValue('true'));
        $this->assertTrue($field->castValue('1'));
        $this->assertTrue($field->castValue('yes'));
    }

    public function test_cast_value_converts_boolean_false(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'boolean',
        ]);

        $this->assertFalse($field->castValue('false'));
        $this->assertFalse($field->castValue('0'));
        $this->assertFalse($field->castValue('no'));
    }

    public function test_cast_value_returns_null_for_empty_string(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'product',
            'field_type'  => 'text',
        ]);

        $this->assertNull($field->castValue(''));
    }

    // ─── validateValue() ─────────────────────────────────────────────────────

    public function test_validate_value_passes_for_valid_email(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'email',
            'is_required' => false,
        ]);

        $this->assertTrue($field->validateValue('user@example.com'));
    }

    public function test_validate_value_fails_for_invalid_email(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'email',
            'is_required' => false,
        ]);

        $this->assertFalse($field->validateValue('not-an-email'));
    }

    public function test_validate_value_fails_required_field_with_null(): void
    {
        $field = CustomField::factory()->create([
            'entity_type' => 'contact',
            'field_type'  => 'text',
            'is_required' => true,
        ]);

        $this->assertFalse($field->validateValue(null));
    }

    // ─── CustomFieldService::getFieldsForEntity() ─────────────────────────────

    public function test_get_fields_for_entity_returns_only_active(): void
    {
        CustomField::factory()->create([
            'entity_type' => 'lead',
            'field_type'  => 'text',
            'is_active'   => true,
        ]);
        CustomField::factory()->create([
            'entity_type' => 'lead',
            'field_type'  => 'text',
            'is_active'   => false,
        ]);

        $grouped = $this->service->getFieldsForEntity('lead');
        $all = array_merge(...array_values($grouped));

        $this->assertEquals(1, count($all));
    }

    public function test_get_fields_for_entity_groups_by_group_name(): void
    {
        CustomField::factory()->create([
            'entity_type' => 'account',
            'field_type'  => 'text',
            'is_active'   => true,
            'group_name'  => 'Personal',
        ]);
        CustomField::factory()->create([
            'entity_type' => 'account',
            'field_type'  => 'text',
            'is_active'   => true,
            'group_name'  => 'Business',
        ]);

        $grouped = $this->service->getFieldsForEntity('account');

        $this->assertArrayHasKey('Personal', $grouped);
        $this->assertArrayHasKey('Business', $grouped);
    }

    // ─── CustomFieldService::validateValues() ─────────────────────────────────

    public function test_validate_values_passes_all_valid(): void
    {
        CustomField::factory()->create([
            'entity_type'  => 'contact',
            'field_key'    => 'email',
            'field_type'   => 'email',
            'is_required'  => true,
            'is_active'    => true,
        ]);

        $result = $this->service->validateValues('contact', ['email' => 'valid@example.com']);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_values_fails_missing_required(): void
    {
        CustomField::factory()->create([
            'entity_type' => 'order',
            'field_key'   => 'reference_number',
            'field_type'  => 'text',
            'field_label' => 'Reference Number',
            'is_required' => true,
            'is_active'   => true,
        ]);

        $result = $this->service->validateValues('order', []);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('reference_number', $result['errors']);
    }

    // ─── CustomField::isActive() helper ──────────────────────────────────────

    public function test_is_active_returns_correct_boolean(): void
    {
        $active   = CustomField::factory()->create(['entity_type' => 'x', 'field_type' => 'text', 'is_active' => true]);
        $inactive = CustomField::factory()->create(['entity_type' => 'x', 'field_type' => 'text', 'is_active' => false]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }
}
