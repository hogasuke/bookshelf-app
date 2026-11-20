<?php

namespace App\Services;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingPlanWriter
{
    public function create(User $user, int $bookId, string $targetDate): ReadingPlan
    {
        return $user->readingPlans()->create([
            'book_id' => $bookId,
            'target_date' => $targetDate,
            'status' => ReadingPlanStatus::InProgress,
        ]);
    }

    /**
     * 期日を変える。期限切れの計画は、今日以降へ延ばすと読書中に戻る（期日は今日以降しか通さない）。
     */
    public function reschedule(ReadingPlan $plan, string $targetDate): ReadingPlan
    {
        $plan->target_date = Carbon::parse($targetDate);
        if ($plan->status === ReadingPlanStatus::Expired) {
            $plan->status = ReadingPlanStatus::InProgress;
        }
        $plan->save();

        return $plan;
    }

    public function complete(ReadingPlan $plan): ReadingPlan
    {
        $plan->forceFill(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()])->save();

        return $plan;
    }

    /**
     * 計画と、その計画について出したリマインダー通知を1つの単位で消す（シート3 E34）。
     */
    public function delete(ReadingPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan->user->notifications()
                ->where('type', ReadingPlanReminder::class)
                ->where('data->reading_plan_id', $plan->id)
                ->delete();
            $plan->delete();
        });
    }
}
