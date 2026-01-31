<?php
session_start();
include("../API_gateway.php");

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    die(json_encode(['success' => false, 'message' => 'Database connection not found']));
}
$conn = $connections[$db_name];

// Include forecast functions
include("forecast_functions.php");

$forecaster = new BudgetForecast($conn);

// Get forecast type
$type = $_GET['type'] ?? $_POST['type'] ?? '';

if (empty($type)) {
    die(json_encode(['success' => false, 'message' => 'Forecast type is required']));
}

// Simulate 10-second loading for demonstration
sleep(2); // Reduced for testing, change to 10 for production

// Generate forecast based on type
switch ($type) {
    case 'annual_budget':
        $result = $forecaster->forecastAnnualBudget();
        $period = 'Next 12 months';
        break;
        
    case 'budget_allocations':
        $result = $forecaster->forecastBudgetAllocations();
        $period = 'Next month';
        break;
        
    case 'budget_disbursement':
        $result = $forecaster->forecastBudgetDisbursement();
        $period = 'Next month';
        break;
        
    case 'collections':
        $result = $forecaster->forecastCollections();
        $period = 'Next month';
        break;
        
    default:
        die(json_encode(['success' => false, 'message' => 'Invalid forecast type']));
}

// Save to history if successful
if ($result['success']) {
    $amount = $result['forecast_amount'] ?? $result['total_forecast'] ?? 0;
    $confidence = $result['confidence_score'] ?? 75.0;
    $algorithm = $result['algorithm_used'] ?? 'linear_regression';
    $dataPoints = $result['historical_data_points'] ?? $result['historical_months'] ?? 0;
    $trend = $result['trend_direction'] ?? 'stable';
    
    $forecaster->saveForecastHistory(
        $type,
        $period,
        $amount,
        $confidence,
        $algorithm,
        $dataPoints,
        $trend,
        'Automated forecast generated'
    );
}

// Return result
header('Content-Type: application/json');
echo json_encode($result);
?>