@extends('admin.layout')

@section('title', 'Dashboard - Vantora Admin')

@section('content')
    <h1 class="mb-6 text-lg font-semibold">Dashboard</h1>

    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-sm text-neutral-500">Total shops</div>
            <div class="mt-1 text-2xl font-bold">{{ $totalShops }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-sm text-neutral-500">Active shops</div>
            <div class="mt-1 text-2xl font-bold">{{ $activeShops }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-sm text-neutral-500">Paused (admin)</div>
            <div class="mt-1 text-2xl font-bold">{{ $pausedShops }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4">
            <div class="text-sm text-neutral-500">On Pro plan</div>
            <div class="mt-1 text-2xl font-bold">{{ $proShops }}</div>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold text-neutral-700">Recently installed shops</h2>

        <table class="w-full text-left text-sm">
            <tbody class="divide-y divide-neutral-100">
                @forelse ($recentShops as $shop)
                    <tr>
                        <td class="py-2">
                            <a href="{{ route('admin.shops.show', $shop) }}" class="font-medium hover:underline">
                                {{ $shop->domain }}
                            </a>
                        </td>
                        <td class="py-2 capitalize text-neutral-500">{{ $shop->subscriptions->first()->plan ?? 'starter' }}</td>
                        <td class="py-2 text-right text-neutral-500">{{ $shop->installed_at?->format('M j, Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="py-6 text-center text-neutral-400">No shops installed yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
