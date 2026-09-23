@extends('admin.layout')

@section('title', ($plan->exists ? 'Edit' : 'New') . ' Plan - Vantora Admin')

@section('content')
    <a href="{{ route('admin.plans.index') }}" class="text-sm text-neutral-500 hover:underline">&larr; Plans</a>

    <h1 class="mb-6 mt-2 text-lg font-semibold">{{ $plan->exists ? 'Edit plan' : 'New plan' }}</h1>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
        class="max-w-xl space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div>
            <label for="name" class="block text-sm font-medium text-neutral-700">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $plan->name) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="handle" class="block text-sm font-medium text-neutral-700">Handle</label>
            <input id="handle" name="handle" type="text" value="{{ old('handle', $plan->handle) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-neutral-500">Must match the plan name in Shopify's Partner Dashboard, case-insensitive.</p>
            @error('handle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="price" class="block text-sm font-medium text-neutral-700">Price (USD/month)</label>
            <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $plan->price) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="features" class="block text-sm font-medium text-neutral-700">Features</label>
            <textarea id="features" name="features" rows="5"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">One per line -- shown as bullet points on the pricing card.</p>
        </div>

        <div>
            <label for="sort_order" class="block text-sm font-medium text-neutral-700">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm text-neutral-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
            Active (visible on the landing page)
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
                {{ $plan->exists ? 'Save changes' : 'Create plan' }}
            </button>
            <a href="{{ route('admin.plans.index') }}" class="text-sm text-neutral-500 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
