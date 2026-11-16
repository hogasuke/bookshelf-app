<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;

class SocialToggles
{
    // HTTP response and self-like permission must be decided by the caller.
    // A second call intentionally reverses the state; this is not idempotent.
    public function favorite(User $user, Book $book): bool
    {
        $result = $user->favoriteBooks()->toggle([$book->id]);

        return count($result['attached']) > 0;
    }

    public function like(User $user, Review $review): bool
    {
        $result = $user->likedReviews()->toggle([$review->id]);

        return count($result['attached']) > 0;
    }
}
