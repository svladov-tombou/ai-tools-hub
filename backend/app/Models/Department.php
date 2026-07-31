<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Department extends Model
{
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }
}
