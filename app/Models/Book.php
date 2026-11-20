<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * 書籍に付いたジャンル（中間表 book_genre）。
     *
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    /**
     * 書籍を登録した人。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍へのレビュー。
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 書籍を対象にした読書計画。
     *
     * @return HasMany<ReadingPlan, $this>
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }

    /**
     * 書籍をお気に入りにした人（中間表 favorites）。
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoriteByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * 一覧で選べる並び順。latest が既定（画面の「新しい順」は newest で送られるので同じ扱い）。
     */
    public const SORTS = ['latest', 'newest', 'oldest', 'title', 'rating'];

    /**
     * タイトルか著者にキーワードを部分一致で含む書籍に絞る。
     *
     * @param  Builder<static>  $query  クエリ
     * @param  ?string  $keyword  キーワード（空なら絞らない）
     * @return Builder<static> 絞り込んだクエリ
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        return $query->when($keyword !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', "%{$keyword}%")->orWhere('author', 'like', "%{$keyword}%")));
    }

    /**
     * 指定したジャンルに紐づく書籍に絞る。空なら絞らない。
     *
     * @param  Builder<static>  $query  クエリ
     * @param  int|string|null  $genreId  ジャンルID（空なら絞らない）
     * @return Builder<static> 絞り込んだクエリ
     */
    public function scopeInGenre(Builder $query, int|string|null $genreId): Builder
    {
        return $query->when(filled($genreId), fn (Builder $query) => $query
            ->whereHas('genres', fn (Builder $genres) => $genres->whereKey($genreId)));
    }

    /**
     * 並び順を当てる。rating は reviews_avg_rating（withAvg）が要り、レビューの無い書籍は最後になる。
     * 知らない値は latest として扱う。
     *
     * @param  Builder<static>  $query  クエリ
     * @param  ?string  $sort  並び順（latest・newest・oldest・title・rating）
     * @return Builder<static> 絞り込んだクエリ
     */
    public function scopeSortBy(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest()->orderBy('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            'rating' => $query->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating')->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }
}
