<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenreRequest;
use App\Models\Genre;
use App\Services\GenreWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', compact('genres'));
    }

    public function create(): View
    {
        return view('genres.create');
    }

    public function store(GenreRequest $request, GenreWriter $writer): RedirectResponse
    {
        $writer->create($request->validated('name'));

        return redirect()->route('genres.index')->with('success', 'ジャンルを登録しました。');
    }

    public function show(Genre $genre): View
    {
        $books = $genre->books()->with('genres')->paginate(10);

        return view('genres.show', compact('genre', 'books'));
    }

    public function edit(Genre $genre): View
    {
        return view('genres.edit', compact('genre'));
    }

    public function update(GenreRequest $request, Genre $genre, GenreWriter $writer): RedirectResponse
    {
        $writer->update($genre, $request->validated('name'));

        return redirect()->route('genres.index')->with('success', 'ジャンルを更新しました。');
    }

    public function destroy(Genre $genre, GenreWriter $writer): RedirectResponse
    {
        if (! $writer->delete($genre)) {
            return redirect()->route('genres.index')
                ->with('error', '書籍が紐付いているジャンルは削除できません。');
        }

        return redirect()->route('genres.index')->with('success', 'ジャンルを削除しました。');
    }
}
