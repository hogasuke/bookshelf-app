<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を更新できるのは登録者本人だけ（登録者の無い書籍は誰も不可）。
     *
     * @param  User  $user  操作する人
     * @param  Book  $book  対象の書籍
     * @return bool 許可するなら true
     */
    public function update(User $user, Book $book): bool
    {
        return $book->user_id !== null && (int) $user->id === (int) $book->user_id;
    }

    /**
     * 書籍を削除できる人は、更新できる人と同じ。
     *
     * @param  User  $user  操作する人
     * @param  Book  $book  対象の書籍
     * @return bool 許可するなら true
     */
    public function delete(User $user, Book $book): bool
    {
        return $this->update($user, $book);
    }
}
