<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests use a new in-memory database, never the local MySQL data.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_review_loads_its_author_and_target_book_without_mixing_other_records(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook('9780000000001');
        $otherBook = $this->createBook('9780000000002');
        $review = Review::create(['user_id' => $author->id, 'book_id' => $book->id, 'rating' => 4]);
        Review::create(['user_id' => $otherUser->id, 'book_id' => $otherBook->id, 'rating' => 2]);

        $loaded = Review::with(['user', 'book'])->findOrFail($review->id);

        $this->assertTrue($loaded->relationLoaded('user'));
        $this->assertTrue($loaded->relationLoaded('book'));
        $this->assertTrue($loaded->user->is($author));
        $this->assertTrue($loaded->book->is($book));
        $this->assertSame([$review->id], $book->reviews->modelKeys());
        $this->assertSame([$review->id], $author->reviews->modelKeys());
    }

    public function test_favorite_resolves_the_saved_book_and_user_in_both_directions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook('9780000000003');
        $otherBook = $this->createBook('9780000000004');
        $favorite = Favorite::create(['user_id' => $user->id, 'book_id' => $book->id]);
        Favorite::create(['user_id' => $otherUser->id, 'book_id' => $otherBook->id]);

        $loaded = Favorite::with(['user', 'book'])->findOrFail($favorite->id);

        $this->assertTrue($loaded->user->is($user));
        $this->assertTrue($loaded->book->is($book));
        $this->assertSame([$book->id], $user->favoriteBooks->modelKeys());
        $this->assertSame([$user->id], $book->favoriteByUsers->modelKeys());
    }

    private function createBook(string $isbn): Book
    {
        return Book::create([
            'title' => 'Relation test book',
            'author' => 'Test author',
            'isbn' => $isbn,
            'published_date' => '2026-01-01',
        ]);
    }
}
