<?php
// sidebar.php
$role = $_SESSION['role'] ?? 'guest';
// $permissions = include 'USM/role_permissions.php';
$allowed_modules = $permissions[$role] ?? [];
$is_supervisor = ($role === 'supervisor' || $role === 'admin');

// Define base path for consistent URL structure
$base_url = '/FINANCIALS/'; // Adjust to your project base URL
?>

<div class="bg-white pt-5 pb-4 flex flex-col fixed md:relative h-full transition-all duration-300 ease-in-out shadow-xl border-r border-gray-200 transform -translate-x-full md:transform-none md:translate-x-0 z-20 w-64 md:w-64" id="sidebar">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6">
        <div class="flex items-center gap-2">
            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-[#001f54] to-[#0a2a5a] flex items-center justify-center">
                <i data-lucide="plane" class="w-6 h-6 text-white"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800 sidebar-text" id="sidebar-logo">
                System name
            </h1>
            <h1 class="text-xl font-bold text-gray-800 hidden" id="sonly">
                TP
            </h1>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="flex-1 flex flex-col overflow-hidden hover:overflow-y-auto">
        <nav class="flex-1 px-2 space-y-1">
            <!-- DASHBOARD SECTION -->
            <div class="px-4 py-2 mt-2 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Dashboard</p>
            </div>
            <a href="<?php echo $base_url; ?>/dashboard.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="layout-dashboard" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Dashboard</span>
                </div>
            </a>

            <!-- FINANCIAL MANAGEMENT SECTION -->
            <?php if ($is_supervisor || in_array('financial_management', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Financial Management</p>
            </div>
            
            <!-- Budget Management Single Link -->
            <?php if ($is_supervisor || in_array('budget_management', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/BUDGET/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="pie-chart" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Budget Management</span>
                </div>
            </a>
            <?php endif; ?>
            
            <!-- Accounts Receivable -->
            <?php if ($is_supervisor || in_array('accounts_receivable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/RECEIVABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="trending-up" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Receivable</span>
                </div>
            </a>
            <?php endif; ?>
            
            <!-- Accounts Payable -->
            <?php if ($is_supervisor || in_array('accounts_payable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/PAYABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="trending-down" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Payable</span>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($is_supervisor || in_array('disbursements', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/DISBURSEMENT/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="wallet" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Disbursements</span>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($is_supervisor || in_array('collections', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/COLLECTION/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="credit-card" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Collections</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- General Ledger -->
            <?php if ($is_supervisor || in_array('general_ledger', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/LEDGER/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="book-open" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">General Ledger</span>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- TOUR OPERATIONS SECTION -->
            <?php if ($is_supervisor || in_array('tour_operations', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Tour Operations</p>
            </div>
            
            <?php if ($is_supervisor || in_array('tour_packages', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/tours/packages.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="map-pin" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Tour Packages</span>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if ($is_supervisor || in_array('bookings', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/tours/bookings.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="calendar" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Bookings</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- Customers -->
            <?php if ($is_supervisor || in_array('customers', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>/tours/customers.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="users" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Customers</span>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- ANALYTICS & REPORTING SECTION -->
            <?php if ($is_supervisor || in_array('analytics', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Analytics</p>
            </div>
            <a href="<?php echo $base_url; ?>/analytics/reports.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="bar-chart-2" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Reports</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- ADMINISTRATION SECTION -->
            <?php if ($is_supervisor || in_array('administration', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Administration</p>
            </div>
            
            <!-- User Management Dropdown -->
            <?php if ($is_supervisor || in_array('user_management', $allowed_modules)): ?>
            <div class="relative group menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                            <i data-lucide="users" class="w-5 h-5 text-[#001f54]"></i>
                        </div>
                        <span class="ml-3 sidebar-text">User management</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 group-hover:rotate-180 dropdown-arrow text-gray-500 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0 group-hover:max-h-96">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/admin/department-accounts.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-gray-900 group/item ml-8">
                            <i data-lucide="user-cog" class="w-4 h-4 mr-3 text-[#001f54]"></i>
                            <span class="sidebar-text">Department Accounts</span>
                        </a>
                       
                        <a href="<?php echo $base_url; ?>/admin/audit-trail.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-gray-900 group/item ml-8">
                            <i data-lucide="history" class="w-4 h-4 mr-3 text-[#001f54]"></i>
                            <span class="sidebar-text">Audit Trail</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Logout -->
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Account</p>
            </div>
            <form action="<?php echo $base_url; ?>/USM/logout.php" method="POST" class="px-4 py-3">
                <button type="submit" class="flex items-center w-full text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 group">
                    <div class="p-1.5 rounded-lg bg-blue-100 group-hover:bg-blue-200 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5 text-[#001f54]"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Logout</span>
                </button>
            </form>
        </nav>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-10 md:hidden" onclick="toggleSidebar()" style="display: none;"></div>

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

    /* Desktop styles */
    .w-20 .sidebar-text {
        display: none;
    }
    
    .w-20 .sidebar-section {
        display: none;
    }
    
    .w-20 .flex.items-center {
        justify-content: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .w-20 .dropdown-icon {
        display: none;
    }
    
    .w-20 .dropdown-content {
        display: none;
    }
    
    .w-20 .p-1.5.rounded-lg {
        margin-right: 0;
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
</style>