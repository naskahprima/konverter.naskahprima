<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConversionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';




Route::middleware(['auth'])->group(function () {
    Route::get('/conversions', [ConversionController::class, 'index'])->name('conversions.index');
    Route::get('/conversions/create', [ConversionController::class, 'create'])->name('conversions.create');
    Route::post('/conversions', [ConversionController::class, 'store'])->name('conversions.store');
    Route::get('/conversions/{conversion}', [ConversionController::class, 'show'])->name('conversions.show');
    Route::get('/conversions/{conversion}/poll', [ConversionController::class, 'poll'])->name('conversions.poll');
    Route::post('/conversions/{conversion}/fallback', [ConversionController::class, 'uploadFallback'])->name('conversions.fallback');
    Route::post('/conversions/{conversion}/qa', [ConversionController::class, 'submitQa'])->name('conversions.qa');
    Route::get('/conversions/{conversion}/result', [ConversionController::class, 'result'])->name('conversions.result');
    Route::get('/conversions/{conversion}/download', [ConversionController::class, 'download'])->name('conversions.download');
});
