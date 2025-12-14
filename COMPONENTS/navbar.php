<?php
// navbar.php
$current_user = $_SESSION['user_name'] ?? 'User';
$user_initials = strtoupper(substr($current_user, 0, 2));
$base_url = '/FINANCIALS/'; // Adjust to your project base URL
?>

<header class="bg-card shadow-sm z-10 border-b border-border">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Left Section -->
            <div class="flex items-center">
                <!-- Mobile Sidebar Toggle -->
                <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-accent transition-all hover:scale-105 md:hidden mr-4">
                    <i data-lucide="menu" class="w-5 h-5 text-foreground"></i>
                </button>

                <!-- Desktop Sidebar Toggle -->
                <form method="POST" action="<?php echo $base_url; ?>/toggle-sidebar.php" class="hidden md:block">
                    <button type="submit" class="p-2 rounded-lg hover:bg-accent transition-all hover:scale-105 mr-4">
                        <i data-lucide="layout-sidebar" class="w-5 h-5 text-foreground"></i>
                    </button>
                </form>

                <!-- Theme Toggle -->
                <form method="POST" action="<?php echo $base_url; ?>/toggle-theme.php" class="hidden md:block">
                    <button type="submit" class="p-2 rounded-lg hover:bg-accent transition-all hover:scale-105">
                        <i data-lucide="<?php echo isset($_SESSION['dark_mode']) ? 'sun' : 'moon'; ?>" class="w-5 h-5 text-foreground"></i>
                    </button>
                </form>
            </div>

            <!-- Right Section -->
            <div class="flex items-center gap-4">
                <!-- Time Display -->
                <div class="animate-fadeIn hidden md:block">
                    <span id="philippineTime" class="font-medium text-base text-foreground"></span>
                </div>

                <!-- Notification Dropdown -->
                <div class="dropdown dropdown-end relative">
                    <!-- Bell Button -->
                    <button id="notification-button" tabindex="0"
                        class="p-2 rounded-lg relative hover:scale-105 transition-transform duration-200 hover:bg-accent">
                        <i data-lucide="bell" class="w-5 h-5 text-foreground"></i>
                        <span id="notif-badge"
                            class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-destructive rounded-full animate-pulse"></span>
                    </button>

                    <!-- Dropdown Content -->
                    <ul tabindex="0"
                        class="dropdown-content w-80 md:w-96 mt-3 bg-card rounded-lg shadow-xl border border-border overflow-hidden transform transition-all duration-200 hidden">
                        
                        <!-- Header -->
                        <li
                            class="px-4 py-3 border-b border-border flex justify-between items-center sticky top-0 bg-card z-10">
                            <div class="flex items-center gap-2">
                                <i data-lucide="bell" class="w-5 h-5 text-primary"></i>
                                <span class="font-semibold text-card-foreground tracking-wide">Notifications</span>
                            </div>
                            <button
                                class="text-muted-foreground hover:text-card-foreground text-xs flex items-center gap-1 transition-colors duration-150 hover:scale-105">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                <span>Clear All</span>
                            </button>
                        </li>

                        <!-- Notification Items -->
                        <div class="max-h-96 overflow-y-auto px-2 py-2 space-y-2 scrollbar-thin scrollbar-thumb-border scrollbar-track-muted rounded-lg">
                            <div class="p-3 bg-muted hover:bg-accent rounded-xl transition-all duration-150 border border-border">
                                <p class="text-sm text-card-foreground"><span class="font-semibold text-primary">Budget Alert</span>: Marketing department exceeded Q1 budget by 15%.</p>
                                <span class="text-xs text-muted-foreground">2 hours ago</span>
                            </div>
                            <div class="p-3 bg-muted hover:bg-accent rounded-xl transition-all duration-150 border border-border">
                                <p class="text-sm text-card-foreground"><span class="font-semibold text-primary">New Proposal</span>: Operations submitted a new budget proposal.</p>
                                <span class="text-xs text-muted-foreground">1 day ago</span>
                            </div>
                            <div class="p-3 bg-muted hover:bg-accent rounded-xl transition-all duration-150 border border-border">
                                <p class="text-sm text-card-foreground"><span class="font-semibold text-primary">Collection Update</span>: ₱250,000 collected from Bohol tour packages.</p>
                                <span class="text-xs text-muted-foreground">2 days ago</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <li
                            class="px-4 py-3 border-t border-border sticky bottom-0 bg-card text-center">
                            <a href="<?php echo $base_url; ?>/notifications.php"
                                class="text-primary hover:text-primary/80 text-sm flex items-center justify-center gap-1 transition-colors duration-150 hover:scale-105">
                                <i data-lucide="list" class="w-4 h-4"></i>
                                <span>View All Notifications</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- User Profile -->
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-full bg-gradient-to-r from-primary to-primary/80 flex items-center justify-center">
                        <span class="text-primary-foreground text-sm font-medium"><?php echo $user_initials; ?></span>
                    </div>
                    <span class="text-card-foreground font-medium hidden md:block"><?php echo $current_user; ?></span>
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
        // Desktop toggle - submit form via PHP instead
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo $base_url; ?>/toggle-sidebar.php';
        
        // Add CSRF token if needed
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo $_SESSION["csrf_token"] ?? ""; ?>';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
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
        // Load state from PHP session
        const collapsedState = <?php echo ($_SESSION['sidebar_collapsed'] ?? false) ? 'true' : 'false'; ?>;
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

// Philippine Time Display
function updatePhilippineTime() {
    const now = new Date();
    const options = { 
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    const phTime = now.toLocaleString('en-PH', options);
    document.getElementById('philippineTime').textContent = phTime;
}

// Apply initial state
document.addEventListener('DOMContentLoaded', () => {
    handleResize();
    window.addEventListener('resize', handleResize);
    
    // Initialize time display
    updatePhilippineTime();
    setInterval(updatePhilippineTime, 1000);
    
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

    // Close sidebar when clicking on overlay (mobile)
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (isMobileView()) {
                toggleSidebar();
            }
        });
    }
});

// Keyboard shortcut for sidebar (B key with Ctrl/Cmd)
document.addEventListener('keydown', function(event) {
    if ((event.ctrlKey || event.metaKey) && event.key === 'b') {
        event.preventDefault();
        toggleSidebar();
    }
});
</script>

<style>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: hsl(var(--border));
    border-radius: 10px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background-color: hsl(var(--muted));
}

/* Mobile dropdown alignment fix */
@media (max-width: 767px) {
    .dropdown-content {
        left: 50% !important;
        transform: translateX(-80%) !important;
    }
}

/* Smooth transitions */
header {
    transition: all 0.3s ease;
}

.animate-fadeIn {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>