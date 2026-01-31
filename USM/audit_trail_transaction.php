<?php
session_start();
include("../main_connection.php");

$db_name = "rest_core_2_usm";
$conn = $connections[$db_name] ?? die("❌ Connection not found for $db_name");

// Pagination settings
$perPage = 25;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Get total number of records
$totalQuery = "SELECT COUNT(*) as total FROM dept_audit_transc";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $perPage);

// Fetch data from database with pagination
$query = "SELECT * FROM dept_audit_transc ORDER BY date DESC LIMIT $perPage OFFSET $offset";
$result = $conn->query($query);

$trails = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $trails[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Audit Trail | Soliera Restaurant</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- UI Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../CSS/sidebar.css">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .table th {
            background-color: #f1f5f9;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .action-create {
            background-color: #ecfdf5;
            color: #10b981;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .action-update {
            background-color: #eff6ff;
            color: #3b82f6;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .action-delete {
            background-color: #fef2f2;
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .action-access {
            background-color: #f5f3ff;
            color: #8b5cf6;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .empty-state-icon {
            color: #cbd5e1;
        }
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .action-btn {
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            transform: translateY(-1px);
        }
        .module-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 9999px;
            background-color: #e0e7ff;
            color: #4f46e5;
            display: inline-block;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        .activity-text {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pagination-link {
            min-width: 2.5rem;
        }
        .pagination-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body class="min-h-screen">
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include '../sidebarr.php'; ?>

    <!-- Content Area -->
    <div class="flex flex-col flex-1 overflow-auto bg-gray-50">
      <!-- Navbar -->
      <?php include '../navbar.php'; ?>

      <!-- Main Content -->
      <main class="p-6">
        <div class="card bg-white border border-gray-100">
          <!-- Card Header -->
          <div class="card-body p-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
              <div>
                <h6 class="text-3xl font-bold text-gray-900 mb-1">Audit Trail & Transaction</h6>
                <p class="text-gray-500">Total <?php echo $totalRecords; ?> records</p>
              </div>
            
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto rounded-xl border border-gray-100">
              <table class="table divide-y divide-gray-200">
                <!-- Table Head -->
                <thead>
                  <tr class="bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modules</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (count($trails) > 0): ?>
                    <?php foreach ($trails as $trail): ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          #<?php echo htmlspecialchars($trail['a&t_id'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php echo htmlspecialchars($trail['dept_name'] ?? ''); ?>
                          <div class="text-xs text-gray-400">ID: <?php echo htmlspecialchars($trail['dept_id'] ?? ''); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                              <i data-lucide="user" class="text-gray-500"></i>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($trail['employee_name'] ?? ''); ?>
                              </div>
                              <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($trail['role'] ?? ''); ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                          <?php 
                            $modules = explode(',', $trail['modules_cover'] ?? '');
                            foreach ($modules as $module):
                              if (trim($module)):
                          ?>
                            <span class="module-badge"><?php echo htmlspecialchars(trim($module)); ?></span>
                          <?php 
                              endif;
                            endforeach; 
                          ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php 
                            $action = strtolower($trail['action'] ?? '');
                            $actionClass = 'action-' . $action;
                            $actionText = ucfirst($action);
                          ?>
                          <span class="<?php echo $actionClass; ?>">
                            <i data-lucide="<?php 
                              switch($action) {
                                case 'create': echo 'plus'; break;
                                case 'update': echo 'edit'; break;
                                case 'delete': echo 'trash-2'; break;
                                default: echo 'activity';
                              }
                            ?>" class="w-4 h-4 mr-1"></i>
                            <?php echo $actionText; ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                          <div class="activity-text" title="<?php echo htmlspecialchars($trail['activity'] ?? ''); ?>">
                            <?php echo htmlspecialchars($trail['activity'] ?? ''); ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php 
                            $date = new DateTime($trail['date'] ?? '');
                            echo htmlspecialchars($date->format('M j, Y H:i:s')); 
                          ?>
                        </td>
                        
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="8" class="text-center py-12">
                        <div class="flex flex-col items-center justify-center gap-4">
                          <div class="p-4 bg-gray-50 rounded-full">
                            <i data-lucide="clipboard-list" class="w-10 h-10 empty-state-icon"></i>
                          </div>
                          <div class="space-y-1">
                            <h3 class="text-lg font-medium text-gray-900">No audit trail records found</h3>
                            <p class="text-gray-500">System activities will appear here once recorded</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row items-center justify-between mt-6 pt-6 border-t border-gray-100 gap-4">
              <div class="text-sm text-gray-500">
                Showing <span class="font-medium text-gray-700"><?php echo ($offset + 1); ?></span> to <span class="font-medium text-gray-700"><?php echo min($offset + $perPage, $totalRecords); ?></span> of <span class="font-medium text-gray-700"><?php echo $totalRecords; ?></span> records
              </div>
              <div class="join">
                <?php if ($page > 1): ?>
                  <a href="?page=<?php echo $page - 1; ?>" class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                  </a>
                <?php else: ?>
                  <button class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50 disabled opacity-50">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                  </button>
                <?php endif; ?>

                <?php
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1) {
                    echo '<a href="?page=1" class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50">1</a>';
                    if ($startPage > 2) {
                        echo '<button class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50 disabled opacity-50">...</button>';
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a href="?page='.$i.'" class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50 pagination-link '.$active.'">'.$i.'</a>';
                }
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<button class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50 disabled opacity-50">...</button>';
                    }
                    echo '<a href="?page='.$totalPages.'" class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50">'.$totalPages.'</a>';
                }
                ?>

                <?php if ($page < $totalPages): ?>
                  <a href="?page=<?php echo $page + 1; ?>" class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                  </a>
                <?php else: ?>
                  <button class="join-item btn btn-sm btn-ghost border border-gray-200 hover:bg-gray-50 disabled opacity-50">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Details Modal -->
  <div id="trailModal" class="modal modal-bottom sm:modal-middle">
    <div class="modal-box max-w-2xl">
      <h3 class="font-bold text-lg">Audit Trail Details</h3>
      <div class="py-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Log ID</label>
            <p class="mt-1 text-sm text-gray-900" id="modal-id"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Date</label>
            <p class="mt-1 text-sm text-gray-900" id="modal-date"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Department</label>
            <p class="mt-1 text-sm text-gray-900" id="modal-dept"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Employee</label>
            <p class="mt-1 text-sm text-gray-900" id="modal-employee"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Role</label>
            <p class="mt-1 text-sm text-gray-900" id="modal-role"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Action</label>
            <p class="mt-1 text-sm" id="modal-action"></p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-500">Modules</label>
          <div class="mt-1 flex flex-wrap gap-2" id="modal-modules"></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-500">Activity Details</label>
          <div class="mt-1 p-3 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-900" id="modal-activity"></p>
          </div>
        </div>
      </div>
      <div class="modal-action">
        <button class="btn btn-ghost" onclick="document.getElementById('trailModal').close()">Close</button>
        <button class="btn btn-primary">Export Details</button>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
      const searchValue = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
        if (row.querySelector('td')) { // Skip empty state row
          const textContent = row.textContent.toLowerCase();
          row.style.display = textContent.includes(searchValue) ? '' : 'none';
        }
      });
    });

    // Show trail details modal
    function showTrailDetails(trail) {
      const modal = document.getElementById('trailModal');
      document.getElementById('modal-id').textContent = '#' + trail['a&t_id'];
      document.getElementById('modal-date').textContent = new Date(trail.date).toLocaleString();
      document.getElementById('modal-dept').textContent = trail.dept_name + ' (ID: ' + trail.dept_id + ')';
      document.getElementById('modal-employee').textContent = trail.employee_name + ' (ID: ' + trail.employee_id + ')';
      document.getElementById('modal-role').textContent = trail.role;
      
      // Set action with appropriate styling
      const action = document.getElementById('modal-action');
      action.textContent = trail.action;
      action.className = 'mt-1 text-sm ' + getActionClass(trail.action.toLowerCase());
      
      // Set modules
      const modulesContainer = document.getElementById('modal-modules');
      modulesContainer.innerHTML = '';
      trail.modules_cover.split(',').forEach(module => {
        if (module.trim()) {
          const badge = document.createElement('span');
          badge.className = 'module-badge';
          badge.textContent = module.trim();
          modulesContainer.appendChild(badge);
        }
      });
      
      // Set activity
      document.getElementById('modal-activity').textContent = trail.activity;
      
      modal.showModal();
    }

    function getActionClass(action) {
      switch(action) {
        case 'create': return 'action-create';
        case 'update': return 'action-update';
        case 'delete': return 'action-delete';
        default: return 'action-access';
      }
    }
  </script>
  <script src="../JavaScript/sidebar.js"></script>
</body>
</html>