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
$totalQuery = "SELECT COUNT(*) as total FROM department_logs";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $perPage);

// Fetch data from database with pagination
$query = "SELECT * FROM department_logs ORDER BY dept_logs_id  DESC LIMIT $perPage OFFSET $offset";
$result = $conn->query($query);

$logs = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
      <?php include '../header.php'; ?>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Department Logs | Soliera Restaurant</title>

    
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
        .status-success {
            background-color: #ecfdf5;
            color: #10b981;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .status-failed {
            background-color: #fef2f2;
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .status-warning {
            background-color: #fffbeb;
            color: #f59e0b;
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
        .log-type {
            font-weight: 500;
            font-size: 0.875rem;
        }
        .log-type-login {
            color: #3b82f6;
        }
        .log-type-logout {
            color: #8b5cf6;
        }
        .log-type-access {
            color: #10b981;
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
                <h6 class="text-3xl font-bold text-gray-900 mb-1">Department Logs</h6>
                <p class="text-gray-500">Total <?php echo $totalRecords; ?> log entries</p>
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          #<?php echo htmlspecialchars($log['dept_logs_id'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php echo htmlspecialchars($log['dept_id'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                              <i data-lucide="user" class="text-gray-500"></i>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['employee_name'] ?? ''); ?>
                              </div>
                              <div class="text-sm text-gray-500">
                                ID: <?php echo htmlspecialchars($log['employee_id'] ?? ''); ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <span class="log-type log-type-<?php echo strtolower($log['log_type'] ?? ''); ?>">
                            <i data-lucide="<?php 
                              switch(strtolower($log['log_type'] ?? '')) {
                                case 'login': echo 'log-in'; break;
                                case 'logout': echo 'log-out'; break;
                                default: echo 'shield';
                              }
                            ?>" class="w-4 h-4 mr-1"></i>
                            <?php echo htmlspecialchars(ucfirst($log['log_type'] ?? '')); ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php 
                            $status = strtolower($log['log_status'] ?? 'failed');
                            $statusClass = 'status-' . ($status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : 'warning'));
                            $statusText = ucfirst($status);
                          ?>
                          <span class="<?php echo $statusClass; ?>">
                            <i data-lucide="<?php 
                              echo $status === 'success' ? 'check-circle' : 
                                   ($status === 'failed' ? 'x-circle' : 'alert-circle'); 
                            ?>" class="w-4 h-4 mr-1"></i>
                            <?php echo $statusText; ?>
                          </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                          <?php if (!empty($log['failure_reason'])): ?>
                            <div class="flex items-start">
                              <i data-lucide="alert-triangle" class="flex-shrink-0 w-4 h-4 mt-0.5 mr-1 text-yellow-500"></i>
                              <span><?php echo htmlspecialchars($log['failure_reason']); ?></span>
                            </div>
                          <?php elseif ($log['attempt_count'] > 1): ?>
                            <div class="flex items-start">
                              <i data-lucide="repeat" class="flex-shrink-0 w-4 h-4 mt-0.5 mr-1 text-blue-500"></i>
                              <span><?php echo htmlspecialchars($log['attempt_count']); ?> attempts</span>
                            </div>
                          <?php elseif (!empty($log['cooldown'])): ?>
                            <div class="flex items-start">
                              <i data-lucide="clock" class="flex-shrink-0 w-4 h-4 mt-0.5 mr-1 text-purple-500"></i>
                              <span>Cooldown: <?php echo htmlspecialchars($log['cooldown']); ?></span>
                            </div>
                          <?php else: ?>
                            <span class="text-gray-400">No additional details</span>
                          <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php 
                            $date = new DateTime($log['date'] ?? '');
                            echo htmlspecialchars($date->format('M j, Y H:i:s')); 
                          ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="text-center py-12">
                        <div class="flex flex-col items-center justify-center gap-4">
                          <div class="p-4 bg-gray-50 rounded-full">
                            <i data-lucide="activity" class="w-10 h-10 empty-state-icon"></i>
                          </div>
                          <div class="space-y-1">
                            <h3 class="text-lg font-medium text-gray-900">No department logs found</h3>
                            <p class="text-gray-500">Activity logs will appear here once available</p>
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
                Showing <span class="font-medium text-gray-700"><?php echo ($offset + 1); ?></span> to <span class="font-medium text-gray-700"><?php echo min($offset + $perPage, $totalRecords); ?></span> of <span class="font-medium text-gray-700"><?php echo $totalRecords; ?></span> logs
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
  </script>
  <script src="../JavaScript/sidebar.js"></script>
</body>
</html>