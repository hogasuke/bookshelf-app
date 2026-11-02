<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
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
Route::get('/books/create', [BookController::class, 'create'])->middleware('auth')->name('books.create');
Route::get('/books/{book}/edit', [BookController::class, 'edit'])->middleware('auth')->name('books.edit');
Route::delete('/books/{book}', [BookController::class, 'destroy'])->middleware('auth')->name('books.destroy');
Route::resource('books', BookController::class)->only(['index', 'show']);

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
Route::middleware('auth')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::resource('genres', GenreController::class)->only(['index', 'show']);
    Route::resource('reviews', ReviewController::class)->only('edit');
});
