<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * レビューのある書籍を平均評価の高い順に10件出す。
     *
     * @return View 画面
     */
    public function index(): View
    {
        $rankedBooks = Book::whereHas('reviews')->withAvg('reviews', 'rating')
            ->withCount('reviews')->orderByDesc('reviews_avg_rating')->limit(10)->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
