<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class BookSeeder extends Seeder
{
    public const RECORDS = [
        ['吾輩は猫である', '夏目漱石', '9784101010014', '1905-01-01', ['小説']],
        ['人を動かす', 'D・カーネギー', '9784422100524', '1936-10-01', ['ビジネス', '自己啓発']],
        ['リーダブルコード', 'Dustin Boswell', '9784873115658', '2012-06-23', ['技術書']],
        ['7つの習慣', 'スティーブン・R・コヴィー', '9784863940246', '2013-08-30', ['ビジネス', '自己啓発']],
        ['坊っちゃん', '夏目漱石', '9784101010021', '1906-04-01', ['小説']],
        ['サピエンス全史', 'ユヴァル・ノア・ハラリ', '9784309226712', '2016-09-08', ['歴史', '科学']],
        ['Clean Code', 'Robert C. Martin', '9784048930598', '2017-12-18', ['技術書']],
        ['嫌われる勇気', '岸見一郎・古賀史健', '9784478025819', '2013-12-13', ['自己啓発']],
        ['火花', '又吉直樹', '9784163902302', '2015-03-11', ['小説']],
        ['FACTFULNESS', 'ハンス・ロスリング', '9784822289607', '2019-01-11', ['ビジネス', '科学']],
        ['コンテナ物語', 'マルク・レビンソン', '9784822251468', '2007-01-18', ['ビジネス', '歴史']],
    ];

    public function run(): void
    {
        // 応用：マイ読書レポートで複数ユーザーの所有書籍を出すため、登録者をランダムに割り当てる。
        $users = User::all();
        if ($users->isEmpty()) {
            throw new LogicException('Run UserSeeder before BookSeeder.');
        }
        $genres = Genre::all()->keyBy('name');
        foreach (self::RECORDS as $record) {
            foreach ($record[4] as $name) {
                if (! $genres->has($name)) {
                    throw new LogicException('Run GenreSeeder before BookSeeder.');
                }
            }
        }
        DB::transaction(function () use ($users, $genres) {
            foreach (self::RECORDS as $index => [$title, $author, $isbn, $date, $names]) {
                // Only the fixed assignment dataset is unguarded, never request input.
                $book = Book::unguarded(fn () => Book::firstOrCreate(['isbn' => $isbn], [
                    'title' => $title, 'author' => $author, 'published_date' => $date,
                    'user_id' => $users->random()->id,
                    'description' => $title.'（模擬案件の動作確認用データ）',
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ]));
                $book->genres()->sync(collect($names)->map(fn ($name) => $genres[$name]->id)->all());
            }
        });
    }
}
