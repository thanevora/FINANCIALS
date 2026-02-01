<?php
// RBAC (Role-Based Access Control) Implementation
$role = $_SESSION['role'] ?? 'guest';
$permissions = include '../USM/role_permissions.php';
$allowed_modules = $permissions[$role] ?? [];

// Define base path for consistent URL structure
$base_url = '/FINANCIALS'; // Adjust this to your actual base path

// Check for privileged roles (supervisor, manager, admin, security)
$privileged_roles = ['supervisor', 'manager', 'admin', 'security'];
$is_privileged_user = in_array($role, $privileged_roles);

// Check if sidebar is collapsed from session
$sidebar_collapsed = isset($_SESSION['sidebar_collapsed']) ? $_SESSION['sidebar_collapsed'] : false;
?>

<div class="bg-[#001f54] pt-5 pb-4 flex flex-col fixed md:relative h-full transition-all duration-300 ease-in-out shadow-xl -translate-x-full md:translate-x-0 <?php echo $sidebar_collapsed ? 'w-20' : 'w-64'; ?>" id="sidebar">
    <!-- Sidebar Header with Logos -->
    <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6 text-center">
        <img src="<?php echo $base_url; ?>/images/full_logo.png" 
            alt="Full Logo" 
            class="h-16 w-auto <?php echo !$sidebar_collapsed ? '' : 'hidden'; ?>"
            id="sidebar-logo">
        <img src="<?php echo $base_url; ?>/images/icon_logo.png" 
            alt="Icon Logo" 
            class="h-10 w-auto <?php echo $sidebar_collapsed ? '' : 'hidden'; ?>"
            id="icon-logo">
    </div>

    <!-- Navigation Menu -->
    <div class="flex-1 flex flex-col overflow-hidden hover:overflow-y-auto">
        <nav class="flex-1 px-2 space-y-1">
            <!-- ANALYTICS & REPORTING SECTION -->
            <?php if ($is_privileged_user || in_array('analytics', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-2">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Analytics & Reporting</p>
            </div>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>/Analytics/Analytics_metabase.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="bar-chart-2" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Financial Reports & Analytics</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- BUDGET MANAGEMENT SECTION -->
            <?php if ($is_privileged_user || in_array('budget_management', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Budget Management</p>
            </div>
            <?php endif; ?>
            
            <!-- Budget Management Dropdown -->
            <div class="menu-dropdown">
                <button type="button" class="dropdown-toggle flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                            <i data-lucide="pie-chart" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                        </div>
                        <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Budget Management</span>
                    </div>
                    <?php if(!$sidebar_collapsed): ?>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-icon dropdown-arrow"></i>
                    <?php endif; ?>
                </button>
                
                <!-- Dropdown Menu -->
                <?php if(!$sidebar_collapsed): ?>
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/BUDGET/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item ml-8">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                            <span class="sidebar-text">Main Budget Management</span>
                        </a>
                        
                        <a href="<?php echo $base_url; ?>/BUDGET/sub-modules/budget_allocating.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                            <span class="sidebar-text">Budget Allocating</span>
                        </a>
                        
                        <a href="<?php echo $base_url; ?>/BUDGET/sub-modules/budget_proposal.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item ml-8">
                            <i data-lucide="file-text" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                            <span class="sidebar-text">Budget Proposal</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ACCOUNTS MANAGEMENT SECTION -->
            <?php if ($is_privileged_user || in_array('receivable', $allowed_modules) || in_array('payable', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Accounts Management</p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_privileged_user || in_array('receivable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/RECEIVABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="trending-up" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Accounts Receivable</span>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($is_privileged_user || in_array('payable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/PAYABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="trending-down" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Accounts Payable</span>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- DISBURSEMENTS & COLLECTIONS SECTION -->
            <?php if ($is_privileged_user || in_array('disbursements', $allowed_modules) || in_array('collections', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Disbursements & Collections</p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_privileged_user || in_array('disbursements', $allowed_modules)): ?>
            <!-- Disbursements Dropdown -->
            <div class="menu-dropdown">
                <button type="button" class="dropdown-toggle flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                            <i data-lucide="wallet" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                        </div>
                        <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Disbursements</span>
                    </div>
                    <?php if(!$sidebar_collapsed): ?>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-icon dropdown-arrow"></i>
                    <?php endif; ?>
                </button>
                
                <!-- Dropdown Menu -->
                <?php if(!$sidebar_collapsed): ?>
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/DISBURSEMENT/sub-modules/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item ml-8">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                            <span class="sidebar-text">Disbursement Overview</span>
                        </a>
                        
                        <a href="<?php echo $base_url; ?>/DISBURSEMENT/sub-modules/disburse_allocation.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                            <span class="sidebar-text">Disburse Allocation</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($is_privileged_user || in_array('collections', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/COLLECTION/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="credit-card" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Collections</span>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- GENERAL LEDGER SECTION -->
            <?php if ($is_privileged_user || in_array('ledger', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Financial Records</p>
            </div>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>/LEDGER/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="book-open" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">General Ledger</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- ADMINISTRATION SECTION -->
            <?php if ($is_privileged_user || in_array('administration', $allowed_modules) || in_array('user_management', $allowed_modules)): ?>
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Administration</p>
            </div>
            <?php endif; ?>
            
            <?php if ($is_privileged_user || in_array('user_management', $allowed_modules)): ?>
            <!-- User Management Dropdown -->
            <div class="menu-dropdown">
                <button type="button" class="dropdown-toggle flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                            <i data-lucide="users" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                        </div>
                        <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">User Management</span>
                    </div>
                    <?php if(!$sidebar_collapsed): ?>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-icon dropdown-arrow"></i>
                    <?php endif; ?>
                </button>
                
                <!-- Dropdown Menu -->
                <?php if(!$sidebar_collapsed): ?>
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <!-- Profile Management Section -->
                        <div class="px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-300 mb-2 flex items-center gap-2">
                                <i data-lucide="user-circle" class="w-3 h-3"></i>
                                Profile Management
                            </p>
                            <div class="space-y-1 ml-4">
                                <a href="<?php echo $base_url; ?>/USM/profile.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item">
                                    <i data-lucide="settings" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                                    <span class="sidebar-text">Profile Settings</span>
                                </a>
                            </div>
                        </div>

                        <!-- System Management Section -->
                        <div class="px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-300 mb-2 flex items-center gap-2">
                                <i data-lucide="shield" class="w-3 h-3"></i>
                                Sub-user Management
                            </p>
                            <div class="space-y-1 ml-4">
                                <a href="<?php echo $base_url; ?>/USM/department_accounts.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item">
                                    <i data-lucide="user-cog" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                                    <span class="sidebar-text">Department Accounts</span>
                                </a>
                                
                                <!-- Login Logs -->
                                <a href="<?php echo $base_url; ?>/USM/department_logs.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item">
                                    <i data-lucide="key" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                                    <span class="sidebar-text">Login Logs</span>
                                </a>
                               
                                <a href="<?php echo $base_url; ?>/USM/audit_trail&transaction.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-600/30 text-blue-100 hover:text-white group/item">
                                    <i data-lucide="history" class="w-4 h-4 mr-3 text-[#F7B32B] group-hover/item:text-white"></i>
                                    <span class="sidebar-text">Audit Trail & Transaction</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Logout -->
            <?php if(!$sidebar_collapsed): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider sidebar-text">Account</p>
            </div>
            <?php endif; ?>
            <form action="<?php echo $base_url; ?>/USM/logout.php" method="POST" class="px-4 py-3">
                <button type="submit" class="flex items-center w-full text-sm font-medium rounded-lg transition-all hover:bg-blue-600/50 text-white group">
                    <div class="p-1.5 rounded-lg bg-blue-800/30 group-hover:bg-blue-700/50 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5 text-[#F7B32B] group-hover:text-white"></i>
                    </div>
                    <span class="ml-3 sidebar-text <?php echo $sidebar_collapsed ? 'hidden' : ''; ?>">Logout</span>
                </button>
            </form>
        </nav>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<style>
/* Mobile styles */
@media (max-width: 767px) {
    #sidebar {
        z-index: 40;
        width: 16rem; /* w-64 equivalent */
        left: 0;
        top: 0;
        bottom: 0;
        transition: transform 0.1s ease;
        background: #001f54;
    }
    
    #sidebar.translate-x-0 {
        transform: translateX(0);
    }
    
    #sidebar.-translate-x-full {
        transform: translateX(-100%);
    }
    
    /* Optional overlay */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: rgba(0,0,0,0.5);
        z-index: 30;
        display: none;
    }
    
    #sidebar.translate-x-0 + .sidebar-overlay {
        display: block;
    }
}

/* Desktop collapsed styles */
#sidebar.w-20 .sidebar-text,
#sidebar.w-20 .dropdown-content,
#sidebar.w-20 .text-xs.uppercase,
#sidebar.w-20 .dropdown-arrow {
    display: none !important;
}

#sidebar.w-20 .flex.items-center {
    justify-content: center;
}

#sidebar.w-20 .p-1.5.rounded-lg {
    margin-right: 0;
}

#sidebar.w-20 .px-4 {
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
}

#sidebar.w-20 .dropdown-toggle {
    justify-content: center !important;
}

#sidebar.w-20 .group {
    justify-content: center !important;
}

/* Hide scrollbar but keep scrolling */
#sidebar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}

#sidebar::-webkit-scrollbar {
    display: none;  /* Chrome, Safari and Opera */
}

/* Only show scrollbar on hover */
.overflow-hidden {
    overflow: hidden;
}

.hover\:overflow-y-auto:hover {
    overflow-y: auto;
}

/* SweetAlert Fix */
.swal2-styled {
  opacity: 1 !important;
  visibility: visible !important;
  color: #fff !important;
  background-color: #3085d6 !important;
  transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
}

.swal2-styled:hover {
  background-color: #2563eb !important; /* Tailwind's blue-600 */
  transform: scale(1.03);
}

.swal2-cancel {
  background-color: #e5e7eb !important; /* neutral-200 */
  color: #111 !important;
}
.swal2-cancel:hover {
  background-color: #d1d5db !important; /* neutral-300 */
}

/* Remove all SweetAlert hover effects */
.swal2-confirm:hover,
.swal2-cancel:hover,
.swal2-deny:hover {
    transform: none !important;
    box-shadow: none !important;
    background-color: inherit !important;
    filter: none !important;
}

/* Remove transitions */
.swal2-confirm,
.swal2-cancel,
.swal2-deny {
    transition: none !important;
}

/* Remove focus effects if desired */
.swal2-confirm:focus,
.swal2-cancel:focus,
.swal2-deny:focus {
    box-shadow: none !important;
}

/* Smooth dropdown animations */
.dropdown-content {
    transition: max-height 0.3s ease-in-out, opacity 0.2s ease-in-out;
    max-height: 0;
    opacity: 0;
}

.menu-dropdown.active .dropdown-content {
    max-height: 500px !important;
    opacity: 1;
}

.menu-dropdown.active .dropdown-arrow {
    transform: rotate(180deg);
}

/* Active state for dropdown parent */
.menu-dropdown.active .dropdown-toggle {
    background: rgba(59, 130, 246, 0.5) !important;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .menu-dropdown .dropdown-content {
        max-height: 300px;
        overflow-y: auto;
    }
}

/* Active link styling */
nav a.active .dropdown-toggle,
nav a.active > div {
    background: rgba(59, 130, 246, 0.5) !important;
}

nav a.active .dropdown-toggle i,
nav a.active > div i {
    color: #2563eb !important;
}

/* Improved hover effects */
.dropdown-content a:hover {
    background: rgba(59, 130, 246, 0.4);
    transform: translateX(2px);
    transition: all 0.2s ease;
}
</style>

<script>
// Initialize lucide icons
lucide.createIcons();

function isMobileView() {
    return window.innerWidth < 768; // Tailwind's md breakpoint
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarLogo = document.getElementById('sidebar-logo');
    const iconLogo = document.getElementById('icon-logo');

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
        const newCollapsedState = !currentlyCollapsed;
        
        // Toggle width classes
        sidebar.classList.toggle('w-20', newCollapsedState);
        sidebar.classList.toggle('w-64', !newCollapsedState);

        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', newCollapsedState);

        // Send AJAX request to update session
        updateSidebarState(newCollapsedState);
        
        // Update text visibility
        document.querySelectorAll('.sidebar-text').forEach(text => {
            text.classList.toggle('hidden', newCollapsedState);
        });
        
        // Hide/show dropdown arrows and section headers when collapsed
        document.querySelectorAll('.dropdown-arrow, .text-xs.uppercase').forEach(el => {
            el.classList.toggle('hidden', newCollapsedState);
        });
        
        // Hide dropdown content when sidebar is collapsed
        if (newCollapsedState) {
            document.querySelectorAll('.dropdown-content').forEach(content => {
                content.style.maxHeight = '0';
                content.style.opacity = '0';
            });
            document.querySelectorAll('.menu-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
        
        // Toggle logos
        if (newCollapsedState) {
            sidebarLogo.classList.add('hidden');
            iconLogo.classList.remove('hidden');
        } else {
            sidebarLogo.classList.remove('hidden');
            iconLogo.classList.add('hidden');
        }
    }

    updateDropdownIndicators();
    updateNavbarToggleIcon();
}

function updateSidebarState(collapsedState) {
    // Send AJAX request to update PHP session
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $base_url; ?>/USM/toggle-sidebar.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            console.log('Sidebar state updated');
        }
    };
    xhr.send('collapsed=' + collapsedState);
}

function updateDropdownIndicators() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = sidebar.classList.contains('w-20') && !isMobileView();

    document.querySelectorAll('.dropdown-icon').forEach(icon => {
        const parentDropdown = icon.closest('.menu-dropdown');
        const isOpen = parentDropdown ? parentDropdown.classList.contains('active') : false;
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
    const iconLogo = document.getElementById('icon-logo');

    if (isMobileView()) {
        // Reset to mobile closed state
        sidebar.classList.remove('w-64', 'w-20');
        sidebar.classList.add('-translate-x-full');
        sidebarLogo.classList.remove('hidden');
        iconLogo.classList.add('hidden');
    } else {
        // Load state from localStorage
        const collapsedState = localStorage.getItem('sidebarCollapsed') === 'true';
        sidebar.classList.remove('-translate-x-full', 'translate-x-0');
        sidebar.classList.toggle('w-20', collapsedState);
        sidebar.classList.toggle('w-64', !collapsedState);

        document.querySelectorAll('.sidebar-text').forEach(text => {
            text.classList.toggle('hidden', collapsedState);
        });
        
        // Hide/show dropdown arrows and section headers
        document.querySelectorAll('.dropdown-arrow, .text-xs.uppercase').forEach(el => {
            el.classList.toggle('hidden', collapsedState);
        });
        
        // Toggle logos
        if (collapsedState) {
            sidebarLogo.classList.add('hidden');
            iconLogo.classList.remove('hidden');
        } else {
            sidebarLogo.classList.remove('hidden');
            iconLogo.classList.add('hidden');
        }
    }

    updateDropdownIndicators();
}

// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    lucide.createIcons();
    
    // Handle dropdown click events
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const isCollapsed = sidebar.classList.contains('w-20') && !isMobileView();
            
            // Don't open dropdowns when sidebar is collapsed
            if (isCollapsed) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const parentDropdown = this.closest('.menu-dropdown');
            const content = parentDropdown.querySelector('.dropdown-content');
            const arrow = this.querySelector('.dropdown-arrow');
            
            // Check if this dropdown is currently open
            const isCurrentlyOpen = parentDropdown.classList.contains('active');
            
            // Close all other dropdowns first
            document.querySelectorAll('.menu-dropdown.active').forEach(otherDropdown => {
                if (otherDropdown !== parentDropdown) {
                    otherDropdown.classList.remove('active');
                    const otherContent = otherDropdown.querySelector('.dropdown-content');
                    const otherArrow = otherDropdown.querySelector('.dropdown-arrow');
                    otherContent.style.maxHeight = '0';
                    otherContent.style.opacity = '0';
                    if (otherArrow) otherArrow.style.transform = '';
                }
            });
            
            // Toggle current dropdown
            if (!isCurrentlyOpen) {
                parentDropdown.classList.add('active');
                content.style.maxHeight = content.scrollHeight + 'px';
                content.style.opacity = '1';
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            } else {
                parentDropdown.classList.remove('active');
                content.style.maxHeight = '0';
                content.style.opacity = '0';
                if (arrow) arrow.style.transform = '';
            }
            
            updateDropdownIndicators();
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.menu-dropdown')) {
            document.querySelectorAll('.menu-dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
                const content = dropdown.querySelector('.dropdown-content');
                const arrow = dropdown.querySelector('.dropdown-arrow');
                content.style.maxHeight = '0';
                content.style.opacity = '0';
                if (arrow) arrow.style.transform = '';
            });
            updateDropdownIndicators();
        }
    });
    
    // Highlight current page in sidebar
    const currentPath = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('nav a[href]');
    
    sidebarLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (currentPath.includes(linkPath) && linkPath !== '/') {
            link.classList.add('active');
            
            // If it's in a dropdown, expand the parent dropdown
            const dropdown = link.closest('.menu-dropdown');
            if (dropdown) {
                const isCollapsed = document.getElementById('sidebar').classList.contains('w-20') && !isMobileView();
                if (!isCollapsed) {
                    dropdown.classList.add('active');
                    const content = dropdown.querySelector('.dropdown-content');
                    const arrow = dropdown.querySelector('.dropdown-arrow');
                    content.style.maxHeight = content.scrollHeight + 'px';
                    content.style.opacity = '1';
                    if (arrow) arrow.style.transform = 'rotate(180deg)';
                }
            }
        }
    });
    
    // Apply initial state
    handleResize();
    window.addEventListener('resize', handleResize);
    
    // Mark sidebar as loaded for fade-in effect
    setTimeout(() => {
        document.getElementById('sidebar').classList.add('loaded');
    }, 100);
});
</script>
