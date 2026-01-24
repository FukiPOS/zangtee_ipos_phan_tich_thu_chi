<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Public routes
Route::get('/haha', function () {
    echo 'haha';
    exit();
})->name('haha');

// Authentication routes (Fabi)
Route::middleware('fabi.guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('fabi.auth')->group(function () {
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // API route to get current user info
    Route::get('/api/me', [AuthController::class, 'me'])->name('api.me');

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Settings routes (protected)
    // Settings routes (protected)
    require __DIR__.'/settings.php';

    // Transaction routes
    Route::get('/transactions', [\App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::put('/transactions/{id}', [\App\Http\Controllers\TransactionController::class, 'update'])->name('transactions.update');
    Route::post('/transactions/bulk-update', [\App\Http\Controllers\TransactionController::class, 'bulkUpdate'])->name('transactions.bulk-update');

    // Revenue routes
    Route::get('/revenue', [\App\Http\Controllers\RevenueController::class, 'index'])->name('revenue.index');
});
