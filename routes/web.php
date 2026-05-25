<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/projects/{project}', 'ProjectController@project');

$router->get('/api/load-projects/{project}', 'ApiController@getProject');
$router->get('/api/load-projects', 'ApiController@getProjects');
$router->get('/api/load-counters', 'ApiController@getCounters');

$router->get('/[{page}]', 'PageController@page');