<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * リマインダー通知を出す時点。値は通知一覧の Blade（data['timing']）と揃える。
 */
enum ReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 今日この時点に当たる計画の期日。
     */
    public function targetDateFor(CarbonImmutable $today): CarbonImmutable
    {
        return match ($this) {
            self::ThreeDaysBefore => $today->addDays(3),
            self::OnDueDate => $today,
            self::ThreeDaysAfter => $today->subDays(3),
        };
    }

    /**
     * この時点で通知する計画の状態。期日を過ぎた計画は先に期限切れへ切り替わっている。
     */
    public function planStatus(): ReadingPlanStatus
    {
        return $this === self::ThreeDaysAfter ? ReadingPlanStatus::Expired : ReadingPlanStatus::InProgress;
    }

    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書計画の期日まであと3日です',
            self::OnDueDate => '今日は読書計画の期日です',
            self::ThreeDaysAfter => '読書計画の期日から3日が過ぎました',
        };
    }

    public function body(string $bookTitle, string $targetDate): string
    {
        return match ($this) {
            self::ThreeDaysBefore => "「{$bookTitle}」の期日は{$targetDate}です。",
            self::OnDueDate => "「{$bookTitle}」の期日は今日（{$targetDate}）です。読み終えたら「読了する」を押してください。",
            self::ThreeDaysAfter => "「{$bookTitle}」は期日（{$targetDate}）を過ぎて期限切れになっています。期日を延ばすか、読了にしてください。",
        };
    }
}
