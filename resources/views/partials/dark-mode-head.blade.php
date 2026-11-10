{{-- In <head> on every page, before the first paint: the reader's theme (stored choice, else the OS
     preference - Tailwind's dark variant is class-based here) and text size, and the ONE
     toggleDarkMode() the pages' theme buttons call. --}}
<script>
    (function () {
        var root = document.documentElement;
        try {
            var stored = localStorage.getItem('darkMode');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'true' || (stored === null && prefersDark)) {
                root.classList.add('dark');
            }
            var size = localStorage.getItem('fontSize');
            if (['sm', 'base', 'lg', 'xl', '2xl'].indexOf(size) !== -1) {
                root.setAttribute('data-font-size', size);
            }
        } catch (e) { /* private mode or blocked storage: defaults */ }

        window.toggleDarkMode = function () {
            root.classList.toggle('dark');
            try { localStorage.setItem('darkMode', root.classList.contains('dark')); } catch (e) {}
        };
    })();
</script>
