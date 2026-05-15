<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-dashboard.js'])
    </x-slot>

    <x-slot:title>
        Mes sondages
    </x-slot>

    <div
        id="app"
        data-props='@json([
            "polls"    => $polls,
            "loginUrl" => route("login"),
            "userId"   => $userId,
            "username" => $username,
        ])'
    ></div>
</x-vue-app-layout>
