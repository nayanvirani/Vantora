@extends('admin.layout')

@section('title', 'Plans - Vantora Admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Plans</h1>
        <a href="{{ route('admin.plans.create') }}" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
            New plan
        </a>
    </div>

    <p class="mb-4 text-sm text-neutral-500">
        Display data only -- shown on the landing page and (later) the in-app billing screen.
        Actual billing is Shopify Managed Pricing, configured in the Partner Dashboard.
    </p>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="px-4 py-2 font-medium">Handle</th>
                    <th class="px-4 py-2 font-medium">Price</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($plans as $plan)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $plan->name }}</td>
                        <td class="px-4 py-2 text-neutral-500">{{ $plan->handle }}</td>
                        <td class="px-4 py-2">${{ number_format($plan->price, 2) }}/mo</td>
                        <td class="px-4 py-2">
                            @if ($plan->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                            @else
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-neutral-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Delete this plan?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-neutral-400">No plans yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
