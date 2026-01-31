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
$totalQuery = "SELECT COUNT(*) as total FROM department_accounts";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $perPage);

// Fetch data from database with pagination
$query = "SELECT * FROM department_accounts ORDER BY dept_name ASC LIMIT $perPage OFFSET $offset";
$result = $conn->query($query);

$accounts = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
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
    <title>User Management | Soliera Restaurant</title>
    <!-- Favicon -->

    
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
        .status-active {
            background-color: #ecfdf5;
            color: #10b981;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .status-inactive {
            background-color: #fef2f2;
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .status-pending {
            background-color: #eff6ff;
            color: #3b82f6;
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
                <h6 class="text-3xl font-bold text-gray-900 mb-1">Department Accounts</h6>
                <p class="text-gray-500">Total <?php echo $totalRecords; ?> accounts</p>
              </div>
            
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto rounded-xl border border-gray-100">
              <table class="table divide-y divide-gray-200">
                <!-- Table Head -->
                <thead>
                  <tr class="bg-gray-50">
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dept ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php if (count($accounts) > 0): ?>
                    <?php foreach ($accounts as $account): ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                          <?php echo htmlspecialchars($account['Dept_id'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php echo htmlspecialchars($account['dept_name'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                              <i data-lucide="user" class="text-gray-500"></i>
                            </div>
                            <div class="ml-4">
                              <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($account['employee_name'] ?? ''); ?>
                              </div>
                              <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($account['email'] ?? ''); ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                          <?php echo htmlspecialchars($account['role'] ?? ''); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <?php 
                            $status = $account['status'] ?? 'inactive';
                            $statusClass = 'status-' . $status;
                            $statusText = ucfirst($status);
                          ?>
                          <span class="<?php echo $statusClass; ?>">
                            <i data-lucide="<?php 
                              echo $status === 'active' ? 'check-circle' : 
                                   ($status === 'inactive' ? 'x-circle' : 'clock'); 
                            ?>" class="w-4 h-4 mr-1"></i>
                            <?php echo $statusText; ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center py-12">
                        <div class="flex flex-col items-center justify-center gap-4">
                          <div class="p-4 bg-gray-50 rounded-full">
                            <i data-lucide="user-cog" class="w-10 h-10 empty-state-icon"></i>
                          </div>
                          <div class="space-y-1">
                            <h3 class="text-lg font-medium text-gray-900">No department accounts found</h3>
                            <p class="text-gray-500">Create your first department account to get started</p>
                          </div>
                          <button class="btn btn-primary bg-indigo-600 hover:bg-indigo-700 border-none px-6 py-2.5 rounded-lg flex items-center gap-2 shadow-sm hover:shadow-md transition-all mt-4">
                            <i data-lucide="plus" class="w-5 h-5"></i>
                            <span>Add New Account</span>
                          </button>
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
                Showing <span class="font-medium text-gray-700"><?php echo ($offset + 1); ?></span> to <span class="font-medium text-gray-700"><?php echo min($offset + $perPage, $totalRecords); ?></span> of <span class="font-medium text-gray-700"><?php echo $totalRecords; ?></span> accounts
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