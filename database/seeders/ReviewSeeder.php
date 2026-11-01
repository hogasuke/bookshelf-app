<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = SampleData::users();
        $books = SampleData::books();
        // The assignment requires create(), not overwrite or deletion on a rerun.
        if (Review::whereIn('book_id', $books->modelKeys())->whereIn('user_id', $users->modelKeys())->exists()) {
            throw new LogicException('Sample reviews already exist. ReviewSeeder did not overwrite them.');
        }
        DB::transaction(function () use ($users, $books) {
            foreach ($books as $index => $book) {
                $count = $index === 10 ? 2 : 3; // 10*3+2 = 32, each book has 2-4.
                for ($offset = 0; $offset < $count; $offset++) {
                    Review::create([
                        'book_id' => $book->id, 'user_id' => $users[($index + $offset) % 5]->id,
                        'rating' => 3 + (($index + $offset) % 3),
                        'comment' => $book->title.'を読みました。印象に残る内容でした。',
                    ]);
                }
            }
        });
    }
}
