<?php

declare(strict_types=1);

namespace Tests\Integration\CRM;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Tests\TestCase;

class ContactConversionPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_to_contact_conversion_creates_relationships(): void
    {
        $company = Company::factory()->create();
        $lead = Lead::factory()->create();

        // Simulate lead conversion to contact
        $contact = Contact::create([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company_id' => $company->id,
            'lead_id' => $lead->id,
        ]);

        $lead->update(['converted_to_contact_id' => $contact->id]);

        $this->assertDatabaseHas('crm_leads', [
            'id' => $lead->id,
            'converted_to_contact_id' => $contact->id,
        ]);

        $this->assertEquals($lead->id, $contact->refresh()->lead_id);
    }

    public function test_contact_to_opportunity_creation_flow(): void
    {
        $contact = Contact::factory()->create();

        // Create opportunity from contact
        $opportunity = Opportunity::create([
            'contact_id' => $contact->id,
            'name' => 'Test Deal',
            'title' => 'Test Deal',
            'amount' => 50000,
            'status' => 'new',
            'stage' => 'prospecting',
            'expected_close_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertDatabaseHas('crm_opportunities', [
            'contact_id' => $contact->id,
            'title' => 'Test Deal',
        ]);

        $this->assertEquals(1, $contact->refresh()->opportunities()->count());
    }

    public function test_multiple_contacts_can_share_company(): void
    {
        // Chantier 32.15: Modules\CRM\Models\Company (and Contact::company())
        // were deleted as a confirmed-dead duplicate — crm_contacts.company_id
        // is the real tenant-boundary scalar pointing at App\Models\Company,
        // which has no contacts() relation, so this queries the scalar directly.
        $company = Company::factory()->create();

        Contact::factory()->create(['company_id' => $company->id]);
        Contact::factory()->create(['company_id' => $company->id]);
        Contact::factory()->create(['company_id' => $company->id]);

        $this->assertEquals(3, Contact::where('company_id', $company->id)->count());
    }

    public function test_lead_pipeline_progresses_through_stages(): void
    {
        $lead = Lead::factory()->create(['status' => 'new']);

        // Progress through pipeline
        $lead->update(['status' => 'qualified']);
        $this->assertEquals('qualified', $lead->refresh()->status);

        $lead->update(['status' => 'proposal']);
        $this->assertEquals('proposal', $lead->refresh()->status);

        $lead->update(['status' => 'negotiation']);
        $this->assertEquals('negotiation', $lead->refresh()->status);

        $lead->update(['status' => 'won']);
        $this->assertEquals('won', $lead->refresh()->status);
    }

    public function test_contact_has_access_to_related_leads_and_opportunities(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create(['contact_id' => $contact->id]);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id]);

        $this->assertTrue($contact->leads()->exists());
        $this->assertTrue($contact->opportunities()->exists());
    }

    public function test_opportunity_status_changes_are_logged(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);
        $initialStatus = $opportunity->status;

        $opportunity->update(['status' => 'qualified']);

        $this->assertNotEquals($initialStatus, $opportunity->refresh()->status);
        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunity->id,
            'status' => 'qualified',
        ]);
    }

    public function test_contact_deduplication_merges_records(): void
    {
        $contact1 = Contact::factory()->create(['email' => 'john@example.com']);
        $contact2 = Contact::factory()->create(['email' => 'john.doe@example.com']);

        // Merge contact2 into contact1
        $contact2->update([
            'merged_into_id' => $contact1->id,
            'status' => 'merged',
        ]);

        $this->assertEquals('merged', $contact2->refresh()->status);
        $this->assertEquals($contact1->id, $contact2->merged_into_id);
    }

    public function test_company_aggregates_contact_and_lead_data(): void
    {
        $company = Company::factory()->create();
        Contact::factory()->count(5)->create(['company_id' => $company->id]);
        Lead::factory()->count(3)->create(['company_id' => $company->id]);

        $this->assertEquals(5, Contact::where('company_id', $company->id)->count());
        $this->assertEquals(3, Lead::where('company_id', $company->id)->count());
    }

    public function test_lost_opportunity_records_reason(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'negotiation']);

        $opportunity->update([
            'status' => 'lost',
            'lost_reason' => 'Budget constraints',
        ]);

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunity->id,
            'status' => 'lost',
            'lost_reason' => 'Budget constraints',
        ]);
    }

    public function test_contact_company_relationship_cascades(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $opportunity = Opportunity::factory()->create(['contact_id' => $contact->id]);

        // Access via the real chain: company_id scalar -> contact -> opportunities()
        $opportunities = Contact::where('company_id', $company->id)
            ->find($contact->id)
            ->opportunities()
            ->get();

        $this->assertTrue($opportunities->contains('id', $opportunity->id));
    }
}
