<?php

declare(strict_types=1);

namespace Modules\CRM\GraphQL\Mutations;

use Modules\CRM\Models\Contact;

class UpdateContact
{
    public function __invoke($root, array $args): Contact
    {
        $contact = Contact::findOrFail($args['id']);
        $input = $args['input'];

        $map = [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'jobTitle' => 'job_title',
            'status' => 'status',
            'accountId' => 'account_id',
        ];

        $attributes = [];
        foreach ($map as $inputKey => $column) {
            if (array_key_exists($inputKey, $input)) {
                $attributes[$column] = $input[$inputKey];
            }
        }

        $contact->update($attributes);

        return $contact;
    }
}
