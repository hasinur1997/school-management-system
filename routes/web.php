<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/parents', function () {
    return view('parents.index');
})->name('parents.frontend');
