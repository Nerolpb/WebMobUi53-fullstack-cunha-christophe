<?php

namespace App\Http\Controllers;

class PollVoteController extends Controller
{
    public function __invoke(string $token)
    {
        return view('polls.vote', [
            'token'           => $token,
            'isAuthenticated' => auth()->check(),
        ]);
    }
}
