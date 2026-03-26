<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\RequestController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Employee request management
    Route::middleware('role:employee')->prefix('requests')->group(function () {
        Route::post('/', [RequestController::class, 'store']);
        Route::get('/', [RequestController::class, 'index']);
        Route::get('/{id}', [RequestController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [RequestController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [RequestController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/submit', [RequestController::class, 'submit'])->whereNumber('id');
    });

    // Manager approval workflow
    Route::middleware('role:manager')->prefix('requests')->group(function () {
        Route::get('/approvals/approved', [RequestController::class, 'approvedApprovals']);
        Route::get('/history', [RequestController::class, 'decisionHistory']);
        Route::post('/{id}/approve', [RequestController::class, 'approve'])->whereNumber('id');
        Route::post('/{id}/reject', [RequestController::class, 'reject'])->whereNumber('id');
        Route::get('/{id}/approvals', [RequestController::class, 'approvals'])->whereNumber('id');
        Route::get('/{id}/history', [RequestController::class, 'history'])->whereNumber('id');
    });
});
