<?php

namespace App\Http\Controllers;

class PollVoteController extends Controller
{
    public function __invoke(string $token)
    {
        $propsJson = json_encode([
            'token'           => $token,
            'isAuthenticated' => auth()->check(),
            'loginUrl'        => route('login'),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        return view('polls.vote', ['propsJson' => $propsJson]);
    }
}
