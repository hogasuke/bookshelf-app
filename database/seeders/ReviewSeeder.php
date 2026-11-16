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
        // 各書籍の評価（3〜5）。平均が書籍ごとに違う値になるよう固定で配分する。計32件・各書籍2〜4件。
        $ratingsByBook = [
            [5, 5, 5, 4], [3, 3, 4], [5, 5, 5], [4, 4, 3], [3, 3], [5, 5, 4],
            [5, 4, 4], [4, 4, 4], [4, 3], [4, 4, 4, 5], [5, 4],
        ];
        $comments = [
            3 => 'は読みやすかったものの、期待していたほどの新しさはありませんでした。',
            4 => 'は内容が分かりやすく、身近な場面で活かせそうだと感じました。',
            5 => 'は最後まで夢中で読みました。何度も読み返したい一冊です。',
        ];
        DB::transaction(function () use ($users, $books, $ratingsByBook, $comments) {
            foreach ($books as $index => $book) {
                foreach ($ratingsByBook[$index] as $offset => $rating) {
                    Review::create([
                        'book_id' => $book->id, 'user_id' => $users[($index + $offset) % 5]->id,
                        'rating' => $rating,
                        'comment' => '『'.$book->title.'』'.$comments[$rating],
                    ]);
                }
            }
        });
    }
}
