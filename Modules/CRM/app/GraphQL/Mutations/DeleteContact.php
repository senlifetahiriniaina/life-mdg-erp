<?php

declare(strict_types=1);

namespace Modules\CRM\GraphQL\Mutations;

use Modules\CRM\Models\Contact;

class DeleteContact
{
    public function __invoke($root, array $args): bool
    {
        return (bool) Contact::findOrFail($args['id'])->delete();
    }
}
