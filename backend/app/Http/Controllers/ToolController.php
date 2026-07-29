<?php

namespace App\Http\Controllers;

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
}
