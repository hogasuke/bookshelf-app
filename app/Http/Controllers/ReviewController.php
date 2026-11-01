<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);
        $review->load('book');

        return view('reviews.edit', compact('review'));
    }
}
