<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $users = SampleData::users();
        $books = SampleData::books();
        foreach ($users as $index => $user) {
            $ids = [];
            for ($offset = 0; $offset < 4; $offset++) {
                $ids[] = $books[($index + $offset) % 11]->id;
            }
            $user->favoriteBooks()->syncWithoutDetaching($ids);
        }
    }
}
