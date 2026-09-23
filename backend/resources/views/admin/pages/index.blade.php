@extends('admin.layout')

@section('title', 'Pages - Vantora Admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold">Pages</h1>
        <a href="{{ route('admin.pages.create') }}" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
            New page
        </a>
    </div>

    <p class="mb-4 text-sm text-neutral-500">
        Privacy Policy, Terms, FAQ, or any other content page -- published pages are reachable at
        <code class="rounded bg-neutral-100 px-1">/pages/&lbrace;slug&rbrace;</code>,
        and <code class="rounded bg-neutral-100 px-1">privacy</code>/<code class="rounded bg-neutral-100 px-1">terms</code>/<code class="rounded bg-neutral-100 px-1">faq</code>
        slugs also get a clean <code class="rounded bg-neutral-100 px-1">/privacy</code> etc. URL.
    </p>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-neutral-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Slug</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($pages as $page)
                    <tr>
                        <td class="px-4 py-2 font-medium">{{ $page->title }}</td>
                        <td class="px-4 py-2 text-neutral-500">/{{ $page->slug }}</td>
                        <td class="px-4 py-2">
                            @if ($page->is_published)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Published</span>
                            @else
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="text-neutral-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="inline" onsubmit="return confirm('Delete this page?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-neutral-400">No pages yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
