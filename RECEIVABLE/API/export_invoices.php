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

// Get filter parameters from URL
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_customer = isset($_GET['customer']) ? $_GET['customer'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_service_type = isset($_GET['service_type']) ? $_GET['service_type'] : '';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_status) && $filter_status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_customer)) {
    $where_conditions[] = "customer_name LIKE ?";
    $params[] = "%$filter_customer%";
    $param_types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "due_date >= ?";
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "due_date <= ?";
    $params[] = $filter_date_to;
    $param_types .= 's';
}

if (!empty($filter_service_type) && $filter_service_type !== 'all') {
    $where_conditions[] = "service_type = ?";
    $params[] = $filter_service_type;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
} else {
    $where_clause = "WHERE status != 'deleted'";
}

// Fetch all filtered data for export
$sql = "SELECT * FROM accounts_receivable $where_clause ORDER BY due_date DESC";

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
$filename = 'invoices_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers with BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

// Headers
fputcsv($output, [
    'No.',
    'Invoice Number',
    'Customer Name',
    'Amount (PHP)',
    'Balance (PHP)',
    'Service Type',
    'Status',
    'Invoice Date',
    'Due Date',
    'Payment Date',
    'Customer Email',
    'Customer Phone',
    'Notes',
    'Created At',
    'Updated At'
]);

// Add data rows
if ($result && mysqli_num_rows($result) > 0) {
    $counter = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $counter++,
            $row['invoice_number'] ?? '',
            $row['customer_name'] ?? '',
            '="' . number_format($row['amount'] ?? 0, 2) . '"', // Excel formula for number
            '="' . number_format($row['balance'] ?? 0, 2) . '"', // Excel formula for number
            $row['service_type'] ?? '',
            $row['status'] ?? '',
            $row['invoice_date'] ? date('m/d/Y', strtotime($row['invoice_date'])) : '',
            $row['due_date'] ? date('m/d/Y', strtotime($row['due_date'])) : '',
            $row['payment_date'] ? date('m/d/Y', strtotime($row['payment_date'])) : '',
            $row['customer_email'] ?? '',
            $row['customer_phone'] ?? '',
            $row['notes'] ?? '',
            $row['created_at'] ? date('m/d/Y H:i:s', strtotime($row['created_at'])) : '',
            $row['updated_at'] ? date('m/d/Y H:i:s', strtotime($row['updated_at'])) : ''
        ]);
    }
} else {
    fputcsv($output, ['No data found for the selected filters']);
}

fclose($output);
exit;
?>