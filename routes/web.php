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

