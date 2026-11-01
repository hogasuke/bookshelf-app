<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Arr;

class BookWriter
{
    /** Persist validated input against the required owner-column schema. */
    public function create(User $creator, array $attributes, array $genreIds): Book
    {
        return $creator->getConnection()->transaction(function () use ($creator, $attributes, $genreIds) {
            $book = $creator->books()->create(Arr::only($attributes, [
                'title', 'author', 'isbn', 'published_date', 'description', 'image_url',
            ]));
            $book->genres()->sync($genreIds);

            return $book->fresh('genres');
        });
    }

    /**
     * Persist validated input. HTTP validation and response design remain separate.
     */
    public function update(Book $book, array $attributes, array $genreIds): Book
    {
        return $book->getConnection()->transaction(function () use ($book, $attributes, $genreIds) {
            $book->update(Arr::only($attributes, [
                'title', 'author', 'isbn', 'published_date', 'description', 'image_url',
            ]));
            $book->genres()->sync($genreIds);

            return $book->fresh('genres');
        });
    }

    public function delete(Book $book): void
    {
        $book->getConnection()->transaction(fn () => $book->delete());
    }
}
