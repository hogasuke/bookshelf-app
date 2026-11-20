<?php

namespace App\Enums;

/**
 * 読書計画の状態（シート3 E36：限定された値の集合は Enum + casts で持つ）。
 *
 * 読書中 → 読了（本人が「読了する」）
 * 読書中 → 期限切れ（日次バッチが期日の翌日以降に切り替える）
 * 期限切れ → 読書中（本人が期日を今日以降へ延ばす）／読了
 */
enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => '読書中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * 一覧の状態バッジの色（Tailwind の静的解析のため class 全体をリテラルで返す）。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
