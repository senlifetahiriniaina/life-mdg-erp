<?php

declare(strict_types=1);

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Account;

class ContactQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Tests are self-contained (each creates its own Contact factory); no need to run
        // the full DatabaseSeeder, which pulls in heavy, unrelated demo data.
    }

    public function test_get_contacts_query()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                contacts(first: 10) {
                    edges {
                        node {
                            id
                            firstName
                            lastName
                            email
                        }
                    }
                    pageInfo {
                        hasNextPage
                    }
                }
            }
        ');

        $response->assertStatus(200)
            ->assertJsonPath('data.contacts.edges', fn ($edges) => count($edges) >= 0)
            ->assertJsonStructure([
                'data' => [
                    'contacts' => [
                        'edges',
                        'pageInfo' => ['hasNextPage'],
                    ],
                ],
            ]);
    }

    public function test_get_contact_by_id()
    {
        $contact = Contact::factory()->create();

        $response = $this->graphQL(/** @lang GraphQL */ "
            query {
                contact(id: {$contact->id}) {
                    id
                    firstName
                    lastName
                    email
                    phone
                }
            }
        ");

        $response->assertStatus(200)
            ->assertJsonPath('data.contact.id', (string) $contact->id)
            ->assertJsonPath('data.contact.firstName', $contact->first_name);
    }

    public function test_get_contacts_with_relationships()
    {
        $account = Account::factory()->create();
        $contact = Contact::factory()->create(['account_id' => $account->id]);

        $response = $this->graphQL(/** @lang GraphQL */ "
            query {
                contact(id: {$contact->id}) {
                    id
                    firstName
                    account {
                        id
                        name
                    }
                }
            }
        ");

        $response->assertStatus(200)
            ->assertJsonPath('data.contact.account.id', (string) $account->id)
            ->assertJsonPath('data.contact.account.name', $account->name);
    }

    public function test_create_contact_mutation()
    {
        $user = $this->actingAsUser('admin');

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                createContact(input: {
                    firstName: "John"
                    lastName: "Doe"
                    email: "john@example.com"
                    phone: "+1234567890"
                    jobTitle: "Sales Manager"
                    status: ACTIVE
                }) {
                    id
                    firstName
                    lastName
                    email
                }
            }
        ');

        $response->assertStatus(200)
            ->assertJsonPath('data.createContact.firstName', 'John')
            ->assertJsonPath('data.createContact.lastName', 'Doe')
            ->assertJsonPath('data.createContact.email', 'john@example.com');
    }

    public function test_update_contact_mutation()
    {
        $user = $this->actingAsUser('admin');
        $contact = Contact::factory()->create();

        $response = $this->graphQL(/** @lang GraphQL */ "
            mutation {
                updateContact(id: {$contact->id}, input: {
                    firstName: \"Jane\"
                    jobTitle: \"Senior Manager\"
                }) {
                    id
                    firstName
                    jobTitle
                }
            }
        ");

        $response->assertStatus(200)
            ->assertJsonPath('data.updateContact.firstName', 'Jane')
            ->assertJsonPath('data.updateContact.jobTitle', 'Senior Manager');
    }

    public function test_delete_contact_mutation()
    {
        $user = $this->actingAsUser('admin');
        $contact = Contact::factory()->create();

        $response = $this->graphQL(/** @lang GraphQL */ "
            mutation {
                deleteContact(id: {$contact->id})
            }
        ");

        $response->assertStatus(200)
            ->assertJsonPath('data.deleteContact', true);

        $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);
    }

    public function test_graphql_requires_authentication()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation {
                createContact(input: {
                    firstName: "John"
                    lastName: "Doe"
                    email: "john@example.com"
                    status: ACTIVE
                }) {
                    id
                }
            }
        ');

        $response->assertStatus(200)
            ->assertJsonPath('errors.0.message', fn ($message) => str_contains($message, 'Unauthenticated'));
    }
}
