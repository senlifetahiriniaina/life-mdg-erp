<?php

declare(strict_types=1);

namespace App\Policies;

class ConversationPolicy extends BaseErpPolicy
{
    /** Agents can only update conversations assigned to them. */
    protected ?string $ownerColumn = 'assignee_id';
}
