<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use App\Services\GenreWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧を、紐づく書籍の数つきで出す。
     *
     * @return View 画面
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', compact('genres'));
    }

    /**
     * ジャンル登録フォームを出す。
     *
     * @return View 画面
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを登録し、一覧へ戻す。
     *
     * @param  GenreRequest  $request  リクエスト
     * @param  GenreWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function store(GenreRequest $request, GenreWriter $writer): RedirectResponse
    {
        $writer->create($request->validated('name'));

        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    /**
     * ジャンルに紐づく書籍を1ページ10件で出す。
     *
     * @param  Genre  $genre  対象のジャンル
     * @return View 画面
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()->with('genres')->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    /**
     * ジャンルの編集フォームを出す。
     *
     * @param  Genre  $genre  対象のジャンル
     * @return View 画面
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    /**
     * ジャンル名を更新し、一覧へ戻す。
     *
     * @param  GenreRequest  $request  リクエスト
     * @param  Genre  $genre  対象のジャンル
     * @param  GenreWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function update(GenreRequest $request, Genre $genre, GenreWriter $writer): RedirectResponse
    {
        $writer->update($genre, $request->validated('name'));

        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    /**
     * 書籍が紐づいていないジャンルだけを削除し、一覧へ戻す。
     *
     * @param  Genre  $genre  対象のジャンル
     * @param  GenreWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function destroy(Genre $genre, GenreWriter $writer): RedirectResponse
    {
        if (! $writer->delete($genre)) {
            return redirect()->route('genres.index')
                ->with('error', '書籍が紐付いているジャンルは削除できません。');
        }

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
