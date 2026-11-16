<?php

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 公開API：読み取り（一覧・詳細）は認証なし。書き込み（登録・更新・削除）は Sanctum のトークン認証が必須（シート13 AP06）
Route::prefix('v1')->name('api.v1.')->group(function () {
    // メールアドレスとパスワードで APIトークンを発行する（Authorization: Bearer <token> で使う）
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:10,1')->name('tokens.store');

    Route::apiResource('books', BookController::class)->only(['index', 'show']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('books', BookController::class)->only(['store', 'update', 'destroy']);
    });
});
