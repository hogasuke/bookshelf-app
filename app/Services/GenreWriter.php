<?php

namespace App\Services;

use App\Models\Genre;

class GenreWriter
{
    // Validation, actor permissions and HTTP responses remain outside this DB core.
    public function create(string $name): Genre
    {
        return Genre::create(['name' => $name]);
    }

    public function update(Genre $genre, string $name): Genre
    {
        $genre->update(['name' => $name]);

        return $genre->fresh();
    }

    public function delete(Genre $genre): bool
    {
        return $genre->getConnection()->transaction(function () use ($genre) {
            if ($genre->books()->exists()) {
                return false;
            }

            return $genre->delete();
        });
    }
}
