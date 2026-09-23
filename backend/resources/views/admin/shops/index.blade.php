@extends('admin.layout')

@section('title', 'Shops - Vantora Admin')

@section('content')
    <h1 class="mb-6 text-lg font-semibold">Shops ({{ $shops->total() }})</h1>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Domain</th>
                    <th class="px-4 py-2 font-medium">Plan</th>
                    <th class="px-4 py-2 font-medium">Plus</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Installed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($shops as $shop)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <a href="{{ route('admin.shops.show', $shop) }}" class="font-medium text-neutral-900 hover:underline">
                                {{ $shop->domain }}
                            </a>
                        </td>
                        <td class="px-4 py-2 capitalize">{{ $shop->subscriptions->first()->plan ?? 'starter' }}</td>
                        <td class="px-4 py-2">{{ $shop->is_plus ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-2">
                            @if ($shop->isAdminPaused())
                                <span class="text-amber-600">Paused</span>
                            @elseif ($shop->uninstalled_at)
                                <span class="text-neutral-400">Uninstalled</span>
                            @else
                                <span class="text-green-600">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-neutral-500">{{ $shop->installed_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-neutral-400">No shops installed yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $shops->links() }}
    </div>
@endsection
