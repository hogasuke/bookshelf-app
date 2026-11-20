<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\SocialToggles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * レビューのいいねを付け外しし、書籍詳細へ戻す。
     *
     * @param  Request  $request  リクエスト
     * @param  Review  $review  対象のレビュー
     * @param  SocialToggles  $toggles  お気に入り・いいねの付け外しを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function toggle(Request $request, Review $review, SocialToggles $toggles): RedirectResponse
    {
        $liked = $toggles->like($request->user(), $review);

        return redirect()->route('books.show', $review->book_id)
            ->with('success', $liked ? 'いいねしました。' : 'いいねを取り消しました。');
    }
}
