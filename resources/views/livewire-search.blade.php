<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search the Bible</title>
    @include('partials.dark-mode-head')
    {{-- Livewire 3 bundles Alpine; loading resources/js/app.js here started a SECOND
         Alpine instance on the same document, and shipped an unused axios global. --}}
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div class="h-full flex flex-col">
        <main class="flex-1 overflow-hidden">
            <livewire:bible-search />
        </main>
    </div>

    @livewireScripts
    <script>
        // Results scroll inside #search-scroller, not the window. "Load more" keeps the reader's
        // place; a new search starts at the top.
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ component, commit, succeed }) => {
                if (component.name !== 'bible-search') return;
                const scroller = document.getElementById('search-scroller');
                const before = scroller ? scroller.scrollTop : 0;
                const loadingMore = (commit.calls || []).some((call) => call.method === 'loadMore');
                succeed(() => requestAnimationFrame(() => {
                    const s = document.getElementById('search-scroller');
                    if (s) s.scrollTop = loadingMore ? before : 0;
                }));
            });
        });
    </script>
</body>
</html>
