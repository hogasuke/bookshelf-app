<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 日次バッチ（シート7 D68）：リマインダーバッチ・自動失効バッチのテスト観点（シート10 E30・E31）と通知一覧（D66・D67）。
 */
class ReadingPlanBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(9, 0));
    }

    private function plan(string $targetDate, ReadingPlanStatus $status = ReadingPlanStatus::InProgress, ?User $user = null): ReadingPlan
    {
        return ReadingPlan::factory()->create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'target_date' => $targetDate,
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed ? now() : null,
        ]);
    }

    /** @return list<string> */
    private function timingsFor(ReadingPlan $plan): array
    {
        return $plan->user->notifications()->get()
            ->filter(fn ($n) => $n->data['reading_plan_id'] === $plan->id)
            ->pluck('data.timing')->values()->all();
    }

    public function test_command_is_scheduled_daily_at_nine(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'reading-plans:process'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }

    public function test_overdue_in_progress_plans_expire_and_others_do_not(): void
    {
        $yesterday = $this->plan('2026-11-19');
        $today = $this->plan('2026-11-20');
        $completed = $this->plan('2026-11-10', ReadingPlanStatus::Completed);

        $this->artisan('reading-plans:process')->assertSuccessful()->expectsOutputToContain('期限切れにした計画 1 件');

        $this->assertSame(ReadingPlanStatus::Expired, $yesterday->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $today->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Completed, $completed->fresh()->status);
    }

    public function test_reminders_fire_three_days_before_on_the_day_and_three_days_after(): void
    {
        $before = $this->plan('2026-11-23');
        $onDay = $this->plan('2026-11-20');
        $after = $this->plan('2026-11-17', ReadingPlanStatus::Expired);
        $farFuture = $this->plan('2026-11-30');
        $twoDaysBefore = $this->plan('2026-11-22');
        $longExpired = $this->plan('2026-11-15', ReadingPlanStatus::Expired);
        $completedOnDay = $this->plan('2026-11-20', ReadingPlanStatus::Completed);
        $completedBefore = $this->plan('2026-11-23', ReadingPlanStatus::Completed);

        $this->artisan('reading-plans:process')->assertSuccessful()->expectsOutputToContain('出した通知 3 件');

        $this->assertSame(['three_days_before'], $this->timingsFor($before));
        $this->assertSame(['on_due_date'], $this->timingsFor($onDay));
        $this->assertSame(['three_days_after'], $this->timingsFor($after));
        foreach ([$farFuture, $twoDaysBefore, $longExpired, $completedOnDay, $completedBefore] as $silent) {
            $this->assertSame([], $this->timingsFor($silent));
        }
    }

    public function test_plan_that_expires_today_is_not_yet_three_days_after(): void
    {
        // 3日前に期日だったのに読書中のまま（バッチが止まっていた）でも、先に期限切れにしてから3日後の通知を出す
        $missed = $this->plan('2026-11-17');

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(ReadingPlanStatus::Expired, $missed->fresh()->status);
        $this->assertSame(['three_days_after'], $this->timingsFor($missed));
    }

    public function test_notification_content_is_saved_on_the_database_channel(): void
    {
        $plan = $this->plan('2026-11-23');

        $this->artisan('reading-plans:process')->assertSuccessful();

        $notification = $plan->user->notifications()->sole();
        $this->assertSame(ReadingPlanReminder::class, $notification->type);
        $this->assertNull($notification->read_at);
        $this->assertSame([
            'reading_plan_id' => $plan->id,
            'book_id' => $plan->book_id,
            'book_title' => $plan->book->title,
            'timing' => 'three_days_before',
            'target_date' => '2026-11-23',
            'title' => '読書計画の期日まであと3日です',
            'body' => "「{$plan->book->title}」の期日は2026-11-23です。",
        ], $notification->data);
    }

    public function test_running_twice_does_not_duplicate_but_a_new_date_notifies_again(): void
    {
        $plan = $this->plan('2026-11-23');

        $this->artisan('reading-plans:process')->assertSuccessful();
        $this->artisan('reading-plans:process')->assertSuccessful()->expectsOutputToContain('出した通知 0 件');
        $this->assertSame(['three_days_before'], $this->timingsFor($plan));

        // 期日を延ばして、3日後にまた「あと3日」になったら出し直す
        $plan->update(['target_date' => '2026-11-26']);
        $this->artisan('reading-plans:process', ['--date' => '2026-11-23'])->assertSuccessful();
        $this->assertSame(['three_days_before', 'three_days_before'], $this->timingsFor($plan));
    }

    public function test_reminders_go_only_to_the_plan_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->plan('2026-11-23', user: $owner);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_notification_page_lists_own_notifications_and_marks_them_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->plan('2026-11-23', user: $owner);
        $theirs = $this->plan('2026-11-20', user: $other);
        $this->artisan('reading-plans:process')->assertSuccessful();
        $id = $owner->notifications()->sole()->id;

        $this->get('/notifications')->assertRedirect('/login');
        $this->actingAs($owner)->get('/notifications')->assertOk()
            ->assertSee('読書計画の期日まであと3日です')->assertSee($mine->book->title)
            ->assertDontSee($theirs->book->title)->assertSee('既読にする')->assertSee('未読');

        $this->actingAs($other)->post("/notifications/{$id}/read")->assertNotFound();
        $this->assertNull($owner->notifications()->sole()->read_at);

        $this->actingAs($owner)->post("/notifications/{$id}/read")->assertRedirect('/notifications')->assertSessionHas('success');
        $this->assertNotNull($owner->notifications()->sole()->read_at);
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertDontSee('既読にする');
    }

    public function test_header_badge_counts_unread_notifications(): void
    {
        $owner = User::factory()->create();
        $this->plan('2026-11-23', user: $owner);
        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSeeInOrder(['bg-red-600 rounded-full', '1']);
    }

    public function test_seeded_scenarios_cover_every_behaviour(): void
    {
        $this->seed(DatabaseSeeder::class);
        $yamada = User::where('email', 'yamada@example.com')->firstOrFail();
        $suzuki = User::where('email', 'suzuki@example.com')->firstOrFail();

        $this->assertSame(8, $yamada->readingPlans()->count());
        $this->assertSame(2, $suzuki->readingPlans()->count());
        $this->assertEqualsCanonicalizing(
            ['in_progress', 'completed', 'expired'],
            ReadingPlan::pluck('status')->map->value->unique()->values()->all(),
        );
        // 通知一覧を最初から確かめられる：未読1件・既読1件
        $this->assertSame(2, $yamada->notifications()->count());
        $this->assertSame(1, $yamada->unreadNotifications()->count());

        $this->artisan('reading-plans:process')->assertSuccessful()
            ->expectsOutputToContain('期限切れにした計画 1 件・出した通知 4 件');

        $this->assertSame(5, $yamada->notifications()->count());
        $this->assertEqualsCanonicalizing(
            ['three_days_before', 'on_due_date', 'three_days_after'],
            $yamada->notifications()->where('created_at', '>=', today())->get()->pluck('data.timing')->all(),
        );
        $this->assertSame(['three_days_before'], $suzuki->notifications()->get()->pluck('data.timing')->all());
        $this->assertSame(4, $yamada->readingPlans()->where('status', 'in_progress')->count());
        $this->assertSame(3, $yamada->readingPlans()->where('status', 'expired')->count());
    }
}
