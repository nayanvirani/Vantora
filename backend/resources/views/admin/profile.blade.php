@extends('admin.layout')

@section('title', 'Profile - Vantora Admin')

@section('content')
    <h1 class="mb-6 text-lg font-semibold">Profile</h1>

    <div class="max-w-xl space-y-6">
        <div class="rounded-lg border border-neutral-200 bg-white p-6">
            <div class="text-sm text-neutral-500">Signed in as</div>
            <div class="mt-1 font-medium">{{ $admin->name }} &lt;{{ $admin->email }}&gt;</div>
        </div>

        <form method="POST" action="{{ route('admin.profile.update-password') }}"
            class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
            @csrf
            @method('PUT')

            <h2 class="text-sm font-semibold text-neutral-700">Change password</h2>

            <div>
                <label for="current_password" class="block text-sm font-medium text-neutral-700">Current password</label>
                <input id="current_password" name="current_password" type="password" required
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
                @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-neutral-700">New password</label>
                <input id="password" name="password" type="password" required
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-neutral-700">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            </div>

            <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
                Update password
            </button>
        </form>
    </div>
@endsection
