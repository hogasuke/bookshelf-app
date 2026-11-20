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

    public function create(): View
    {
        $books = Book::orderBy('title')->get(['id', 'title', 'author']);

        return view('reading-plans.create', compact('books'));
    }

    public function store(ReadingPlanRequest $request, ReadingPlanWriter $writer): RedirectResponse
    {
        $writer->create($request->user(), (int) $request->validated('book_id'), $request->validated('target_date'));

        return redirect()->route('reading-plans.index')->with('success', '読書計画を登録しました。');
    }

    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);
        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    public function update(ReadingPlanRequest $request, ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('update', $readingPlan);
        $writer->reschedule($readingPlan, $request->validated('target_date'));

        return redirect()->route('reading-plans.index')->with('success', '読書計画を更新しました。');
    }

    public function complete(ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);
        $writer->complete($readingPlan);

        return redirect()->route('reading-plans.index')->with('success', '読了にしました。');
    }

    public function destroy(ReadingPlan $readingPlan, ReadingPlanWriter $writer): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);
        $writer->delete($readingPlan);

        return redirect()->route('reading-plans.index')->with('success', '読書計画を削除しました。');
    }
}
