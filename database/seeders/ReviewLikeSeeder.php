<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    public function run(): void
    {
        $users = SampleData::users();
        $books = SampleData::books();
        $reviews = Review::whereIn('book_id', $books->modelKeys())->whereIn('user_id', $users->modelKeys())->orderBy('id')->get();
        foreach ($reviews as $index => $review) {
            $ids = $users->reject(fn ($user) => $user->id === $review->user_id)->take($index % 4)->modelKeys();
            $review->likedByUsers()->syncWithoutDetaching($ids);
        }
    }
}
