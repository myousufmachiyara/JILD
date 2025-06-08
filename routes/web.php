<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ProductionController;

Route::get('/', function () {
    return view('home');
});

Route::resource('/products', ProductController::class);
Route::resource('/purchases', PurchaseController::class);
Route::get('/production/receiving', [ProductionController::class, 'receiving'])->name('production.receiving');
Route::resource('/production', ProductionController::class);

