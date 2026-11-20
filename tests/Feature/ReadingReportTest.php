<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * マイ読書レポート（シート5 E21・シート7 D48）。
 * 基本サマリー・評価分布・高評価書籍TOP5・ジャンル別評価傾向TOP5を、ログインした人のレビューだけで集計する。
 */
class ReadingReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    /**
     * @param  list<Genre>  $genres
     */
    private function review(User $user, int $rating, array $genres = [], ?string $title = null, ?string $createdAt = null): Review
    {
        $book = Book::factory()->create($title ? ['title' => $title] : []);
        $book->genres()->attach(collect($genres)->pluck('id'));

        $review = Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'rating' => $rating]);
        if ($createdAt) {
            $review->forceFill(['created_at' => $createdAt])->save();
        }

        return $review;
    }

    /** @return array<string, mixed> */
    private function stats(User $user): array
    {
        return $this->actingAs($user)->get('/reports')->assertOk()->viewData('stats');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_page_shows_the_four_sections_for_a_logged_in_user(): void
    {
        $user = User::factory()->create();
        $this->review($user, 5, [Genre::factory()->create(['name' => '小説'])], 'レポートの本');

        $this->actingAs($user)->get('/reports')->assertOk()
            ->assertSeeInOrder(['マイ読書レポート', '基本統計', '評価分布', '高評価書籍 TOP5', 'ジャンル別評価傾向 TOP5'])
            ->assertSee('レポートの本')
            ->assertSee('小説');
    }

    public function test_user_without_reviews_sees_zeros_and_empty_messages(): void
    {
        $user = User::factory()->create();
        $stats = $this->stats($user);

        $this->assertSame(['total_reviews' => 0, 'books_read' => 0, 'average_rating' => 0.0], $stats['summary']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);

        $this->actingAs($user)->get('/reports')
            ->assertSee('4星以上の書籍がありません')
            ->assertSee('ジャンルが設定された書籍のレビューがありません');
    }

    public function test_summary_and_distribution_count_only_my_reviews(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        collect([5, 4, 4, 2])->each(fn (int $rating) => $this->review($me, $rating));
        $this->review($other, 1);

        $stats = $this->stats($me);

        $this->assertSame(['total_reviews' => 4, 'books_read' => 4, 'average_rating' => 3.8], $stats['summary']);
        // 添字0が★1 … 添字4が★5
        $this->assertSame([0, 1, 0, 2, 1], $stats['rating_distribution']->all());
    }

    public function test_top_rated_books_are_four_stars_or_more_highest_first_up_to_five(): void
    {
        $user = User::factory()->create();
        $this->review($user, 3, title: '三つ星');
        $this->review($user, 4, title: '四つ星・古い', createdAt: '2026-01-01 00:00:00');
        $this->review($user, 5, title: '五つ星A', createdAt: '2026-01-02 00:00:00');
        $this->review($user, 4, title: '四つ星・新しい', createdAt: '2026-01-05 00:00:00');
        $this->review($user, 5, title: '五つ星B', createdAt: '2026-01-03 00:00:00');
        $this->review($user, 4, title: '四つ星・中', createdAt: '2026-01-04 00:00:00');

        $top = $this->stats($user)['top_rated_books'];

        $this->assertSame(['五つ星B', '五つ星A', '四つ星・新しい', '四つ星・中', '四つ星・古い'], $top->pluck('title')->all());
        $this->assertSame([5, 5, 4, 4, 4], $top->pluck('rating')->all());
        $this->assertSame(['id', 'title', 'author', 'rating'], array_keys($top->first()));

        $this->actingAs($user)->get('/reports')->assertSee(route('books.show', $top->first()['id']), false);
    }

    public function test_genre_ratings_average_per_genre_highest_first_up_to_five(): void
    {
        $user = User::factory()->create();
        [$novel, $tech, $essay] = collect(['小説', '技術書', 'エッセイ'])
            ->map(fn (string $name) => Genre::factory()->create(['name' => $name]))->all();
        $this->review($user, 5, [$novel, $tech]);   // 1冊が2ジャンル：両方に1件ずつ数える
        $this->review($user, 3, [$novel]);
        $this->review($user, 2, [$essay]);
        $this->review($user, 4);                    // ジャンル無しは数えない
        $this->review(User::factory()->create(), 1, [$tech]); // 他人のレビューは数えない

        $genres = $this->stats($user)['genre_ratings'];

        $this->assertSame([
            ['id' => $tech->id, 'name' => '技術書', 'count' => 1, 'average_rating' => 5.0],
            ['id' => $novel->id, 'name' => '小説', 'count' => 2, 'average_rating' => 4.0],
            ['id' => $essay->id, 'name' => 'エッセイ', 'count' => 1, 'average_rating' => 2.0],
        ], $genres->all());

        $this->actingAs($user)->get('/reports')->assertSee(route('genres.show', $tech->id), false);
    }

    public function test_genre_ratings_keep_only_top_five_and_break_ties_by_count(): void
    {
        $user = User::factory()->create();
        $genres = collect(range(1, 6))->map(fn (int $i) => Genre::factory()->create(['name' => "ジャンル{$i}"]));
        $genres->each(fn (Genre $genre, int $i) => $this->review($user, [5, 5, 4, 3, 2, 1][$i], [$genre]));
        // ジャンル2にもう1件の★5：平均5.0・2件で、1件だけのジャンル1（平均5.0）より上
        $this->review($user, 5, [$genres[1]]);

        $ranked = $this->stats($user)['genre_ratings'];

        $this->assertCount(5, $ranked);
        $this->assertSame('ジャンル2', $ranked[0]['name']);
        $this->assertSame(2, $ranked[0]['count']);
        $this->assertSame('ジャンル1', $ranked[1]['name']);
        $this->assertNotContains('ジャンル6', $ranked->pluck('name'));
    }

    public function test_report_does_not_issue_a_query_per_review(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        collect(range(1, 3))->each(fn () => $this->review($user, 4, [$genre]));
        $this->actingAs($user);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/reports')->assertOk();

            return count(DB::getQueryLog());
        };
        $count(); // 1回目はログイン中の人の読み込みなどが乗るので、2回目と比べる
        $few = $count();
        collect(range(1, 5))->each(fn () => $this->review($user, 5, [$genre]));

        $this->assertSame($few, $count());
    }
}
