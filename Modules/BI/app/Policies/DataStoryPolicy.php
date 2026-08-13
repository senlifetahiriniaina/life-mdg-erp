<?php

declare(strict_types=1);

namespace Modules\BI\Policies;

use App\Models\User;
use Modules\BI\Models\DataStory;

class DataStoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bi.datastory.view-any');
    }

    public function view(User $user, DataStory $story): bool
    {
        return ($user->can('bi.datastory.view') && $this->belongsToCompany($user, $story)) ||
               $story->is_public;
    }

    public function create(User $user): bool
    {
        return $user->can('bi.datastory.create');
    }

    public function update(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.update') &&
               $this->belongsToCompany($user, $story);
    }

    public function delete(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.delete') &&
               $this->belongsToCompany($user, $story);
    }

    public function publish(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.publish') &&
               $this->belongsToCompany($user, $story);
    }

    public function share(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.share') &&
               $this->belongsToCompany($user, $story);
    }

    public function manageNarratives(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.manage-narratives') &&
               $this->belongsToCompany($user, $story);
    }

    public function viewAnalytics(User $user, DataStory $story): bool
    {
        return $user->can('bi.datastory.view-analytics') &&
               $this->belongsToCompany($user, $story);
    }

    private function belongsToCompany(User $user, DataStory $story): bool
    {
        return $user->company_id === $story->company_id;
    }
}
