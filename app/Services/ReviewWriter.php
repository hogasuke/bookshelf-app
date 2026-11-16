<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Arr;

class ReviewWriter
{
    /** Uses the existing DB constraints; review multiplicity is still a coach question. */
    public function create(User $author, Book $book, array $attributes): Review
    {
        $review = new Review(Arr::only($attributes, ['rating', 'comment']));
        $review->user()->associate($author);
        $review->book()->associate($book);
        $review->save();

        return $review;
    }

    /** Persist validated review input; validation and HTTP responses are separate. */
    public function update(Review $review, array $attributes): Review
    {
        $review->update(Arr::only($attributes, ['rating', 'comment']));

        return $review->fresh();
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }
}
