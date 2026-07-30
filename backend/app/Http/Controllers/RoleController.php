<?php

namespace App\Http\Controllers;

use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Role::orderByDesc('level')->get(['id', 'name', 'display_name', 'level']);
    }
}
