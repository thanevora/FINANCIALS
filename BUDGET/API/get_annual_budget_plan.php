<?php
session_start();
include("../../API_gateway.php");

header('Content-Type: application/json');

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    echo json_encode(['success' => false, 'message' => 'Database connection not found']);
    exit;
}
$conn = $connections[$db_name];

try {
    // Get total annual budget from approved proposals
    $budget_query = "SELECT SUM(amount) as total_budget FROM budget_proposals WHERE status = 'approved'";
    $budget_result = mysqli_query($conn, $budget_query);
    $budget_row = mysqli_fetch_assoc($budget_result);
    $total_budget = $budget_row['total_budget'] ?? 0;

    // Get total allocated budget
    $allocated_query = "SELECT SUM(amount) as allocated_budget FROM budget_allocations WHERE status = 'approved'";
    $allocated_result = mysqli_query($conn, $allocated_query);
    $allocated_row = mysqli_fetch_assoc($allocated_result);
    $allocated_budget = $allocated_row['allocated_budget'] ?? 0;

    // Get department-wise allocations
    $dept_query = "SELECT department, SUM(amount) as total_amount 
                   FROM budget_allocations 
                   WHERE status = 'approved' 
                   GROUP BY department 
                   ORDER BY total_amount DESC";
    $dept_result = mysqli_query($conn, $dept_query);
    
    $department_allocations = [];
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $department_allocations[] = [
            'department' => $row['department'],
            'amount' => $row['total_amount']
        ];
    }

    // Calculate remaining budget
    $remaining_budget = $total_budget - $allocated_budget;

    // Prepare response
    $plan = [
        'total_budget' => $total_budget,
        'allocated_budget' => $allocated_budget,
        'remaining_budget' => $remaining_budget,
        'department_allocations' => $department_allocations,
        'fiscal_year' => date('Y'),
        'last_updated' => date('Y-m-d H:i:s'),
        'utilization_rate' => $total_budget > 0 ? round(($allocated_budget / $total_budget) * 100, 2) : 0
    ];

    echo json_encode([
        'success' => true,
        'plan' => $plan,
        'message' => 'Annual budget plan retrieved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving annual budget plan: ' . $e->getMessage()
    ]);
}
?>