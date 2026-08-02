<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Reading the catalogue's reference data is open to every authenticated user:
     * the category filter and the tool form need it (ADR-18).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isCatalogAdmin($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->isCatalogAdmin($user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->isCatalogAdmin($user);
    }

    /**
     * owner and pm are the catalogue administrators, exactly as in ToolPolicy.
     * Explicit role names, not a level threshold (ADR-12).
     */
    private function isCatalogAdmin(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('pm');
    }
}
