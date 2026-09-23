@extends('admin.layout')

@section('title', 'Subscriptions - Vantora Admin')

@section('content')
    <h1 class="mb-2 text-lg font-semibold">Subscriptions</h1>
    <p class="mb-4 text-sm text-neutral-500">
        Every subscription row ever created for any shop -- a full history, not just current state.
        A new row is created on each webhook-driven change or admin plan override; old rows are marked "superseded", never deleted.
    </p>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Shop</th>
                    <th class="px-4 py-2 font-medium">Plan</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Admin override</th>
                    <th class="px-4 py-2 font-medium">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="px-4 py-2">
                            @if ($subscription->shop)
                                <a href="{{ route('admin.shops.show', $subscription->shop) }}" class="font-medium hover:underline">
                                    {{ $subscription->shop->domain }}
                                </a>
                            @else
                                <span class="text-neutral-400">(deleted shop)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 capitalize">{{ $subscription->plan }}</td>
                        <td class="px-4 py-2">
                            @php
                                $tone = match ($subscription->status) {
                                    'active' => 'bg-green-100 text-green-800',
                                    'superseded' => 'bg-neutral-100 text-neutral-500',
                                    default => 'bg-amber-100 text-amber-800',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">{{ ucfirst($subscription->status) }}</span>
                        </td>
                        <td class="px-4 py-2">{{ $subscription->overridden_by_admin ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ $subscription->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-neutral-400">No subscriptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $subscriptions->links() }}
    </div>
@endsection
