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
    public function store(ReviewRequest $request, Book $book, ReviewWriter $writer): RedirectResponse
    {
        $writer->create($request->user(), $book, $request->validated());

        return redirect()->route('books.show', $book)->with('success', 'レビューを投稿しました。');
    }

    public function edit(Review $review): View
    {
        $this->authorize('update', $review);
        $review->load('book');

        return view('reviews.edit', compact('review'));
    }

    public function update(ReviewRequest $request, Review $review, ReviewWriter $writer): RedirectResponse
    {
        $this->authorize('update', $review);
        $writer->update($review, $request->validated());

        return redirect()->route('books.show', $review->book_id)->with('success', 'レビューを更新しました。');
    }

    public function destroy(Review $review, ReviewWriter $writer): RedirectResponse
    {
        $this->authorize('delete', $review);
        $bookId = $review->book_id;
        $writer->delete($review);

        return redirect()->route('books.show', $bookId)->with('success', 'レビューを削除しました。');
    }
}
