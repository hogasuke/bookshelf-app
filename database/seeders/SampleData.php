<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Support\Collection;
use LogicException;

class SampleData
{
    public static function users(): Collection
    {
        $users = User::whereIn('email', ['yamada@example.com', 'suzuki@example.com', 'tanaka@example.com', 'sato@example.com', 'takahashi@example.com'])->orderBy('id')->get();
        if ($users->count() !== 5) {
            throw new LogicException('Run UserSeeder before dependent seeders.');
        }

        return $users;
    }

    public static function books(): Collection
    {
        $byIsbn = Book::whereIn('isbn', array_column(BookSeeder::RECORDS, 2))->get()->keyBy('isbn');
        if ($byIsbn->count() !== 11) {
            throw new LogicException('Run BookSeeder before dependent seeders.');
        }

        return new ModelCollection(collect(BookSeeder::RECORDS)->map(fn ($record) => $byIsbn[$record[2]])->all());
    }
}
