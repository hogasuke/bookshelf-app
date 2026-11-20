<?php

namespace App\Http\Controllers;

use App\Services\ReadingReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（GET /reports・認証必須）。
     */
    public function index(Request $request, ReadingReport $report): View
    {
        return view('reports.index', ['stats' => $report->for($request->user())]);
    }
}
