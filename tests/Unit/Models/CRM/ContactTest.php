<?php

declare(strict_types=1);

namespace Tests\Unit\Models\CRM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Company;
use Modules\CRM\Models\Lead;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->assertInstanceOf(Company::class, $contact->company);
        $this->assertEquals($company->id, $contact->company->id);
    }

    public function test_contact_has_many_leads(): void
    {
        $contact = Contact::factory()->create();
        Lead::factory()->count(3)->create(['contact_id' => $contact->id]);

        $this->assertEquals(3, $contact->leads()->count());
    }

    public function test_contact_can_be_marked_as_duplicate(): void
    {
        $contact1 = Contact::factory()->create();
        $contact2 = Contact::factory()->create();

        $contact1->mergeDuplicate($contact2);

        $this->assertEquals('merged', $contact2->refresh()->status);
        $this->assertEquals($contact1->id, $contact2->merged_into_id);
    }

    public function test_contact_email_is_optional_at_model_level(): void
    {
        // Email is optional on the model (the CRM web flow creates contacts without it);
        // requiredness is enforced at the request-validation layer, not the model.
        $contact = Contact::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertNull($contact->email);
    }

    public function test_contact_email_must_be_unique(): void
    {
        Contact::factory()->create(['email' => 'john@example.com']);

        $this->expectException(\Exception::class);
        Contact::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_contact_can_retrieve_full_name(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $contact->full_name);
    }

    public function test_contact_scope_active_returns_only_active_contacts(): void
    {
        Contact::factory()->count(3)->create(['status' => 'active']);
        Contact::factory()->count(2)->create(['status' => 'inactive']);

        $active = Contact::active()->count();

        $this->assertEquals(3, $active);
    }

    public function test_contact_scope_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Contact::factory()->count(3)->create(['company_id' => $company1->id]);
        Contact::factory()->count(2)->create(['company_id' => $company2->id]);

        $contacts = Contact::byCompany($company1)->count();

        $this->assertEquals(3, $contacts);
    }

    public function test_contact_can_archive(): void
    {
        $contact = Contact::factory()->create(['status' => 'active']);

        $contact->archive();

        $this->assertEquals('archived', $contact->refresh()->status);
        $this->assertNotNull($contact->archived_at);
    }

    public function test_contact_can_unarchive(): void
    {
        $contact = Contact::factory()->create(['status' => 'archived']);

        $contact->unarchive();

        $this->assertEquals('active', $contact->refresh()->status);
        $this->assertNull($contact->archived_at);
    }

    public function test_contact_can_have_custom_fields(): void
    {
        $contact = Contact::factory()->create([
            'custom_fields' => ['industry' => 'Technology', 'employees' => 100],
        ]);

        $this->assertEquals('Technology', $contact->custom_fields['industry']);
        $this->assertEquals(100, $contact->custom_fields['employees']);
    }
}
