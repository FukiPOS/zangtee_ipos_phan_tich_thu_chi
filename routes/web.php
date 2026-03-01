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
    Route::post('/transactions', [\App\Http\Controllers\TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{id}', [\App\Http\Controllers\TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{id}', [\App\Http\Controllers\TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::post('/transactions/bulk-update', [\App\Http\Controllers\TransactionController::class, 'bulkUpdate'])->name('transactions.bulk-update');

    // Revenue routes
    Route::get('/revenue', [\App\Http\Controllers\RevenueController::class, 'index'])->name('revenue.index');
});

// Public API — validate by static internal-token header
Route::post('/api/transactions', function (\Illuminate\Http\Request $request) {
    if ($request->header('internal-token') !== 'GHVTHV1') {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $validated = $request->validate([
        'store_uid' => 'required|string',
        'cash_id' => 'required|string|unique:transactions,cash_id',
        'amount' => 'required|numeric|min:0',
        'type' => 'required|in:IN,OUT',
        'note' => 'nullable|string',
        'profession_id' => 'nullable|exists:professions,id',
        'flag' => 'nullable|in:valid,review,invalid',
        'time' => 'nullable|numeric',
    ]);

    $time = $validated['time'] ?? (int)(now()->timestamp * 1000);

    $professionId = $validated['profession_id'] ?? null;
    if ($validated['type'] === 'IN' && !$professionId) {
        $prof = \App\Models\Profession::firstOrCreate(
            ['name' => 'Thu tiền mặt để chi tiêu'],
            ['type' => 'IN']
        );
        $professionId = $prof->id;
    }

    $transaction = \App\Models\Transaction::create([
        'store_uid' => $validated['store_uid'],
        'cash_id' => $validated['cash_id'],
        'amount' => $validated['amount'],
        'type' => $validated['type'],
        'note' => $validated['note'] ?? null,
        'profession_id' => $professionId,
        'flag' => $validated['flag'] ?? 'valid',
        'time' => $time,
        'source' => 'hand',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $store = \App\Models\Store::where('ipos_id', $validated['store_uid'])->first();
    if ($store) {
        $signedAmount = $validated['type'] === 'OUT' ? -(int) $validated['amount'] : (int) $validated['amount'];
        $store->increment('cash', $signedAmount);
        $transaction->update(['cash_processed' => true, 'cash_amount' => $signedAmount]);
    }

    return response()->json(['success' => true, 'transaction' => $transaction], 201);
})->name('api.transactions.store');
