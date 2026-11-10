{{-- In <head>, before the first paint: stored choice wins, else the OS preference. Tailwind's dark
     variant is class-based here, so prefers-color-scheme has to be resolved in script. --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('darkMode');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'true' || (stored === null && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) { /* private mode or blocked storage: fall back to light */ }
    })();
</script>
