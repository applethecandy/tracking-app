<?php

use App\Http\Controllers\GpxExportController;
use App\Http\Controllers\PngExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicRouteController;
use App\Http\Controllers\TrackRouteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('routes.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('routes', TrackRouteController::class)->parameters(['routes' => 'trackRoute']);
    Route::get('/routes/{trackRoute}/gpx', [GpxExportController::class, 'route'])->name('routes.gpx');
    Route::get('/routes/{trackRoute}/png', [PngExportController::class, 'route'])->name('routes.png');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/r/{shareToken}', [PublicRouteController::class, 'show'])->name('public.routes.show');
Route::get('/r/{shareToken}/gpx', [GpxExportController::class, 'public'])->name('public.routes.gpx');

require __DIR__.'/auth.php';
