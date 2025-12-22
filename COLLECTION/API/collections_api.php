<?php
// collections_api.php - Enhanced for Public API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
include("../API_gateway.php");
$db_name = "fina_budget";

if (!isset($connections[$db_name])) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$conn = $connections[$db_name];

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get API key from headers or parameters
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';

// Validate API key (enhance this function for production)
if (!validateApiKey($api_key)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing API key',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Route based on method and action
if ($method === 'GET') {
    handleGetRequest($conn);
} else if ($method === 'POST') {
    handlePostRequest($conn);
} else if ($method === 'PUT') {
    handlePutRequest($conn);
} else if ($method === 'DELETE') {
    handleDeleteRequest($conn);
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// ============ HELPER FUNCTIONS ============

function validateApiKey($api_key) {
    // In production, you should:
    // 1. Store API keys in a database table
    // 2. Check against the database
    // 3. Implement rate limiting
    // 4. Check expiration dates
    
    // For now, using a simple validation
    // You can create an 'api_keys' table and validate against it
    $valid_keys = [
        'COLLECTIONS_API_KEY_2024' => ['name' => 'Internal System', 'permissions' => ['read', 'write', 'delete']],
        'EXTERNAL_PARTNER_12345' => ['name' => 'External Partner', 'permissions' => ['read', 'write']],
        'MOBILE_APP_67890' => ['name' => 'Mobile App', 'permissions' => ['write']]
    ];
    
    return isset($valid_keys[$api_key]);
}

function handleGetRequest($conn) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_stats':
            getCollectionsStats($conn);
            break;
        case 'get_collections':
            getCollections($conn);
            break;
        case 'get_collection':
            getCollectionById($conn);
            break;
        case 'get_by_status':
            getCollectionsByStatus($conn);
            break;
        case 'get_by_customer':
            getCollectionsByCustomer($conn);
            break;
        case 'get_pending':
            getPendingCollections($conn);
            break;
        case 'get_completed':
            getCompletedCollections($conn);
            break;
        case 'export_csv':
            exportCollectionsCSV($conn);
            break;
        case 'ping':
            // Simple health check
            echo json_encode([
                'status' => 'success',
                'message' => 'API is running',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0.0'
            ]);
            break;
        default:
            // Show available endpoints if no action specified
            echo json_encode([
                'status' => 'success',
                'endpoints' => [
                    'GET' => [
                        '?action=get_stats' => 'Get collection statistics',
                        '?action=get_collections&limit=50' => 'Get all collections (paginated)',
                        '?action=get_collection&id=123' => 'Get specific collection',
                        '?action=get_by_status&status=pending' => 'Get collections by status',
                        '?action=get_by_customer&customer=John' => 'Get collections by customer',
                        '?action=get_pending' => 'Get pending collections',
                        '?action=get_completed' => 'Get completed collections',
                        '?action=export_csv' => 'Export collections as CSV',
                        '?action=ping' => 'Health check'
                    ],
                    'POST' => 'Create new collection (send JSON data)',
                    'PUT' => 'Update collection (send ?id=123 and JSON data)',
                    'DELETE' => 'Delete collection (send ?id=123)'
                ],
                'authentication' => 'Include X-API-Key header',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
}

function handlePostRequest($conn) {
    // Check if it's the old form submission or new API call
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_collection' && !empty($_POST['customer_name'])) {
        // Handle old form submission for backward compatibility
        createCollectionForm($conn);
    } else {
        // Handle new API JSON submission
        createCollectionAPI($conn);
    }
}

function handlePutRequest($conn) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Get JSON input
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
    
    // Check if collection exists
    $check_sql = "SELECT id FROM collections WHERE id = $id";
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
    
    // Build update query
    $updates = [];
    
    if (isset($input['customer_name'])) {
        $updates[] = "customer_name = '" . mysqli_real_escape_string($conn, $input['customer_name']) . "'";
    }
    
    if (isset($input['amount'])) {
        $updates[] = "amount = " . floatval($input['amount']);
    }
    
    if (isset($input['service_type'])) {
        $updates[] = "service_type = '" . mysqli_real_escape_string($conn, $input['service_type']) . "'";
    }
    
    if (isset($input['due_date'])) {
        $updates[] = "due_date = '" . mysqli_real_escape_string($conn, $input['due_date']) . "'";
    }
    
    if (isset($input['status'])) {
        $status = mysqli_real_escape_string($conn, $input['status']);
        $updates[] = "status = '$status'";
        
        // Auto-set collection date if status changes to completed
        if ($status === 'completed') {
            $updates[] = "collection_date = NOW()";
        }
    }
    
    if (isset($input['payment_method'])) {
        $updates[] = "payment_method = '" . mysqli_real_escape_string($conn, $input['payment_method']) . "'";
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = '" . mysqli_real_escape_string($conn, $input['description']) . "'";
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

function handleDeleteRequest($conn) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Check if collection exists
    $check_sql = "SELECT id FROM collections WHERE id = $id";
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
    
    // Soft delete (update status to 'deleted')
    $sql = "UPDATE collections SET status = 'deleted', updated_at = NOW() WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Collection deleted successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete collection: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// ============ YOUR EXISTING FUNCTIONS (UPDATED) ============

function getCollectionsStats($conn) {
    $stats = [];
    
    // Total collections
    $sql = "SELECT SUM(amount) as total FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_collections'] = $row['total'] ?? 0;
    
    // Pending collections
    $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['pending_collections'] = $row['count'] ?? 0;
    $stats['pending_amount'] = $row['total'] ?? 0;
    
    // This month collections
    $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections 
            WHERE status = 'completed' 
            AND MONTH(collection_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(collection_date) = YEAR(CURRENT_DATE())";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['successful_this_month'] = $row['total'] ?? 0;
    $stats['monthly_count'] = $row['count'] ?? 0;
    
    // Total and completed counts
    $sql = "SELECT COUNT(*) as total_count FROM collections WHERE status != 'deleted'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['total_count'] = $row['total_count'] ?? 0;
    
    $sql = "SELECT COUNT(*) as completed_count FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $stats['completed_count'] = $row['completed_count'] ?? 0;
    
    // Collection rate
    if ($stats['total_count'] > 0) {
        $stats['collection_rate'] = round(($stats['completed_count'] / $stats['total_count']) * 100, 2);
    } else {
        $stats['collection_rate'] = 0;
    }
    
    // Transaction status breakdown
    $statuses = ['completed', 'pending', 'failed', 'for_approval', 'overdue', 'refund'];
    $stats['transaction_status'] = [];
    
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = '$status'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        $stats['transaction_status'][$status] = [
            'count' => $row['count'] ?? 0,
            'amount' => $row['total'] ?? 0
        ];
    }
    
    // Payment methods
    $sql = "SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as amount
            FROM collections 
            WHERE status = 'completed'
            GROUP BY payment_method";
    $result = mysqli_query($conn, $sql);
    
    $payment_methods = [];
    $total_paid = $stats['total_collections'];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $percentage = $total_paid > 0 ? round(($row['amount'] / $total_paid) * 100, 2) : 0;
        $payment_methods[] = [
            'payment_method' => $row['payment_method'],
            'count' => $row['count'],
            'amount' => $row['amount'],
            'percentage' => $percentage
        ];
    }
    
    $stats['payment_methods'] = $payment_methods;
    
    echo json_encode([
        'status' => 'success', 
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function createCollectionForm($conn) {
    // Your existing form handler - keep for backward compatibility
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $amount = floatval($_POST['amount']);
    $service_type = mysqli_real_escape_string($conn, $_POST['service_type']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    
    // Generate unique request ID
    $request_id = 'COL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), 7, 6));
    
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
        'pending',
        '$payment_method',
        '$description',
        NOW(),
        NOW()
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Collection created successfully',
            'request_id' => $request_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to create collection: ' . mysqli_error($conn),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

function createCollectionAPI($conn) {
    // New API handler for external systems
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
    
    // Validate required fields
    $required = ['customer_name', 'amount', 'service_type', 'due_date', 'payment_method'];
    $missing = [];
    
    foreach ($required as $field) {
        if (empty($input[$field])) {
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
    
    // Prepare data
    $customer_name = mysqli_real_escape_string($conn, $input['customer_name']);
    $amount = floatval($input['amount']);
    $service_type = mysqli_real_escape_string($conn, $input['service_type']);
    $due_date = mysqli_real_escape_string($conn, $input['due_date']);
    $payment_method = mysqli_real_escape_string($conn, $input['payment_method']);
    $status = mysqli_real_escape_string($conn, $input['status'] ?? 'pending');
    $description = mysqli_real_escape_string($conn, $input['description'] ?? '');
    
    // Generate request ID
    $request_id = generateRequestId();
    
    // Build SQL
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
        
        // Return the created collection
        $sql = "SELECT * FROM collections WHERE id = $collection_id";
        $result = mysqli_query($conn, $sql);
        $collection = mysqli_fetch_assoc($result);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Collection created successfully via API',
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

// ============ NEW FUNCTIONS FOR ENHANCED API ============

function getCollections($conn) {
    $limit = intval($_GET['limit'] ?? 50);
    $page = intval($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT * FROM collections WHERE status != 'deleted' ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $result = mysqli_query($conn, $sql);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM collections WHERE status != 'deleted'";
    $count_result = mysqli_query($conn, $count_sql);
    $total = mysqli_fetch_assoc($count_result)['total'];
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getCollectionById($conn) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $sql = "SELECT * FROM collections WHERE id = $id AND status != 'deleted'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $collection = mysqli_fetch_assoc($result);
        echo json_encode([
            'status' => 'success',
            'data' => $collection,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Collection not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

function getCollectionsByStatus($conn) {
    $status = $_GET['status'] ?? '';
    
    if (!$status) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Status parameter is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $status = mysqli_real_escape_string($conn, $status);
    $sql = "SELECT * FROM collections WHERE status = '$status' ORDER BY due_date ASC";
    $result = mysqli_query($conn, $sql);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'count' => count($collections),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getCollectionsByCustomer($conn) {
    $customer = $_GET['customer'] ?? '';
    
    if (!$customer) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Customer parameter is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    $customer = mysqli_real_escape_string($conn, $customer);
    $sql = "SELECT * FROM collections WHERE customer_name LIKE '%$customer%' AND status != 'deleted' ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'count' => count($collections),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getPendingCollections($conn) {
    $sql = "SELECT * FROM collections WHERE status = 'pending' ORDER BY due_date ASC";
    $result = mysqli_query($conn, $sql);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'count' => count($collections),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getCompletedCollections($conn) {
    $limit = intval($_GET['limit'] ?? 100);
    $sql = "SELECT * FROM collections WHERE status = 'completed' ORDER BY collection_date DESC LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $collections,
        'count' => count($collections),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function exportCollectionsCSV($conn) {
    $status = $_GET['status'] ?? '';
    
    $where = "WHERE status != 'deleted'";
    if ($status) {
        $status = mysqli_real_escape_string($conn, $status);
        $where .= " AND status = '$status'";
    }
    
    $sql = "SELECT * FROM collections $where ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="collections_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'ID', 'Request ID', 'Customer Name', 'Amount', 'Service Type', 
        'Due Date', 'Status', 'Payment Method', 'Collection Date', 
        'Description', 'Created At', 'Updated At'
    ]);
    
    // Add data
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['id'],
            $row['request_id'],
            $row['customer_name'],
            $row['amount'],
            $row['service_type'],
            $row['due_date'],
            $row['status'],
            $row['payment_method'],
            $row['collection_date'],
            $row['description'],
            $row['created_at'],
            $row['updated_at']
        ]);
    }
    
    fclose($output);
    exit;
}

function generateRequestId() {
    return 'COL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), 7, 6));
}

// Close connection
mysqli_close($conn);
?>