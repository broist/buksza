<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecurringController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/belepes', [AuthController::class, 'show'])->name('login');
Route::post('/belepes', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/elso-belepes', [AuthController::class, 'setup'])->middleware('throttle:5,1');
Route::post('/passkey/login/opciok', fn (Request $request, PasskeyController $controller) => $controller->options($request, 'login'))->middleware('throttle:10,1');
Route::post('/passkey/login', [PasskeyController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('auth')->group(function () {
    Route::post('/passkey/register/opciok', fn (Request $request, PasskeyController $controller) => $controller->options($request, 'register'))->middleware('throttle:5,1');
    Route::post('/passkey/register', [PasskeyController::class, 'register'])->middleware('throttle:5,1');
    Route::delete('/passkey/{id}', [PasskeyController::class, 'delete'])->middleware('throttle:5,1');
    Route::post('/kilepes', [AuthController::class, 'logout']);
    foreach (['/', '/honap', '/tervezes', '/beallitasok', '/eves', '/szamlak', '/hitelek', '/rendszeres', '/esti-rogzites', '/zsebpenzek', '/befektetesek'] as $path) {
        Route::get($path, [FinanceController::class, 'show']);
    }
    Route::post('/tetelek', [FinanceController::class, 'create']);
    Route::get('/export', [ExportController::class, 'download'])->middleware('throttle:20,1');
    Route::get('/mentes', [BackupController::class, 'download'])->middleware('throttle:10,1');
    Route::get('/mentes/elozo', [BackupController::class, 'previous']);
    Route::post('/visszaallitas', [BackupController::class, 'restore'])->middleware('throttle:3,1');
    Route::post('/befektetesek', [InvestmentController::class, 'save']);
    Route::put('/befektetesek/{investment}', [InvestmentController::class, 'save']);
    Route::post('/befektetesek/{investment}/terv', [InvestmentController::class, 'plan']);
    Route::post('/befektetesek/{investment}/ertek', [InvestmentController::class, 'value']);
    Route::post('/befektetesek/{investment}/kapcsolat', [InvestmentController::class, 'link']);
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
