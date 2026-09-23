@extends('admin.layout')

@section('title', ($page->exists ? 'Edit' : 'New') . ' Page - Vantora Admin')

@section('content')
    <a href="{{ route('admin.pages.index') }}" class="text-sm text-neutral-500 hover:underline">&larr; Pages</a>

    <h1 class="mb-6 mt-2 text-lg font-semibold">{{ $page->exists ? 'Edit page' : 'New page' }}</h1>

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
        class="max-w-2xl space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
        @csrf
        @if ($page->exists) @method('PUT') @endif

        <div>
            <label for="title" class="block text-sm font-medium text-neutral-700">Title</label>
            <input id="title" name="title" type="text" value="{{ old('title', $page->title) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium text-neutral-700">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $page->slug) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-neutral-500">
                Use <code class="rounded bg-neutral-100 px-1">privacy</code>, <code class="rounded bg-neutral-100 px-1">terms</code>
                or <code class="rounded bg-neutral-100 px-1">faq</code> for the clean top-level URLs -- anything else is reachable at /pages/&lbrace;slug&rbrace;.
            </p>
            @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="content" class="block text-sm font-medium text-neutral-700">Content</label>
            <textarea id="content" name="content" rows="14"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 font-mono text-sm">{{ old('content', $page->content) }}</textarea>
            <p class="mt-1 text-xs text-neutral-500">Raw HTML, rendered inside a styled content wrapper.</p>
        </div>

        <div>
            <label for="sort_order" class="block text-sm font-medium text-neutral-700">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $page->sort_order ?? 0) }}" required
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </div>

        <label class="flex items-center gap-2 text-sm text-neutral-700">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published ?? true))>
            Published
        </label>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
                {{ $page->exists ? 'Save changes' : 'Create page' }}
            </button>
            <a href="{{ route('admin.pages.index') }}" class="text-sm text-neutral-500 hover:underline">Cancel</a>
        </div>
    </form>
@endsection
