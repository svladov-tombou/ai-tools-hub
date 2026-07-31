<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

#[Fillable(['name', 'description', 'url', 'documentation_url', 'video_url', 'difficulty', 'status'])]
class Tool extends Model
{
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
}
