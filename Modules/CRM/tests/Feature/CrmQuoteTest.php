<?php

declare(strict_types=1);

use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Quote;
use Modules\CRM\Models\QuoteLine;
use Modules\CRM\Services\CpqService;


it('can create a quote with auto-generated reference', function () {
    $user = actingAsUser('admin');
    $contact = Contact::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/quotes', [
            'contact_id' => $contact->id,
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'notes' => 'Test quote',
        ])
        ->assertStatus(201);

    expect($response->json('reference'))->toMatch('/^QT-\d{4}-\d{4}$/');
    expect($response->json('status'))->toBe('draft');
    $this->assertDatabaseHas('crm_quotes', ['id' => $response->json('id')]);
});

it('can list quotes with contact info', function () {
    $user = actingAsUser('admin');
    $contact = Contact::factory()->create();
    Quote::factory()->count(3)->create(['contact_id' => $contact->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/quotes')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
    expect($response->json('data.0'))->toHaveKey('contact');
});

it('can add a line to a quote and recalculate totals', function () {
    $user = actingAsUser('admin');
    $quote = Quote::factory()->create(['status' => 'draft', 'discount_amount' => '0.00']);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/crm/quotes/{$quote->id}/lines", [
            'description' => 'Widget Pro License',
            'quantity' => 5,
            'unit_price' => 100.00,
            'discount_pct' => 10,
        ])
        ->assertStatus(201);

    // 5 * 100 * (1 - 10/100) = 450
    expect((float) $response->json('line_total'))->toBe(450.0);

    $quote->refresh();
    expect((float) $quote->subtotal)->toBe(450.0);
    expect((float) $quote->tax_amount)->toBe(90.0);   // 450 * 20%
    expect((float) $quote->total)->toBe(540.0);        // 450 + 90
});

it('can recalculate a quote after updating discount', function () {
    $user = actingAsUser('admin');
    $quote = Quote::factory()->create(['status' => 'draft', 'discount_amount' => 0]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'line_total' => 500]);

    app(CpqService::class)->recalculate($quote);
    $quote->refresh();

    expect((float) $quote->subtotal)->toBe(500.0);
    expect((float) $quote->tax_amount)->toBe(100.0);
    expect((float) $quote->total)->toBe(600.0);
});

it('can get the PDF html of a quote', function () {
    $user = actingAsUser('admin');
    $quote = Quote::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->get("/api/v1/crm/quotes/{$quote->id}/pdf")
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/html');
    expect($response->getContent())->toContain($quote->reference);
});

it('can duplicate a quote', function () {
    $user = actingAsUser('admin');
    $quote = Quote::factory()->create(['status' => 'accepted']);
    QuoteLine::factory()->count(2)->create(['quote_id' => $quote->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/crm/quotes/{$quote->id}/duplicate")
        ->assertStatus(201);

    expect($response->json('status'))->toBe('draft');
    expect($response->json('reference'))->not->toBe($quote->reference);
    $this->assertDatabaseCount('crm_quote_lines', 4); // original 2 + duplicate 2
});
