<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * マイ読書レポート（シート7 D48）の統計を、ログインした人のレビューから組み立てる。
 * 集計は foreach を使わず Collection メソッドで書く（シート3 E30）。
 */
class ReadingReport
{
    /**
     * @return array{
     *     summary: array{total_reviews: int, books_read: int, average_rating: float},
     *     rating_distribution: Collection<int, int>,
     *     top_rated_books: Collection<int, array{id: int, title: string, author: string, rating: int}>,
     *     genre_ratings: Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     * }
     */
    public function for(User $user): array
    {
        /** @var Collection<int, Review> $reviews */
        $reviews = $user->reviews()->with('book.genres')->latest()->get();

        return [
            'summary' => $this->summary($reviews),
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];
    }

    /**
     * 総レビュー数・読了冊数（ユニーク書籍数）・平均評価点。レビューが無ければ平均は0。
     *
     * @param  Collection<int, Review>  $reviews
     * @return array{total_reviews: int, books_read: int, average_rating: float}
     */
    private function summary(Collection $reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => $reviews->pluck('book_id')->unique()->count(),
            'average_rating' => round((float) ($reviews->avg('rating') ?? 0), 1),
        ];
    }

    /**
     * 1〜5星ごとの件数。添字0が★1、添字4が★5（Bladeが添字+1を星の数にする）。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, int>
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $counts = $reviews->countBy('rating');

        return collect(range(1, 5))->map(fn (int $rating): int => $counts->get($rating, 0));
    }

    /**
     * 4星以上の書籍を、評価の高い順（同じ評価は新しいレビュー順）に最大5件。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, title: string, author: string, rating: int}>
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review): bool => $review->rating >= 4)
            ->sortByDesc('rating') // 並べ替えは安定なので、同じ評価は latest() の順が残る
            ->take(5)
            ->map(fn (Review $review): array => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => (int) $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンルごとの平均評価と件数を、平均の高い順（同じなら件数の多い順・名前順）に最大5件。
     * 1冊が複数ジャンルなら、そのレビューは各ジャンルに1件ずつ数える。
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, array{id: int, name: string, count: int, average_rating: float}>
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review): Collection => $review->book->genres
                ->map(fn (Genre $genre): array => ['genre' => $genre, 'rating' => (int) $review->rating]))
            ->groupBy(fn (array $row): int => $row['genre']->id)
            ->map(fn (Collection $rows): array => [
                'id' => $rows->first()['genre']->id,
                'name' => $rows->first()['genre']->name,
                'count' => $rows->count(),
                'average_rating' => round((float) $rows->avg('rating'), 1),
            ])
            ->sortBy([
                ['average_rating', 'desc'],
                ['count', 'desc'],
                ['name', 'asc'],
            ])
            ->take(5)
            ->values();
    }
}
