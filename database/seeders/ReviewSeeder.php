<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewSeeder extends Seeder
{
    /**
     * 評価ごとのコメント（応用：1〜5の5段階の汎用文言）。
     */
    public const COMMENTS = [
        1 => '期待していた内容とは合いませんでした。',
        2 => '一部は参考になりましたが、物足りなさが残りました。',
        3 => '可もなく不可もなく、普通に読める一冊でした。',
        4 => '分かりやすく、身近な場面で活かせそうな内容でした。',
        5 => '最後まで夢中で読みました。何度も読み返したい一冊です。',
    ];

    public function run(): void
    {
        $users = SampleData::users();
        $books = SampleData::books();
        // The assignment requires create(), not overwrite or deletion on a rerun.
        if (Review::whereIn('book_id', $books->modelKeys())->whereIn('user_id', $users->modelKeys())->exists()) {
            throw new LogicException('Sample reviews already exist. ReviewSeeder did not overwrite them.');
        }
        // 応用：各書籍に2〜4件、投稿者と評価（1〜5）をランダムにする。同じ人が同じ本に2件書かないよう投稿者は重ねない。
        DB::transaction(function () use ($users, $books) {
            foreach ($books as $book) {
                foreach ($users->random(random_int(2, 4)) as $user) {
                    $rating = random_int(1, 5);
                    Review::create([
                        'book_id' => $book->id, 'user_id' => $user->id,
                        'rating' => $rating, 'comment' => self::COMMENTS[$rating],
                    ]);
                }
            }
        });
    }
}
