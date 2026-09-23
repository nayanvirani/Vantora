@extends('admin.layout')

@section('title', 'Settings - Vantora Admin')

@section('content')
    <h1 class="mb-6 text-lg font-semibold">Settings</h1>

    <form method="POST" action="{{ route('admin.settings.update') }}"
        class="max-w-xl space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
        @csrf
        @method('PUT')

        <label class="flex items-center gap-2 text-sm text-neutral-700">
            <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $settings->maintenance_mode))>
            Maintenance mode
        </label>
        <p class="-mt-3 text-xs text-neutral-500">Reserved for future use -- not yet checked anywhere in the app.</p>

        <div>
            <label for="support_email" class="block text-sm font-medium text-neutral-700">Support email</label>
            <input id="support_email" name="support_email" type="email" value="{{ old('support_email', $settings->support_email) }}"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-neutral-500">
                Use <code class="rounded bg-neutral-100 px-1">&lbrace;&lbrace;SUPPORT_EMAIL&rbrace;&rbrace;</code> inside any
                content page and it's replaced with this value.
            </p>
            @error('support_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="announcement_banner" class="block text-sm font-medium text-neutral-700">Announcement banner</label>
            <input id="announcement_banner" name="announcement_banner" type="text" value="{{ old('announcement_banner', $settings->announcement_banner) }}"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-neutral-500">Leave blank to hide. Not yet rendered anywhere -- reserved for the future embedded admin SPA.</p>
            @error('announcement_banner') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
            Save changes
        </button>
    </form>
@endsection
