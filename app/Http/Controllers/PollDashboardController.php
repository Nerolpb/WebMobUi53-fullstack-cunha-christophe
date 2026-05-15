<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PollDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $polls = $request->user()
            ->polls()
            ->with('options')
            ->orderBy('created_at', 'desc')
            ->get();

        $propsJson = json_encode([
            'polls'    => $polls,
            'loginUrl' => route('login'),
            'userId'   => $request->user()->id,
            'username' => $request->user()->username,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        return view('polls.dashboard', ['propsJson' => $propsJson]);
    }
}
