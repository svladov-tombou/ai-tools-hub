<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Category extends Model
{
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }

    /**
     * `name` is a translation map, e.g. ['bg' => '...', 'en' => '...', 'fr' => '...'].
     * Choosing the value for the current language is the frontend's job (ADR-27).
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
        ];
    }
}
