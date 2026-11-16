<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [BookController::class, 'index']);
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

Route::middleware('auth')->group(function () {
    // books/create を books/{book} より先に登録する
    Route::resource('books', BookController::class)->except(['index', 'show']);
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('books.favorites.toggle');
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('books.reviews.store');

    Route::resource('reviews', ReviewController::class)->only(['edit', 'update', 'destroy']);
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like.toggle');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::resource('genres', GenreController::class);
});

Route::resource('books', BookController::class)->only(['index', 'show']);

// 道筋の無いURLの404でもセッションを読み、ヘッダーにログイン中の表示を出す
Route::fallback(fn () => abort(404));
