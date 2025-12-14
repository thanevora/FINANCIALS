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

// Authentication middleware (optional - for internal departments)
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
            getAccountsReceivableStats($conn);
            break;
        case 'get_invoices':
            getInvoices($conn);
            break;
        case 'get_invoice':
            getInvoice($conn);
            break;
        case 'get_aging_analysis':
            getAgingAnalysis($conn);
            break;
        case 'get_customers':
            getCustomers($conn);
            break;
        case 'get_payments':
            getPayments($conn);
            break;
        case 'get_collections_report':
            getCollectionsReport($conn);
            break;
        default:
            sendResponse(400, [], 'Invalid action for GET request');
    }
}

// Handle POST requests
function handlePostRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    switch ($action) {
        case 'create_invoice':
            createInvoice($conn, $input);
            break;
        case 'record_payment':
            recordPayment($conn, $input);
            break;
        case 'create_customer':
            createCustomer($conn, $input);
            break;
        case 'bulk_import_payments':
            bulkImportPayments($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for POST request');
    }
}

// Handle PUT requests
function handlePutRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update_invoice':
            updateInvoice($conn, $input);
            break;
        case 'update_invoice_status':
            updateInvoiceStatus($conn, $input);
            break;
        case 'update_customer':
            updateCustomer($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for PUT request');
    }
}

// Handle DELETE requests
function handleDeleteRequest($action, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete_invoice':
            deleteInvoice($conn, $input);
            break;
        case 'delete_customer':
            deleteCustomer($conn, $input);
            break;
        default:
            sendResponse(400, [], 'Invalid action for DELETE request');
    }
}

// API Endpoint Implementations

/**
 * Get comprehensive accounts receivable statistics
 */
function getAccountsReceivableStats($conn) {
    $stats = [];
    
    // Total Receivables
    $sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status IN ('pending', 'overdue')";
    $result = mysqli_query($conn, $sql);
    $stats['total_receivables'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Overdue Amount
    $sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status = 'overdue'";
    $result = mysqli_query($conn, $sql);
    $stats['overdue_amount'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Due This Week
    $current_week_start = date('Y-m-d', strtotime('this week'));
    $current_week_end = date('Y-m-d', strtotime('this week +6 days'));
    $sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE due_date BETWEEN '$current_week_start' AND '$current_week_end' AND status = 'pending'";
    $result = mysqli_query($conn, $sql);
    $stats['due_this_week'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Collection Rate
    $sql = "SELECT COUNT(*) as total_count, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count FROM accounts_receivable";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $paid_count = $row['paid_count'] ?? 0;
        $total_count = $row['total_count'] ?? 1;
        $stats['collection_rate'] = $total_count > 0 ? round(($paid_count / $total_count) * 100, 1) : 0;
    } else {
        $stats['collection_rate'] = 0;
    }
    
    // Average Collection Period
    $sql = "SELECT AVG(DATEDIFF(payment_date, invoice_date)) as avg_days FROM accounts_receivable WHERE status = 'paid' AND payment_date IS NOT NULL";
    $result = mysqli_query($conn, $sql);
    $stats['avg_collection_period'] = $result ? round((mysqli_fetch_assoc($result)['avg_days'] ?? 0)) : 0;
    
    // Customer Count
    $sql = "SELECT COUNT(DISTINCT customer_name) as customer_count FROM accounts_receivable";
    $result = mysqli_query($conn, $sql);
    $stats['customer_count'] = $result ? (mysqli_fetch_assoc($result)['customer_count'] ?? 0) : 0;
    
    // Invoice Counts
    $sql = "SELECT 
            COUNT(*) as total_invoices,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed_count
            FROM accounts_receivable";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $stats['total_invoices'] = $row['total_invoices'] ?? 0;
        $stats['paid_invoices'] = $row['paid_count'] ?? 0;
        $stats['pending_invoices'] = $row['pending_count'] ?? 0;
        $stats['overdue_invoices'] = $row['overdue_count'] ?? 0;
        $stats['disputed_invoices'] = $row['disputed_count'] ?? 0;
    }
    
    // Disputed Amount
    $sql = "SELECT SUM(balance) as total FROM accounts_receivable WHERE status = 'disputed'";
    $result = mysqli_query($conn, $sql);
    $stats['disputed_amount'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    // Credit Balance
    $sql = "SELECT SUM(credit_balance) as total FROM accounts_receivable";
    $result = mysqli_query($conn, $sql);
    $stats['credit_balance'] = $result ? (mysqli_fetch_assoc($result)['total'] ?? 0) : 0;
    
    sendResponse(200, $stats, 'Accounts receivable statistics retrieved successfully');
}

/**
 * Get invoices with filtering and pagination
 */
function getInvoices($conn) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    $params = [];
    
    // Status filter
    if (!empty($_GET['status'])) {
        $statuses = explode(',', $_GET['status']);
        $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
        $filters[] = "status IN ($placeholders)";
        $params = array_merge($params, $statuses);
    }
    
    // Customer filter
    if (!empty($_GET['customer_name'])) {
        $filters[] = "customer_name LIKE ?";
        $params[] = '%' . $_GET['customer_name'] . '%';
    }
    
    // Date range filters
    if (!empty($_GET['start_date'])) {
        $filters[] = "invoice_date >= ?";
        $params[] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $filters[] = "invoice_date <= ?";
        $params[] = $_GET['end_date'];
    }
    
    $where_clause = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM accounts_receivable $where_clause";
    $stmt = mysqli_prepare($conn, $count_sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_count = mysqli_fetch_assoc($result)['total'] ?? 0;
    
    // Get invoices
    $sql = "SELECT * FROM accounts_receivable $where_clause ORDER BY due_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('s', count($params) - 2) . 'ii';
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $invoices = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $invoices[] = $row;
    }
    
    $response = [
        'invoices' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ];
    
    sendResponse(200, $response, 'Invoices retrieved successfully');
}

/**
 * Get single invoice by ID
 */
function getInvoice($conn) {
    $invoice_id = intval($_GET['id'] ?? 0);
    
    if (!$invoice_id) {
        sendResponse(400, [], 'Invoice ID is required');
    }
    
    $sql = "SELECT * FROM accounts_receivable WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $invoice_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $invoice = mysqli_fetch_assoc($result);
    
    if (!$invoice) {
        sendResponse(404, [], 'Invoice not found');
    }
    
    sendResponse(200, $invoice, 'Invoice retrieved successfully');
}

/**
 * Get aging analysis
 */
function getAgingAnalysis($conn) {
    $aging_data = [
        'current' => ['amount' => 0, 'count' => 0],
        '1_30_days' => ['amount' => 0, 'count' => 0],
        '31_60_days' => ['amount' => 0, 'count' => 0],
        '60_plus_days' => ['amount' => 0, 'count' => 0]
    ];
    
    // Current
    $sql = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
            FROM accounts_receivable 
            WHERE status IN ('pending', 'overdue') 
            AND DATEDIFF(CURDATE(), due_date) <= 0";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $aging_data['current'] = $row;
    }
    
    // 1-30 Days
    $sql = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
            FROM accounts_receivable 
            WHERE status IN ('pending', 'overdue') 
            AND DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 30";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $aging_data['1_30_days'] = $row;
    }
    
    // 31-60 Days
    $sql = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
            FROM accounts_receivable 
            WHERE status IN ('pending', 'overdue') 
            AND DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $aging_data['31_60_days'] = $row;
    }
    
    // 60+ Days
    $sql = "SELECT COALESCE(SUM(balance), 0) as amount, COUNT(*) as count 
            FROM accounts_receivable 
            WHERE status IN ('pending', 'overdue') 
            AND DATEDIFF(CURDATE(), due_date) > 60";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $aging_data['60_plus_days'] = $row;
    }
    
    sendResponse(200, $aging_data, 'Aging analysis retrieved successfully');
}

/**
 * Get customers list
 */
function getCustomers($conn) {
    $sql = "SELECT DISTINCT customer_name, customer_email, customer_phone, 
                   COUNT(*) as total_invoices,
                   SUM(balance) as total_balance
            FROM accounts_receivable 
            GROUP BY customer_name, customer_email, customer_phone
            ORDER BY customer_name";
    
    $result = mysqli_query($conn, $sql);
    $customers = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    
    sendResponse(200, $customers, 'Customers retrieved successfully');
}

/**
 * Get payments history
 */
function getPayments($conn) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT ar.invoice_number, ar.customer_name, ar.payment_date, 
                   ar.payment_method, ar.amount as invoice_amount,
                   (ar.amount - ar.balance) as payment_amount,
                   ar.reference_number
            FROM accounts_receivable ar
            WHERE ar.status = 'paid' AND ar.payment_date IS NOT NULL
            ORDER BY ar.payment_date DESC
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    
    sendResponse(200, $payments, 'Payments retrieved successfully');
}

/**
 * Get collections report
 */
function getCollectionsReport($conn) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
    
    $sql = "SELECT 
            DATE(payment_date) as collection_date,
            COUNT(*) as invoice_count,
            SUM(amount - balance) as total_collected,
            payment_method,
            customer_name
            FROM accounts_receivable 
            WHERE status = 'paid' 
            AND payment_date BETWEEN ? AND ?
            GROUP BY collection_date, payment_method, customer_name
            ORDER BY collection_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    sendResponse(200, [
        'collections' => $collections,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ]
    ], 'Collections report retrieved successfully');
}

/**
 * Create new invoice
 */
function createInvoice($conn, $data) {
    $required = ['customer_name', 'invoice_number', 'amount', 'invoice_date', 'due_date', 'service_type'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    // Calculate net amount
    $amount = floatval($data['amount']);
    $tax_amount = floatval($data['tax_amount'] ?? 0);
    $discount_amount = floatval($data['discount_amount'] ?? 0);
    $net_amount = $amount + $tax_amount - $discount_amount;
    
    $sql = "INSERT INTO accounts_receivable 
            (invoice_number, customer_name, amount, balance, invoice_date, due_date, 
             service_type, notes, customer_email, customer_phone, tax_amount, 
             discount_amount, net_amount, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssddsssssdddd', 
        $data['invoice_number'],
        $data['customer_name'],
        $amount,
        $net_amount, // Initial balance equals net amount
        $data['invoice_date'],
        $data['due_date'],
        $data['service_type'],
        $data['notes'] ?? '',
        $data['customer_email'] ?? '',
        $data['customer_phone'] ?? '',
        $tax_amount,
        $discount_amount,
        $net_amount
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $invoice_id = mysqli_insert_id($conn);
        sendResponse(201, ['invoice_id' => $invoice_id], 'Invoice created successfully');
    } else {
        sendResponse(500, [], 'Failed to create invoice: ' . mysqli_error($conn));
    }
}

/**
 * Record payment for an invoice
 */
function recordPayment($conn, $data) {
    $required = ['invoice_id', 'payment_amount', 'payment_date', 'payment_method'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $invoice_id = intval($data['invoice_id']);
    $payment_amount = floatval($data['payment_amount']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get current invoice details
        $sql = "SELECT balance, amount FROM accounts_receivable WHERE id = ? FOR UPDATE";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $invoice_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $invoice = mysqli_fetch_assoc($result);
        
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }
        
        $current_balance = floatval($invoice['balance']);
        $invoice_amount = floatval($invoice['amount']);
        
        if ($payment_amount > $current_balance) {
            throw new Exception('Payment amount exceeds invoice balance');
        }
        
        $new_balance = $current_balance - $payment_amount;
        $status = $new_balance <= 0 ? 'paid' : 'pending';
        
        // Update invoice
        $sql = "UPDATE accounts_receivable 
                SET balance = ?, 
                    payment_date = ?, 
                    payment_method = ?, 
                    reference_number = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'dssssi', 
            $new_balance,
            $data['payment_date'],
            $data['payment_method'],
            $data['reference_number'] ?? '',
            $status,
            $invoice_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update invoice');
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        sendResponse(200, [
            'invoice_id' => $invoice_id,
            'payment_amount' => $payment_amount,
            'new_balance' => $new_balance,
            'status' => $status
        ], 'Payment recorded successfully');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendResponse(500, [], 'Payment failed: ' . $e->getMessage());
    }
}

/**
 * Create customer (if you want to maintain a separate customers table)
 */
function createCustomer($conn, $data) {
    $required = ['name', 'email'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    // This is if you decide to maintain a separate customers table
    // For now, we'll just return success since we're using customer_name directly
    sendResponse(201, [], 'Customer created successfully (integrated with invoices)');
}

/**
 * Bulk import payments from other departments
 */
function bulkImportPayments($conn, $data) {
    if (!isset($data['payments']) || !is_array($data['payments'])) {
        sendResponse(400, [], 'Payments array is required');
    }
    
    $results = [
        'successful' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($data['payments'] as $index => $payment) {
            try {
                $required = ['invoice_number', 'payment_amount', 'payment_date', 'payment_method'];
                $missing = validateRequiredFields($required, $payment);
                
                if ($missing) {
                    throw new Exception('Missing fields: ' . implode(', ', $missing));
                }
                
                // Find invoice by invoice number
                $sql = "SELECT id, balance FROM accounts_receivable WHERE invoice_number = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 's', $payment['invoice_number']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $invoice = mysqli_fetch_assoc($result);
                
                if (!$invoice) {
                    throw new Exception('Invoice not found: ' . $payment['invoice_number']);
                }
                
                // Process payment
                $payment_data = [
                    'invoice_id' => $invoice['id'],
                    'payment_amount' => $payment['payment_amount'],
                    'payment_date' => $payment['payment_date'],
                    'payment_method' => $payment['payment_method'],
                    'reference_number' => $payment['reference_number'] ?? ''
                ];
                
                // Reuse the recordPayment logic
                $current_balance = floatval($invoice['balance']);
                $payment_amount = floatval($payment['payment_amount']);
                
                if ($payment_amount > $current_balance) {
                    throw new Exception('Payment amount exceeds invoice balance');
                }
                
                $new_balance = $current_balance - $payment_amount;
                $status = $new_balance <= 0 ? 'paid' : 'pending';
                
                $sql = "UPDATE accounts_receivable 
                        SET balance = ?, 
                            payment_date = ?, 
                            payment_method = ?, 
                            reference_number = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'dssssi', 
                    $new_balance,
                    $payment['payment_date'],
                    $payment['payment_method'],
                    $payment['reference_number'] ?? '',
                    $status,
                    $invoice['id']
                );
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to update invoice');
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
        sendResponse(200, $results, 'Bulk payment import completed');
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendResponse(500, [], 'Bulk import failed: ' . $e->getMessage());
    }
}

/**
 * Update invoice
 */
function updateInvoice($conn, $data) {
    $required = ['id'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $updatable_fields = [
        'customer_name', 'amount', 'invoice_date', 'due_date', 'service_type',
        'notes', 'customer_email', 'customer_phone', 'tax_amount', 'discount_amount'
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
    
    $sql = "UPDATE accounts_receivable SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Invoice updated successfully');
    } else {
        sendResponse(500, [], 'Failed to update invoice: ' . mysqli_error($conn));
    }
}

/**
 * Update invoice status
 */
function updateInvoiceStatus($conn, $data) {
    $required = ['id', 'status'];
    $missing = validateRequiredFields($required, $data);
    
    if ($missing) {
        sendResponse(400, [], 'Missing required fields: ' . implode(', ', $missing));
    }
    
    $allowed_statuses = ['pending', 'paid', 'overdue', 'disputed'];
    if (!in_array($data['status'], $allowed_statuses)) {
        sendResponse(400, [], 'Invalid status. Allowed: ' . implode(', ', $allowed_statuses));
    }
    
    $sql = "UPDATE accounts_receivable SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $data['status'], $data['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Invoice status updated successfully');
    } else {
        sendResponse(500, [], 'Failed to update invoice status: ' . mysqli_error($conn));
    }
}

/**
 * Delete invoice
 */
function deleteInvoice($conn, $data) {
    $invoice_id = intval($data['id'] ?? 0);
    
    if (!$invoice_id) {
        sendResponse(400, [], 'Invoice ID is required');
    }
    
    $sql = "DELETE FROM accounts_receivable WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $invoice_id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, [], 'Invoice deleted successfully');
    } else {
        sendResponse(500, [], 'Failed to delete invoice: ' . mysqli_error($conn));
    }
}

// Close connection
mysqli_close($conn);
?>