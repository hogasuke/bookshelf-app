<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 自分宛ての通知だけ既読にできる。ほかの人の通知のIDは見つからない扱い（404）にする。
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return redirect()->route('notifications.index')->with('success', '通知を既読にしました。');
    }
}
