<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BookRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
    }

    public function test_seven_book_routes_exist_and_only_writes_require_auth(): void
    {
        $expected = [
            'books.index' => [['GET', 'HEAD'], 'books', false],
            'books.create' => [['GET', 'HEAD'], 'books/create', true],
            'books.store' => [['POST'], 'books', true],
            'books.show' => [['GET', 'HEAD'], 'books/{book}', false],
            'books.edit' => [['GET', 'HEAD'], 'books/{book}/edit', true],
            'books.update' => [['PUT', 'PATCH'], 'books/{book}', true],
            'books.destroy' => [['DELETE'], 'books/{book}', true],
        ];

        foreach ($expected as $name => [$methods, $uri, $needsAuth]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "{$name} が定義されていない");
            $this->assertSame($methods, $route->methods(), $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertSame($needsAuth, in_array('auth', $route->gatherMiddleware(), true), "{$name} の認証");
        }
    }

    public function test_guest_reads_books_but_is_sent_to_login_for_create_and_edit_screens(): void
    {
        $book = Book::factory()->create();

        $this->get(route('books.index'))->assertOk();
        $this->get(route('books.show', $book))->assertOk();
        $this->get(route('books.create'))->assertRedirect(route('login'));
        $this->get(route('books.edit', $book))->assertRedirect(route('login'));
    }
}
