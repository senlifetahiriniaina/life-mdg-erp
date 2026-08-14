<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Import/Index.vue's hand-rolled step indicator was replaced with
 * WorkflowStepper (F3, separate from the Setup module repair phases) —
 * this page and its backend (Modules\Core\Http\Controllers\Api\
 * ImportController) were already real and working, so this is just a
 * smoke test that the route still renders after the swap.
 */
class ImportPageWebTest extends TestCase
{
    public function test_import_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/import');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Import/Index', false));
    }
}
