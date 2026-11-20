<?php

namespace App\Notifications;

use App\Enums\ReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

/**
 * 読書計画のリマインダー（DR10）。Laravel 標準の DatabaseChannel で notifications に保存する。
 */
class ReadingPlanReminder extends Notification
{
    public function __construct(
        public readonly ReadingPlan $plan,
        public readonly ReminderTiming $timing,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $targetDate = $this->plan->target_date->format('Y-m-d');
        $bookTitle = $this->plan->book->title;

        return [
            'reading_plan_id' => $this->plan->id,
            'book_id' => $this->plan->book_id,
            'book_title' => $bookTitle,
            'timing' => $this->timing->value,
            'target_date' => $targetDate,
            'title' => $this->timing->title(),
            'body' => $this->timing->body($bookTitle, $targetDate),
        ];
    }
}
