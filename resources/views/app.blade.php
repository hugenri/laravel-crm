<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        @vite('resources/js/app.js')
        <x-inertia::head>
            <title inertia>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-slate-50 font-sans text-slate-800 antialiased">
        <x-inertia::app />
    </body>
</html>
