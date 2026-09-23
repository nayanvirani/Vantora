@extends('admin.layout')

@section('title', $shop->domain . ' - Vantora Admin')

@section('content')
    <a href="{{ route('admin.shops.index') }}" class="text-sm text-neutral-500 hover:underline">&larr; Shops</a>

    <h1 class="mb-6 mt-2 text-lg font-semibold">{{ $shop->domain }}</h1>

    <div class="mb-6 grid grid-cols-2 gap-4 rounded-lg border border-neutral-200 bg-white p-6 text-sm sm:grid-cols-4">
        <div>
            <div class="text-neutral-500">Plan</div>
            <div class="font-medium capitalize">{{ $shop->currentPlan() }}</div>
        </div>
        <div>
            <div class="text-neutral-500">Shopify Plus</div>
            <div class="font-medium">{{ $shop->is_plus ? 'Yes' : 'No' }}</div>
        </div>
        <div>
            <div class="text-neutral-500">Installed</div>
            <div class="font-medium">{{ $shop->installed_at?->format('Y-m-d') ?? '—' }}</div>
        </div>
        <div>
            <div class="text-neutral-500">Status</div>
            <div class="font-medium">
                @if ($shop->isAdminPaused())
                    Paused
                @elseif ($shop->uninstalled_at)
                    Uninstalled
                @else
                    Active
                @endif
            </div>
        </div>
    </div>

    <div class="mb-6 flex gap-3">
        @if ($shop->isAdminPaused())
            <form method="POST" action="{{ route('admin.shops.unpause', $shop) }}">
                @csrf
                <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm hover:bg-neutral-50">
                    Unpause
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.shops.pause', $shop) }}">
                @csrf
                <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm hover:bg-neutral-50">
                    Pause
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.shops.resync-plan', $shop) }}">
            @csrf
            <button type="submit" class="rounded-md border border-neutral-300 px-4 py-2 text-sm hover:bg-neutral-50">
                Resync plan from Shopify
            </button>
        </form>

        <form method="POST" action="{{ route('admin.shops.plan-override', $shop) }}" class="flex items-center gap-2">
            @csrf
            <select name="plan" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                <option value="starter" @selected($shop->currentPlan() === 'starter')>Starter</option>
                <option value="pro" @selected($shop->currentPlan() === 'pro')>Pro</option>
            </select>
            <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm text-white hover:bg-neutral-800">
                Set plan (admin override)
            </button>
        </form>
    </div>

    <h2 class="mb-2 text-sm font-semibold text-neutral-700">Subscription history</h2>
    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Plan</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Admin override</th>
                    <th class="px-4 py-2 font-medium">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($shop->subscriptions as $subscription)
                    <tr>
                        <td class="px-4 py-2 capitalize">{{ $subscription->plan }}</td>
                        <td class="px-4 py-2">{{ $subscription->status }}</td>
                        <td class="px-4 py-2">{{ $subscription->overridden_by_admin ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ $subscription->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-neutral-400">No subscriptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
