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

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }

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
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        return $query->when($keyword !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', "%{$keyword}%")->orWhere('author', 'like', "%{$keyword}%")));
    }

    /**
     * 指定したジャンルに紐づく書籍に絞る。空なら絞らない。
     */
    public function scopeInGenre(Builder $query, int|string|null $genreId): Builder
    {
        return $query->when(filled($genreId), fn (Builder $query) => $query
            ->whereHas('genres', fn (Builder $genres) => $genres->whereKey($genreId)));
    }

    /**
     * 並び順を当てる。rating は reviews_avg_rating（withAvg）が要り、レビューの無い書籍は最後になる。
     * 知らない値は latest として扱う。
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
