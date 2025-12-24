<?php
// sidebar.php
// $role = $_SESSION['role'] ?? 'guest';
// $permissions = include 'USM/role_permissions.php';
// $allowed_modules = $permissions[$role] ?? [];
// $is_supervisor = ($role === 'supervisor' || $role === 'admin');

// Define base path for consistent URL structure
$base_url = '/FINANCIALS/'; // Adjust to your project base URL

// Sidebar state from session
$sidebar_collapsed = $_SESSION['sidebar_collapsed'] ?? false;
?>

<div class="bg-sidebar border-sidebar-border pt-5 pb-4 flex flex-col fixed md:relative h-full transition-all duration-300 ease-in-out shadow-xl border-r z-20 w-64 md:<?php echo $sidebar_collapsed ? 'w-20' : 'w-64'; ?>" 
     id="sidebar"
     data-state="<?php echo $sidebar_collapsed ? 'collapsed' : 'expanded'; ?>">
    
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6">
        <div class="flex items-center gap-2">
            <div class="h-10 w-10 rounded-lg bg-gradient-to-r from-sidebar-primary to-sidebar-primary/80 flex items-center justify-center">
                <i data-lucide="plane" class="w-6 h-6 text-sidebar-primary-foreground"></i>
            </div>
            <h1 class="text-xl font-bold text-sidebar-foreground sidebar-text" id="sidebar-logo">
                System name
            </h1>
            <h1 class="text-xl font-bold text-sidebar-foreground hidden" id="sonly">
                TP
            </h1>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="flex-1 flex flex-col overflow-hidden hover:overflow-y-auto">
        <nav class="flex-1 px-2 space-y-1">
            

            <!-- FINANCIAL MANAGEMENT SECTION -->
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-sidebar-foreground/70 uppercase tracking-wider sidebar-text">Financial Management</p>
            </div>
            
            <!-- Budget Management Dropdown -->
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                            <i data-lucide="pie-chart" class="w-5 h-5 text-sidebar-primary"></i>
                        </div>
                        <span class="ml-3 sidebar-text">Budget Management</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-sidebar-foreground/70 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/BUDGET/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="pie-chart" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Main Budget Management</span>
                        </a>
                        <a href="<?php echo $base_url; ?>/BUDGET/sub - modules/budget_monitoring.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Budget Monitoring</span>
                        </a>
                        <a href="<?php echo $base_url; ?>/BUDGET/sub - modules/budget_allocating.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Budget Allocating</span>
                        </a>
                        <a href="<?php echo $base_url; ?>/BUDGET/sub - modules/budget_proposal.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="file-text" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Budget Proposal</span>
                        </a>
                        <a href="<?php echo $base_url; ?>/BUDGET/sub - modules/budget_transactions.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="credit-card" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Budget Transactions</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Accounts Receivable -->
            <a href="<?php echo $base_url; ?>/RECEIVABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group">
                    <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                        <i data-lucide="trending-up" class="w-5 h-5 text-sidebar-primary"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Receivable</span>
                </div>
            </a>
            
            <!-- Accounts Payable -->
            <a href="<?php echo $base_url; ?>/PAYABLE/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group">
                    <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                        <i data-lucide="trending-down" class="w-5 h-5 text-sidebar-primary"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Accounts Payable</span>
                </div>
            </a>
            
            <!-- Disbursement Management Dropdown -->
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                            <i data-lucide="wallet" class="w-5 h-5 text-sidebar-primary"></i>
                        </div>
                        <span class="ml-3 sidebar-text">Disbursements</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-sidebar-foreground/70 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/DISBURSEMENT/sub-modules/main.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Disbursement Overview</span>
                        </a>
                        <a href="<?php echo $base_url; ?>/DISBURSEMENT/sub-modules/disburse_allocation.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Disburse Allocation</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <a href="<?php echo $base_url; ?>/COLLECTION/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group">
                    <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                        <i data-lucide="credit-card" class="w-5 h-5 text-sidebar-primary"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Collections</span>
                </div>
            </a>

            <!-- General Ledger -->
            <a href="<?php echo $base_url; ?>/LEDGER/main.php" class="block">
                <div class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group">
                    <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                        <i data-lucide="book-open" class="w-5 h-5 text-sidebar-primary"></i>
                    </div>
                    <span class="ml-3 sidebar-text">General Ledger</span>
                </div>
            </a>

           

            <!-- ADMINISTRATION SECTION -->
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-sidebar-foreground/70 uppercase tracking-wider sidebar-text">Administration</p>
            </div>
            
            <!-- User Management Dropdown -->
            <div class="relative menu-dropdown">
                <button class="flex items-center justify-between w-full px-4 py-3 text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group dropdown-toggle">
                    <div class="flex items-center">
                        <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                            <i data-lucide="users" class="w-5 h-5 text-sidebar-primary"></i>
                        </div>
                        <span class="ml-3 sidebar-text">User management</span>
                    </div>
                    <i data-lucide="chevron-down" class="w-4 h-4 ml-auto transition-transform duration-200 dropdown-arrow text-sidebar-foreground/70 dropdown-icon"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="dropdown-content overflow-hidden transition-all duration-300 max-h-0">
                    <div class="py-2 space-y-1">
                        <a href="<?php echo $base_url; ?>/admin/department-accounts.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="user-cog" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Department Accounts</span>
                        </a>
                       
                        <a href="<?php echo $base_url; ?>/admin/audit-trail.php" class="flex items-center px-4 py-2 text-sm rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground/70 hover:text-sidebar-accent-foreground group/item ml-8">
                            <i data-lucide="history" class="w-4 h-4 mr-3 text-sidebar-primary"></i>
                            <span class="sidebar-text">Audit Trail</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <div class="px-4 py-2 mt-4 sidebar-section">
                <p class="text-xs font-semibold text-sidebar-foreground/70 uppercase tracking-wider sidebar-text">Account</p>
            </div>
            <form action="<?php echo $base_url; ?>/USM/logout.php" method="POST" class="px-4 py-3">
                <button type="submit" class="flex items-center w-full text-sm font-medium rounded-lg transition-all hover:bg-sidebar-accent text-sidebar-foreground hover:text-sidebar-accent-foreground group">
                    <div class="p-1.5 rounded-lg bg-sidebar-primary/10 group-hover:bg-sidebar-primary/20 transition-colors">
                        <i data-lucide="log-out" class="w-5 h-5 text-sidebar-primary"></i>
                    </div>
                    <span class="ml-3 sidebar-text">Logout</span>
                </button>
            </form>
        </nav>
    </div>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-10 md:hidden" onclick="toggleSidebar()" style="display: none;"></div>

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
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    #sidebar::-webkit-scrollbar {
        display: none;
    }
    
    /* Only show scrollbar on hover */
    .overflow-hidden {
        overflow: hidden;
    }
    
    .hover\:overflow-y-auto:hover {
        overflow-y: auto;
    }

    /* Smooth transitions */
    #sidebar {
        transition: all 0.3s ease;
    }

    /* Dropdown animations */
    .dropdown-content {
        transition: max-height 0.3s ease;
    }
    
    /* Active dropdown styles */
    .menu-dropdown.active .dropdown-toggle {
        background-color: rgba(59, 130, 246, 0.1);
    }
    
    .menu-dropdown.active .dropdown-toggle .dropdown-arrow {
        transform: rotate(180deg);
    }
    
    /* Cursor pointer for dropdown toggles */
    .dropdown-toggle {
        cursor: pointer;
    }

    /* Sidebar color variables */
    .bg-sidebar {
        background-color: #001f54;
    }
    
    .border-sidebar-border {
        border-color: rgba(255, 255, 255, 0.1);
    }
    
    .text-sidebar-foreground {
        color: white;
    }
    
    .text-sidebar-foreground\/70 {
        color: rgba(255, 255, 255, 0.7);
    }
    
    .bg-sidebar-primary {
        background-color: #F7B32B;
    }
    
    .text-sidebar-primary {
        color: #F7B32B;
    }
    
    .text-sidebar-primary-foreground {
        color: #001f54;
    }
    
    .hover\:bg-sidebar-accent:hover {
        background-color: rgba(247, 179, 43, 0.1);
    }
    
    .hover\:text-sidebar-accent-foreground:hover {
        color: #F7B32B;
    }
</style>