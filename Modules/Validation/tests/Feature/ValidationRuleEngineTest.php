<?php

namespace Modules\Validation\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Services\ValidationEngine;

class ValidationRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private ValidationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ValidationEngine();
    }

    // ──────────────────────────────────────────────────────────────────
    // RULE CREATION TESTS (6 tests)
    // ──────────────────────────────────────────────────────────────────

    public function test_create_simple_required_rule(): void
    {
        $rule = $this->engine->createRule(
            name: 'Email Required',
            field: 'email',
            type: 'required'
        );

        $this->assertNotNull($rule->id);
        $this->assertEquals('required', $rule->type);
    }

    public function test_create_email_validation_rule(): void
    {
        $rule = $this->engine->createRule(
            name: 'Valid Email',
            field: 'email',
            type: 'email',
            params: ['strict' => true]
        );

        $this->assertEquals('email', $rule->type);
    }

    public function test_create_conditional_rule(): void
    {
        $rule = $this->engine->createRule(
            name: 'Address Required If Shipping',
            field: 'address',
            type: 'required_if',
            params: ['field' => 'ship_internationally', 'value' => true]
        );

        $this->assertEquals('required_if', $rule->type);
        $this->assertArrayHasKey('field', $rule->params);
    }

    public function test_create_cross_field_validation_rule(): void
    {
        $rule = $this->engine->createRule(
            name: 'Password Confirmation',
            field: 'password',
            type: 'confirmed',
            params: ['confirm_field' => 'password_confirmation']
        );

        $this->assertEquals('confirmed', $rule->type);
    }

    public function test_create_custom_regex_rule(): void
    {
        $rule = $this->engine->createRule(
            name: 'Phone Format',
            field: 'phone',
            type: 'regex',
            params: ['pattern' => '/^\+?1?\d{9,15}$/']
        );

        $this->assertEquals('regex', $rule->type);
    }

    public function test_create_rule_with_custom_message(): void
    {
        $rule = $this->engine->createRule(
            name: 'Custom Message Rule',
            field: 'age',
            type: 'min',
            params: ['value' => 18],
            message: 'You must be at least 18 years old'
        );

        $this->assertEquals('You must be at least 18 years old', $rule->message);
    }

    // ──────────────────────────────────────────────────────────────────
    // VALIDATION EXECUTION TESTS (10 tests)
    // ──────────────────────────────────────────────────────────────────

    public function test_validate_required_field_passes(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'required',
            'field' => 'email'
        ]);

        $result = $this->engine->validate(['email' => 'john@example.com'], [$rule]);

        $this->assertTrue($result->passes());
    }

    public function test_validate_required_field_fails(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'required',
            'field' => 'email'
        ]);

        $result = $this->engine->validate(['email' => ''], [$rule]);

        $this->assertFalse($result->passes());
        $this->assertArrayHasKey('email', $result->errors());
    }

    public function test_validate_email_format(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'email',
            'field' => 'email'
        ]);

        $invalidResult = $this->engine->validate(['email' => 'not-email'], [$rule]);
        $validResult = $this->engine->validate(['email' => 'john@example.com'], [$rule]);

        $this->assertFalse($invalidResult->passes());
        $this->assertTrue($validResult->passes());
    }

    public function test_validate_numeric_range(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'between',
            'field' => 'age',
            'params' => ['min' => 18, 'max' => 65]
        ]);

        $belowMin = $this->engine->validate(['age' => 10], [$rule]);
        $inRange = $this->engine->validate(['age' => 30], [$rule]);
        $aboveMax = $this->engine->validate(['age' => 70], [$rule]);

        $this->assertFalse($belowMin->passes());
        $this->assertTrue($inRange->passes());
        $this->assertFalse($aboveMax->passes());
    }

    public function test_validate_conditional_rule_required_if(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'required_if',
            'field' => 'billing_address',
            'params' => ['condition_field' => 'is_business', 'condition_value' => true]
        ]);

        $skipValidation = $this->engine->validate(
            ['is_business' => false, 'billing_address' => ''],
            [$rule]
        );

        $requireValidation = $this->engine->validate(
            ['is_business' => true, 'billing_address' => ''],
            [$rule]
        );

        $this->assertTrue($skipValidation->passes());
        $this->assertFalse($requireValidation->passes());
    }

    public function test_validate_password_confirmation(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'confirmed',
            'field' => 'password'
        ]);

        $match = $this->engine->validate([
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ], [$rule]);

        $noMatch = $this->engine->validate([
            'password' => 'secret123',
            'password_confirmation' => 'different'
        ], [$rule]);

        $this->assertTrue($match->passes());
        $this->assertFalse($noMatch->passes());
    }

    public function test_validate_array_min_items(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'min_items',
            'field' => 'tags',
            'params' => ['min' => 2]
        ]);

        $tooFew = $this->engine->validate(['tags' => ['tag1']], [$rule]);
        $sufficient = $this->engine->validate(['tags' => ['tag1', 'tag2']], [$rule]);

        $this->assertFalse($tooFew->passes());
        $this->assertTrue($sufficient->passes());
    }

    public function test_validate_unique_database_value(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'unique',
            'field' => 'email',
            'params' => ['table' => 'users', 'column' => 'email']
        ]);

        $newEmail = $this->engine->validate(['email' => 'new@example.com'], [$rule]);

        $this->assertTrue($newEmail->passes());
    }

    public function test_validate_regex_pattern(): void
    {
        $rule = ValidationRule::factory()->create([
            'type' => 'regex',
            'field' => 'phone',
            'params' => ['pattern' => '/^\+?1?\d{9,15}$/']
        ]);

        $valid = $this->engine->validate(['phone' => '+1234567890'], [$rule]);
        $invalid = $this->engine->validate(['phone' => 'invalid'], [$rule]);

        $this->assertTrue($valid->passes());
        $this->assertFalse($invalid->passes());
    }

    // ──────────────────────────────────────────────────────────────────
    // RULE SET TESTS (4 tests)
    // ──────────────────────────────────────────────────────────────────

    public function test_create_rule_set(): void
    {
        $ruleSet = $this->engine->createRuleSet(
            name: 'User Registration',
            description: 'Validation rules for user registration'
        );

        $this->assertNotNull($ruleSet->id);
        $this->assertEquals('User Registration', $ruleSet->name);
    }

    public function test_add_rules_to_rule_set(): void
    {
        $ruleSet = $this->engine->createRuleSet('User Registration');

        $rule1 = ValidationRule::factory()->create();
        $rule2 = ValidationRule::factory()->create();

        $ruleSet->addRule($rule1);
        $ruleSet->addRule($rule2);

        $this->assertCount(2, $ruleSet->rules);
    }

    public function test_validate_with_rule_set(): void
    {
        $ruleSet = $this->engine->createRuleSet('User Registration');
        $emailRule = ValidationRule::factory()->create(['type' => 'email', 'field' => 'email']);
        $ruleSet->addRule($emailRule);

        $result = $this->engine->validateWithRuleSet(
            ['email' => 'john@example.com'],
            $ruleSet
        );

        $this->assertTrue($result->passes());
    }

    public function test_rule_set_versioning(): void
    {
        $ruleSet = $this->engine->createRuleSet('User Registration');
        $version1 = $ruleSet->version;

        $ruleSet->addRule(ValidationRule::factory()->create());
        $version2 = $ruleSet->version;

        $this->assertGreaterThan($version1, $version2);
    }

    // ──────────────────────────────────────────────────────────────────
    // API ENDPOINT TESTS (5 tests)
    // ──────────────────────────────────────────────────────────────────

    public function test_api_create_rule(): void
    {
        $this->actingAsUser('admin');

        $response = $this->postJson('/api/v1/validation-rules', [
            'name' => 'Email Required',
            'field' => 'email',
            'type' => 'required'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Email Required');
    }

    public function test_api_list_rules_paginated(): void
    {
        $this->actingAsUser('admin');

        ValidationRule::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/validation-rules?page=1&per_page=15');

        $response->assertStatus(200);
        $response->assertJsonCount(15, 'data');
    }

    public function test_api_update_rule(): void
    {
        $this->actingAsUser('admin');

        $rule = ValidationRule::factory()->create();

        $response = $this->putJson("/api/v1/validation-rules/{$rule->id}", [
            'message' => 'Updated message'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated message', $rule->refresh()->message);
    }

    public function test_api_delete_rule(): void
    {
        $this->actingAsUser('admin');

        $rule = ValidationRule::factory()->create();

        $response = $this->deleteJson("/api/v1/validation-rules/{$rule->id}");

        $response->assertStatus(204);
        $this->assertNull(ValidationRule::find($rule->id));
    }

    public function test_api_validate_data_endpoint(): void
    {
        $this->actingAsUser('admin');

        ValidationRule::factory()->create(['type' => 'email', 'field' => 'email']);

        $response = $this->postJson('/api/v1/validation/validate', [
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('valid', true);
    }

    // ──────────────────────────────────────────────────────────────────
    // ERROR HANDLING & EDGE CASES (3 tests)
    // ──────────────────────────────────────────────────────────────────

    public function test_validate_with_empty_data(): void
    {
        $rule = ValidationRule::factory()->create(['type' => 'required', 'field' => 'email']);

        $result = $this->engine->validate([], [$rule]);

        $this->assertFalse($result->passes());
    }

    public function test_invalid_rule_type_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->engine->createRule(
            name: 'Invalid Rule',
            field: 'email',
            type: 'invalid_type_xyz'
        );
    }

    public function test_circular_dependency_detection(): void
    {
        $rule1 = ValidationRule::factory()->create(['field' => 'field1']);
        $rule2 = ValidationRule::factory()->create(['field' => 'field2']);

        $rule1->dependsOn($rule2);
        $rule2->dependsOn($rule1);

        $this->assertTrue($this->engine->hasCircularDependency([$rule1, $rule2]));
    }
}
