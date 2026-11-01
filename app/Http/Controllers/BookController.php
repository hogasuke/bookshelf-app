<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\View\View;

class BookController extends Controller
{
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        $book->load('genres');
        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    public function index(): View
    {
        $books = Book::with('genres')->withAvg('reviews', 'rating')->latest()->paginate(10);

        return view('books.index', compact('books'));
    }

    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user', 'reviews.likedByUsers']);

        return view('books.show', compact('book'));
    }
}
