<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\Review;
use App\Models\ReviewLike;
use App\Models\User;
use App\Services\BookWriter;
use App\Services\GenreWriter;
use App\Services\ReviewWriter;
use App\Services\SocialToggles;
use Database\Seeders\GenreSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogCoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->withoutVite();
    }

    public function test_book_create_form_requires_login_and_provides_all_genres(): void
    {
        $first = Genre::create(['name' => 'First genre']);
        $second = Genre::create(['name' => 'Second genre']);
        $this->get('/books/create')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/books/create')->assertOk()
            ->assertSee('First genre')->assertSee('Second genre')
            ->assertViewHas('genres', fn ($genres) => $genres->modelKeys() === [$first->id, $second->id]);
    }

    public function test_genres_require_authentication_and_show_counts_and_only_their_own_ten_books(): void
    {
        $genre = Genre::create(['name' => 'Target genre']);
        $otherGenre = Genre::create(['name' => 'Other genre']);
        for ($number = 1; $number <= 11; $number++) {
            $genre->books()->attach($this->book($number));
        }
        $otherGenre->books()->attach($this->book(12));
        $this->get('/genres')->assertRedirect('/login');
        $this->get('/genres/'.$genre->id)->assertRedirect('/login');

        $this->actingAs(User::factory()->create());
        $this->get('/genres')->assertOk()->assertViewHas('genres', fn ($genres) => $genres->find($genre->id)->books_count === 11 && $genres->find($otherGenre->id)->books_count === 1);
        $this->get('/genres/'.$genre->id)->assertOk()->assertDontSee('Book 12')
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 11
                && $books->every(fn ($book) => $book->relationLoaded('genres')));
        $this->get('/genres/'.$genre->id.'?page=2')->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_favorites_are_private_and_paginated_without_other_users_books(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($number = 1; $number <= 11; $number++) {
            $user->favoriteBooks()->attach($this->book($number));
        }
        $other->favoriteBooks()->attach($this->book(12));
        $this->get('/favorites')->assertRedirect('/login');
        $this->actingAs($user)->get('/favorites')->assertOk()->assertDontSee('Book 12')
            ->assertViewHas('books', fn ($books) => $books->count() === 10 && $books->total() === 11);
        $this->get('/favorites?page=2')->assertOk()
            ->assertViewHas('books', fn ($books) => $books->count() === 1);
    }

    public function test_ranking_is_public_and_limits_to_ten_reviewed_books_by_average(): void
    {
        $unreviewed = $this->book(99);
        $reviewer = User::factory()->create();
        for ($number = 1; $number <= 11; $number++) {
            Review::create(['book_id' => $this->book($number)->id, 'user_id' => $reviewer->id, 'rating' => ($number % 5) + 1]);
        }
        $this->get('/ranking')->assertOk()->assertViewHas('rankedBooks', function ($books) use ($unreviewed) {
            return $books->count() === 10 && ! $books->contains('id', $unreviewed->id)
                && $books->pluck('reviews_avg_rating')->values()->all()
                    === $books->sortByDesc('reviews_avg_rating')->pluck('reviews_avg_rating')->values()->all();
        });
    }

    public function test_registered_book_policy_allows_owner_and_rejects_other_and_unassigned_books(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book = new Book;
        $book->forceFill(['user_id' => $owner->id]);
        foreach (['update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $book));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $book));
            $this->assertFalse(Gate::forUser($owner)->allows($ability, new Book));
        }
    }

    public function test_book_update_replaces_genres_without_changing_another_book_or_unrelated_attributes(): void
    {
        $book = $this->book(1);
        $other = $this->book(2);
        $old = Genre::create(['name' => 'Old']);
        $new = Genre::create(['name' => 'New']);
        $book->genres()->attach($old);
        $other->genres()->attach($old);

        app(BookWriter::class)->update($book, ['title' => 'Updated', 'id' => 999], [$new->id]);

        $this->assertSame('Updated', $book->fresh()->title);
        $this->assertSame([$new->id], $book->fresh()->genres->modelKeys());
        $this->assertSame('Book 02', $other->fresh()->title);
        $this->assertSame([$old->id], $other->fresh()->genres->modelKeys());
        $this->assertDatabaseMissing('books', ['id' => 999]);
    }

    public function test_failed_genre_sync_rolls_back_the_book_and_previous_genres(): void
    {
        $book = $this->book(1);
        $genre = Genre::create(['name' => 'Keep']);
        $book->genres()->attach($genre);
        try {
            app(BookWriter::class)->update($book, ['title' => 'Must roll back'], [99999]);
            $this->fail('Invalid foreign key should fail.');
        } catch (QueryException $exception) {
            $this->assertSame('Book 01', $book->fresh()->title);
            $this->assertSame([$genre->id], $book->fresh()->genres->modelKeys());
        }
    }

    public function test_book_delete_cascades_dependents_but_preserves_other_books_users_and_genres(): void
    {
        $book = $this->book(1);
        $other = $this->book(2);
        $user = User::factory()->create();
        $genre = Genre::create(['name' => 'Keep genre']);
        $book->genres()->attach($genre);
        $review = Review::create(['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 5]);
        $like = ReviewLike::create(['user_id' => $user->id, 'review_id' => $review->id]);
        $favorite = Favorite::create(['user_id' => $user->id, 'book_id' => $book->id]);
        app(BookWriter::class)->delete($book);
        foreach (['books' => $book, 'reviews' => $review, 'review_likes' => $like, 'favorites' => $favorite] as $table => $model) {
            $this->assertDatabaseMissing($table, ['id' => $model->id]);
        }
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseHas('books', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }

    private function book(int $number): Book
    {
        return Book::factory()->create(['title' => sprintf('Book %02d', $number), 'author' => 'Test author',
            'isbn' => sprintf('978000000%04d', $number), 'published_date' => '2026-01-01']);
    }

    public function test_factories_create_relations_and_independent_records_in_the_isolated_database(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->attach($genre);
        $review = Review::factory()->create(['book_id' => $book->id]);
        $favorite = Favorite::factory()->create(['book_id' => $book->id]);
        $like = ReviewLike::factory()->create(['review_id' => $review->id]);
        $this->get('/reviews/'.$review->id.'/edit')->assertRedirect('/login');
        $this->actingAs($review->user)->get('/reviews/'.$review->id.'/edit')->assertOk()
            ->assertViewHas('review', fn ($loaded) => $loaded->id === $review->id && $loaded->relationLoaded('book'));

        $this->assertTrue($review->book->is($book));
        $this->assertTrue($favorite->book->is($book));
        $this->assertTrue($like->review->is($review));
        $this->assertSame([$genre->id], $book->fresh()->genres->modelKeys());
        $this->assertSame(13, strlen($book->isbn));
        $this->assertNotSame($book->isbn, Book::factory()->create()->isbn);
    }

    public function test_social_toggles_add_remove_and_readd_without_affecting_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $book = $this->book(1);
        $review = Review::factory()->create(['book_id' => $book->id]);
        $service = app(SocialToggles::class);
        $other->favoriteBooks()->attach($book);
        $other->likedReviews()->attach($review);

        foreach ([true, false, true] as $expected) {
            $this->assertSame($expected, $service->favorite($user, $book));
            $this->assertSame($expected, $service->like($user, $review));
            $this->assertSame($expected ? 1 : 0, $user->favoriteBooks()->count());
            $this->assertSame($expected ? 1 : 0, $user->likedReviews()->count());
            $this->assertSame(1, $other->favoriteBooks()->count());
            $this->assertSame(1, $other->likedReviews()->count());
        }
    }

    public function test_fixed_master_seeders_match_the_assignment_and_do_not_duplicate_on_rerun(): void
    {
        $this->seed([UserSeeder::class, GenreSeeder::class]);
        $hash = User::where('email', 'yamada@example.com')->firstOrFail()->password;
        $this->seed([UserSeeder::class, GenreSeeder::class]);
        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('genres', 10);
        $this->assertDatabaseHas('users', ['name' => '山田太郎', 'email' => 'yamada@example.com']);
        $this->assertTrue(Hash::check('password', $hash));
        $this->assertSame($hash, User::where('email', 'yamada@example.com')->firstOrFail()->password);
        $this->assertEqualsCanonicalizing(['小説', 'ビジネス', '技術書', '自己啓発', 'エッセイ', '歴史', '科学', '芸術', '料理', '旅行'], Genre::pluck('name')->all());
    }

    public function test_review_policy_and_write_core_preserve_author_book_and_unrelated_review(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $author->id]);
        $untouched = Review::factory()->create();
        $like = ReviewLike::factory()->create(['review_id' => $review->id]);
        foreach (['update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($author)->allows($ability, $review));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $review));
        }
        $updated = app(ReviewWriter::class)->update($review, ['rating' => 3, 'comment' => 'Updated', 'user_id' => $other->id, 'book_id' => $untouched->book_id]);
        $this->assertSame('Updated', $updated->comment);
        $this->assertSame($author->id, $updated->user_id);
        $this->assertSame($review->book_id, $updated->book_id);
        app(ReviewWriter::class)->delete($updated);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['id' => $like->id]);
        $this->assertDatabaseHas('reviews', ['id' => $untouched->id]);
    }

    public function test_genre_write_core_restricts_linked_deletion_and_preserves_the_book(): void
    {
        $writer = app(GenreWriter::class);
        $genre = $writer->create('Created genre');
        $other = $writer->create('Unrelated genre');
        $this->assertSame('Updated genre', $writer->update($genre, 'Updated genre')->name);
        $book = $this->book(1);
        $book->genres()->attach($genre);
        $this->assertFalse($writer->delete($genre));
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
        $this->assertTrue($writer->delete($other));
        $this->assertDatabaseMissing('genres', ['id' => $other->id]);
    }

    public function test_review_create_core_uses_the_given_actor_and_book_not_injected_ids(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $book = $this->book(1);
        $otherBook = $this->book(2);
        $review = app(ReviewWriter::class)->create($author, $book, [
            'rating' => 4, 'comment' => 'Read it', 'user_id' => $other->id, 'book_id' => $otherBook->id,
        ]);
        $this->assertTrue($review->user->is($author));
        $this->assertTrue($review->book->is($book));
        $this->assertSame(4, $review->rating);
        $this->assertDatabaseCount('reviews', 1);
    }
}
