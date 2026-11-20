<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewRequest;
use App\Models\Book;
use App\Models\Review;
use App\Services\ReviewWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * ログインした人のレビューを書籍に投稿し、詳細画面へ戻す。
     *
     * @param  ReviewRequest  $request  リクエスト
     * @param  Book  $book  対象の書籍
     * @param  ReviewWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function store(ReviewRequest $request, Book $book, ReviewWriter $writer): RedirectResponse
    {
        $writer->create($request->user(), $book, $request->validated());

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    /**
     * 投稿者本人だけに、レビューの編集フォームを出す。
     *
     * @param  Review  $review  対象のレビュー
     * @return View 画面
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);
        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    /**
     * 投稿者本人だけがレビューを更新し、書籍詳細へ戻す。
     *
     * @param  ReviewRequest  $request  リクエスト
     * @param  Review  $review  対象のレビュー
     * @param  ReviewWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function update(ReviewRequest $request, Review $review, ReviewWriter $writer): RedirectResponse
    {
        $this->authorize('update', $review);
        $writer->update($review, $request->validated());

        return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを更新しました。');
    }

    /**
     * 投稿者本人だけがレビューを削除し、書籍詳細へ戻す。
     *
     * @param  Review  $review  対象のレビュー
     * @param  ReviewWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function destroy(Review $review, ReviewWriter $writer): RedirectResponse
    {
        $this->authorize('delete', $review);
        $bookId = $review->book_id;
        $writer->delete($review);

        return redirect()->route('books.show', $bookId)->with('success', 'レビューを削除しました。');
    }
}
