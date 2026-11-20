<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 読書計画の日次バッチ（シート7 D68）。Kernel の Schedule から毎日定刻に呼ぶ。
 * 1. 期日を過ぎた読書中の計画を期限切れにする
 * 2. 期日の3日前・当日・3日後の計画にリマインダー通知を出す（同じ計画・時点・期日には1回だけ）
 */
class ProcessReadingPlans extends Command
{
    protected $signature = 'reading-plans:process {--date= : 今日として扱う日付（Y-m-d・確認用）}';

    protected $description = '期日を過ぎた読書計画を期限切れにし、リマインダー通知を出す';

    public function handle(): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $expired = $this->expireOverduePlans($today);
        $sent = 0;
        foreach (ReminderTiming::cases() as $timing) {
            $sent += $this->sendReminders($timing, $today);
        }

        $this->info("{$today->format('Y-m-d')}: 期限切れにした計画 {$expired} 件・出した通知 {$sent} 件");

        return self::SUCCESS;
    }

    private function expireOverduePlans(CarbonImmutable $today): int
    {
        return DB::transaction(fn () => ReadingPlan::query()
            ->where('status', ReadingPlanStatus::InProgress->value)
            ->whereDate('target_date', '<', $today->format('Y-m-d'))
            ->update(['status' => ReadingPlanStatus::Expired->value, 'updated_at' => now()]));
    }

    private function sendReminders(ReminderTiming $timing, CarbonImmutable $today): int
    {
        $plans = ReadingPlan::query()
            ->with(['user', 'book'])
            ->where('status', $timing->planStatus()->value)
            ->whereDate('target_date', $timing->targetDateFor($today)->format('Y-m-d'))
            ->get();

        $sent = 0;
        foreach ($plans as $plan) {
            if ($this->alreadyNotified($plan, $timing)) {
                continue;
            }
            $plan->user->notify(new ReadingPlanReminder($plan, $timing));
            $sent++;
        }

        return $sent;
    }

    /**
     * 期日を変えた計画には、新しい期日について改めて出す。
     */
    private function alreadyNotified(ReadingPlan $plan, ReminderTiming $timing): bool
    {
        return $plan->user->notifications()
            ->where('type', ReadingPlanReminder::class)
            ->where('data->reading_plan_id', $plan->id)
            ->where('data->timing', $timing->value)
            ->where('data->target_date', $plan->target_date->format('Y-m-d'))
            ->exists();
    }
}
