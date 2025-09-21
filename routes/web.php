<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChirpController;
use App\Http\Controllers\Auth\Register;

Route::get('/', [ChirpController::class, 'index']);


Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');
 
Route::post('/register', Register::class)
    ->middleware('guest'); // Only unauthenticated users can register

Route::post('/logout', Register::class)
    ->middleware('auth'); // Only authenticated users can logout

Route::get('/login', Login::class)
    ->middleware('guest') // Only unauthenticated users can login
    ->name('login');

Route::post('/login', Login::class)
    ->middleware('guest'); // Only unauthenticated users can login

Route::middleware('auth')->group(function () { // Only authenticated users can access these routes
    Route::post('/chirps', [ChirpController::class, 'store']);
    Route::get('/chirps/{chirp}/edit', [ChirpController::class, 'edit']);
    Route::put('/chirps/{chirp}', [ChirpController::class, 'update']);
    Route::delete('/chirps/{chirp}', [ChirpController::class, 'destroy']);
});