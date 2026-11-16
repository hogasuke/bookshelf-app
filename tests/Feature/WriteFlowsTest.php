<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WriteFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    private function bookInput(array $overrides = []): array
    {
        return array_merge([
            'title' => '動作確認の本', 'author' => '確認太郎', 'isbn' => '9784000000001',
            'published_date' => '2020-01-01', 'description' => '説明', 'image_url' => 'https://example.com/a.png',
            'genres' => [Genre::factory()->create()->id],
        ], $overrides);
    }

    public function test_user_registers_a_book_with_genres(): void
    {
        $user = User::factory()->create();
        $input = $this->bookInput();

        $response = $this->actingAs($user)->post(route('books.store'), $input);

        $book = Book::firstWhere('isbn', '9784000000001');
        $response->assertRedirect(route('books.show', $book))->assertSessionHas('success', '書籍を登録しました。');
        $this->assertSame($user->id, $book->user_id);
        $this->assertSame($input['genres'], $book->genres->modelKeys());
    }

    public function test_book_validation_errors_are_japanese_and_isbn_must_be_unique(): void
    {
        $user = User::factory()->create();
        Book::factory()->create(['isbn' => '9784000000001']);

        $this->actingAs($user)->from(route('books.create'))
            ->post(route('books.store'), $this->bookInput(['title' => '', 'genres' => [], 'image_url' => 'abc']))
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors([
                'title' => 'タイトルを入力してください。',
                'isbn' => 'このISBNの書籍は既に登録されています。',
                'genres' => 'ジャンルを1つ以上選択してください。',
                'image_url' => '画像URLはURLの形式で入力してください。',
            ]);
        $this->assertDatabaseCount('books', 1);
    }

    public function test_guest_is_sent_to_login_for_every_write(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->create();
        $genre = Genre::factory()->create();

        $this->post(route('books.store'), [])->assertRedirect(route('login'));
        $this->put(route('books.update', $book), [])->assertRedirect(route('login'));
        $this->post(route('books.favorites.toggle', $book))->assertRedirect(route('login'));
        $this->post(route('books.reviews.store', $book), [])->assertRedirect(route('login'));
        $this->post(route('reviews.like.toggle', $review))->assertRedirect(route('login'));
        $this->put(route('reviews.update', $review), [])->assertRedirect(route('login'));
        $this->delete(route('reviews.destroy', $review))->assertRedirect(route('login'));
        $this->get(route('genres.create'))->assertRedirect(route('login'));
        $this->get(route('genres.edit', $genre))->assertRedirect(route('login'));
        $this->post(route('genres.store'), [])->assertRedirect(route('login'));
    }

    public function test_owner_updates_book_and_its_genres_keeping_own_isbn(): void
    {
        $book = Book::factory()->create(['isbn' => '9784000000002']);
        $newGenre = Genre::factory()->create();

        $this->actingAs($book->user)->put(route('books.update', $book), $this->bookInput([
            'title' => '更新後', 'isbn' => '9784000000002', 'genres' => [$newGenre->id],
        ]))->assertRedirect(route('books.show', $book))->assertSessionHas('success', '書籍を更新しました。');

        $this->assertSame('更新後', $book->fresh()->title);
        $this->assertSame([$newGenre->id], $book->fresh()->genres->modelKeys());
    }

    public function test_other_user_cannot_update_a_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('books.update', $book), $this->bookInput(['title' => '乗っ取り']))
            ->assertForbidden();
        $this->assertNotSame('乗っ取り', $book->fresh()->title);
    }

    public function test_favorite_toggles_add_remove_add(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post(route('books.favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book))->assertSessionHas('success', 'お気に入りに追加しました。');
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        $this->post(route('books.favorites.toggle', $book))->assertSessionHas('success', 'お気に入りを解除しました。');
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);

        $this->post(route('books.favorites.toggle', $book));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    public function test_review_is_posted_validated_and_only_once_per_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('books.reviews.store', $book), ['rating' => 6])
            ->assertSessionHasErrors(['rating' => '評価は1〜5の中から選択してください。']);

        $this->post(route('books.reviews.store', $book), ['rating' => 5, 'comment' => 'よかった'])
            ->assertRedirect(route('books.show', $book))->assertSessionHas('success', 'レビューを投稿しました。');
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'book_id' => $book->id, 'rating' => 5]);

        $this->post(route('books.reviews.store', $book), ['rating' => 4])
            ->assertSessionHasErrors(['rating' => 'この書籍には既にレビューを投稿しています。']);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_author_updates_review_and_others_get_403(): void
    {
        $review = Review::factory()->create(['rating' => 4, 'comment' => '元']);

        $this->actingAs(User::factory()->create())
            ->put(route('reviews.update', $review), ['rating' => 1])->assertForbidden();

        $this->actingAs($review->user)
            ->put(route('reviews.update', $review), ['rating' => 5, 'comment' => '変更'])
            ->assertRedirect(route('books.show', $review->book_id))->assertSessionHas('success', 'レビューを更新しました。');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5, 'comment' => '変更']);
    }

    public function test_author_deletes_review_with_its_likes_and_others_get_403(): void
    {
        $review = Review::factory()->create();
        $liker = User::factory()->create();
        $review->likedByUsers()->attach($liker);

        $this->actingAs($liker)->delete(route('reviews.destroy', $review))->assertForbidden();

        $this->actingAs($review->user)->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $review->book_id))->assertSessionHas('success', 'レビューを削除しました。');
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('review_likes', ['review_id' => $review->id]);
    }

    public function test_like_toggles_add_remove(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)->post(route('reviews.like.toggle', $review))
            ->assertRedirect(route('books.show', $review->book_id))->assertSessionHas('success', 'いいねしました。');
        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);

        $this->post(route('reviews.like.toggle', $review))->assertSessionHas('success', 'いいねを取り消しました。');
        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }

    public function test_genre_create_edit_update_and_unique_name(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '既存']);

        $this->actingAs($user)->get(route('genres.create'))->assertOk()->assertSee('name="name"', false);
        $this->get(route('genres.edit', $genre))->assertOk()->assertSee('value="既存"', false);
        $this->post(route('genres.store'), ['name' => '新ジャンル'])
            ->assertRedirect(route('genres.index'))->assertSessionHas('success', 'ジャンルを登録しました。');
        $this->assertDatabaseHas('genres', ['name' => '新ジャンル']);
        $this->post(route('genres.store'), ['name' => '既存'])
            ->assertSessionHasErrors(['name' => 'このジャンル名は既に登録されています。']);
        $this->post(route('genres.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名を入力してください。']);

        $this->put(route('genres.update', $genre), ['name' => '既存'])->assertSessionDoesntHaveErrors();
        $this->put(route('genres.update', $genre), ['name' => '改名'])
            ->assertRedirect(route('genres.index'))->assertSessionHas('success', 'ジャンルを更新しました。');
        $this->assertSame('改名', $genre->fresh()->name);
    }

    public function test_genre_delete_is_refused_while_books_are_linked(): void
    {
        $user = User::factory()->create();
        $linked = Genre::factory()->create();
        Book::factory()->create()->genres()->attach($linked);
        $free = Genre::factory()->create();

        $this->actingAs($user)->delete(route('genres.destroy', $linked))
            ->assertRedirect(route('genres.index'))->assertSessionHas('error', '書籍が紐付いているジャンルは削除できません。');
        $this->assertDatabaseHas('genres', ['id' => $linked->id]);

        $this->delete(route('genres.destroy', $free))->assertSessionHas('success', 'ジャンルを削除しました。');
        $this->assertDatabaseMissing('genres', ['id' => $free->id]);
    }

    public function test_book_list_is_newest_first_even_with_the_same_timestamp(): void
    {
        $this->travelTo(now());
        $first = Book::factory()->create(['title' => '先に登録']);
        $second = Book::factory()->create(['title' => '後に登録']);

        $this->get('/')->assertSeeInOrder([$second->title, $first->title]);
    }

    public function test_unknown_url_404_keeps_the_logged_in_header(): void
    {
        $user = User::factory()->create(['name' => 'ログイン中の人']);

        $this->actingAs($user)->get('/no-such-page')->assertNotFound()->assertSee('ログイン中の人');
    }

    public function test_toggling_a_favorite_from_the_favorites_list_returns_to_the_list(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->favoriteBooks()->attach($book);

        $this->actingAs($user)->from(route('favorites.index'))
            ->post(route('books.favorites.toggle', $book))
            ->assertRedirect(route('favorites.index'));
        $this->assertFalse($user->favoriteBooks()->whereKey($book->id)->exists());

        $this->actingAs($user)->from(route('books.show', $book))
            ->post(route('books.favorites.toggle', $book))
            ->assertRedirect(route('books.show', $book));
    }
}
