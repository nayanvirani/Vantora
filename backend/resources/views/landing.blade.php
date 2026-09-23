<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vantora - Store Audit & Fixes</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-neutral-900">
    <header class="border-b border-neutral-200 px-6 py-4">
        <div class="mx-auto flex max-w-5xl items-center justify-between">
            <span class="font-semibold">Vantora</span>
            <a href="#pricing" class="text-sm text-neutral-600 hover:text-neutral-900">Pricing</a>
        </div>
    </header>

    <section class="mx-auto max-w-3xl px-6 py-20 text-center">
        <h1 class="text-4xl font-bold tracking-tight">Find what's hurting your sales.<br>Fix it in one click.</h1>
        <p class="mx-auto mt-4 max-w-xl text-neutral-600">
            Vantora audits your Shopify store, tells you the few things that matter most, and lets
            you fix each one with a single click using built-in tools.
        </p>
    </section>

    @if ($plans->isNotEmpty())
        <section id="pricing" class="mx-auto max-w-5xl px-6 py-16">
            <h2 class="mb-8 text-center text-2xl font-bold">Pricing</h2>

            <div class="grid gap-6 sm:grid-cols-2 mx-auto max-w-2xl">
                @foreach ($plans as $plan)
                    <div class="rounded-xl border border-neutral-200 p-6">
                        <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
                        <p class="mt-2 text-3xl font-bold">${{ number_format($plan->price, 2) }}<span class="text-base font-normal text-neutral-500">/mo</span></p>

                        @if (!empty($plan->features))
                            <ul class="mt-4 space-y-2 text-sm text-neutral-600">
                                @foreach ($plan->features as $feature)
                                    <li class="flex items-start gap-2">
                                        <span class="mt-0.5 text-green-600">&check;</span>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <footer class="border-t border-neutral-200 px-6 py-8">
        <div class="mx-auto flex max-w-5xl flex-col items-center gap-3 text-sm text-neutral-500 sm:flex-row sm:justify-between">
            <span>&copy; {{ now()->year }} Vantora. Not affiliated with Shopify Inc.</span>

            @if ($footerPages->isNotEmpty())
                <nav class="flex gap-4">
                    @foreach ($footerPages as $footerPage)
                        <a href="{{ url($footerPage->slug) }}" class="hover:text-neutral-900">{{ $footerPage->title }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </footer>
</body>
</html>
