<x-default-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <x-slot:title>
        Sondage
    </x-slot>

    <div
        id="poll-vote-app"
        data-props='@json([
            "token"           => $token,
            "isAuthenticated" => $isAuthenticated,
            "loginUrl"        => route("login"),
        ])'
    ></div>
</x-default-layout>
