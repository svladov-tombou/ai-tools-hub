<?php

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;

class ToolPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * Mirrors Tool::scopeVisibleTo (ADR-35): a published tool is readable by anyone,
     * a draft only by owner/pm/manager or by its own author. An authorless draft
     * (created_by null) is therefore readable only by the first three.
     */
    public function view(User $user, Tool $tool): bool
    {
        if ($tool->status === 'published') {
            return true;
        }

        if ($this->seesAllTools($user)) {
            return true;
        }

        return $tool->created_by !== null && $tool->created_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can put a tool into the published state (ADR-35).
     *
     * Deliberately model-less: the answer never depends on WHICH tool, and the check runs
     * in the Form Requests before a tool exists (create) or is loaded (update). Explicit
     * role names, mirroring update() below.
     */
    public function publish(User $user): bool
    {
        return $user->hasRole('owner')
            || $user->hasRole('pm');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tool $tool): bool
    {
        return $tool->created_by === $user->id
            || $user->hasRole('owner')
            || $user->hasRole('pm');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tool $tool): bool
    {
        return $this->update($user, $tool);
    }

    private function seesAllTools(User $user): bool
    {
        foreach (Tool::SEES_ALL_TOOLS_ROLES as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
