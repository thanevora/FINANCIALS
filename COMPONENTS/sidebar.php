<?php
// RBAC (Role-Based Access Control) Implementation - COMMENTED FOR TESTING
// ================================================
// To enable RBAC, uncomment the following lines and remove the test variables

// $role = $_SESSION['role'] ?? 'guest'; // Get user role from session
// $permissions = include 'USM/role_permissions.php'; // Load permissions config
// $allowed_modules = $permissions[$role] ?? []; // Get allowed modules
// $is_supervisor = ($role === 'supervisor' || $role === 'admin'); // Check supervisor

// TESTING/DEVELOPMENT SETTINGS - REMOVE THESE WHEN ENABLING RBAC
$allowed_modules = [
    'financial_management',
    'budget_management', 
    'receivable',
    'payable',
    'disbursements',
    'collections',
    'ledger',
    'administration',
    'user_management'
];
$is_supervisor = true; // Set to true to show all menu items for testing

// Define base path for consistent URL structure
$base_url = '/FINANCIALS/'; // Correct full URL

// Sidebar state from session (for expandable/collapsible sidebar feature)
$sidebar_collapsed = $_SESSION['sidebar_collapsed'] ?? false;
?>

<div class="bg-white pt-5 pb-4 flex flex-col fixed h-full transition-all duration-300 shadow-lg border-r z-20 w-64 md:<?php echo $sidebar_collapsed ? 'w-20' : 'w-64'; ?>" 
     id="sidebar"
     data-state="<?php echo $sidebar_collapsed ? 'collapsed' : 'expanded'; ?>">
    
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6">
        <div class="flex items-center gap-2">
            <div class="h-10 w-10 rounded-lg bg-blue-600 flex items-center justify-center">
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
            <!-- FINANCIAL MANAGEMENT SECTION -->
            <?php if ($is_supervisor || in_array('financial_management', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Financial Management</p>
            </div>
            
            <!-- Budget Management Dropdown -->
            <?php if ($is_supervisor || in_array('budget_management', $allowed_modules)): ?>
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                            <i data-lucide="pie-chart" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <span class="ml-3 sidebar-text">Budget Management</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-gray-400 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>BUDGET/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="pie-chart" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Main Budget Management</span>
                        </a>
                        <a href="<?php echo $base_url; ?>BUDGET/sub-modules/budget_allocating.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Budget Allocating</span>
                        </a>
                        <a href="<?php echo $base_url; ?>BUDGET/sub-modules/budget_proposal.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="file-text" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Budget Proposal</span>
                        </a>
                        <a href="<?php echo $base_url; ?>BUDGET/sub-modules/budget_transactions.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="credit-card" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Budget Transactions</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Accounts Receivable -->
            <?php if ($is_supervisor || in_array('receivable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>RECEIVABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group">
                    <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="trending-up" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Receivable</span>
                </div>
            </a>
            <?php endif; ?>
            
            <!-- Accounts Payable -->
            <?php if ($is_supervisor || in_array('payable', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>PAYABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group">
                    <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="trending-down" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Payable</span>
                </div>
            </a>
            <?php endif; ?>
            
            <!-- Disbursement Management Dropdown -->
            <?php if ($is_supervisor || in_array('disbursements', $allowed_modules)): ?>
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                            <i data-lucide="wallet" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <span class="ml-3 sidebar-text">Disbursements</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-gray-400 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>DISBURSEMENT/sub-modules/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Disbursement Overview</span>
                        </a>
                        <a href="<?php echo $base_url; ?>DISBURSEMENT/sub-modules/disburse_allocation.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Disburse Allocation</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Collections -->
            <?php if ($is_supervisor || in_array('collections', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>COLLECTION/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group">
                    <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="credit-card" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Collections</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- General Ledger -->
            <?php if ($is_supervisor || in_array('ledger', $allowed_modules)): ?>
            <a href="<?php echo $base_url; ?>LEDGER/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group">
                    <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="book-open" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="ml-3 sidebar-text">General Ledger</span>
                </div>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <!-- ADMINISTRATION SECTION -->
            <?php if ($is_supervisor || in_array('administration', $allowed_modules)): ?>
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Administration</p>
            </div>
            
            <!-- User Management Dropdown -->
            <?php if ($is_supervisor || in_array('user_management', $allowed_modules)): ?>
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                            <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <span class="ml-3 sidebar-text">User Management</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-gray-400 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>USM/department_accounts.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="user-cog" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Department Accounts</span>
                        </a>
                        <a href="<?php echo $base_url; ?>USM/audit_trail&transaction.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-blue-50 text-gray-600 hover:text-blue-600 group/item ml-8">
                            <i data-lucide="history" class="w-4 h-4 mr-3 text-blue-600"></i>
                            <span class="sidebar-text">Audit Trail & Transaction</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Logout Section -->
            <div class="px-4 py-2 mt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider sidebar-text">Account</p>
            </div>
            <form action="<?php echo $base_url; ?>USM/logout.php" method="POST" class="px-4 py-3">
                <button type="submit" class="flex items-center w-full text-sm font-medium rounded-lg transition-all hover:bg-blue-50 text-gray-700 hover:text-blue-600 group">
                    <div class="p-1.5 rounded-lg bg-blue-50 group-hover:bg-blue-100 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Logout</span>
                </button>
            </form>
        </nav>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-10 md:hidden" onclick="toggleSidebar()" style="display: none;"></div>

<script>
// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        overlay.style.display = 'block';
    } else {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        overlay.style.display = 'none';
    }
}

// Clickable dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.menu-dropdown');
            const content = dropdown.querySelector('.dropdown-content');
            const arrow = dropdown.querySelector('.dropdown-arrow');
            const isOpen = content.style.maxHeight !== '0px' && content.style.maxHeight !== '';
            
            // Close all other dropdowns
            document.querySelectorAll('.menu-dropdown').forEach(otherDropdown => {
                if (otherDropdown !== dropdown) {
                    const otherContent = otherDropdown.querySelector('.dropdown-content');
                    const otherArrow = otherDropdown.querySelector('.dropdown-arrow');
                    otherContent.style.maxHeight = '0px';
                    otherArrow.style.transform = 'rotate(0deg)';
                    otherArrow.style.transition = 'transform 0.3s ease';
                    otherDropdown.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            if (isOpen) {
                content.style.maxHeight = '0px';
                arrow.style.transform = 'rotate(0deg)';
                dropdown.classList.remove('active');
            } else {
                content.style.maxHeight = content.scrollHeight + 'px';
                arrow.style.transform = 'rotate(180deg)';
                dropdown.classList.add('active');
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.menu-dropdown')) {
            document.querySelectorAll('.menu-dropdown').forEach(dropdown => {
                const content = dropdown.querySelector('.dropdown-content');
                const arrow = dropdown.querySelector('.dropdown-arrow');
                content.style.maxHeight = '0px';
                arrow.style.transform = 'rotate(0deg)';
                arrow.style.transition = 'transform 0.3s ease';
                dropdown.classList.remove('active');
            });
        }
    });
    
    // Close sidebar when clicking on a link (mobile)
    const sidebarLinks = document.querySelectorAll('#sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                toggleSidebar();
            }
        });
    });

    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<style>
    /* Mobile styles */
    @media (max-width: 767px) {
        #sidebar {
            z-index: 40;
            width: 16rem;
            left: 0;
            top: 0;
            bottom: 0;
            transition: transform 0.3s ease;
        }
        
        #sidebar.translate-x-0 {
            transform: translateX(0);
        }
        
        #sidebar.-translate-x-full {
            transform: translateX(-100%);
        }
    }

    /* Desktop collapsed styles */
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
    
    /* Dropdown animations */
    .dropdown-content {
        transition: max-height 0.3s ease;
    }
    
    /* Active dropdown styles */
    .menu-dropdown.active .dropdown-toggle {
        background-color: #eff6ff;
    }
    
    .menu-dropdown.active .dropdown-toggle .dropdown-arrow {
        transform: rotate(180deg);
    }
    
    /* Cursor pointer for dropdown toggles */
    .dropdown-toggle {
        cursor: pointer;
    }
</style>