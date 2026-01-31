// Initialize lucide icons
lucide.createIcons();

function isMobileView() {
    return window.innerWidth < 768; // Tailwind's md breakpoint
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarLogo = document.getElementById('sidebar-logo');
    const sonlyLogo = document.getElementById('sonly');

    if (isMobileView()) {
        // Mobile toggle
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
    } else {
        // Desktop toggle
        const currentlyCollapsed = sidebar.classList.contains('w-20');
        sidebar.classList.toggle('w-20', !currentlyCollapsed);
        sidebar.classList.toggle('w-64', currentlyCollapsed);

        // Save state
        localStorage.setItem('sidebarCollapsed', !currentlyCollapsed);

        // Toggle text & logos
        document.querySelectorAll('.sidebar-text').forEach(text => {
            text.classList.toggle('hidden', !currentlyCollapsed);
        });

        if (!currentlyCollapsed) {
            sidebarLogo.classList.add('hidden');
            sonlyLogo.classList.remove('hidden');
        } else {
            sidebarLogo.classList.remove('hidden');
            sonlyLogo.classList.add('hidden');
        }
    }

    updateDropdownIndicators();
}

function updateDropdownIndicators() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = sidebar.classList.contains('w-20') && !isMobileView();

    document.querySelectorAll('.dropdown-icon').forEach(icon => {
        const isOpen = icon.closest('.collapse')?.querySelector('input[type="checkbox"]').checked;
        if (isCollapsed) {
            icon.setAttribute('data-lucide', isOpen ? 'plus' : 'minus');
        } else {
            icon.setAttribute('data-lucide', isOpen ? 'chevron-down' : 'chevron-right');
        }
    });

    // Re-render all icons
    lucide.createIcons();
}

function handleResize() {
    const sidebar = document.getElementById('sidebar');
    const sidebarLogo = document.getElementById('sidebar-logo');
    const sonlyLogo = document.getElementById('sonly');

    if (isMobileView()) {
        // Reset to mobile closed state
        sidebar.classList.remove('w-64', 'w-20');
        sidebar.classList.add('-translate-x-full');
        sidebarLogo.classList.remove('hidden');
        sonlyLogo.classList.add('hidden');
    } else {
        const collapsedState = localStorage.getItem('sidebarCollapsed') === 'true';
        sidebar.classList.remove('-translate-x-full', 'translate-x-0');
        sidebar.classList.toggle('w-20', collapsedState);
        sidebar.classList.toggle('w-64', !collapsedState);

        document.querySelectorAll('.sidebar-text').forEach(text => {
            text.classList.toggle('hidden', collapsedState);
        });

        if (collapsedState) {
            sidebarLogo.classList.add('hidden');
            sonlyLogo.classList.remove('hidden');
        } else {
            sidebarLogo.classList.remove('hidden');
            sonlyLogo.classList.add('hidden');
        }
    }

    updateDropdownIndicators();
}

// Apply initial state
document.addEventListener('DOMContentLoaded', () => {
    handleResize();
    window.addEventListener('resize', handleResize);
});