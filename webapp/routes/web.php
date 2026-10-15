<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecurringController;
use Illuminate\Support\Facades\Route;

Route::get('/belepes', [AuthController::class, 'show'])->name('login');
Route::post('/belepes', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/elso-belepes', [AuthController::class, 'setup'])->middleware('throttle:5,1');
Route::middleware('auth')->group(function () {
    Route::post('/kilepes', [AuthController::class, 'logout']);
    foreach (['/', '/honap', '/tervezes', '/beallitasok', '/eves', '/szamlak', '/hitelek', '/rendszeres', '/esti-rogzites'] as $path) {
        Route::get($path, [FinanceController::class, 'show']);
    }
    Route::post('/tetelek', [FinanceController::class, 'create']);
    Route::put('/tetelek/{transaction}', [FinanceController::class, 'update']);
    Route::delete('/tetelek/{transaction}', [FinanceController::class, 'delete']);
    Route::post('/tetelek/{id}/visszavonas', [FinanceController::class, 'undo']);
    Route::post('/napi-megjegyzes', [FinanceController::class, 'note']);
    Route::post('/beallitasok', [FinanceController::class, 'settings']);
    Route::post('/tervek', [FinanceController::class, 'plans']);
    Route::post('/kategoriak', [FinanceController::class, 'category']);
    Route::put('/kategoriak/{category}', [FinanceController::class, 'category']);
    Route::post('/kategoriak/sorrend', [FinanceController::class, 'reorder']);
    Route::post('/szamlak', [AccountController::class, 'save']);
    Route::put('/szamlak/{account}', [AccountController::class, 'save']);
    Route::post('/szamlak/{account}/helyzetjelentes', [AccountController::class, 'snapshot']);
    Route::post('/hitelek', [LoanController::class, 'save']);
    Route::put('/hitelek/{loan}', [LoanController::class, 'save']);
    Route::post('/rendszeres', [RecurringController::class, 'save']);
    Route::put('/rendszeres/{template}', [RecurringController::class, 'save']);
    Route::post('/rendszeres/{template}/teljesites', [RecurringController::class, 'confirm']);
    Route::post('/honap-teljes', [RecurringController::class, 'observation']);
    Route::post('/ertesitesek', [PushController::class, 'settings']);
    Route::post('/ertesitesek/eszkoz', [PushController::class, 'register'])->middleware('throttle:10,1');
    Route::delete('/ertesitesek/eszkoz/{device}', [PushController::class, 'disable']);
    Route::post('/ertesitesek/eszkoz/{device}/proba', [PushController::class, 'trial'])->middleware('throttle:3,1');
});
