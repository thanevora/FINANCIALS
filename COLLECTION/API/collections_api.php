<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die(json_encode(["status" => "error", "message" => "Connection not found for $db_name"]));
}

$conn = $connections[$db_name];

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the action from query parameters or request body
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Helper function to send JSON response
function sendResponse($status, $data = [], $message = '') {
    http_response_code($status);
    echo json_encode([
        'status' => $status >= 200 && $status < 300 ? 'success' : 'error',
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Helper function to validate required fields
function validateRequiredFields($fields, $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}

// Authentication middleware
function authenticateRequest() {
    // Add your authentication logic here
    // This could be API keys, JWT tokens, etc.
    $api_key = $_SERVER['HTTP_API_KEY'] ?? $_GET['api_key'] ?? '';
    
    // For now, we'll allow all requests. Implement proper authentication in production.
    return true;
}

// Main API router
try {
    // Authenticate the request
    if (!authenticateRequest()) {
        sendResponse(401, [], 'Unauthorized access');
    }

    switch ($method) {
        case 'GET':
            handleGetRequest($action, $conn);
            break;
        case 'POST':
            handlePostRequest($action, $conn);
            break;
        case 'PUT':
            handlePutRequest($action, $conn);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $conn);
            break;
        default:
            sendResponse(405, [], 'Method not allowed');
    }
} catch (Exception $e) {
    sendResponse(500, [], 'Server error: ' . $e->getMessage());
}

// Handle GET requests
function handleGetRequest($action, $conn) {
    switch ($action) {
        case 'get_stats':
            getCollectionsStats($conn);
            break;
        case 'get_collections':
            getCollections($conn);
            break;
        case 'get_collection':
            getCollection($conn);
            break;
        case 'get_transaction_status':
            getTransactionStatus($conn);
            break;
        case 'get_payment_methods':
            getPaymentMethods($conn);
            break;
        case 'get_collection_requests':
            getCollectionRequests($conn);
            break;
        case 'get_collections_report':
            getCollectionsReport($conn);
            break;
        case 'export_collections':
            exportCollections($conn);
            break;
        default:
            sendResponse(400, [], 'Invalid action for GET request');
    }
}

// Handle POST requests
function handlePostRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'create_collection':
            createCollection($conn, $input);
            break;
        case 'update_collection_status':
            updateCollectionStatus($conn, $input);
            break;
        case 'bulk_update_collections':
            bulkUpdateCollections($conn, $input);
            break;
        case 'import_collections':
            importCollections($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for POST request');
    }
}

// Handle PUT requests
function handlePutRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_collection':
            updateCollection($conn, $input);
            break;
        case 'process_payment':
            processPayment($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for PUT request');
    }
}

// Handle DELETE requests
function handleDeleteRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete_collection':
            deleteCollection($conn, $input);
            break;
        case 'bulk_delete_collections':
            bulkDeleteCollections($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for DELETE request');
    }
}

// API Endpoint Implementations

/**
 * Get comprehensive collections statistics
 */
function getCollectionsStats($conn) {
    $stats = [];
    
    // Total Collections (completed status)
    $sql = "SELECT SUM(amount) as total FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    $stats['total_collections'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Pending Collections
    $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'pending'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['pending_collections'] = $row['count'] ?? 0;
        $stats['pending_amount'] = $row['total'] ?? 0;
    } else {
        $stats['pending_collections'] = 0;
        $stats['pending_amount'] = 0;
    }
    
    // Successful This Month
    $current_month_start = date('Y-m-01');
    $current_month_end = date('Y-m-t');
    $sql = "SELECT SUM(amount) as total, COUNT(*) as count FROM collections WHERE status = 'completed' AND collection_date BETWEEN '$current_month_start' AND '$current_month_end'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['successful_this_month'] = $row['total'] ?? 0;
        $stats['monthly_count'] = $row['count'] ?? 0;
    } else {
        $stats['successful_this_month'] = 0;
        $stats['monthly_count'] = 0;
    }
    
    // Collection Rate
    $sql = "SELECT COUNT(*) as total_count, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count FROM collections";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $completed_count = $row['completed_count'] ?? 0;
        $total_count = $row['total_count'] ?? 1;
        $stats['collection_rate'] = $total_count > 0 ? round(($completed_count / $total_count) * 100, 1) : 0;
        $stats['completed_count'] = $completed_count;
        $stats['total_count'] = $total_count;
    } else {
        $stats['collection_rate'] = 0;
        $stats['completed_count'] = 0;
        $stats['total_count'] = 0;
    }
    
    // Transaction Status Overview
    $transaction_status = [
        'completed' => ['count' => 0, 'amount' => 0],
        'failed' => ['count' => 0, 'amount' => 0],
        'refund' => ['count' => 0, 'amount' => 0],
        'pending' => ['count' => 0, 'amount' => 0],
        'for_approval' => ['count' => 0, 'amount' => 0],
        'overdue' => ['count' => 0, 'amount' => 0]
    ];
    
    $sql = "SELECT status, COUNT(*) as count, SUM(amount) as amount FROM collections GROUP BY status";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $status = strtolower($row['status']);
            if (isset($transaction_status[$status])) {
                $transaction_status[$status]['count'] = $row['count'];
                $transaction_status[$status]['amount'] = $row['amount'] ?? 0;
            }
        }
    }
    $stats['transaction_status'] = $transaction_status;
    
    // Payment Methods Breakdown
    $payment_methods = [];
    $sql = "SELECT payment_method, COUNT(*) as count, SUM(amount) as amount 
            FROM collections 
            WHERE status = 'completed' AND payment_method IS NOT NULL 
            GROUP BY payment_method";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payment_methods[] = $row;
        }
    }
    
    // Calculate payment method percentages
    $total_payment_amount = array_sum(array_column($payment_methods, 'amount'));
    foreach ($payment_methods as &$method) {
        $method['percentage'] = $total_payment_amount > 0 ? round(($method['amount'] / $total_payment_amount) * 100) : 0;
    }
    $stats['payment_methods'] = $payment_methods;
    
    // Recent Activity
    $sql = "SELECT COUNT(*) as today_count, 
                   SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as today_amount
            FROM collections 
            WHERE DATE(created_at) = CURDATE()";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['today_count'] = $row['today_count'] ?? 0;
        $stats['today_amount'] = $row['today_amount'] ?? 0;
    }
    
    sendResponse(200, $stats, 'Collections statistics retrieved successfully');
}

/**
 * Get collections with filtering and pagination
 */
function getCollections($conn) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    $params = [];
    $types = '';
    
    // Status filter
    if (!empty($_GET['status'])) {
        $statuses = explode(',', $_GET['status']);
        $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
        $filters[] = "status IN ($placeholders)";
        $params = array_merge($params, $statuses);
        $types .= str_repeat('s', count($statuses));
    }
    
    // Customer filter
    if (!empty($_GET['customer_name'])) {
        $filters[] = "customer_name LIKE ?";
        $params[] = '%' . $_GET['customer_name'] . '%';
        $types .= 's';
    }
    
    // Service type filter
    if (!empty($_GET['service_type'])) {
        $filters[] = "service_type = ?";
        $params[] = $_GET['service_type'];
        $types .= 's';
    }
    
    // Payment method filter
    if (!empty($_GET['payment_method'])) {
        $filters[] = "payment_method = ?";
        $params[] = $_GET['payment_method'];
        $types .= 's';
    }
    
    // Date range filters
    if (!empty($_GET['start_date'])) {
        $filters[] = "due_date >= ?";
        $params[] = $_GET['start_date'];
        $types .= 's';
    }
    if (!empty($_GET['end_date'])) {
        $filters[] = "due_date <= ?";
        $params[] = $_GET['end_date'];
        $types .= 's';
    }
    
    $where_clause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM collections $where_clause";
    if ($params) {
        $stmt = mysqli_prepare($conn, $count_sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $count_sql);
    }
    $total_count = mysqli_fetch_assoc($result)['total'] ?? 0;
    
    // Get collections
    $sql = "SELECT * FROM collections $where_clause ORDER BY due_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    $response = [
        'collections' => $collections,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ];
    
    sendResponse(200, $response, 'Collections retrieved successfully');
}

/**
 * Get single collection by ID
 */
function getCollection($conn) {
    $collection_id = intval($_GET['id'] ?? 0);
    
    if (!$collection_id) {
        sendResponse(400, [], 'Collection ID is required');
    }
    
    $sql = "SELECT * FROM collections WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $collection_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $collection = mysqli_fetch_assoc($result);
    
    if (!$collection) {
        sendResponse(404, [], 'Collection not found');
    }
    
    sendResponse(200, $collection, 'Collection retrieved successfully');
}

/**
 * Get transaction status overview
 */
function getTransactionStatus($conn) {
    $status_counts = [];
    
    $sql = "SELECT status, COUNT(*) as count, SUM(amount) as amount 
            FROM collections 
            GROUP BY status";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $status_counts[strtolower($row['status'])] = [
            'count' => $row['count'],
            'amount' => $row['amount'] ?? 0
        ];
    }
    
    // Ensure all statuses are present
    $all_statuses = ['completed', 'failed', 'refund', 'pending', 'for_approval', 'overdue'];
    foreach ($all_statuses as $status) {
        if (!isset($status_counts[$status])) {
            $status_counts[$status] = ['count' => 0, 'amount' => 0];
        }
    }
    
    sendResponse(200, $status_counts, 'Transaction status overview retrieved successfully');
}

/**
 * Get payment methods breakdown
 */
function getPaymentMethods($conn) {
    $payment_methods = [];
    
    $sql = "SELECT payment_method, COUNT(*) as count, SUM(amount) as amount 
            FROM collections 
            WHERE status = 'completed' AND payment_method IS NOT NULL 
            GROUP BY payment_method 
            ORDER BY amount DESC";
    
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $payment_methods[] = $row;
    }
    
    // Calculate percentages
    $total_amount = array_sum(array_column($payment_methods, 'amount'));
    foreach ($payment_methods as &$method) {
        $method['percentage'] = $total_amount > 0 ? round(($method['amount'] / $total_amount) * 100, 1) : 0;
    }
    
    sendResponse(200, $payment_methods, 'Payment methods breakdown retrieved successfully');
}

/**
 * Get collection requests
 */
function getCollectionRequests($conn) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT * FROM collections 
            ORDER BY due_date DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    sendResponse(200, $collections, 'Collection requests retrieved successfully');
}

/**
 * Get collections report
 */
function getCollectionsReport($conn) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $group_by = $_GET['group_by'] ?? 'daily'; // daily, weekly, monthly, status, payment_method
    
    $report_data = [];
    
    switch ($group_by) {
        case 'daily':
            $sql = "SELECT DATE(collection_date) as period, 
                           COUNT(*) as count, 
                           SUM(amount) as total_amount,
                           status
                    FROM collections 
                    WHERE collection_date BETWEEN ? AND ?
                    GROUP BY period, status
                    ORDER BY period DESC";
            break;
        case 'status':
            $sql = "SELECT status, 
                           COUNT(*) as count, 
                           SUM(amount) as total_amount
                    FROM collections 
                    WHERE collection_date BETWEEN ? AND ?
                    GROUP BY status
                    ORDER BY total_amount DESC";
            break;
        case 'payment_method':
            $sql = "SELECT payment_method, 
                           COUNT(*) as count, 
                           SUM(amount) as total_amount
                    FROM collections 
                    WHERE status = 'completed' 
                    AND collection_date BETWEEN ? AND ?
                    GROUP BY payment_method
                    ORDER BY total_amount DESC";
            break;
        default:
            $sql = "SELECT DATE(collection_date) as period, 
                           COUNT(*) as count, 
                           SUM(amount) as total_amount
                    FROM collections 
                    WHERE collection_date BETWEEN ? AND ?
                    GROUP BY period
                    ORDER BY period DESC";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
    
    $response = [
        'report_data' => $report_data,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
        'group_by' => $group_by
    ];
    
    sendResponse(200, $response, 'Collections report generated successfully');
}

/**
 * Create new collection request
 */
function createCollection($conn, $data) {
    $required = ['customer_name', 'amount', 'service_type', 'due_date', 'payment_method'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    // Generate unique request ID
    $request_id = 'COL-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    $sql = "INSERT INTO collections 
            (request_id, customer_name, amount, service_type, due_date, 
             payment_method, description, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssdssss', 
        $request_id,
        $data['customer_name'],
        floatval($data['amount']),
        $data['service_type'],
        $data['due_date'],
        $data['payment_method'],
        $data['description'] ?? ''
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $collection_id = mysqli_insert_id($conn);
        sendResponse(201, [
            'collection_id' => $collection_id,
            'request_id' => $request_id
        ], 'Collection request created successfully');
    } else {
        sendResponse(500, [], 'Failed to create collection request: ' . mysqli_error($conn));
    }
}

/**
 * Update collection status
 */
function updateCollectionStatus($conn, $data) {
    $required = ['id', 'status'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $allowed_statuses = ['pending', 'completed', 'failed', 'refund', 'for_approval', 'overdue'];
    if (!in_array($data['status'], $allowed_statuses)) {
        sendResponse(400, [], 'Invalid status. Allowed: ' . implode(', ', $allowed_statuses));
    }
    
    $additional_fields = '';
    $params = [];
    $types = 's';
    
    // If status is completed, set collection date
    if ($data['status'] === 'completed') {
        $additional_fields = ', collection_date = ?';
        $params[] = date('Y-m-d');
        $types .= 's';
    }
    
    $params[] = $data['status'];
    $params[] = $data['id'];
    $types .= 'i';
    
    $sql = "UPDATE collections SET status = ? $additional_fields, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Collection status updated successfully');
    } else {
        sendResponse(500, [], 'Failed to update collection status: ' . mysqli_error($conn));
    }
}

/**
 * Process payment for collection
 */
function processPayment($conn, $data) {
    $required = ['id', 'payment_amount', 'payment_date'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $collection_id = intval($data['id']);
    $payment_amount = floatval($data['payment_amount']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get collection details
        $sql = "SELECT amount, status FROM collections WHERE id = ? FOR UPDATE";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $collection_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $collection = mysqli_fetch_assoc($result);
        
        if (!$collection) {
            throw new Exception('Collection not found');
        }
        
        $collection_amount = floatval($collection['amount']);
        
        // Validate payment amount
        if ($payment_amount > $collection_amount) {
            throw new Exception('Payment amount exceeds collection amount');
        }
        
        $status = $payment_amount >= $collection_amount ? 'completed' : 'pending';
        
        // Update collection
        $sql = "UPDATE collections 
                SET status = ?, 
                    collection_date = ?,
                    payment_reference = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssi', 
            $status,
            $data['payment_date'],
            $data['payment_reference'] ?? '',
            $collection_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update collection');
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        sendResponse(200, [
            'collection_id' => $collection_id,
            'payment_amount' => $payment_amount,
            'status' => $status
        ], 'Payment processed successfully');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendResponse(500, [], 'Payment processing failed: ' . $e->getMessage());
    }
}

/**
 * Update collection details
 */
function updateCollection($conn, $data) {
    $required = ['id'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $updatable_fields = [
        'customer_name', 'amount', 'service_type', 'due_date', 
        'payment_method', 'description'
    ];
    
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($updatable_fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= 's';
        }
    }
    
    if (empty($updates)) {
        sendResponse(400, [], 'No fields to update');
    }
    
    $params[] = $data['id'];
    $types .= 'i';
    
    $sql = "UPDATE collections SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Collection updated successfully');
    } else {
        sendResponse(500, [], 'Failed to update collection: ' . mysqli_error($conn));
    }
}

/**
 * Bulk update collections
 */
function bulkUpdateCollections($conn, $data) {
    if (!isset($data['collections']) || !is_array($data['collections'])) {
        sendResponse(400, [], 'Collections array is required');
    }
    
    $results = [
        'successful' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($data['collections'] as $index => $collection) {
            try {
                if (!isset($collection['id'])) {
                    throw new Exception('Collection ID is required');
                }
                
                $updatable_fields = ['status', 'payment_method', 'due_date'];
                $updates = [];
                $params = [];
                $types = '';
                
                foreach ($updatable_fields as $field) {
                    if (isset($collection[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $collection[$field];
                        $types .= 's';
                    }
                }
                
                if (empty($updates)) {
                    throw new Exception('No fields to update');
                }
                
                $params[] = $collection['id'];
                $types .= 'i';
                
                $sql = "UPDATE collections SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to update collection');
                }
                
                $results['successful']++;
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        mysqli_commit($conn);
        sendResponse(200, $results, 'Bulk update completed');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendResponse(500, [], 'Bulk update failed: ' . $e->getMessage());
    }
}

/**
 * Import collections from other systems
 */
function importCollections($conn, $data) {
    if (!isset($data['collections']) || !is_array($data['collections'])) {
        sendResponse(400, [], 'Collections array is required');
    }
    
    $results = [
        'imported' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($data['collections'] as $index => $collection) {
            try {
                $required = ['customer_name', 'amount', 'service_type', 'due_date'];
                $missing = validateRequiredFields($required, $collection);
                
                if ($missing) {
                    throw new Exception('Missing fields: ' . implode(', ', $missing));
                }
                
                $request_id = $collection['request_id'] ?? 'IMP-' . date('Ymd') . '-' . strtoupper(uniqid());
                
                $sql = "INSERT INTO collections 
                        (request_id, customer_name, amount, service_type, due_date, 
                         payment_method, description, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'ssdsssss', 
                    $request_id,
                    $collection['customer_name'],
                    floatval($collection['amount']),
                    $collection['service_type'],
                    $collection['due_date'],
                    $collection['payment_method'] ?? 'Unknown',
                    $collection['description'] ?? '',
                    $collection['status'] ?? 'pending'
                );
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to insert collection');
                }
                
                $results['imported']++;
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        mysqli_commit($conn);
        sendResponse(200, $results, 'Collections import completed');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendResponse(500, [], 'Import failed: ' . $e->getMessage());
    }
}

/**
 * Delete collection
 */
function deleteCollection($conn, $data) {
    $collection_id = intval($data['id'] ?? 0);
    
    if (!$collection_id) {
        sendResponse(400, [], 'Collection ID is required');
    }
    
    $sql = "DELETE FROM collections WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $collection_id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Collection deleted successfully');
    } else {
        sendResponse(500, [], 'Failed to delete collection: ' . mysqli_error($conn));
    }
}

/**
 * Bulk delete collections
 */
function bulkDeleteCollections($conn, $data) {
    if (!isset($data['ids']) || !is_array($data['ids'])) {
        sendResponse(400, [], 'Collection IDs array is required');
    }
    
    $placeholders = str_repeat('?,', count($data['ids']) - 1) . '?';
    $sql = "DELETE FROM collections WHERE id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($data['ids'])), ...$data['ids']);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_affected_rows($conn);
        sendResponse(200, ['deleted_count' => $affected_rows], 'Collections deleted successfully');
    } else {
        sendResponse(500, [], 'Failed to delete collections: ' . mysqli_error($conn));
    }
}

/**
 * Export collections to CSV or PDF
 */
function exportCollections($conn) {
    $format = $_GET['format'] ?? 'csv';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    $filters = [];
    $params = [];
    $types = '';
    
    if (!empty($start_date)) {
        $filters[] = "due_date >= ?";
        $params[] = $start_date;
        $types .= 's';
    }
    
    if (!empty($end_date)) {
        $filters[] = "due_date <= ?";
        $params[] = $end_date;
        $types .= 's';
    }
    
    $where_clause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    $sql = "SELECT * FROM collections $where_clause ORDER BY due_date DESC";
    
    if ($params) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $sql);
    }
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    if ($format === 'csv') {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="collections_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        if (!empty($collections)) {
            fputcsv($output, array_keys($collections[0]));
        }
        
        // Add data rows
        foreach ($collections as $collection) {
            fputcsv($output, $collection);
        }
        
        fclose($output);
        exit;
    } else {
        // For PDF, you would need a PDF library like TCPDF or Dompdf
        // This is a simplified response - implement PDF generation as needed
        sendResponse(200, [
            'collections' => $collections,
            'format' => 'pdf',
            'message' => 'PDF export would be generated here with proper PDF library'
        ], 'PDF export placeholder');
    }
}

// Close connection
mysqli_close($conn);
?>