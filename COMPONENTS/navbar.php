<header class="bg-white shadow-sm z-10 border-b border-gray-200">
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <!-- Sidebar Toggle -->
      <div class="flex items-center">
        <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-gray-100 transition-all" id="sidebar-toggle">
          <i data-lucide="menu" class="w-5 h-5 text-gray-700"></i>
        </button>
      </div>

      <!-- Right Section -->
      <div class="flex items-center gap-4">
        <!-- Time Display -->
        <div>
          <span id="philippineTime" class="font-medium text-base text-gray-700"></span>
        </div>

        <!-- Notification Dropdown -->
        <div class="dropdown dropdown-end relative">
          <!-- Bell Button -->
          <button id="notification-button" tabindex="0"
            class="p-2 rounded-lg hover:bg-gray-100 relative transition-all">
            <i data-lucide="bell" class="w-5 h-5 text-gray-700"></i>
            <span id="notif-badge"
              class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
          </button>

          <!-- Dropdown Content -->
          <ul tabindex="0"
            class="dropdown-content w-80 md:w-96 mt-3 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
            
            <!-- Header -->
            <li
              class="px-4 py-3 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white z-10">
              <div class="flex items-center gap-2">
                <i data-lucide="bell" class="w-5 h-5 text-blue-600"></i>
                <span class="font-semibold text-gray-800 tracking-wide">Notifications</span>
              </div>
              <button
                class="text-blue-600 hover:text-blue-800 text-xs flex items-center gap-1 transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Clear All</span>
              </button>
            </li>

            <!-- Notification Items -->
            <div class="max-h-96 overflow-y-auto px-2 py-2 space-y-2">
              <!-- Notification items will be dynamically loaded here -->
            </div>

            <!-- Footer -->
            <li
              class="px-4 py-3 border-t border-gray-200 sticky bottom-0 bg-white text-center">
              <a
                class="text-blue-600 hover:text-blue-800 text-sm flex items-center justify-center gap-1 transition-colors">
                <i data-lucide="list" class="w-4 h-4"></i>
                <span>View All Notifications</span>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const philippineTimeEl = document.getElementById("philippineTime");

  function updatePhilippineTime() {
    const now = new Date();

    // Convert to Philippine Time (UTC+8)
    const phTime = new Date(now.toLocaleString("en-US", { timeZone: "Asia/Manila" }));

    // Format: Wed, Oct 23, 2025 03:05 PM
    const options = {
      weekday: "short",
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: true
    };
    philippineTimeEl.textContent = phTime.toLocaleString("en-PH", options);
  }

  // Initial call
  updatePhilippineTime();

  // Update every second
  setInterval(updatePhilippineTime, 1000);

  // Improved sidebar toggle functionality
  window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const sidebarToggleIcon = document.querySelector('#sidebar-toggle i');
    
    if (!sidebar) return;
    
    // Toggle sidebar state
    if (sidebar.classList.contains('hidden') || sidebar.classList.contains('-translate-x-full')) {
      // Show sidebar
      sidebar.classList.remove('hidden', '-translate-x-full');
      sidebar.classList.add('translate-x-0');
      if (overlay) overlay.style.display = 'block';
      
      // Change icon to X when sidebar is open
      sidebarToggleIcon.setAttribute('data-lucide', 'x');
    } else {
      // Hide sidebar
      sidebar.classList.remove('translate-x-0');
      sidebar.classList.add('-translate-x-full');
      if (overlay) overlay.style.display = 'none';
      
      // Change icon back to menu
      sidebarToggleIcon.setAttribute('data-lucide', 'menu');
    }
    
    // Update icons
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
    
    // Update session state for desktop
    if (window.innerWidth >= 768) {
      const isCollapsed = sidebar.classList.contains('w-20');
      const newWidth = isCollapsed ? 'w-64' : 'w-20';
      
      // Toggle width classes
      sidebar.classList.remove('w-20', 'w-64');
      sidebar.classList.add(newWidth);
      
      // Store state in session
      fetch('<?php echo $base_url; ?>USM/toggle_sidebar.php?state=' + (isCollapsed ? 'expanded' : 'collapsed'))
        .catch(err => console.error('Error saving sidebar state:', err));
    }
  };

  // Close sidebar when clicking on overlay
  const overlay = document.querySelector('.sidebar-overlay');
  if (overlay) {
    overlay.addEventListener('click', toggleSidebar);
  }

  // Close sidebar when clicking on a link (mobile)
  const sidebarLinks = document.querySelectorAll('#sidebar a');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
      if (window.innerWidth < 768) {
        toggleSidebar();
      }
    });
  });

  // Close sidebar on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && !sidebar.classList.contains('hidden') && !sidebar.classList.contains('-translate-x-full')) {
        toggleSidebar();
      }
    }
  });
});
</script>

<style>
  /* Mobile dropdown alignment fix */
  @media (max-width: 767px) {
    .dropdown-content {
      left: 50% !important;
      transform: translateX(-80%) !important;
    }
    
    /* Mobile sidebar styles */
    #sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
    }
    
    #sidebar.translate-x-0 {
      transform: translateX(0);
    }
    
    #sidebar.-translate-x-full {
      transform: translateX(-100%);
    }
  }

  /* Desktop sidebar toggle styles */
  @media (min-width: 768px) {
    #sidebar {
      transition: width 0.3s ease-in-out;
    }
    
    #sidebar.w-20 .sidebar-text,
    #sidebar.w-20 .sidebar-section,
    #sidebar.w-20 .dropdown-icon,
    #sidebar.w-20 #sidebar-logo,
    #sidebar.w-20 .dropdown-content {
      display: none;
    }
    
    #sidebar.w-20 #sonly {
      display: block;
    }
    
    #sidebar.w-20 .flex.items-center {
      justify-content: center;
      padding-left: 0.5rem;
      padding-right: 0.5rem;
    }
    
    #sidebar.w-20 .p-1.5.rounded-lg {
      margin-right: 0;
    }
  }

  /* Overlay for mobile */
  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 30;
  }
</style>