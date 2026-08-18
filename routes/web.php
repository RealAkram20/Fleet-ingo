<?php

use App\Http\Controllers\BikeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\ServiceRecordController;
use App\Http\Controllers\RiderController;
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

    Route::resource('riders', RiderController::class)->except(['show', 'create']);
    Route::resource('bikes', BikeController::class)->except(['show', 'create']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
