<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}

$conn = $connections[$db_name];

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_customer = $_GET['customer'] ?? '';
$filter_service_type = $_GET['service_type'] ?? '';
$filter_payment_method = $_GET['payment_method'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_amount_min = $_GET['amount_min'] ?? '';
$filter_amount_max = $_GET['amount_max'] ?? '';

// Build WHERE clause for filters
$where_clauses = ["status != 'deleted'"];
$params = [];
$param_types = "";

// Status filter
if (!empty($filter_status)) {
    if ($filter_status === 'all') {
        // Show all except deleted
        $where_clauses[] = "status != 'deleted'";
    } else {
        $where_clauses[] = "status = ?";
        $params[] = $filter_status;
        $param_types .= "s";
    }
}

// Customer filter
if (!empty($filter_customer)) {
    $where_clauses[] = "(customer_name LIKE ?)";
    $params[] = "%$filter_customer%";
    $param_types .= "s";
}

// Service type filter
if (!empty($filter_service_type)) {
    $where_clauses[] = "service_type = ?";
    $params[] = $filter_service_type;
    $param_types .= "s";
}

// Payment method filter
if (!empty($filter_payment_method)) {
    $where_clauses[] = "payment_method = ?";
    $params[] = $filter_payment_method;
    $param_types .= "s";
}

// Date range filter
if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $where_clauses[] = "DATE(due_date) BETWEEN ? AND ?";
    $params[] = $filter_date_from;
    $params[] = $filter_date_to;
    $param_types .= "ss";
} elseif (!empty($filter_date_from)) {
    $where_clauses[] = "DATE(due_date) >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
} elseif (!empty($filter_date_to)) {
    $where_clauses[] = "DATE(due_date) <= ?";
    $params[] = $filter_date_to;
    $param_types .= "s";
}

// Amount range filter
if (!empty($filter_amount_min) && !empty($filter_amount_max)) {
    $where_clauses[] = "amount BETWEEN ? AND ?";
    $params[] = $filter_amount_min;
    $params[] = $filter_amount_max;
    $param_types .= "dd";
} elseif (!empty($filter_amount_min)) {
    $where_clauses[] = "amount >= ?";
    $params[] = $filter_amount_min;
    $param_types .= "d";
} elseif (!empty($filter_amount_max)) {
    $where_clauses[] = "amount <= ?";
    $params[] = $filter_amount_max;
    $param_types .= "d";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "WHERE status != 'deleted'";

// Fetch all filtered data for export
$sql = "SELECT * FROM collections $where_sql ORDER BY due_date DESC";

// Prepare and execute query with parameters
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

// Set headers for CSV download
$filename = 'collections_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers with BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Headers - simplified based on your table structure
fputcsv($output, [
    'No.',
    'Request ID',
    'Customer Name',
    'Amount (PHP)',
    'Service Type',
    'Payment Method',
    'Status',
    'Due Date',
    'Collection Date',
    'Description',
    'Created Date',
    'Last Updated'
]);

// Add data rows
if ($result && mysqli_num_rows($result) > 0) {
    $counter = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        // Format amount without commas for Excel - Excel will add them automatically
        // Adding "=" in front makes Excel treat it as a formula/raw value
        $amount = $row['amount'] ?? 0;
        $excel_amount = '=' . number_format($amount, 2, '.', ''); // No thousands separator
        
        fputcsv($output, [
            $counter++, // Auto-increment number
            $row['request_id'] ?? '',
            $row['customer_name'] ?? '',
            $excel_amount, // Formatted for Excel
            $row['service_type'] ?? '',
            $row['payment_method'] ?? '',
            $row['status'] ?? '',
            isset($row['due_date']) ? date('m/d/Y', strtotime($row['due_date'])) : '',
            isset($row['collection_date']) && !empty($row['collection_date']) ? 
                date('m/d/Y', strtotime($row['collection_date'])) : '',
            $row['description'] ?? '',
            isset($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '',
            isset($row['updated_at']) ? date('m/d/Y H:i:s', strtotime($row['updated_at'])) : ''
        ]);
    }
} else {
    fputcsv($output, ['No data found for the selected filters']);
}

fclose($output);
exit;
?>