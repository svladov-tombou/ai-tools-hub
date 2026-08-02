<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ToolController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tools', [ToolController::class, 'index']);
    Route::get('/tools/{tool}', [ToolController::class, 'show']);
    Route::post('/tools', [ToolController::class, 'store']);
    Route::put('/tools/{tool}', [ToolController::class, 'update']);
    Route::delete('/tools/{tool}', [ToolController::class, 'destroy']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/departments', [DepartmentController::class, 'index']);
});
