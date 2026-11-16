<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookOwnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_owner_opens_own_book_detail_and_sees_delete_button(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->user)->get(route('books.show', $book))
            ->assertOk()
            ->assertSee(route('books.destroy', $book), false);
    }

    public function test_owner_deletes_own_book_and_returns_to_list(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->user)->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_other_user_and_guest_cannot_delete_a_book(): void
    {
        $book = Book::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('books.destroy', $book))->assertForbidden();
        auth()->logout();
        $this->delete(route('books.destroy', $book))->assertRedirect(route('login'));

        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_deleting_the_owner_also_deletes_their_books(): void
    {
        $book = Book::factory()->create();
        $other = Book::factory()->create();

        $book->user->delete();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseHas('books', ['id' => $other->id]);
    }
}
