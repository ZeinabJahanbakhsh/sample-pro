<?php

use Illuminate\Http\Request;
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


use App\Http\Controllers\System\PersonController;

Route::controller(PersonController::class)->prefix('people')->group(function () {
    Route::get('/test', 'test');
    Route::post('index', 'index');
    Route::post('/', 'store');
    Route::put('{person}', 'update');
    Route::delete('{person}', 'destroy');
});
