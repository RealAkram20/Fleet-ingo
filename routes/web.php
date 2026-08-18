<?php

use App\Http\Controllers\BikeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ServiceRecordController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Weekly odometer entry — the screen the yard uses most.
    Route::get('/readings', [ReadingController::class, 'index'])->name('readings.index');
    Route::post('/readings', [ReadingController::class, 'store'])->name('readings.store');
    Route::patch('/readings/{reading}', [ReadingController::class, 'update'])->name('readings.update');
    Route::delete('/readings/{reading}', [ReadingController::class, 'destroy'])->name('readings.destroy');

    Route::post('/bikes/{bike}/service', [ServiceRecordController::class, 'store'])->name('services.store');

    // Reading the roster is fine for anyone signed in; changing it is not.
    Route::get('riders', [RiderController::class, 'index'])->name('riders.index');
    Route::get('bikes', [BikeController::class, 'index'])->name('bikes.index');

    Route::middleware('can:manage-fleet')->group(function () {
        Route::resource('riders', RiderController::class)->except(['show', 'create', 'index']);
        Route::resource('bikes', BikeController::class)->except(['show', 'create', 'index']);
    });

    // Accounts and configuration — admin only.
    Route::middleware('can:administer')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'create']);

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding');
        Route::patch('settings/fleet', [SettingsController::class, 'updateFleet'])->name('settings.fleet');
        Route::patch('settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [SettingsController::class, 'sendTestEmail'])->name('settings.mail.test');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
