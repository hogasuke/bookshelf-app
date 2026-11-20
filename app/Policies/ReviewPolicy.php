<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新できるのは投稿者本人だけ。
     *
     * @param  User  $user  操作する人
     * @param  Review  $review  対象のレビュー
     * @return bool 許可するなら true
     */
    public function update(User $user, Review $review): bool
    {
        return $review->user_id !== null && (int) $user->id === (int) $review->user_id;
    }

    /**
     * レビューを削除できる人は、更新できる人と同じ。
     *
     * @param  User  $user  操作する人
     * @param  Review  $review  対象のレビュー
     * @return bool 許可するなら true
     */
    public function delete(User $user, Review $review): bool
    {
        return $this->update($user, $review);
    }
}
