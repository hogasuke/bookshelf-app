<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $books = $request->user()->favoriteBooks()->with('genres')->paginate(10);

        return view('favorites.index', compact('books'));
    }
}
