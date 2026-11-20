<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Enums\ReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 読書計画の画面と操作（シート7 D56〜D65）・読書計画（期限変更）のテスト観点（シート10 E28・E29）。
 */
class ReadingPlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
    }

    private function plan(User $user, array $attributes = []): ReadingPlan
    {
        return ReadingPlan::factory()->create(['user_id' => $user->id] + $attributes);
    }

    public function test_guest_is_redirected_to_login_on_every_page(): void
    {
        $plan = ReadingPlan::factory()->create();

        $this->get('/reading-plans')->assertRedirect('/login');
        $this->get('/reading-plans/create')->assertRedirect('/login');
        $this->get("/reading-plans/{$plan->id}/edit")->assertRedirect('/login');
        $this->post('/reading-plans', [])->assertRedirect('/login');
        $this->post("/reading-plans/{$plan->id}/complete")->assertRedirect('/login');
        $this->delete("/reading-plans/{$plan->id}")->assertRedirect('/login');
    }

    public function test_index_lists_only_own_plans_in_due_order(): void
    {
        $user = User::factory()->create();
        $this->plan($user, ['book_id' => Book::factory()->create(['title' => '遅い期日の本'])->id, 'target_date' => '2026-12-20']);
        $this->plan($user, ['book_id' => Book::factory()->create(['title' => '近い期日の本'])->id, 'target_date' => '2026-11-25']);
        $this->plan(User::factory()->create(), ['book_id' => Book::factory()->create(['title' => '他人の本'])->id]);

        $this->actingAs($user)->get('/reading-plans')->assertOk()
            ->assertSeeInOrder(['近い期日の本', '遅い期日の本'])
            ->assertDontSee('他人の本')
            ->assertSee('新規計画作成');
    }

    public function test_status_filter_shows_only_that_status_and_unknown_status_shows_all(): void
    {
        $user = User::factory()->create();
        $this->plan($user, ['book_id' => Book::factory()->create(['title' => '読書中の本'])->id]);
        ReadingPlan::factory()->completed()->create(['user_id' => $user->id, 'book_id' => Book::factory()->create(['title' => '読了した本'])->id]);
        ReadingPlan::factory()->expired()->create(['user_id' => $user->id, 'book_id' => Book::factory()->create(['title' => '期限切れの本'])->id]);

        $this->actingAs($user)->get('/reading-plans?status=completed')->assertOk()
            ->assertSee('読了した本')->assertDontSee('読書中の本')->assertDontSee('期限切れの本');
        $this->actingAs($user)->get('/reading-plans?status=expired')->assertOk()
            ->assertSee('期限切れの本')->assertDontSee('読了した本');
        $this->actingAs($user)->get('/reading-plans?status=nothing')->assertOk()
            ->assertSee('読書中の本')->assertSee('読了した本')->assertSee('期限切れの本');
        $this->actingAs($user)->get('/reading-plans?status=completed')->assertViewHas('currentStatus', 'completed');
    }

    public function test_completed_plan_has_no_complete_or_edit_button(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/reading-plans')->assertOk()
            ->assertDontSee("/reading-plans/{$plan->id}/edit")
            ->assertDontSee("/reading-plans/{$plan->id}/complete")
            ->assertSee('削除');
    }

    public function test_empty_list_message(): void
    {
        $this->actingAs(User::factory()->create())->get('/reading-plans')->assertOk()->assertSee('該当する読書計画はありません。');
    }

    public function test_create_page_shows_book_select_and_date_input(): void
    {
        Book::factory()->create(['title' => '選べる本']);

        $this->actingAs(User::factory()->create())->get('/reading-plans/create')->assertOk()
            ->assertSee('name="book_id"', false)->assertSee('name="target_date"', false)->assertSee('選べる本');
    }

    public function test_store_creates_an_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-11-20'])
            ->assertRedirect('/reading-plans')->assertSessionHas('success');

        $plan = ReadingPlan::sole();
        $this->assertSame($user->id, $plan->user_id);
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertSame('2026-11-20', $plan->target_date->format('Y-m-d'));
    }

    public function test_store_validation_errors(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->actingAs($user)->post('/reading-plans', [])
            ->assertSessionHasErrors(['book_id' => '書籍を選択してください。', 'target_date' => '期日を入力してください。']);
        $this->actingAs($user)->post('/reading-plans', ['book_id' => 9999, 'target_date' => '2026-11-19'])
            ->assertSessionHasErrors(['book_id' => '選択した書籍が見つかりません。', 'target_date' => '期日は今日以降の日付を入力してください。']);
        $this->actingAs($user)->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026/12/01'])
            ->assertSessionHasErrors('target_date');
        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_same_book_cannot_have_two_open_plans_but_can_after_completion(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $open = ReadingPlan::factory()->expired()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->actingAs($user)->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertSessionHasErrors(['book_id' => 'この書籍には読了していない読書計画が既にあります。']);

        // 別の人なら同じ本でも立てられる
        $this->actingAs(User::factory()->create())->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertSessionHasNoErrors();

        $open->forceFill(['status' => ReadingPlanStatus::Completed, 'completed_at' => now()])->save();
        $this->actingAs($user)->post('/reading-plans', ['book_id' => $book->id, 'target_date' => '2026-12-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $user->readingPlans()->count());
    }

    public function test_edit_page_shows_current_date_for_owner_only(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, ['target_date' => '2026-12-05']);

        $this->actingAs($user)->get("/reading-plans/{$plan->id}/edit")->assertOk()
            ->assertSee('value="2026-12-05"', false)->assertSee($plan->book->title);
        $this->actingAs(User::factory()->create())->get("/reading-plans/{$plan->id}/edit")->assertForbidden();
    }

    public function test_update_changes_the_date_for_owner_only(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, ['target_date' => '2026-12-05']);

        $this->actingAs(User::factory()->create())->put("/reading-plans/{$plan->id}", ['target_date' => '2026-12-10'])->assertForbidden();
        $this->actingAs($user)->put("/reading-plans/{$plan->id}", ['target_date' => '2026-12-10'])
            ->assertRedirect('/reading-plans')->assertSessionHas('success');
        $this->assertSame('2026-12-10', $plan->fresh()->target_date->format('Y-m-d'));
    }

    public function test_update_validation_rejects_past_and_empty_dates(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, ['target_date' => '2026-12-05']);

        $this->actingAs($user)->put("/reading-plans/{$plan->id}", ['target_date' => ''])->assertSessionHasErrors('target_date');
        $this->actingAs($user)->put("/reading-plans/{$plan->id}", ['target_date' => '2026-11-19'])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を入力してください。']);
        $this->assertSame('2026-12-05', $plan->fresh()->target_date->format('Y-m-d'));
    }

    public function test_extending_an_expired_plan_puts_it_back_in_progress(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $user->id, 'target_date' => '2026-11-15']);

        $this->actingAs($user)->put("/reading-plans/{$plan->id}", ['target_date' => '2026-11-27'])->assertRedirect('/reading-plans');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertSame('2026-11-27', $plan->target_date->format('Y-m-d'));
    }

    public function test_completed_plan_cannot_be_edited_or_completed_again(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->completed()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get("/reading-plans/{$plan->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/reading-plans/{$plan->id}", ['target_date' => '2026-12-10'])->assertForbidden();
        $this->actingAs($user)->post("/reading-plans/{$plan->id}/complete")->assertForbidden();
    }

    public function test_complete_marks_the_plan_completed_for_owner_only(): void
    {
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->expired()->create(['user_id' => $user->id]);

        $this->actingAs(User::factory()->create())->post("/reading-plans/{$plan->id}/complete")->assertForbidden();
        $this->actingAs($user)->post("/reading-plans/{$plan->id}/complete")->assertRedirect('/reading-plans')->assertSessionHas('success');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertTrue($plan->completed_at->equalTo(now()));
    }

    public function test_destroy_deletes_the_plan_and_its_reminders_for_owner_only(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $other = $this->plan($user);
        $user->notify(new ReadingPlanReminder($plan, ReminderTiming::ThreeDaysBefore));
        $user->notify(new ReadingPlanReminder($other, ReminderTiming::ThreeDaysBefore));

        $this->actingAs(User::factory()->create())->delete("/reading-plans/{$plan->id}")->assertForbidden();
        $this->assertModelExists($plan);

        $this->actingAs($user)->delete("/reading-plans/{$plan->id}")->assertRedirect('/reading-plans')->assertSessionHas('success');
        $this->assertModelMissing($plan);
        $this->assertSame([$other->id], $user->notifications()->get()->pluck('data.reading_plan_id')->all());
    }

    public function test_deleting_a_book_removes_its_plans(): void
    {
        $plan = ReadingPlan::factory()->create();

        $plan->book->delete();

        $this->assertModelMissing($plan);
    }

    public function test_header_links_to_reading_plans_and_notifications(): void
    {
        $this->actingAs(User::factory()->create())->get('/reading-plans')->assertOk()
            ->assertSee(route('reading-plans.index'), false)->assertSee(route('notifications.index'), false);
    }
}
