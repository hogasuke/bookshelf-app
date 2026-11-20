<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 読書計画の確認用データ（シート9 D13）。日付はすべて Carbon::today() からの相対で置くので、
 * 採点日が変わっても同じ場面になる。日次バッチ（reading-plans:process）をその日に1回走らせると：
 *
 * 山田太郎（yamada@example.com）
 *   A 読書中・期日+10日 …… 何も起きない（発火しない例）
 *   B 読書中・期日+3日 …… 「あと3日」の通知が出る
 *   C 読書中・期日=今日 …… 「今日が期日」の通知が出る
 *   D 読書中・期日-1日 …… 期限切れに切り替わる（自動失効）
 *   E 期限切れ・期日-3日 …… 「3日が過ぎた」の通知が出る
 *   F 期限切れ・期日-5日 …… 何も起きない。期日を今日以降へ延ばすと読書中に戻る（期限変更）
 *   G 読了 ……………… 編集・読了ボタンが出ない。バッチの対象外
 *   H 読書中・期日+2日 …… 昨日出た「あと3日」の通知（未読）が通知一覧にある。今日は何も出ない
 *   G の当日に出た通知（既読）も通知一覧にある
 * 鈴木花子（suzuki@example.com）…… 他人の計画（山田さんからは見えない・編集/削除/読了は403）
 *   I 読書中・期日+3日 …… 通知は鈴木さんにだけ出る
 *   J 期限切れ・期日-2日
 */
class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();
        $books = Book::orderBy('id')->get();

        $make = function (User $user, int $bookIndex, int $days, ReadingPlanStatus $status, ?Carbon $completedAt = null) use ($books, $today): ReadingPlan {
            $readingPlan = new ReadingPlan([
                'book_id' => $books[$bookIndex]->id,
                'target_date' => $today->copy()->addDays($days),
                'status' => $status,
                'completed_at' => $completedAt,
            ]);
            $readingPlan->user()->associate($user)->save();

            return $readingPlan;
        };

        $make($yamada, 0, 10, ReadingPlanStatus::InProgress);
        $make($yamada, 1, 3, ReadingPlanStatus::InProgress);
        $make($yamada, 2, 0, ReadingPlanStatus::InProgress);
        $make($yamada, 3, -1, ReadingPlanStatus::InProgress);
        $make($yamada, 4, -3, ReadingPlanStatus::Expired);
        $make($yamada, 5, -5, ReadingPlanStatus::Expired);
        $completed = $make($yamada, 6, -2, ReadingPlanStatus::Completed, $today->copy()->subDays(2)->setTime(21, 0));
        $soon = $make($yamada, 7, 2, ReadingPlanStatus::InProgress);

        $make($suzuki, 1, 3, ReadingPlanStatus::InProgress);
        $make($suzuki, 8, -2, ReadingPlanStatus::Expired);

        // 通知一覧を最初から確かめられるよう、過去の日付に出た通知を2件置く（今日のバッチとは重ならない）
        $this->pastNotification($yamada, $soon, ReminderTiming::ThreeDaysBefore, $today->copy()->subDay()->setTime(9, 0), read: false);
        $this->pastNotification($yamada, $completed, ReminderTiming::OnDueDate, $today->copy()->subDays(2)->setTime(9, 0), read: true);
    }

    private function pastNotification(User $user, ReadingPlan $plan, ReminderTiming $timing, Carbon $at, bool $read): void
    {
        $notification = new ReadingPlanReminder($plan->load('book'), $timing);
        $notification->id = (string) Str::uuid();
        $user->notify($notification);
        $user->notifications()->findOrFail($notification->id)->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
            'read_at' => $read ? $at->copy()->addHours(3) : null,
        ])->save();
    }
}
