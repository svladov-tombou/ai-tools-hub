<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * owner and pm are the user administrators.
     */
    public function viewAny(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    /**
     * owner and pm are the user administrators.
     */
    public function create(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    /**
     * owner and pm are the user administrators; a pm may not act on an owner at all.
     */
    public function update(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && $this->mayActOn($actor, $target);
    }

    /**
     * owner and pm are the user administrators; a pm may not act on an owner at all;
     * nobody may change their OWN roles.
     */
    public function updateRoles(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && $this->mayActOn($actor, $target) && $actor->id !== $target->id;
    }

    /**
     * owner and pm are the user administrators; a pm may not act on an owner at all.
     * Self IS allowed here: there is no self-service password reset in this app, so an
     * admin without this path would be locked out permanently on a forgotten password.
     */
    public function updatePassword(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && $this->mayActOn($actor, $target);
    }

    /**
     * owner and pm are the user administrators; a pm may not act on an owner at all.
     */
    public function activate(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && $this->mayActOn($actor, $target);
    }

    /**
     * owner and pm are the user administrators; a pm may not act on an owner at all;
     * nobody may deactivate their OWN account.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $this->isAdmin($actor) && $this->mayActOn($actor, $target) && $actor->id !== $target->id;
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('owner') || $user->hasRole('pm');
    }

    /**
     * A pm may not act on an owner at all.
     */
    private function mayActOn(User $actor, User $target): bool
    {
        return $actor->hasRole('owner') || ! $target->hasRole('owner');
    }
}
