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
     */
    public function view(User $user, Tool $tool): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
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
}
