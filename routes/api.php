<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/webhook/{id}', [\App\Http\Controllers\Controller::class, 'handleWebhook']);
Route::post('/webhook_domo/{id}', [\App\Http\Controllers\Controller::class, 'handleWebhookDomo']);
Route::get('/get-list-linkedin', [\App\Http\Controllers\Controller::class, 'getLinkedinJob']);
