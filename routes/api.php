<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::controller(App\Http\Controllers\System\PersonController::class)->prefix('people')->group(function () {
    Route::post('index', 'index');
    Route::post('/', 'store');
    Route::put('{person}', 'update');
    Route::delete('{person}', 'destroy');
});


BasicCrudRoutes::prefix('tags')->controller(App\Http\Controllers\Base\TagController::class)->register();



