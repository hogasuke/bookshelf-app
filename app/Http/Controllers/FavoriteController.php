<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\SocialToggles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * ログインした人のお気に入り書籍を1ページ10件で出す。
     *
     * @param  Request  $request  リクエスト
     * @return View 画面
     */
    public function index(Request $request): View
    {
        $books = $request->user()->favoriteBooks()->with('genres')->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * 書籍のお気に入りを付け外しし、押した画面へ戻す。
     *
     * @param  Request  $request  リクエスト
     * @param  Book  $book  対象の書籍
     * @param  SocialToggles  $toggles  お気に入り・いいねの付け外しを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function toggle(Request $request, Book $book, SocialToggles $toggles): RedirectResponse
    {
        $added = $toggles->favorite($request->user(), $book);

        // お気に入り一覧から押した時は一覧に戻す（押した画面へ戻り、無ければ書籍詳細）
        return redirect()->back(fallback: route('books.show', $book))
            ->with('success', $added ? 'お気に入りに追加しました。' : 'お気に入りを解除しました。');
    }
}
