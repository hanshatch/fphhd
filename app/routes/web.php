<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\TotpController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Rutas protegidas con auth + TOTP verificado
Route::middleware(['auth', 'totp'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('accounts', AccountController::class)->except('show');
    Route::get('/import',         [ImportController::class, 'create'])->name('import.create');
    Route::post('/import/upload', [ImportController::class, 'upload'])->name('import.upload');
    Route::get('/import/review',  [ImportController::class, 'review'])->name('import.review');
    Route::post('/import/confirm',[ImportController::class, 'confirm'])->name('import.confirm');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('sources', SourceController::class)->except('show');
    Route::resource('transactions', TransactionController::class)->except('show');
});

// Rutas TOTP (solo auth, sin requerir TOTP verificado todavía)
Route::middleware('auth')->group(function () {
    Route::get('/totp/setup', [TotpController::class, 'setupShow'])->name('totp.setup');
    Route::post('/totp/setup', [TotpController::class, 'setupConfirm'])->name('totp.setup.confirm');
    Route::get('/totp/challenge', [TotpController::class, 'challengeShow'])->name('totp.challenge');
    Route::post('/totp/challenge', [TotpController::class, 'challengeVerify'])->name('totp.challenge.verify');
});

require __DIR__.'/auth.php';
