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

// ============ START OF CRUD API ============
// Turn off all error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any stray output
ob_start();

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Cache-Control: no-cache, must-revalidate');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to truncate text safely
function truncateText($text, $maxLength = 1000) {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    // Truncate and add ellipsis
    return substr($text, 0, $maxLength - 3) . '...';
}

// Helper function to check if transaction can be modified
function canModifyTransaction($conn, $id) {
    $sql = "SELECT status FROM collections WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $status = strtolower($row['status']);
        
        // Cannot modify if status is 'completed', 'received', 'for_receivable_approval', or 'for_compliance'
        $protected_statuses = ['completed', 'received', 'for_receivable_approval', 'for_compliance'];
        return !in_array($status, $protected_statuses);
    }
    
    return false;
}

try {
    // Clear any output buffer
    ob_clean();
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Route based on method
    switch ($method) {
        case 'GET':
            handleGetRequest($conn);
            break;
        case 'POST':
            handlePostRequest($conn);
            break;
        case 'PUT':
            handlePutRequest($conn);
            break;
        case 'DELETE':
            handleDeleteRequest($conn);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// End output buffering
ob_end_flush();

// ============ GET REQUEST HANDLER ============
function handleGetRequest($conn) {
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? 0;
    
    switch ($action) {
        case 'get_stats':
            getCollectionStats($conn);
            break;
            
        case 'get_transaction':
            if ($id) {
                getTransactionById($conn, $id);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Transaction ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        default:
            // Default: get all collections
            getAllCollections($conn);
    }
}

// ============ POST REQUEST HANDLER ============
function handlePostRequest($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'No data provided',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'save_compliance':
            saveCompliance($conn, $input);
            break;
            
        case 'create_collection':
            createCollection($conn, $input);
            break;
            
        case 'mark_collected':
            markAsCollected($conn, $input);
            break;
            
        default:
            // Create new collection
            createCollection($conn, $input);
    }
}

// ============ PUT REQUEST HANDLER ============
function handlePutRequest($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'No data provided for update',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    updateCollection($conn, $id, $input);
}

// ============ DELETE REQUEST HANDLER ============
function handleDeleteRequest($conn) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $reason = $input['reason'] ?? 'No reason provided';
    
    deleteCollection($conn, $id, $reason);
}

// ============ CRUD FUNCTIONS ============

// Get collection statistics
function getCollectionStats($conn) {
    $stats = [];
    
    // Total collections (completed only)
    $sql = "SELECT COALESCE(SUM(amount), 0) as total_collections FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_collections'] = floatval($row['total_collections']);
    
    // Pending collections
    $sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM collections WHERE status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['pending_collections'] = intval($row['count']);
    $stats['pending_amount'] = floatval($row['amount']);
    
    // Successful this month
    $sql = "SELECT COALESCE(SUM(amount), 0) as amount FROM collections 
            WHERE status = 'completed' 
            AND MONTH(collection_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(collection_date) = YEAR(CURRENT_DATE())";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['successful_this_month'] = floatval($row['amount']);
    
    // Total count
    $sql = "SELECT COUNT(*) as total FROM collections";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_count'] = intval($row['total']);
    
    // Completed count
    $sql = "SELECT COUNT(*) as completed FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['completed_count'] = intval($row['completed']);
    
    // Calculate collection rate
    $stats['collection_rate'] = $stats['total_count'] > 0 ? 
        round(($stats['completed_count'] / $stats['total_count']) * 100, 2) : 0;
    
    // Monthly count
    $sql = "SELECT COUNT(*) as monthly FROM collections 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['monthly_count'] = intval($row['monthly']);
    
    // Transaction status breakdown
    $statuses = ['completed', 'pending', 'failed', 'for_compliance', 'overdue', 'refund', 'for_receivable_approval', 'received'];
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
                FROM collections WHERE LOWER(status) = '$status'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $stats['transaction_status'][$status] = [
            'count' => intval($row['count']),
            'amount' => floatval($row['amount'])
        ];
    }
    
    // Payment methods breakdown
    $sql = "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
            FROM collections 
            WHERE payment_method IS NOT NULL AND payment_method != ''
            GROUP BY payment_method";
    $result = mysqli_query($conn, $sql);
    $payment_methods = [];
    $total_payment_amount = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $payment_methods[] = [
            'payment_method' => $row['payment_method'],
            'count' => intval($row['count']),
            'amount' => floatval($row['amount'])
        ];
        $total_payment_amount += floatval($row['amount']);
    }
    
    // Calculate percentages
    foreach ($payment_methods as &$method) {
        $method['percentage'] = $total_payment_amount > 0 ? 
            round(($method['amount'] / $total_payment_amount) * 100, 2) : 0;
    }
    
    $stats['payment_methods'] = $payment_methods;
    
    echo json_encode([
        'status' => 'success',
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Get transaction by ID
function getTransactionById($conn, $id) {
    $sql = "SELECT * FROM collections WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $transaction = mysqli_fetch_assoc($result);
        
        // Convert amount to float
        $transaction['amount'] = floatval($transaction['amount']);
        
        echo json_encode([
            'status' => 'success',
            'data' => $transaction,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Get all collections
function getAllCollections($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
    
    $where = '';
    if ($status) {
        $where = "WHERE status = '$status'";
    }
    
    $sql = "SELECT * FROM collections $where ORDER BY due_date DESC LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['amount'] = floatval($row['amount']);
        $collections[] = $row;
    }
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM collections $where";
    $count_result = mysqli_query($conn, $count_sql);
    $count_row = mysqli_fetch_assoc($count_result);
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'total' => intval($count_row['total']),
        'limit' => $limit,
        'offset' => $offset,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Create new collection
function createCollection($conn, $data) {
    // Validate required fields
    $required = ['customer_name', 'amount', 'service_type', 'due_date', 'payment_method'];
    $missing = [];
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields: ' . implode(', ', $missing),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Generate request ID
    $request_id = 'COL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), 7, 6));
    
    // Prepare data - truncate description to prevent overflow
    $customer_name = mysqli_real_escape_string($conn, $data['customer_name']);
    $amount = floatval($data['amount']);
    $service_type = mysqli_real_escape_string($conn, $data['service_type']);
    $due_date = mysqli_real_escape_string($conn, $data['due_date']);
    $payment_method = mysqli_real_escape_string($conn, $data['payment_method']);
    $status = mysqli_real_escape_string($conn, $data['status'] ?? 'pending');
    $description = truncateText(mysqli_real_escape_string($conn, $data['description'] ?? ''), 1000);
    
    // Insert query
    $sql = "INSERT INTO collections (
        request_id, 
        customer_name, 
        amount, 
        service_type, 
        due_date, 
        status, 
        payment_method, 
        description,
        created_at,
        updated_at
    ) VALUES (
        '$request_id',
        '$customer_name',
        $amount,
        '$service_type',
        '$due_date',
        '$status',
        '$payment_method',
        '$description',
        NOW(),
        NOW()
    )";
    
    if (mysqli_query($conn, $sql)) {
        $collection_id = mysqli_insert_id($conn);
        
        // Get created record
        $sql = "SELECT * FROM collections WHERE id = $collection_id";
        $result = mysqli_query($conn, $sql);
        $collection = mysqli_fetch_assoc($result);
        $collection['amount'] = floatval($collection['amount']);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Collection created successfully',
            'data' => $collection,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create collection: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Update collection
function updateCollection($conn, $id, $data) {
    // Check if collection exists
    $check_sql = "SELECT id, status, request_id, customer_name FROM collections WHERE id = $id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $current_data = mysqli_fetch_assoc($check_result);
    
    // Check if transaction can be modified
    if (!canModifyTransaction($conn, $id)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot modify transaction. Once marked as "Completed", "Received", "For Receivable Approval", or "For Compliance", it cannot be altered.',
            'transaction_info' => [
                'request_id' => $current_data['request_id'],
                'customer_name' => $current_data['customer_name'],
                'current_status' => $current_data['status']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Build update query
    $updates = [];
    
    if (isset($data['customer_name'])) {
        $updates[] = "customer_name = '" . mysqli_real_escape_string($conn, $data['customer_name']) . "'";
    }
    
    if (isset($data['amount'])) {
        $updates[] = "amount = " . floatval($data['amount']);
    }
    
    if (isset($data['service_type'])) {
        $updates[] = "service_type = '" . mysqli_real_escape_string($conn, $data['service_type']) . "'";
    }
    
    if (isset($data['due_date'])) {
        $updates[] = "due_date = '" . mysqli_real_escape_string($conn, $data['due_date']) . "'";
    }
    
    if (isset($data['status'])) {
        $status = mysqli_real_escape_string($conn, $data['status']);
        $updates[] = "status = '$status'";
        
        // Auto-set collection date if status changes to completed
        if ($status === 'completed' && !isset($data['collection_date'])) {
            $updates[] = "collection_date = NOW()";
        }
    }
    
    if (isset($data['payment_method'])) {
        $updates[] = "payment_method = '" . mysqli_real_escape_string($conn, $data['payment_method']) . "'";
    }
    
    if (isset($data['collection_date'])) {
        $collection_date = mysqli_real_escape_string($conn, $data['collection_date']);
        if ($collection_date === 'NULL' || $collection_date === '') {
            $updates[] = "collection_date = NULL";
        } else {
            $updates[] = "collection_date = '$collection_date'";
        }
    }
    
    if (isset($data['description'])) {
        $description = truncateText(mysqli_real_escape_string($conn, $data['description']), 1000);
        $updates[] = "description = '$description'";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'No valid fields to update',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $updates[] = "updated_at = NOW()";
    
    $sql = "UPDATE collections SET " . implode(', ', $updates) . " WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        // Return updated collection
        $sql = "SELECT * FROM collections WHERE id = $id";
        $result = mysqli_query($conn, $sql);
        $collection = mysqli_fetch_assoc($result);
        $collection['amount'] = floatval($collection['amount']);
        
        // Add notes to description if provided (with truncation)
        if (isset($data['notes']) && !empty($data['notes'])) {
            $notes = truncateText(mysqli_real_escape_string($conn, $data['notes']), 500);
            $note_entry = "\n[Note: " . date('Y-m-d H:i:s') . "]\n" . $notes;
            
            // Check current description length and truncate if needed
            $current_desc = $collection['description'] ?? '';
            $max_total_length = 2000; // Adjust based on your database column size
            
            if (strlen($current_desc . $note_entry) > $max_total_length) {
                // Truncate existing description to make room
                $available_space = $max_total_length - strlen($note_entry) - 100; // Leave some buffer
                $current_desc = substr($current_desc, 0, max(0, $available_space));
            }
            
            $update_desc_sql = "UPDATE collections SET description = CONCAT('$current_desc', '$note_entry') WHERE id = $id";
            mysqli_query($conn, $update_desc_sql);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Collection updated successfully',
            'data' => $collection,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update collection: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Delete collection (PERMANENT DELETE)
function deleteCollection($conn, $id, $reason) {
    // Check if collection exists
    $check_sql = "SELECT id, request_id, customer_name, status, amount FROM collections WHERE id = $id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $collection = mysqli_fetch_assoc($check_result);
    
    // Check if transaction can be deleted (only if not in protected status)
    if (!canModifyTransaction($conn, $id)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot delete transaction. Once marked as "Completed", "Received", "For Receivable Approval", or "For Compliance", it cannot be deleted.',
            'transaction_info' => [
                'request_id' => $collection['request_id'],
                'customer_name' => $collection['customer_name'],
                'amount' => floatval($collection['amount']),
                'current_status' => $collection['status']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Log deletion info before deleting
    $request_id = $collection['request_id'];
    $customer_name = mysqli_real_escape_string($conn, $collection['customer_name']);
    $amount = floatval($collection['amount']);
    $reason_safe = mysqli_real_escape_string($conn, $reason);
    $deleted_by = $_SESSION['user_id'] ?? 'system';
    $deleted_at = date('Y-m-d H:i:s');
    
    // Create deletion log entry (optional - if you have a deletion_logs table)
    // $log_sql = "INSERT INTO deletion_logs (request_id, customer_name, amount, reason, deleted_by, deleted_at) 
    //            VALUES ('$request_id', '$customer_name', $amount, '$reason_safe', '$deleted_by', '$deleted_at')";
    // mysqli_query($conn, $log_sql);
    
    // PERMANENT DELETE - Remove from database completely
    $sql = "DELETE FROM collections WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        // Log successful deletion
        error_log("Collection permanently deleted: ID=$id, Request=$request_id, Customer=$customer_name, Amount=$amount, Reason=$reason");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Collection permanently deleted from database',
            'deleted_info' => [
                'request_id' => $request_id,
                'customer_name' => $customer_name,
                'amount' => $amount,
                'reason' => $reason,
                'deleted_at' => $deleted_at
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        error_log("Failed to delete collection: " . mysqli_error($conn));
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete collection: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Save compliance notes
function saveCompliance($conn, $data) {
    $transaction_id = intval($data['transaction_id'] ?? 0);
    $notes = $data['notes'] ?? '';
    $mark_compliant = boolval($data['mark_compliant'] ?? false);
    
    if (!$transaction_id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    if (empty($notes)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Compliance notes are required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Check if transaction can be modified
    if (!canModifyTransaction($conn, $transaction_id)) {
        $check_sql = "SELECT request_id, customer_name, status FROM collections WHERE id = $transaction_id";
        $check_result = mysqli_query($conn, $check_sql);
        $collection = mysqli_fetch_assoc($check_result);
        
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot add compliance notes. Transaction cannot be modified in its current status.',
            'transaction_info' => [
                'request_id' => $collection['request_id'],
                'customer_name' => $collection['customer_name'],
                'current_status' => $collection['status']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Truncate notes to prevent overflow
    $notes = truncateText($notes, 500);
    $notes_safe = mysqli_real_escape_string($conn, $notes);
    
    // Save compliance notes to the description field
    $compliance_note = "\n\n[COMPLIANCE: " . date('Y-m-d H:i:s') . "]\n" . $notes_safe;
    
    // Get current description and check total length
    $desc_sql = "SELECT description FROM collections WHERE id = $transaction_id";
    $desc_result = mysqli_query($conn, $desc_sql);
    $desc_row = mysqli_fetch_assoc($desc_result);
    $current_desc = $desc_row['description'] ?? '';
    
    $max_total_length = 2000; // Adjust based on your database column size
    
    if (strlen($current_desc . $compliance_note) > $max_total_length) {
        // Truncate existing description to make room for compliance note
        $available_space = $max_total_length - strlen($compliance_note) - 100;
        $current_desc = substr($current_desc, 0, max(0, $available_space));
    }
    
    $sql = "UPDATE collections SET description = CONCAT('$current_desc', '$compliance_note'), updated_at = NOW() WHERE id = $transaction_id";
    
    if (mysqli_query($conn, $sql)) {
        $response = [
            'status' => 'success',
            'message' => 'Compliance notes saved successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // If marking as compliant, update status to "for_compliance"
        if ($mark_compliant) {
            $update_sql = "UPDATE collections SET status = 'for_compliance', updated_at = NOW() WHERE id = $transaction_id";
            if (mysqli_query($conn, $update_sql)) {
                $response['message'] = 'Compliance notes saved and status updated to "For Compliance"';
                $response['status_updated'] = true;
                $response['new_status'] = 'for_compliance';
                $response['note'] = 'Transaction is now locked and cannot be modified further.';
            }
        }
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save compliance notes: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Mark as collected
function markAsCollected($conn, $data) {
    $transaction_id = intval($data['transaction_id'] ?? 0);
    
    if (!$transaction_id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaction ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Check if transaction can be modified
    if (!canModifyTransaction($conn, $transaction_id)) {
        $check_sql = "SELECT request_id, customer_name, status FROM collections WHERE id = $transaction_id";
        $check_result = mysqli_query($conn, $check_sql);
        $collection = mysqli_fetch_assoc($check_result);
        
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot mark as collected. Transaction cannot be modified in its current status.',
            'transaction_info' => [
                'request_id' => $collection['request_id'],
                'customer_name' => $collection['customer_name'],
                'current_status' => $collection['status']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Add note about waiting for final review
    $note = "Marked as collected - Waiting for final review of receivable module";
    $note_safe = mysqli_real_escape_string($conn, $note);
    $note_entry = "\n\n[MARKED AS COLLECTED: " . date('Y-m-d H:i:s') . "]\n$note_safe";
    
    // Get current description and check total length
    $desc_sql = "SELECT description FROM collections WHERE id = $transaction_id";
    $desc_result = mysqli_query($conn, $desc_sql);
    $desc_row = mysqli_fetch_assoc($desc_result);
    $current_desc = $desc_row['description'] ?? '';
    
    $max_total_length = 2000; // Adjust based on your database column size
    
    if (strlen($current_desc . $note_entry) > $max_total_length) {
        // Truncate existing description to make room
        $available_space = $max_total_length - strlen($note_entry) - 100;
        $current_desc = substr($current_desc, 0, max(0, $available_space));
    }
    
    // Update status to "for_receivable_approval" and add note
    $sql = "UPDATE collections SET 
            status = 'for_receivable_approval', 
            description = CONCAT('$current_desc', '$note_entry'),
            collection_date = NOW(),
            updated_at = NOW() 
            WHERE id = $transaction_id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Transaction marked as collected and sent for receivable approval',
            'new_status' => 'for_receivable_approval',
            'note' => 'Transaction is now locked and cannot be modified further.',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to mark as collected: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}