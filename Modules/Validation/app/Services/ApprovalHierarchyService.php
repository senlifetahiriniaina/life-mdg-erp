<?php

namespace Modules\Validation\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;

class ApprovalHierarchyService
{
    public function createHierarchy(array $data): ApprovalHierarchy
    {
        return ApprovalHierarchy::create($data);
    }

    public function updateHierarchy(ApprovalHierarchy $hierarchy, array $data): ApprovalHierarchy
    {
        $hierarchy->update($data);

        return $hierarchy;
    }

    public function addLevel(ApprovalHierarchy $hierarchy, array $levelData): HierarchyLevel
    {
        return $hierarchy->levels()->create($levelData);
    }

    public function addLevelApprovers(HierarchyLevel $level, array $approverIds): void
    {
        foreach ($approverIds as $order => $userId) {
            LevelApprover::create([
                'hierarchy_level_id' => $level->id,
                'user_id' => $userId,
                'approver_order' => $order,
                'is_active' => true,
            ]);
        }
    }

    public function getApproverChain(ApprovalHierarchy $hierarchy, string $level): Collection
    {
        $hierarchyLevel = $hierarchy->levels()
            ->where('title', $level)
            ->first();

        if (! $hierarchyLevel) {
            return collect();
        }

        return $hierarchyLevel->activeApprovers()->orderBy('approver_order')->get();
    }

    public function setBackupApprover(LevelApprover $approver, User $backup): void
    {
        $approver->update(['backup_user_id' => $backup->id]);
    }

    public function removeBackupApprover(LevelApprover $approver): void
    {
        $approver->update(['backup_user_id' => null]);
    }

    public function deactivateApprover(LevelApprover $approver): void
    {
        $approver->update(['is_active' => false]);
    }

    public function activateApprover(LevelApprover $approver): void
    {
        $approver->update(['is_active' => true]);
    }

    public function getAllHierarchies(): Collection
    {
        return ApprovalHierarchy::where('is_active', true)->get();
    }

    public function getHierarchyByCompany(int $companyId): ?ApprovalHierarchy
    {
        return ApprovalHierarchy::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
    }

    public function getHierarchy(int $id): ApprovalHierarchy
    {
        return ApprovalHierarchy::findOrFail($id);
    }

    public function canApprove(User $user, ApprovalHierarchy $hierarchy, int $levelOrder): bool
    {
        $level = $hierarchy->levels()->where('level_order', $levelOrder)->first();

        if (! $level) {
            return false;
        }

        foreach ($level->activeApprovers()->get() as $levelApprover) {
            if ($levelApprover->resolvesToUsers()->contains('id', $user->id)) {
                return true;
            }
        }

        return false;
    }
}
