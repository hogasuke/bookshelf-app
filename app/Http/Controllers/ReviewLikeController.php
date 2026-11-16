<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\SocialToggles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    public function toggle(Request $request, Review $review, SocialToggles $toggles): RedirectResponse
    {
        $liked = $toggles->like($request->user(), $review);

        return redirect()->route('books.show', $review->book_id)
            ->with('success', $liked ? 'いいねしました。' : 'いいねを取り消しました。');
    }
}
