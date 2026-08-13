<?php

declare(strict_types=1);

use Modules\Workflow\Exceptions\WorkflowDslParseException;
use Modules\Workflow\Services\WorkflowDslParser;

/*
 * DSL Parser tests — pure PHP, no database required.
 * We use beforeEach to disable the RefreshDatabase behaviour inherited
 * from the global Pest.php uses(TestCase::class) definition.
 * The parser is instantiated directly (no DI container needed).
 */

// ─── Helpers ──────────────────────────────────────────────────────────────────

function dslParser(): WorkflowDslParser
{
    return new WorkflowDslParser();
}

// ─── Simple SI … ALORS rule ───────────────────────────────────────────────────

it('parses a simple SI … ALORS rule', function () {
    $parser = dslParser();
    $defs   = $parser->parse('SI montant > 500000 ALORS approuver_3_niveaux');

    expect($defs)->toHaveCount(1);

    $def = $defs[0];
    expect($def['trigger_key'])->toBe('generic.condition_check');
    expect($def['conditions'])->toHaveCount(1);
    expect($def['conditions'][0]['field'])->toBe('amount');
    expect($def['conditions'][0]['operator'])->toBe('greater_than');
    expect($def['conditions'][0]['value'])->toBe(500000);
    expect($def['actions'])->toHaveCount(1);
    expect($def['actions'][0]['key'])->toBe('approval.request_multi_level');
    expect($def['actions'][0]['params']['levels'])->toBe(3);
});

// ─── QUAND trigger rule ───────────────────────────────────────────────────────

it('parses a QUAND trigger with condition and action', function () {
    $parser = dslParser();
    $defs   = $parser->parse('QUAND opportunite_gagnee SI montant > 0 ALORS creer_commande');

    $def = $defs[0];
    expect($def['trigger_key'])->toBe('crm.opportunity.won');
    expect($def['conditions'][0]['value'])->toBe(0);
    expect($def['actions'][0]['key'])->toBe('sales.create_order_from_opportunity');
});

// ─── SINON branch ─────────────────────────────────────────────────────────────

it('parses a rule with SINON branch', function () {
    $parser = dslParser();
    $defs   = $parser->parse('QUAND facture_recue SI montant > 100000 ALORS approbation_1_niveau SINON comptabiliser_direct');

    $def = $defs[0];
    expect($def['actions'])->toHaveCount(1);
    expect($def['actions'][0]['key'])->toBe('approval.request_single_level');
    expect($def['else_actions'])->toHaveCount(1);
    expect($def['else_actions'][0]['key'])->toBe('accounting.book_purchase_invoice');
});

// ─── Multi-action with ET ─────────────────────────────────────────────────────

it('parses multiple actions joined by ET', function () {
    $parser = dslParser();
    $defs   = $parser->parse('SI montant > 500000 ALORS approuver_3_niveaux ET notifier_manager');

    $actions = $defs[0]['actions'];
    expect($actions)->toHaveCount(2);
    expect($actions[0]['key'])->toBe('approval.request_multi_level');
    expect($actions[1]['key'])->toBe('notify.in_app');
    expect($actions[1]['params']['role'])->toBe('manager');
});

// ─── CHAQUE_JOUR scheduled trigger ───────────────────────────────────────────

it('parses a CHAQUE_JOUR scheduled rule', function () {
    $parser = dslParser();
    $defs   = $parser->parse('CHAQUE_JOUR SI contrat_expire_dans <= 30 ALORS alerter_rh');

    $def = $defs[0];
    expect($def['trigger_key'])->toBe('hr.contract_expiry_check');
    expect($def['conditions'][0]['field'])->toBe('days_until_expiry');
    expect($def['conditions'][0]['operator'])->toBe('less_than_or_equal');
    expect($def['conditions'][0]['value'])->toBe(30);
});

// ─── Boolean value ────────────────────────────────────────────────────────────

it('parses boolean value vrai', function () {
    $parser = dslParser();
    $defs   = $parser->parse('QUAND stock_critique SI reappro_auto = vrai ALORS creer_bon_commande');

    expect($defs[0]['conditions'][0]['value'])->toBe(true);
    expect($defs[0]['actions'][0]['key'])->toBe('achats.auto_create_po');
});

// ─── Quoted string value ──────────────────────────────────────────────────────

it('parses quoted string values', function () {
    $parser = dslParser();
    $defs   = $parser->parse("QUAND conge_approuve SI type_conge = 'non_paye' ALORS ajuster_paie");

    expect($defs[0]['conditions'][0]['value'])->toBe('non_paye');
    expect($defs[0]['actions'][0]['key'])->toBe('payroll.adjust_for_leave');
});

// ─── Invalid DSL raises parse exception with line number ──────────────────────

it('throws WorkflowDslParseException for unknown action', function () {
    $parser = dslParser();

    expect(fn () => $parser->parse('SI montant > 0 ALORS action_inexistante'))
        ->toThrow(WorkflowDslParseException::class);
});

it('throws WorkflowDslParseException for missing ALORS', function () {
    $parser = dslParser();

    expect(fn () => $parser->parse('QUAND opportunite_gagnee SI montant > 0'))
        ->toThrow(WorkflowDslParseException::class);
});

it('includes line number in parse exception', function () {
    $parser = dslParser();

    try {
        $parser->parse("QUAND opportunite_gagnee SI montant > 0 ALORS action_inconnue");
    } catch (WorkflowDslParseException $e) {
        expect($e->getLineNumber())->toBe(1);
    }
});

// ─── Validate returns structured result ──────────────────────────────────────

it('validate() returns valid=true for correct DSL', function () {
    $parser = dslParser();
    $result = $parser->validate('SI montant > 100000 ALORS approuver_3_niveaux');

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

it('validate() returns valid=false with errors for bad DSL', function () {
    $parser = dslParser();
    $result = $parser->validate('QUAND inexistant ALORS rien');

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->not->toBeEmpty();
});

// ─── Multi-line DSL ───────────────────────────────────────────────────────────

it('parses multiple rules from multi-line DSL text', function () {
    $dsl = implode("\n", [
        'SI montant > 500000 ALORS approuver_3_niveaux',
        'QUAND stock_critique SI reappro_auto = vrai ALORS creer_bon_commande',
    ]);

    $defs = dslParser()->parse($dsl);
    expect($defs)->toHaveCount(2);
});

it('skips blank lines and comments in multi-line DSL', function () {
    $dsl = implode("\n", [
        '# Règle 1 - approbation',
        'SI montant > 500000 ALORS approuver_3_niveaux',
        '',
        '# Règle 2 - réappro',
        'QUAND stock_critique SI reappro_auto = vrai ALORS creer_bon_commande',
    ]);

    $defs = dslParser()->parse($dsl);
    expect($defs)->toHaveCount(2);
});

// ─── Round-trip DSL → JSON → DSL ─────────────────────────────────────────────

it('performs a round-trip DSL → JSON → DSL', function () {
    $parser  = dslParser();
    $original = 'QUAND opportunite_gagnee SI montant > 0 ALORS creer_commande';

    // DSL → internal definition
    $defs = $parser->parse($original);
    expect($defs)->toHaveCount(1);

    // Definition → DSL text
    $reconstructed = $parser->fromJson($defs[0]);
    expect($reconstructed)->toContain('QUAND opportunite_gagnee');
    expect($reconstructed)->toContain('ALORS');
    expect($reconstructed)->toContain('creer_commande');
});

// ─── toJson() helper ──────────────────────────────────────────────────────────

it('toJson() converts DSL to a valid JSON string', function () {
    $parser = dslParser();
    $json   = $parser->toJson('SI montant > 500000 ALORS approuver_3_niveaux');

    $decoded = json_decode($json, true);
    expect($decoded)->not->toBeNull();
    expect($decoded['trigger_key'])->toBe('generic.condition_check');
    expect($decoded['actions'][0]['key'])->toBe('approval.request_multi_level');
});

// ─── French trigger name mapping ─────────────────────────────────────────────

it('correctly maps all French trigger names to internal keys', function () {
    $parser = dslParser();

    $mappings = [
        'QUAND opportunite_gagnee SI montant > 0 ALORS creer_commande'           => 'crm.opportunity.won',
        'QUAND facture_recue SI montant > 100000 ALORS approuver_3_niveaux'      => 'achats.invoice_received',
        'QUAND stock_critique SI reappro_auto = vrai ALORS creer_bon_commande'   => 'inventory.stock_below_reorder_point',
        'QUAND conge_approuve SI montant > 0 ALORS ajuster_paie'                 => 'hr.leave_approved',
    ];

    foreach ($mappings as $dsl => $expectedTrigger) {
        $defs = $parser->parse($dsl);
        expect($defs[0]['trigger_key'])->toBe($expectedTrigger, "Failed for: $dsl");
    }
});
