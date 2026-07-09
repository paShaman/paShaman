<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/full', HomeController::class)->name('home.full');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('project.show');

// API для фронта
Route::get('/api/projects', [HomeController::class, 'projectsApi'])->name('api.projects');
Route::get('/api/tags', [HomeController::class, 'tagsApi'])->name('api.tags');
