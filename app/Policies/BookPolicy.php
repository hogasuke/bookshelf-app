<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function update(User $user, Book $book): bool
    {
        return $book->user_id !== null && (int) $user->id === (int) $book->user_id;
    }

    public function delete(User $user, Book $book): bool
    {
        return $this->update($user, $book);
    }
}
