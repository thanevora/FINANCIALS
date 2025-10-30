<?php
// navbar.php
$current_user = $_SESSION['user_name'] ?? 'User';
$user_initials = strtoupper(substr($current_user, 0, 2));
?>

<header class="bg-white shadow-sm z-10 border-b border-gray-200">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Left Section -->
            <div class="flex items-center">
                <!-- Mobile Sidebar Toggle -->
                <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-all hover:scale-105 md:hidden mr-4">
                    <i data-lucide="menu" class="w-5 h-5 text-gray-700"></i>
                </button>

                <!-- Desktop Sidebar Toggle -->
                <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-all hover:scale-105 hidden md:flex mr-4">
                    <i data-lucide="layout-sidebar" class="w-5 h-5 text-gray-700"></i>
                </button>

                
            </div>

            <!-- Right Section -->
            <div class="flex items-center gap-4">
                <!-- Time Display -->
                <div class="animate-fadeIn hidden md:block">
                    <span id="philippineTime" class="font-medium text-base text-gray-700"></span>
                </div>

                <!-- Notification Dropdown -->
                <div class="dropdown dropdown-end relative">
                    <!-- Bell Button -->
                    <button id="notification-button" tabindex="0"
                        class="p-2 rounded-lg relative hover:scale-105 transition-transform duration-200 hover:bg-gray-100">
                        <i data-lucide="bell" class="w-5 h-5 text-gray-700"></i>
                        <span id="notif-badge"
                            class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
                    </button>

                    <!-- Dropdown Content -->
                    <ul tabindex="0"
                        class="dropdown-content w-80 md:w-96 mt-3 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden transform transition-all duration-200 hidden">
                        
                        <!-- Header -->
                        <li
                            class="px-4 py-3 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white z-10">
                            <div class="flex items-center gap-2">
                                <i data-lucide="bell" class="w-5 h-5 text-[#001f54]"></i>
                                <span class="font-semibold text-gray-800 tracking-wide">Notifications</span>
                            </div>
                            <button
                                class="text-gray-600 hover:text-gray-800 text-xs flex items-center gap-1 transition-colors duration-150 hover:scale-105">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                <span>Clear All</span>
                            </button>
                        </li>

                        <!-- Notification Items -->
                        <div class="max-h-96 overflow-y-auto px-2 py-2 space-y-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 rounded-lg">
                            <div class="p-3 bg-gray-50 hover:bg-blue-50 rounded-xl transition-all duration-150 border border-gray-100">
                                <p class="text-sm text-gray-700"><span class="font-semibold text-[#001f54]">Budget Alert</span>: Marketing department exceeded Q1 budget by 15%.</p>
                                <span class="text-xs text-gray-500">2 hours ago</span>
                            </div>
                            <div class="p-3 bg-gray-50 hover:bg-blue-50 rounded-xl transition-all duration-150 border border-gray-100">
                                <p class="text-sm text-gray-700"><span class="font-semibold text-[#001f54]">New Proposal</span>: Operations submitted a new budget proposal.</p>
                                <span class="text-xs text-gray-500">1 day ago</span>
                            </div>
                            <div class="p-3 bg-gray-50 hover:bg-blue-50 rounded-xl transition-all duration-150 border border-gray-100">
                                <p class="text-sm text-gray-700"><span class="font-semibold text-[#001f54]">Collection Update</span>: ₱250,000 collected from Bohol tour packages.</p>
                                <span class="text-xs text-gray-500">2 days ago</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <li
                            class="px-4 py-3 border-t border-gray-200 sticky bottom-0 bg-white text-center">
                            <a href="<?php echo $base_url; ?>/notifications.php"
                                class="text-[#001f54] hover:text-blue-700 text-sm flex items-center justify-center gap-1 transition-colors duration-150 hover:scale-105">
                                <i data-lucide="list" class="w-4 h-4"></i>
                                <span>View All Notifications</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- User Profile -->
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-full bg-gradient-to-r from-[#001f54] to-[#0a2a5a] flex items-center justify-center">
                        <span class="text-white text-sm font-medium"><?php echo $user_initials; ?></span>
                    </div>
                    <span class="text-gray-700 font-medium hidden md:block"><?php echo $current_user; ?></span>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
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
        
        // Toggle overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.display = sidebar.classList.contains('translate-x-0') ? 'block' : 'none';
        }
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

        document.querySelectorAll('.sidebar-section').forEach(section => {
            section.classList.toggle('hidden', !currentlyCollapsed);
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
        const isOpen = icon.closest('.menu-dropdown')?.querySelector('.dropdown-content').style.maxHeight !== '0px';
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
        
        // Hide overlay
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    } else {
        const collapsedState = localStorage.getItem('sidebarCollapsed') === 'true';
        sidebar.classList.remove('-translate-x-full', 'translate-x-0');
        sidebar.classList.toggle('w-20', collapsedState);
        sidebar.classList.toggle('w-64', !collapsedState);

        document.querySelectorAll('.sidebar-text').forEach(text => {
            text.classList.toggle('hidden', collapsedState);
        });

        document.querySelectorAll('.sidebar-section').forEach(section => {
            section.classList.toggle('hidden', collapsedState);
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
    
    // Notification dropdown functionality
    const notificationButton = document.getElementById('notification-button');
    const notificationDropdown = document.querySelector('.dropdown-content');

    if (notificationButton && notificationDropdown) {
        notificationButton.addEventListener('click', function() {
            notificationDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!notificationButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
    }
});


</script>

<style>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 10px;
}

/* Mobile dropdown alignment fix */
@media (max-width: 767px) {
    .dropdown-content {
        left: 50% !important;
        transform: translateX(-80%) !important;
    }
}
</style>