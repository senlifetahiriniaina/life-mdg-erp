<?php

declare(strict_types=1);

namespace Modules\CRM\GraphQL\Mutations;

use Modules\CRM\Models\Contact;

class CreateContact
{
    public function __invoke($root, array $args): Contact
    {
        $input = $args['input'];

        return Contact::create([
            'first_name' => $input['firstName'],
            'last_name' => $input['lastName'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'job_title' => $input['jobTitle'] ?? null,
            'status' => $input['status'] ?? 'active',
            'account_id' => $input['accountId'] ?? null,
        ]);
    }
}
