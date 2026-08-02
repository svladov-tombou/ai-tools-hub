<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        // Ordered by slug, not name: `name` is a JSON translation map (ADR-27), so the
        // database cannot sort it by the language the user is reading. The slug gives a
        // stable, deterministic order; the frontend sorts by the displayed name.
        return Category::orderBy('slug')->get(['id', 'name', 'slug']);
    }
}
