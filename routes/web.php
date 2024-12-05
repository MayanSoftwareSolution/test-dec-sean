<?php

use App\Http\Controllers\GeoCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GeoCheckController::class, 'renderGeoCheckForm'])->name('location.form');
Route::post('/location/check', [GeoCheckController::class, 'submitLocation'])->name('location.check');
