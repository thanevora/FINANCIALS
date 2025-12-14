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
    // Get available reports (this would typically come from a reports table)
    // For now, we'll generate them dynamically based on current data
    
    $reports = [];
    
    // Monthly Summary Report
    $monthly_query = "
        SELECT 
            COUNT(*) as total_requests,
            SUM(amount) as total_amount,
            AVG(amount) as avg_amount,
            SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
        FROM budget_allocations 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ";
    
    $monthly_result = mysqli_query($conn, $monthly_query);
    $monthly_data = mysqli_fetch_assoc($monthly_result);
    
    $reports[] = [
        'id' => 'monthly_' . date('Y_m'),
        'title' => 'Monthly Budget Summary - ' . date('F Y'),
        'description' => 'Comprehensive overview of budget allocations for the current month',
        'type' => 'monthly',
        'status' => 'generated',
        'period' => date('F Y'),
        'data' => [
            'total_requests' => $monthly_data['total_requests'] ?? 0,
            'total_amount' => $monthly_data['total_amount'] ?? 0,
            'approval_rate' => $monthly_data['total_amount'] > 0 ? 
                round(($monthly_data['approved_amount'] / $monthly_data['total_amount']) * 100, 1) : 0
        ]
    ];
    
    // Quarterly Analysis Report
    $quarter_query = "
        SELECT 
            COUNT(*) as total_requests,
            SUM(amount) as total_amount,
            department,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM budget_allocations 
        WHERE QUARTER(created_at) = QUARTER(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY department
        ORDER BY total_amount DESC
    ";
    
    $quarter_result = mysqli_query($conn, $quarter_query);
    $department_data = [];
    
    while ($row = mysqli_fetch_assoc($quarter_result)) {
        $department_data[] = $row;
    }
    
    $reports[] = [
        'id' => 'quarterly_' . date('Y_Q'),
        'title' => 'Quarterly Department Analysis - Q' . ceil(date('n') / 3) . ' ' . date('Y'),
        'description' => 'Department-wise budget allocation analysis for the current quarter',
        'type' => 'quarterly',
        'status' => 'generated',
        'period' => 'Q' . ceil(date('n') / 3) . ' ' . date('Y'),
        'data' => $department_data
    ];
    
    // Annual Budget Report
    $annual_query = "
        SELECT 
            MONTH(created_at) as month,
            COUNT(*) as monthly_requests,
            SUM(amount) as monthly_amount,
            AVG(amount) as avg_amount
        FROM budget_allocations 
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(created_at)
        ORDER BY month
    ";
    
    $annual_result = mysqli_query($conn, $annual_query);
    $monthly_trends = [];
    
    while ($row = mysqli_fetch_assoc($annual_result)) {
        $monthly_trends[] = $row;
    }
    
    $reports[] = [
        'id' => 'annual_' . date('Y'),
        'title' => 'Annual Budget Report - ' . date('Y'),
        'description' => 'Comprehensive annual budget performance and trends',
        'type' => 'annual',
        'status' => 'generated',
        'period' => date('Y'),
        'data' => $monthly_trends
    ];
    
    // Budget Utilization Report
    $utilization_query = "
        SELECT 
            bp.department,
            bp.total_budget,
            COALESCE(SUM(ba.amount), 0) as allocated_amount,
            CASE 
                WHEN bp.total_budget > 0 THEN ROUND((COALESCE(SUM(ba.amount), 0) / bp.total_budget) * 100, 2)
                ELSE 0 
            END as utilization_rate
        FROM budget_proposals bp
        LEFT JOIN budget_allocations ba ON bp.department = ba.department AND ba.status = 'approved'
        WHERE bp.status = 'approved'
        GROUP BY bp.department, bp.total_budget
        ORDER BY utilization_rate DESC
    ";
    
    $utilization_result = mysqli_query($conn, $utilization_query);
    $utilization_data = [];
    
    while ($row = mysqli_fetch_assoc($utilization_result)) {
        $utilization_data[] = $row;
    }
    
    $reports[] = [
        'id' => 'utilization_' . date('Y_m'),
        'title' => 'Budget Utilization Report - ' . date('F Y'),
        'description' => 'Department-wise budget utilization rates and efficiency analysis',
        'type' => 'utilization',
        'status' => 'generated',
        'period' => date('F Y'),
        'data' => $utilization_data
    ];

    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'message' => 'Budget reports retrieved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving budget reports: ' . $e->getMessage()
    ]);
}
?>