<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\ProcurementOrderController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\VendorController;
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

    // Purchasing verification workflow
    Route::middleware('role:purchasing')->prefix('requests')->group(function () {
        Route::get('/verification-queue', [RequestController::class, 'verificationQueue']);
        Route::get('/verification-queue/{id}', [RequestController::class, 'verificationQueueShow'])->whereNumber('id');
        Route::post('/{id}/verify', [RequestController::class, 'verify'])->whereNumber('id');
    });

    // Manager approval workflow
    Route::middleware('role:manager')->prefix('requests')->group(function () {
        Route::get('/approval-queue', [RequestController::class, 'approvalQueue']);
        Route::get('/approval-queue/{id}', [RequestController::class, 'approvalQueueShow'])->whereNumber('id');
        Route::get('/approvals/approved', [RequestController::class, 'approvedApprovals']);
        Route::get('/history', [RequestController::class, 'decisionHistory']);
        Route::post('/{id}/approve', [RequestController::class, 'approve'])->whereNumber('id');
        Route::post('/{id}/reject', [RequestController::class, 'reject'])->whereNumber('id');
        Route::get('/{id}/approvals', [RequestController::class, 'approvals'])->whereNumber('id');
        Route::get('/{id}/history', [RequestController::class, 'history'])->whereNumber('id');
    });

    // Vendor endpoints
    Route::middleware('role:purchasing,warehouse')->get('/vendors', [VendorController::class, 'index']);
    Route::middleware('role:purchasing')->post('/vendors', [VendorController::class, 'store']);

    // Stock visibility and movement viewers
    Route::prefix('stocks')->group(function () {
        Route::middleware('role:warehouse,purchasing')->get('/movements', [StockController::class, 'movementIndex']);
        Route::middleware('role:warehouse,purchasing')->get('/movements/{id}', [StockController::class, 'movementShow'])->whereNumber('id');
        Route::middleware('role:manager')->get('/movements/summary', [StockController::class, 'movementSummary']);

        Route::middleware('role:warehouse')->get('/', [StockController::class, 'index']);
        Route::middleware('role:warehouse')->get('/{id}', [StockController::class, 'show'])->whereNumber('id');
    });

    // Warehouse procurement flow
    Route::middleware('role:warehouse')->prefix('requests')->group(function () {
        Route::get('/procurement-queue', [RequestController::class, 'procurementQueue']);
        Route::get('/procurement-queue/{id}', [RequestController::class, 'procurementQueueShow'])->whereNumber('id');
        Route::post('/{id}/issue', [RequestController::class, 'issue']);
    });
    Route::middleware('role:warehouse')->post('/requests/{id}/procure', [ProcurementOrderController::class, 'store'])->whereNumber('id');

    // Procurement order management
    Route::middleware('role:purchasing,warehouse')->prefix('procurement-orders')->group(function () {
        Route::get('/', [ProcurementOrderController::class, 'index']);
        Route::get('/{id}', [ProcurementOrderController::class, 'show'])->whereNumber('id');
        Route::put('/{id}/status', [ProcurementOrderController::class, 'updateStatus'])->whereNumber('id');
    });
});
