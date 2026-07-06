<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MfaController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'app' => 'SecureWallet']));

    Route::get('/auth/captcha/site-key', [AuthController::class, 'captchaSiteKey'])->middleware('rate.limit:captcha-config,20,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('rate.limit:register,5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('rate.limit:login,5,1');
    Route::post('/auth/mfa/verify', [AuthController::class, 'verifyMfa'])->middleware('rate.limit:mfa,5,1');
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('rate.limit:refresh,10,1');

    Route::middleware('auth.token')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/mfa/enable', [MfaController::class, 'enable']);
        Route::post('/auth/mfa/enable/confirm', [MfaController::class, 'confirm'])->middleware('rate.limit:mfa-enable-confirm,5,1');
        Route::post('/auth/mfa/disable', [MfaController::class, 'disable']);

        Route::get('/me', [ProfileController::class, 'me']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/topup', [WalletController::class, 'topup'])->middleware('rate.limit:topup,20,1');

        Route::post('/transfers', [TransferController::class, 'store'])->middleware('rate.limit:transfers,10,1');
        Route::post('/transfers/{uuid}/confirm', [TransferController::class, 'confirm'])->middleware('rate.limit:transfer-confirm,10,1');
        Route::get('/transactions', [TransactionController::class, 'index']);

        Route::middleware('admin')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'users']);
            Route::patch('/admin/users/{uuid}/block', [AdminController::class, 'block']);
            Route::delete('/admin/users/{uuid}', [AdminController::class, 'destroy']);
            Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs']);
        });
    });
});
