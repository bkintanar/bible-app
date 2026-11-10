<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sign in — Bible</title>
    @vite(['resources/css/app.css'])
    @include('partials.dark-mode-head')
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <main class="min-h-full flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <h1 class="text-2xl font-serif font-semibold text-gray-900 dark:text-gray-100 text-center mb-2">
                Sign in
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-8">
                Required to edit verse titles.
            </p>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email
                    </label>
                    <input id="email" name="email" type="email" required autofocus
                           @error('email') aria-invalid="true" aria-describedby="login-error" @enderror
                           autocomplete="username"
                           value="{{ old('email') }}"
                           class="w-full px-3 py-3 rounded-md border border-gray-400 dark:border-gray-600
                                  bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Password
                    </label>
                    <input id="password" name="password" type="password" required
                           autocomplete="current-password"
                           class="w-full px-3 py-3 rounded-md border border-gray-400 dark:border-gray-600
                                  bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                                  focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="remember" value="1"
                           class="rounded border-gray-400 dark:border-gray-600">
                    Remember me
                </label>

                @if ($errors->any())
                    {{-- role=alert so the failure is announced, not just shown --}}
                    <div id="login-error" role="alert" class="text-sm text-red-700 dark:text-red-400">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button type="submit"
                        class="w-full py-3 rounded-md bg-blue-700 hover:bg-blue-800 text-white font-medium
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-700">
                    Sign in
                </button>
            </form>

            <p class="mt-8 text-center text-sm">
                <a href="{{ route('bible.index') }}"
                   class="text-blue-700 dark:text-blue-400 hover:underline">
                    Back to reading
                </a>
            </p>
        </div>
    </main>
</body>
</html>
