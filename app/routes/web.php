<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\TotpController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomePlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringChargeController;
use App\Http\Controllers\ScheduledController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Webhook del bot de Telegram (validado por secret token + chat_id, sin sesión)
Route::post('/telegram/webhook', \App\Http\Controllers\TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::middleware(['auth', 'totp'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('sources', SourceController::class)->except('show');
    Route::resource('transactions', TransactionController::class)->except('show');
    Route::post('/transactions/{transaction}/duplicate', [TransactionController::class, 'duplicate'])->name('transactions.duplicate');
    Route::get('/accounts/{account}/adjust', [AccountController::class, 'adjustShow'])->name('accounts.adjust.show');
    Route::post('/accounts/{account}/adjust', [AccountController::class, 'adjustStore'])->name('accounts.adjust.store');
    Route::resource('recurring', RecurringChargeController::class)->except('show');
    Route::resource('income-plans', IncomePlanController::class)->except('show');
    Route::get('/income-plans/{incomePlan}/register',  [IncomePlanController::class, 'registerShow'])->name('income-plans.register.show');
    Route::post('/income-plans/{incomePlan}/register', [IncomePlanController::class, 'registerStore'])->name('income-plans.register.store');
    Route::post('/income-plans/{incomePlan}/toggle',   [IncomePlanController::class, 'toggle'])->name('income-plans.toggle');
    Route::post('/income-plans/{incomePlan}/duplicate', [IncomePlanController::class, 'duplicate'])->name('income-plans.duplicate');
    Route::post('/recurring/{recurring}/apply-now', [RecurringChargeController::class, 'applyNow'])->name('recurring.apply-now');
    Route::post('/recurring/{recurring}/toggle',    [RecurringChargeController::class, 'toggleActive'])->name('recurring.toggle');
    Route::get('/scheduled', [ScheduledController::class, 'index'])->name('scheduled.index');
    Route::resource('budgets', BudgetController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::view('/more', 'pages.more.index')->name('more');
    Route::view('/settings', 'pages.settings.index')->name('settings');
});

Route::middleware('auth')->group(function () {
    Route::get('/totp/setup', [TotpController::class, 'setupShow'])->name('totp.setup');
    Route::post('/totp/setup', [TotpController::class, 'setupConfirm'])
        ->middleware('throttle:5,1')
        ->name('totp.setup.confirm');
    Route::get('/totp/challenge', [TotpController::class, 'challengeShow'])->name('totp.challenge');
    Route::post('/totp/challenge', [TotpController::class, 'challengeVerify'])
        ->middleware('throttle:5,1')
        ->name('totp.challenge.verify');
});

require __DIR__.'/auth.php';
