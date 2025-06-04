<!-- JavaScript Functions -->
<script>
    function toggleDarkMode() {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
    }

    // Book flip animation function
    function flipPage(direction, url) {
        const currentPage = document.querySelector('.page-current');
        const prevPage = document.querySelector('.page-prev');
        const nextPage = document.querySelector('.page-next');

        if (!currentPage) return;

        // Prevent multiple clicks during animation
        if (currentPage.classList.contains('flipping-next') || currentPage.classList.contains('flipping-prev')) {
            return;
        }

        // Reset all preview pages to low opacity
        if (prevPage) {
            prevPage.style.opacity = '0.1';
            prevPage.style.zIndex = '0';
        }
        if (nextPage) {
            nextPage.style.opacity = '0.1';
            nextPage.style.zIndex = '0';
        }

        // Show only the appropriate preview page during animation
        if (direction === 'prev' && prevPage) {
            prevPage.style.opacity = '1';
            prevPage.style.zIndex = '1';
        } else if (direction === 'next' && nextPage) {
            nextPage.style.opacity = '1';
            nextPage.style.zIndex = '1';
        }

        // Start flip animation on current page only
        currentPage.classList.add(`flipping-${direction}`);

        // Navigate after 400ms (when pages meet at 90 degrees)
        setTimeout(() => {
            window.location.href = url;
        }, 400);
    }

    // Page entrance animation
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = document.querySelector('.page-current');

        if (currentPage) {
            setTimeout(() => {
                currentPage.style.transition = 'opacity 0.4s ease';
                currentPage.style.opacity = '1';
            }, 50);
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const currentPage = document.querySelector('.page-current');
        if (currentPage) {
            currentPage.classList.add('flipping-prev');
            setTimeout(() => {
                currentPage.classList.remove('flipping-prev');
            }, 800);
        }
    });
</script>
