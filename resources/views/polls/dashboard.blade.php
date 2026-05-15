<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-dashboard.js'])
    </x-slot>

    <x-slot:title>
        Mes sondages
    </x-slot>

    <div id="app" data-props="{{ $propsJson }}"></div>
</x-vue-app-layout>
