<?php

namespace App\Http\Controllers;

use App\Services\ReadingReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（GET /reports・認証必須）。
     *
     * @param  Request  $request  リクエスト
     * @param  ReadingReport  $report  読書レポートの集計サービス
     * @return View 画面
     */
    public function index(Request $request, ReadingReport $report): View
    {
        return view('reports.index', ['stats' => $report->for($request->user())]);
    }
}
