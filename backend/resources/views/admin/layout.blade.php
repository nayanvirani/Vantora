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
        <div class="flex min-h-screen">
            <aside class="flex w-56 flex-col border-r border-neutral-200 bg-white">
                <div class="border-b border-neutral-200 px-5 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="font-semibold">Vantora Admin</a>
                </div>

                <nav class="flex flex-1 flex-col gap-1 p-3">
                    @php
                        $navItems = [
                            ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Dashboard'],
                            ['route' => 'admin.shops.index', 'match' => 'admin.shops.*', 'label' => 'Shops'],
                        ];
                    @endphp
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}"
                            class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($item['match']) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-neutral-200 p-3">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-md px-3 py-2 text-left text-sm text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900">
                            Log out
                        </button>
                    </form>
                </div>
            </aside>

            <main class="flex-1 px-8 py-8">
                <div class="mx-auto max-w-4xl">
                    @if (session('status'))
                        <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main class="px-8 py-8">
            @yield('content')
        </main>
    @endauth
</body>
</html>
