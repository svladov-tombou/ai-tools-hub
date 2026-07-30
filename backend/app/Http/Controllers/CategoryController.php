<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::orderBy('name')->get(['id', 'name', 'slug']);
    }
}
