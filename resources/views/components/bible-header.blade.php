<!-- Mobile-First Navigation Header -->
<nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 fixed top-0 left-0 right-0 z-50 backdrop-blur-md bg-white/90 dark:bg-gray-800/90 w-full">
    <div class="px-4 sm:px-6 w-full max-w-none">
        <!-- Main Navigation Row -->
        <div class="flex justify-between items-center h-16 w-full">
            <!-- Logo/Home -->
            <div class="flex items-center">
                <a href="{{ route('bible.index') }}" class="text-xl font-bold text-blue-600 dark:text-blue-400 touch-friendly flex items-center">
                    📖 <span class="hidden sm:inline ml-2">Bible Reader</span>
                </a>
            </div>

            <!-- Mobile Actions -->
            <div class="flex items-center space-x-1 sm:hidden">
                <!-- Search Toggle (Mobile) -->
                <button onclick="toggleMobileSearch()" class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>

                <!-- Dark Mode Toggle (Mobile) -->
                <button onclick="toggleDarkMode()" class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
                    <svg class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                    </svg>
                    <svg class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                </button>

                <!-- Menu Toggle (Mobile) -->
                <button onclick="toggleMobileMenu()" class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <svg id="menu-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg id="menu-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Desktop Controls -->
            <div class="hidden sm:flex items-center space-x-4">
                <!-- Translation Selector -->
                @if(isset($availableTranslations) && $availableTranslations->count() > 1)
                    <div class="relative">
                        <form action="{{ route('bible.switch-translation') }}" method="POST" id="translationForm">
                            @csrf
                            <select name="translation"
                                    onchange="document.getElementById('translationForm').submit()"
                                    class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                @foreach($availableTranslations as $translation)
                                    <option value="{{ $translation['key'] }}"
                                            {{ (isset($currentTranslation) && $currentTranslation['key'] === $translation['key']) ? 'selected' : '' }}>
                                        {{ $translation['short_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @elseif(isset($currentTranslation))
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                        {{ $currentTranslation['short_name'] }}
                    </span>
                @endif

                <!-- Desktop Search Form -->
                <div class="relative">
                    <input type="text"
                           name="q"
                           placeholder="Search verses..."
                           value="{{ request('q') }}"
                           style="padding-left: 2.5rem;"
                           class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400 w-64">
                    <div style="position: absolute; top: 50%; left: 0.75rem; transform: translateY(-50%); pointer-events: none;">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <form action="{{ route('bible.search') }}" method="GET" class="hidden">
                        <input type="text" name="q" value="{{ request('q') }}">
                    </form>
                </div>

                <!-- Desktop Dark Mode Toggle -->
                <button onclick="toggleDarkMode()" id="darkModeToggle"
                        class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
                    <svg id="sunIcon" class="w-5 h-5 hidden dark:block" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                    </svg>
                    <svg id="moonIcon" class="w-5 h-5 block dark:hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Search Bar -->
    <div id="mobile-search" class="hidden border-t border-gray-200 dark:border-gray-700 p-4 sm:hidden">
        <div class="relative">
            <input type="text"
                   name="q"
                   placeholder="Search verses, words, or Strong's numbers..."
                   value="{{ request('q') }}"
                   style="padding-left: 2.5rem; width: 100%;"
                   class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500 dark:placeholder-gray-400">
            <div style="position: absolute; top: 50%; left: 0.75rem; transform: translateY(-50%); pointer-events: none;">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>
        <script>
            // Handle mobile search form submission
            document.addEventListener('DOMContentLoaded', function() {
                const mobileInput = document.querySelector('#mobile-search input[name="q"]');
                if (mobileInput) {
                    mobileInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const form = document.createElement('form');
                            form.method = 'GET';
                            form.action = '{{ route("bible.search") }}';
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'q';
                            input.value = this.value;
                            form.appendChild(input);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }
            });
        </script>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden border-t border-gray-200 dark:border-gray-700 sm:hidden">
        <div class="px-4 py-3 space-y-3">
            <!-- Translation Selector (Mobile) -->
            @if(isset($availableTranslations) && $availableTranslations->count() > 1)
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Translation</label>
                    <form action="{{ route('bible.switch-translation') }}" method="POST" id="mobileTranslationForm">
                        @csrf
                        <select name="translation"
                                onchange="document.getElementById('mobileTranslationForm').submit()"
                                class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
                            @foreach($availableTranslations as $translation)
                                <option value="{{ $translation['key'] }}"
                                        {{ (isset($currentTranslation) && $currentTranslation['key'] === $translation['key']) ? 'selected' : '' }}>
                                    {{ $translation['short_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @elseif(isset($currentTranslation))
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Translation</label>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                        {{ $currentTranslation['short_name'] }}
                    </div>
                </div>
            @endif

            <!-- Navigation Links (Mobile) -->
            <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
                <div class="grid grid-cols-1 gap-3">
                    <a href="{{ route('strongs.index') }}" class="touch-friendly flex items-center justify-center space-x-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg p-3 font-medium">
                        <span>🔤</span>
                        <span>Strong's Concordance</span>
                    </a>
                    <a href="{{ route('bible.index') }}" class="touch-friendly flex items-center justify-center space-x-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg p-3 font-medium">
                        <span>📖</span>
                        <span>Browse Bible</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

@push('scripts')
<script>
    // Handle desktop search form submission
    document.addEventListener('DOMContentLoaded', function() {
        const desktopInput = document.querySelector('.hidden.sm\\:flex input[name="q"]');
        if (desktopInput) {
            desktopInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = document.createElement('form');
                    form.method = 'GET';
                    form.action = '{{ route("bible.search") }}';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'q';
                    input.value = this.value;
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    });

    // Dark mode functionality
    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
    }

    // Mobile menu functionality
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        const openIcon = document.getElementById('menu-open');
        const closeIcon = document.getElementById('menu-close');

        menu.classList.toggle('hidden');
        openIcon.classList.toggle('hidden');
        closeIcon.classList.toggle('hidden');

        // Close search if open
        const search = document.getElementById('mobile-search');
        search.classList.add('hidden');
    }

    // Mobile search functionality
    function toggleMobileSearch() {
        const search = document.getElementById('mobile-search');
        search.classList.toggle('hidden');

        // Close menu if open
        const menu = document.getElementById('mobile-menu');
        const openIcon = document.getElementById('menu-open');
        const closeIcon = document.getElementById('menu-close');

        if (!menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            openIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        }

        // Focus search input when opened
        if (!search.classList.contains('hidden')) {
            setTimeout(() => {
                search.querySelector('input').focus();
            }, 100);
        }
    }
</script>
@endpush
