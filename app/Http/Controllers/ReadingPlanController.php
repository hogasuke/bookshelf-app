<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\ReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Services\ReadingPlanWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * ログインした人の計画を期日の近い順に出す。?status= が Enum の値でなければ「すべて」と同じ扱い。
     *
     * @param  Request  $request  リクエスト
     * @return View 画面
     */
    public function index(Request $request): View
    {
        $status = ReadingPlanStatus::tryFrom((string) $request->query('status'));

        $readingPlans = $request->user()->readingPlans()
            ->with('book')
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        return view('reading-plans.index', [
            'readingPlans' => $readingPlans,
            'currentStatus' => $status?->value,
        ]);
    }

    /**
     * 読書計画の作成フォームを、選べる書籍つきで出す。
     *
     * @return View 画面
     */
    public function create(): View
    {
        $books = Book::orderBy('title')->get(['id', 'title', 'author']);

        return view('reading-plans.create', compact('books'));
    }

    /**
     * ログインした人の読書計画を作成し、一覧へ戻す。
     *
     * @param  ReadingPlanRequest  $request  リクエスト
     * @param  ReadingPlanWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function store(ReadingPlanRequest $request, ReadingPlanWriter $writer): RedirectResponse
    {
        $writer->create($request->user(), (int) $request->validated('book_id'), $request->validated('target_date'));

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    /**
     * 計画を立てた本人だけに、期日の変更フォームを出す（読了済みは不可）。
     *
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @return View 画面
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);
        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 計画を立てた本人だけが期日を変更し、一覧へ戻す。
     *
     * @param  ReadingPlanRequest  $request  リクエスト
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @param  ReadingPlanWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function update(ReadingPlanRequest $request, ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('update', $readingPlan);
        $writer->reschedule($readingPlan, $request->validated('target_date'));

        return redirect()->route('reading-plans.index')->with('success', '読書計画を更新しました。');
    }

    /**
     * 計画を立てた本人だけが計画を読了にし、一覧へ戻す。
     *
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @param  ReadingPlanWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function complete(ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);
        $writer->complete($readingPlan);

        return redirect()->route('reading-plans.index')->with('success', '読了にしました。');
    }

    /**
     * 計画を立てた本人だけが計画を削除し、一覧へ戻す。
     *
     * @param  ReadingPlan  $readingPlan  対象の読書計画
     * @param  ReadingPlanWriter  $writer  書き込みを受け持つサービス
     * @return RedirectResponse リダイレクト
     */
    public function destroy(ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);
        $writer->delete($readingPlan);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }
}
