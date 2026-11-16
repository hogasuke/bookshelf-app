<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookIndexRequest;
use App\Http\Requests\Api\V1\BookStoreRequest;
use App\Http\Requests\Api\V1\BookUpdateRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\User;
use App\Services\BookWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BookController extends Controller
{
    public function index(BookIndexRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('keyword');
        $genreId = $request->validated('genre_id');

        $books = Book::with('genres')->withAvg('reviews', 'rating')->withCount('reviews')
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$keyword}%")->orWhere('author', 'like', "%{$keyword}%")))
            ->when($genreId, fn ($query) => $query->whereHas('genres', fn ($genres) => $genres->whereKey($genreId)))
            ->latest()->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? 20)
            ->withQueryString();

        return BookResource::collection($books);
    }

    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user'])->loadAvg('reviews', 'rating')->loadCount('reviews');

        return new BookResource($book);
    }

    public function store(BookStoreRequest $request, BookWriter $writer): JsonResponse
    {
        $creator = User::findOrFail($request->validated('user_id'));
        $book = $writer->create($creator, $request->validated(), $request->validated('genre_ids'));
        $book->loadAvg('reviews', 'rating')->loadCount('reviews');

        return (new BookResource($book))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(BookUpdateRequest $request, Book $book, BookWriter $writer): BookResource
    {
        $book = $writer->update($book, $request->validated(), $request->validated('genre_ids'));
        $book->loadAvg('reviews', 'rating')->loadCount('reviews');

        return new BookResource($book);
    }

    public function destroy(Book $book, BookWriter $writer): Response
    {
        $writer->delete($book);

        return response()->noContent();
    }
}
