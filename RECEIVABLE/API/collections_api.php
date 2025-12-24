<?php
session_start();
include("../../API_gateway.php");

// Database configuration
$db_name = "fina_budget";

// Check database connection
if (!isset($connections[$db_name])) {
    die(json_encode(["status" => "error", "message" => "❌ Connection not found for $db_name"]));
}

$conn = $connections[$db_name];

// Set header for JSON response
header('Content-Type: application/json');

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'get_pending_collections':
            getPendingCollections();
            break;
        case 'update_collection_status':
            updateCollectionStatus();
            break;
        case 'get_collection_details':
            getCollectionDetails();
            break;
        default:
            echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

function getPendingCollections() {
    global $conn;
    
    // Updated to match your exact status
    $sql = "SELECT * FROM collections WHERE status = 'for_receivable_approval' ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        echo json_encode(["status" => "error", "message" => "Query failed: " . mysqli_error($conn)]);
        return;
    }
    
    $collections = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $collections[] = $row;
    }
    
    echo json_encode(["status" => "success", "data" => $collections]);
}

function getCollectionDetails() {
    global $conn;
    
    $id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
    
    if (empty($id)) {
        echo json_encode(["status" => "error", "message" => "Missing collection ID"]);
        return;
    }
    
    $sql = "SELECT * FROM collections WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        echo json_encode(["status" => "error", "message" => "Query failed: " . mysqli_error($conn)]);
        return;
    }
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(["status" => "success", "data" => $row]);
    } else {
        echo json_encode(["status" => "error", "message" => "Collection not found"]);
    }
}

function updateCollectionStatus() {
    global $conn;
    
    $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? '');
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $updated_by = $_SESSION['user_id'] ?? 'system';
    
    if (empty($id) || empty($status)) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        return;
    }
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Since collections table doesn't have a 'notes' column, we'll update description
        if (!empty($notes)) {
            // Get current description to append notes
            $getDescSql = "SELECT description FROM collections WHERE id = '$id'";
            $descResult = mysqli_query($conn, $getDescSql);
            $currentDesc = '';
            
            if ($descResult && $descRow = mysqli_fetch_assoc($descResult)) {
                $currentDesc = $descRow['description'] ?? '';
            }
            
            // Append notes to description
            $newDescription = $currentDesc;
            if (!empty($newDescription)) {
                $newDescription .= ' | ';
            }
            $newDescription .= "Status Update [$status]: " . $notes;
            
            // Update collection status and description
            $sql = "UPDATE collections SET 
                    status = '$status',
                    description = '" . mysqli_real_escape_string($conn, $newDescription) . "',
                    updated_at = NOW()
                    WHERE id = '$id'";
        } else {
            // Update only status if no notes
            $sql = "UPDATE collections SET 
                    status = '$status',
                    updated_at = NOW()
                    WHERE id = '$id'";
        }
        
        $result = mysqli_query($conn, $sql);
        
        if (!$result) {
            throw new Exception("Failed to update collection: " . mysqli_error($conn));
        }
        
        // If status is RECEIVED, create a new record in accounts_receivable table
        if ($status === 'RECEIVED') {
            // First, get the collection details
            $getSql = "SELECT * FROM collections WHERE id = '$id'";
            $getResult = mysqli_query($conn, $getSql);
            
            if ($getResult && $collection = mysqli_fetch_assoc($getResult)) {
                // Generate invoice number
                $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
                
                // Calculate aging category based on due date
                $due_date = $collection['due_date'];
                $today = date('Y-m-d');
                
                if (!empty($due_date)) {
                    $due_date_obj = new DateTime($due_date);
                    $today_obj = new DateTime($today);
                    $interval = $today_obj->diff($due_date_obj);
                    $days_diff = $interval->days;
                    $days_diff = $interval->invert ? -$days_diff : $days_diff;
                    
                    $aging_category = 'current';
                    if ($days_diff < -60) {
                        $aging_category = '60_plus_days';
                    } elseif ($days_diff < -30) {
                        $aging_category = '31_60_days';
                    } elseif ($days_diff < 0) {
                        $aging_category = '1_30_days';
                    }
                } else {
                    $aging_category = 'current';
                }
                
                // Prepare notes from description and status update
                $ar_notes = $collection['description'] ?? '';
                if (!empty($notes)) {
                    if (!empty($ar_notes)) {
                        $ar_notes .= ' | ';
                    }
                    $ar_notes .= "Status changed to RECEIVED: " . $notes;
                }
                
                // Get the collection date or use current date
                $collection_date = !empty($collection['collection_date']) ? 
                    $collection['collection_date'] : date('Y-m-d');
                
                // Calculate collection period (difference between collection date and invoice date)
                $collection_period = 0;
                if (!empty($collection['collection_date'])) {
                    $collection_date_obj = new DateTime($collection['collection_date']);
                    $invoice_date_obj = new DateTime(date('Y-m-d'));
                    $collection_period = $collection_date_obj->diff($invoice_date_obj)->days;
                }
                
                // INSERT INTO accounts_receivable - SET STATUS TO 'RECEIVED' as requested
                $arSql = "INSERT INTO accounts_receivable (
                    invoice_number, 
                    customer_name, 
                    amount, 
                    balance,
                    invoice_date, 
                    due_date, 
                    payment_date,
                    status, 
                    service_type,
                    notes, 
                    payment_method, 
                    customer_id,
                    credit_balance,
                    aging_category,
                    collection_period,
                    dispute_reason,
                    reminder_sent,
                    reminder_date,
                    escalation_level,
                    tax_amount,
                    discount_amount,
                    net_amount,
                    currency,
                    exchange_rate,
                    customer_email,
                    customer_phone,
                    customer_address,
                    reference_number,
                    bank_name,
                    transaction_id,
                    created_by, 
                    updated_by, 
                    created_at, 
                    updated_at
                ) VALUES (
                    '$invoice_number',
                    '" . mysqli_real_escape_string($conn, $collection['customer_name']) . "',
                    " . floatval($collection['amount']) . ",
                    " . floatval($collection['amount']) . ",
                    NOW(),
                    '" . mysqli_real_escape_string($conn, $collection['due_date']) . "',
                    '" . mysqli_real_escape_string($conn, $collection['collection_date']) . "',  -- payment_date set to collection_date
                    'RECEIVED',  -- CHANGED: status set to 'RECEIVED' as requested
                    '" . mysqli_real_escape_string($conn, $collection['service_type']) . "',
                    '" . mysqli_real_escape_string($conn, $ar_notes) . "',
                    '" . mysqli_real_escape_string($conn, $collection['payment_method']) . "',
                    NULL,  -- customer_id (if you have customer table)
                    0.00,  -- credit_balance
                    '$aging_category',
                    $collection_period,
                    NULL,  -- dispute_reason
                    0,     -- reminder_sent
                    NULL,  -- reminder_date
                    0,     -- escalation_level
                    0.00,  -- tax_amount
                    0.00,  -- discount_amount
                    " . floatval($collection['amount']) . ",  -- net_amount (same as amount)
                    'PHP', -- currency
                    1.00,  -- exchange_rate
                    NULL,  -- customer_email
                    NULL,  -- customer_phone
                    NULL,  -- customer_address
                    '" . mysqli_real_escape_string($conn, $collection['request_id'] ?? '') . "',  -- reference_number
                    NULL,  -- bank_name
                    NULL,  -- transaction_id
                    '$updated_by',
                    '$updated_by',
                    NOW(),
                    NOW()
                )";
                
                $arResult = mysqli_query($conn, $arSql);
                
                if (!$arResult) {
                    throw new Exception("Failed to add to accounts receivable: " . mysqli_error($conn));
                }
                
                // Get the newly created accounts_receivable ID
                $ar_id = mysqli_insert_id($conn);
                
                // Add invoice_number and ar_reference_id columns to collections table if they don't exist
                // You can run this SQL to add them:
                /*
                ALTER TABLE collections 
                ADD COLUMN invoice_number VARCHAR(50) NULL AFTER request_id,
                ADD COLUMN ar_reference_id INT NULL AFTER invoice_number;
                */
                
                // If columns exist, update them
                $checkColumnSql = "SHOW COLUMNS FROM collections LIKE 'invoice_number'";
                $checkResult = mysqli_query($conn, $checkColumnSql);
                
                if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                    // Update collection with invoice number and AR reference
                    $updateInvSql = "UPDATE collections SET 
                                    invoice_number = '$invoice_number',
                                    ar_reference_id = '$ar_id',
                                    updated_at = NOW()
                                    WHERE id = '$id'";
                    mysqli_query($conn, $updateInvSql);
                }
                
                // Log the transaction
                $logSql = "INSERT INTO transaction_logs (
                    table_name, record_id, action, details, user_id, created_at
                ) VALUES (
                    'collections',
                    '$id',
                    'status_update',
                    'Collection marked as RECEIVED and invoice $invoice_number created in accounts_receivable with status RECEIVED',
                    '$updated_by',
                    NOW()
                )";
                mysqli_query($conn, $logSql);
            }
        }
        // For FOR COMPLIANCE and REJECTED statuses, no accounts_receivable record is created
        
        mysqli_commit($conn);
        
        echo json_encode([
            "status" => "success", 
            "message" => "Collection status updated successfully"
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>