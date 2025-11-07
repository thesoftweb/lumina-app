<?php

use Illuminate\Support\Facades\Route;

Route::post('/artisan', App\Http\Controllers\ArtisanController::class);

Route::get('/', function () {
    return view('welcome');
});
