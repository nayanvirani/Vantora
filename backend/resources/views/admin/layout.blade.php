<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vantora Admin')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-900">
    @auth('admin')
        <nav class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-3">
                <a href="{{ route('admin.shops.index') }}" class="font-semibold">Vantora Admin</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-neutral-500 hover:text-neutral-900">Log out</button>
                </form>
            </div>
        </nav>
    @endauth

    <main class="mx-auto max-w-5xl px-6 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
