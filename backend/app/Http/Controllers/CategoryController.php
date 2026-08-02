<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        // Ordered by slug, not name: `name` is a JSON translation map (ADR-27), so the
        // database cannot sort it by the language the user is reading. The slug gives a
        // stable, deterministic order; the frontend sorts by the displayed name.
        return Category::orderBy('slug')->get(['id', 'name', 'slug']);
    }

    /**
     * Authorization for store/update lives in the Form Request, which runs before
     * validation so a non-admin cannot get a 422 out of a write endpoint.
     */
    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return response()->json($category->only(['id', 'name', 'slug']), 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return $category->only(['id', 'name', 'slug']);
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        // Deleting is BLOCKED while tools use the category. The category_tool foreign keys
        // cascade, so a delete would quietly detach the category from every tool and leave
        // no trace of what was lost. Refusing with the count tells the admin what to fix.
        $toolCount = $category->tools()->count();

        if ($toolCount > 0) {
            throw ValidationException::withMessages([
                'category' => ["This category cannot be deleted because {$toolCount} tool(s) still use it."],
            ]);
        }

        $category->delete();

        return response()->noContent();
    }
}
