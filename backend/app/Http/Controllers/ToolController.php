<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreToolRequest;
use App\Http\Requests\UpdateToolRequest;
use App\Models\Tool;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    public function index(Request $request)
    {
        return Tool::filter($request)
            ->with(['categories', 'roles', 'creator'])
            ->paginate(15);
    }

    public function show(Tool $tool)
    {
        return $tool->load('categories', 'roles', 'creator');
    }

    public function store(StoreToolRequest $request)
    {
        $this->authorize('create', Tool::class);

        $tool = Tool::create($request->safe()->except(['category_ids', 'role_ids']));
        $tool->created_by = $request->user()->id;
        $tool->save();

        if ($request->has('category_ids')) {
            $tool->categories()->sync($request->input('category_ids', []));
        }

        if ($request->has('role_ids')) {
            $tool->roles()->sync($request->input('role_ids', []));
        }

        return response()->json(
            $tool->load('categories', 'roles', 'creator'),
            201
        );
    }

    public function update(UpdateToolRequest $request, Tool $tool)
    {
        $this->authorize('update', $tool);

        $tool->update($request->safe()->except(['category_ids', 'role_ids']));

        if ($request->has('category_ids')) {
            $tool->categories()->sync($request->input('category_ids', []));
        }

        if ($request->has('role_ids')) {
            $tool->roles()->sync($request->input('role_ids', []));
        }

        return $tool->load('categories', 'roles', 'creator');
    }

    public function destroy(Tool $tool)
    {
        $this->authorize('delete', $tool);

        $tool->delete();

        return response()->noContent();
    }
}
