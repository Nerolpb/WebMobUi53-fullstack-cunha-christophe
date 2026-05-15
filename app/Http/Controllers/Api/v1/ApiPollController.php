<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->polls()
            ->with('options')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => fn($q) => $q->withCount('votes')])
            ->where('secret_token', $token)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user = auth('sanctum')->user();
        $isOwner = $user && $poll->user_id === $user->id;
        $isExpired = $poll->ends_at && now()->isAfter($poll->ends_at);

        $userVotes = $user
            ? PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id')
                ->all()
            : [];

        $data = $poll->toArray();
        $data['is_owner'] = $isOwner;
        $data['is_expired'] = $isExpired;
        $data['user_votes'] = $userVotes;
        $data['total_votes'] = $poll->votes()->count();
        $data['is_authenticated'] = $user !== null;

        if (!$poll->results_public && !$isOwner) {
            foreach ($data['options'] as &$opt) {
                unset($opt['votes_count']);
            }
            $data['total_votes'] = null;
        }

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'question'               => 'required|string|max:500',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
            'start_now'              => 'boolean',
        ]);

        $startNow = $data['start_now'] ?? false;
        $duration = $data['duration'] ?? null;

        $poll = $request->user()->polls()->create([
            'question'               => $data['question'],
            'title'                  => $data['title'] ?? null,
            'secret_token'           => Str::random(40),
            'is_draft'               => !$startNow,
            'allow_multiple_choices' => $data['allow_multiple_choices'] ?? false,
            'allow_vote_change'      => $data['allow_vote_change'] ?? false,
            'results_public'         => $data['results_public'] ?? false,
            'duration'               => $duration,
            'started_at'             => $startNow ? now() : null,
            'ends_at'                => ($startNow && $duration) ? now()->addSeconds($duration) : null,
        ]);

        foreach ($data['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }

    public function update(Request $request, int $id)
    {
        $poll = Poll::with('options')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $data = $request->validate([
            'question'               => 'sometimes|required|string|max:500',
            'title'                  => 'nullable|string|max:255',
            'options'                => 'sometimes|required|array|min:2',
            'options.*.id'           => 'nullable|integer',
            'options.*.label'        => 'required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        $updateData = [];
        if (array_key_exists('question', $data))               $updateData['question']               = $data['question'];
        if (array_key_exists('title', $data))                  $updateData['title']                  = $data['title'];
        if (array_key_exists('allow_multiple_choices', $data)) $updateData['allow_multiple_choices'] = $data['allow_multiple_choices'];
        if (array_key_exists('allow_vote_change', $data))      $updateData['allow_vote_change']      = $data['allow_vote_change'];
        if (array_key_exists('results_public', $data))         $updateData['results_public']         = $data['results_public'];
        if (array_key_exists('duration', $data))               $updateData['duration']               = $data['duration'];

        if (!empty($updateData)) {
            $poll->update($updateData);
        }

        if (isset($data['options'])) {
            $ownOptionIds = $poll->options->pluck('id')->all();
            $incomingIds = collect($data['options'])->pluck('id')->filter()->values()->all();

            foreach ($incomingIds as $optId) {
                if (!in_array($optId, $ownOptionIds)) {
                    return response()->json(['message' => 'Option invalide.'], 422);
                }
            }

            $poll->options()->whereNotIn('id', $incomingIds)->delete();

            foreach ($data['options'] as $opt) {
                $optId = $opt['id'] ?? null;
                if ($optId && in_array($optId, $ownOptionIds)) {
                    $poll->options()->where('id', $optId)->update(['label' => $opt['label']]);
                } else {
                    $poll->options()->create(['label' => $opt['label']]);
                }
            }
        }

        return response()->json($poll->fresh()->load('options'));
    }

    public function start(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        if (!$poll->is_draft) {
            return response()->json(['message' => 'Le sondage est déjà lancé.'], 422);
        }

        $poll->update([
            'is_draft'   => false,
            'started_at' => now(),
            'ends_at'    => $poll->duration ? now()->addSeconds($poll->duration) : null,
        ]);

        return response()->json($poll->fresh()->load('options'));
    }

    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas encore ouvert.'], 422);
        }

        if ($poll->ends_at && now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 422);
        }

        $data = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'required|integer',
        ]);

        $validIds = $poll->options->pluck('id')->all();
        foreach ($data['option_ids'] as $optId) {
            if (!in_array($optId, $validIds)) {
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        if (!$poll->allow_multiple_choices && count($data['option_ids']) > 1) {
            return response()->json(['message' => 'Ce sondage n\'accepte qu\'un seul choix.'], 422);
        }

        $user = $request->user();
        $hasVoted = PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->exists();

        if ($hasVoted) {
            if ($poll->allow_vote_change) {
                PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->delete();
            } else {
                return response()->json(['message' => 'Vous avez déjà voté.'], 422);
            }
        }

        foreach ($data['option_ids'] as $optId) {
            PollVote::create([
                'poll_id'        => $poll->id,
                'user_id'        => $user->id,
                'poll_option_id' => $optId,
            ]);
        }

        return response()->json(['message' => 'Vote enregistré.'], 201);
    }

    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'success']);
    }
}
