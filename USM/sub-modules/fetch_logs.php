<?php

include("../../main_connection.php");

$db_name = "rest_core_2_usm";
$conn = $connections[$db_name] ?? die("❌ Connection not found for $db_name");

$type = $_GET['type'] ?? 'department';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

if ($type === 'department') {
    // Fetch department logs
    $totalQuery = "SELECT COUNT(*) as total FROM department_logs";
    $totalResult = $conn->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
    $totalPages = ceil($totalRecords / $perPage);

    $query = "SELECT * FROM department_logs ORDER BY dept_logs_id DESC LIMIT $perPage OFFSET $offset";
    $result = $conn->query($query);

    $logs = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
} else if ($type === 'audit') {
    // Fetch audit trails
    $totalQuery = "SELECT COUNT(*) as total FROM dept_audit_transc";
    $totalResult = $conn->query($totalQuery);
    $totalRow = $totalResult->fetch_assoc();
    $totalRecords = $totalRow['total'];
    $totalPages = ceil($totalRecords / $perPage);

    $query = "SELECT * FROM dept_audit_transc ORDER BY date DESC LIMIT $perPage OFFSET $offset";
    $result = $conn->query($query);

    $trails = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $trails[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'trails' => $trails,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid type specified'
    ]);
}
?>