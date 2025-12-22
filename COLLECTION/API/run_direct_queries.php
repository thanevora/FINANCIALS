<?php
// run_direct_queries.php - Direct database queries when all else fails

try {
    // Total collections
    $sql = "SELECT SUM(amount) as total FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $total_collections = $row['total'] ?? 0;
    }
    
    // Pending collections
    $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = 'pending'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $pending_collections = $row['count'] ?? 0;
        $pending_amount = $row['total'] ?? 0;
    }
    
    // This month collections
    $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections 
            WHERE status = 'completed' 
            AND MONTH(collection_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(collection_date) = YEAR(CURRENT_DATE())";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $successful_this_month = $row['total'] ?? 0;
        $monthly_count = $row['count'] ?? 0;
    }
    
    // Total and completed counts
    $sql = "SELECT COUNT(*) as total_count FROM collections";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $total_count = $row['total_count'] ?? 0;
    }
    
    $sql = "SELECT COUNT(*) as completed_count FROM collections WHERE status = 'completed'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $completed_count = $row['completed_count'] ?? 0;
    }
    
    // Collection rate
    if ($total_count > 0) {
        $collection_rate = round(($completed_count / $total_count) * 100, 2);
    }
    
    // Transaction status breakdown
    $statuses = ['completed', 'pending', 'failed', 'for_approval', 'overdue', 'refund'];
    foreach ($statuses as $status) {
        $sql = "SELECT COUNT(*) as count, SUM(amount) as total FROM collections WHERE status = '$status'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $transaction_status[$status] = [
                'count' => $row['count'] ?? 0,
                'amount' => $row['total'] ?? 0
            ];
        }
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
    
    if ($result) {
        $total_paid = $total_collections;
        while ($row = mysqli_fetch_assoc($result)) {
            $percentage = $total_paid > 0 ? round(($row['amount'] / $total_paid) * 100, 2) : 0;
            $payment_methods[] = [
                'payment_method' => $row['payment_method'],
                'count' => $row['count'],
                'amount' => $row['amount'],
                'percentage' => $percentage
            ];
        }
    }
    
} catch (Exception $e) {
    // All variables already initialized with defaults
}
?>