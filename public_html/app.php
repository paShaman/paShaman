<?php

/*
 * Ранее index.php до появления отдельного проекта на vue
 */

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());