<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

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
    require __DIR__.'/settings.php';
});
