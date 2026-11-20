<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    protected $attributes = [
        'status' => 'in_progress',
    ];

    /**
     * 状態は Enum に紐付ける（Laravel 10 では casts() メソッドでなく $casts で宣言する）。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'status' => ReadingPlanStatus::class,
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ReadingPlanStatus::Completed;
    }

    /**
     * 読了していない計画（読書中・期限切れ）。同じ書籍の計画を重ねない判定に使う。
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', ReadingPlanStatus::Completed->value);
    }
}
