<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use App\Services\BookWriter;
use Database\Seeders\BookSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AssignmentSeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_full_seed_matches_the_assignment_contract(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('genres', 10);
        $this->assertDatabaseCount('books', 11);
        // 応用：レビューは各書籍2〜4件のランダム、登録者もランダム（シート9 D11・D12）
        $this->assertGreaterThanOrEqual(22, Review::count());
        $this->assertLessThanOrEqual(44, Review::count());
        $this->assertTrue(Book::all()->every(fn ($book) => User::whereKey($book->user_id)->exists()));
        $this->assertDatabaseHas('books', ['title' => '吾輩は猫である', 'author' => '夏目漱石', 'isbn' => '9784101010014']);
        $this->assertSame('1905-01-01', Book::where('isbn', '9784101010014')->firstOrFail()->published_date->format('Y-m-d'));
        $this->assertDatabaseHas('books', ['title' => 'コンテナ物語', 'isbn' => '9784822251468']);
        $this->assertEqualsCanonicalizing([
            '9784101010014', '9784422100524', '9784873115658', '9784863940246', '9784101010021',
            '9784309226712', '9784048930598', '9784478025819', '9784163902302', '9784822289607', '9784822251468',
        ], Book::pluck('isbn')->all());
        $this->assertSame(['ビジネス', '自己啓発'], Book::where('isbn', '9784422100524')->firstOrFail()->genres->pluck('name')->all());
        foreach (Book::withCount('reviews')->orderBy('id')->get() as $index => $book) {
            $this->assertGreaterThanOrEqual(2, $book->reviews_count);
            $this->assertLessThanOrEqual(4, $book->reviews_count);
            $this->assertNotEmpty($book->description);
            $this->assertSame('https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1), $book->image_url);
        }
        $this->assertTrue(Review::all()->every(fn ($review) => $review->rating >= 1 && $review->rating <= 5
            && $review->comment === ReviewSeeder::COMMENTS[$review->rating]));
        foreach (User::withCount('favoriteBooks')->get() as $user) {
            $this->assertGreaterThanOrEqual(3, $user->favorite_books_count);
            $this->assertLessThanOrEqual(5, $user->favorite_books_count);
        }
        $this->assertTrue(Review::withCount('likedByUsers')->get()->every(fn ($review) => $review->liked_by_users_count <= 3));
        $this->assertTrue(ReviewLike::with('review')->get()->every(fn ($like) => $like->user_id !== $like->review->user_id));

        $favoriteCount = DB::table('favorites')->count();
        $likeCount = ReviewLike::count();
        $this->seed([BookSeeder::class, FavoriteSeeder::class, ReviewLikeSeeder::class]);
        $this->assertDatabaseCount('books', 11);
        $this->assertDatabaseCount('favorites', $favoriteCount);
        $this->assertDatabaseCount('review_likes', $likeCount);
    }

    public function test_review_create_requirement_does_not_overwrite_sample_data_on_full_rerun(): void
    {
        $this->seed(DatabaseSeeder::class);
        $review = Review::firstOrFail();
        $reviewCount = Review::count();
        $review->update(['comment' => 'Keep this existing comment']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('ReviewSeeder uses create and must not overwrite existing sample reviews.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('already exist', $exception->getMessage());
        }
        $this->assertDatabaseCount('reviews', $reviewCount);
        $this->assertSame('Keep this existing comment', $review->fresh()->comment);
    }

    public function test_book_create_core_uses_the_actor_and_rolls_back_failed_genre_binding(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $attributes = ['title' => 'New book', 'author' => 'Test author', 'isbn' => '9780000000101',
            'published_date' => '2026-01-01', 'user_id' => $other->id];
        $book = app(BookWriter::class)->create($author, $attributes, [$genre->id]);
        $this->assertTrue($book->user->is($author));
        $this->assertSame([$genre->id], $book->genres->modelKeys());
        try {
            $attributes['isbn'] = '9780000000102';
            app(BookWriter::class)->create($author, $attributes, [99999]);
            $this->fail('Unknown genre should reject the write.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('books', 1);
            $this->assertDatabaseMissing('books', ['isbn' => '9780000000102']);
        }
    }
}
