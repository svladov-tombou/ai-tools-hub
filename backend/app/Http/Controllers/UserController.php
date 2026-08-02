<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRolesRequest;
use App\Models\Role;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        // A PLAIN ARRAY, deliberately NOT paginated — this is a company of tens of
        // employees, and pagination would force the frontend to unwrap an envelope
        // for nothing. Deactivated users ARE included: reactivating one has to be
        // possible from somewhere.
        return User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->userPayload($user))
            ->values();
    }

    /**
     * Authorization for store/update lives in the Form Request, which runs before
     * validation so a non-admin cannot get a 422 out of a write endpoint.
     */
    public function store(StoreUserRequest $request)
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        // department_id is outside #[Fillable] just like is_active, so it is set by
        // direct property assignment rather than through the mass-assigned array.
        $user->department_id = $request->validated('department_id');
        $user->save();
        $user->roles()->sync($request->validated('role_ids'));
        $user->load('roles');

        return response()->json($this->userPayload($user), 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->fill($request->safe()->only(['name', 'email']));
        // department_id is outside #[Fillable] just like is_active, so it is set by
        // direct property assignment rather than through the mass-assigned array.
        $user->department_id = $request->validated('department_id');
        $user->save();
        $user->load('roles');

        return $this->userPayload($user);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user)
    {
        // sync, never syncWithoutDetaching — this endpoint REPLACES the role set, and
        // syncWithoutDetaching would make removing a role silently do nothing.
        $user->roles()->sync($request->validated('role_ids'));
        $user->load('roles');

        return $this->userPayload($user);
    }

    public function updatePassword(UpdateUserPasswordRequest $request, User $user)
    {
        // The model's `hashed` cast hashes on assignment, so do NOT call Hash::make here.
        $user->password = $request->validated('password');
        $user->save();

        return response()->noContent();
    }

    /**
     * No Form Request: this action carries no payload, so it authorizes in the
     * controller body — the same split CategoryController::destroy already uses.
     */
    public function activate(User $user)
    {
        $this->authorize('activate', $user);

        // Deliberate anti-tampering: is_active is outside #[Fillable], so it is set by
        // direct property assignment, never through update([...]).
        $user->is_active = true;
        $user->save();
        $user->load('roles');

        return $this->userPayload($user);
    }

    /**
     * No Form Request: this action carries no payload, so it authorizes in the
     * controller body — the same split CategoryController::destroy already uses.
     */
    public function deactivate(User $user)
    {
        $this->authorize('deactivate', $user);

        // Deliberate anti-tampering: is_active is outside #[Fillable], so it is set by
        // direct property assignment, never through update([...]).
        $user->is_active = false;
        $user->save();

        // This is the ENTIRE mechanism by which a deactivated user loses access. The
        // project deliberately has no middleware checking is_active on every request
        // (ADR-32), so a token that is not deleted here stays valid until it expires.
        // Deleting the target's tokens must not touch anyone else's.
        $user->tokens()->delete();
        $user->load('roles');

        return $this->userPayload($user);
    }

    /**
     * The exact wire shape of a user. Explicit rather than returning the model, so
     * timestamps, the password hash and the belongsToMany `pivot` object never leak
     * into the contract (the enumerated-columns rule). The role shape matches the
     * frontend's RoleOption type exactly.
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'department_id' => $user->department_id,
            'roles' => $user->roles
                ->map(fn (Role $role) => $role->only(['id', 'name', 'display_name', 'level']))
                ->values(),
        ];
    }
}
