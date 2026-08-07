<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

#[Fillable(['name', 'description', 'url', 'documentation_url', 'video_url', 'difficulty', 'status'])]
class Tool extends Model
{
    /**
     * owner, pm and manager see every tool, including every draft (ADR-35). Explicit role
     * names, mirroring ToolPolicy (ADR-12) and CategoryPolicy (ADR-28) — the `level` helpers
     * on User stay unused on purpose.
     *
     * @var list<string>
     */
    public const SEES_ALL_TOOLS_ROLES = ['owner', 'pm', 'manager'];

    /**
     * The SAME trap User documents: a column default is a DATABASE default, so after an insert
     * that omitted `status` the stored row says 'published' while the model in memory carries
     * null — and the create endpoint returned `"status": null` for a tool the database had
     * published. ADR-35 lets owner and pm rely on that default, so model and schema must agree.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'published',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = $request->string('search');

                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('category'), function (Builder $query) use ($request) {
                $slug = $request->string('category');

                $query->whereHas('categories', fn (Builder $query) => $query->where('slug', $slug));
            })
            ->when($request->filled('role'), function (Builder $query) use ($request) {
                $name = $request->string('role');

                $query->whereHas('roles', fn (Builder $query) => $query->where('name', $name));
            })
            ->when($request->filled('department'), function (Builder $query) use ($request) {
                $slug = $request->string('department');
                $query->whereHas('departments', fn (Builder $query) => $query->where('slug', $slug));
            });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        foreach (self::SEES_ALL_TOOLS_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $query;
            }
        }

        // Nested, so the OR cannot leak past a filter clause AND-ed on afterwards.
        return $query->where(function (Builder $query) use ($user) {
            $query->where('status', 'published')
                ->orWhere('created_by', $user->id);
        });
    }
}
