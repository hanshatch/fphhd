<?php

use App\Http\Controllers\Auth\TotpController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Rutas protegidas con auth + TOTP verificado
Route::middleware(['auth', 'totp'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Rutas TOTP (solo auth, sin requerir TOTP verificado todavía)
Route::middleware('auth')->group(function () {
    Route::get('/totp/setup', [TotpController::class, 'setupShow'])->name('totp.setup');
    Route::post('/totp/setup', [TotpController::class, 'setupConfirm'])->name('totp.setup.confirm');
    Route::get('/totp/challenge', [TotpController::class, 'challengeShow'])->name('totp.challenge');
    Route::post('/totp/challenge', [TotpController::class, 'challengeVerify'])->name('totp.challenge.verify');
});

require __DIR__.'/auth.php';
