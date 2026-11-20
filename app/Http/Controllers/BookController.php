<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\BookWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧。キーワード・ジャンル・並び順で絞り、1ページ10件で出す。
     *
     * @param  Request  $request  リクエスト
     * @return View 画面
     */
    public function index(Request $request): View
    {
        // keyword[]=… のような配列の値は捨てる（画面の入力欄へ戻す時に壊れるため）
        $request->query->replace(array_filter($request->query->all(), 'is_string'));

        $books = Book::with('genres')->withAvg('reviews', 'rating')
            ->keyword($request->query('keyword'))
            ->inGenre($request->query('genre'))
            ->sortBy($request->query('sort'))
            ->paginate(10)->withQueryString();
        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 書籍登録フォームを、選べるジャンルつきで出す。
     *
     * @return View 画面
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * ログインした人を登録者として書籍を登録し、詳細画面へ戻す。
     *
     * @param  BookRequest  $request  リクエスト
     * @param  BookWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function store(BookRequest $request, BookWriter $writer): RedirectResponse
    {
        $book = $writer->create($request->user(), $request->validated(), $request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細を、ジャンル・レビュー・いいねした人つきで出す。
     *
     * @param  Book  $book  対象の書籍
     * @return View 画面
     */
    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);

        return view('books.show', compact('book'));
    }

    /**
     * 登録者本人だけに、書籍の編集フォームを出す。
     *
     * @param  Book  $book  対象の書籍
     * @return View 画面
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        $book->load('genres');
        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 登録者本人だけが書籍とジャンルを更新し、詳細画面へ戻す。
     *
     * @param  BookRequest  $request  リクエスト
     * @param  Book  $book  対象の書籍
     * @param  BookWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function update(BookRequest $request, Book $book, BookWriter $writer): RedirectResponse
    {
        $this->authorize('update', $book);
        $writer->update($book, $request->validated(), $request->validated('genres'));

        return redirect()->route('books.show', $book)->with('success', '書籍を更新しました。');
    }

    /**
     * 登録者本人だけが書籍を削除し、一覧へ戻す。
     *
     * @param  Book  $book  対象の書籍
     * @param  BookWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function destroy(Book $book, BookWriter $writer): RedirectResponse
    {
        $this->authorize('delete', $book);
        $writer->delete($book);

        return redirect()->route('books.index')->with('success', '書籍を削除しました。');
    }
}
