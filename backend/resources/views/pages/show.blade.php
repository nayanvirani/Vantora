<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} - Vantora</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-neutral-900">
    <header class="border-b border-neutral-200 px-6 py-4">
        <a href="{{ route('landing') }}" class="font-semibold">Vantora</a>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-12">
        <h1 class="mb-6 text-2xl font-bold">{{ $page->title }}</h1>
        <div class="prose prose-neutral max-w-none">
            {!! $page->content !!}
        </div>
    </main>
</body>
</html>
