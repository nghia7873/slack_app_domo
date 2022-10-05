<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [\App\Http\Controllers\Controller::class, 'showListAccountSlack'])->name('list-slack');
Route::get('/slack/{id}', [\App\Http\Controllers\Controller::class, 'editAccountSlack'])->name('edit-slack');
Route::get('/slack', [\App\Http\Controllers\Controller::class, 'createAccountSlack'])->name('create-slack');
Route::post('/slack/create', [\App\Http\Controllers\Controller::class, 'postCreateAccountSlack'])->name('post-create-slack');
Route::post('/slack', [\App\Http\Controllers\Controller::class, 'postEditAccountSlack'])->name('post-edit-slack');
Route::get('/linked-cookie', [\App\Http\Controllers\Controller::class, 'authenLinkedin']);
Route::get('/test', [\App\Http\Controllers\Controller::class, 'linkedCookie']);
Route::post('/linked-cookie', [\App\Http\Controllers\Controller::class, 'handleLinkedin']);
Route::get('/me', [\App\Http\Controllers\Controller::class, 'me']);
Route::get('/export', [\App\Http\Controllers\Controller::class, 'export']);
Route::get('/clear-cache', [\App\Http\Controllers\Controller::class, 'clearCache']);


