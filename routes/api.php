<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\PageController;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

//Route::get('/projects/{project}', [ProjectController::class, 'project']);

Route::get('/api/load-projects/{project}', [ApiController::class, 'getProject']);
Route::get('/api/load-projects', [ApiController::class, 'getProjects']);
Route::get('/api/load-counters', [ApiController::class, 'getCounters']);

//Route::get('/{page?}', [PageController::class, 'page']);