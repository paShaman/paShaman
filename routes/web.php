<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/full', fn () => (new HomeController())(request()->merge(['full' => true])))->name('home.full');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('project.show');