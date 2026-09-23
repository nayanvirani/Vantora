@extends('admin.layout')

@section('title', 'Admin Login - Vantora')

@section('content')
    <div class="mx-auto mt-16 max-w-sm rounded-lg border border-neutral-200 bg-white p-8">
        <h1 class="mb-6 text-lg font-semibold">Vantora Admin</h1>

        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-neutral-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-neutral-700">Password</label>
                <input id="password" name="password" type="password" required
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-600">
                <input type="checkbox" name="remember">
                Remember me
            </label>

            <button type="submit"
                class="w-full rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800">
                Log in
            </button>
        </form>
    </div>
@endsection
