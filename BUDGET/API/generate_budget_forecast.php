<?php
session_start();
include("../../API_gateway.php");

header('Content-Type: application/json');

// Database connection
$db_name = "fina_budget";
if (!isset($connections[$db_name])) {
    echo json_encode(['success' => false, 'message' => 'Database connection not found']);
    exit;
}
$conn = $connections[$db_name];

try {
    // Get historical data for forecasting (last 12 months)
    $history_query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(amount) as monthly_total,
            COUNT(*) as request_count,
            AVG(amount) as avg_amount
        FROM budget_allocations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ";
    
    $history_result = mysqli_query($conn, $history_query);
    $historical_data = [];
    $total_requests = 0;
    $total_amount = 0;
    
    while ($row = mysqli_fetch_assoc($history_result)) {
        $historical_data[] = $row;
        $total_requests += $row['request_count'];
        $total_amount += $row['monthly_total'];
    }

    // Simple forecasting algorithm (you can replace this with TensorFlow later)
    $next_quarter_forecast = calculateSimpleForecast($historical_data);
    
    // Calculate confidence level based on data consistency
    $confidence_level = calculateConfidenceLevel($historical_data);
    
    // Trend analysis
    $trend_analysis = analyzeTrends($historical_data);

    // Prepare forecast data
    $forecast = [
        'next_quarter_forecast' => $next_quarter_forecast,
        'confidence_level' => $confidence_level,
        'trend_analysis' => $trend_analysis,
        'historical_months' => count($historical_data),
        'average_monthly_spend' => count($historical_data) > 0 ? $total_amount / count($historical_data) : 0,
        'generated_at' => date('Y-m-d H:i:s'),
        'model_used' => 'Statistical Time Series',
        'recommendations' => generateRecommendations($trend_analysis, $next_quarter_forecast)
    ];

    echo json_encode([
        'success' => true,
        'forecast' => $forecast,
        'message' => 'Budget forecast generated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating budget forecast: ' . $e->getMessage()
    ]);
}

// Helper functions for forecasting
function calculateSimpleForecast($historical_data) {
    if (empty($historical_data)) {
        return 0;
    }
    
    // Simple moving average for next quarter forecast
    $recent_months = array_slice($historical_data, 0, 3);
    $total = 0;
    
    foreach ($recent_months as $month) {
        $total += $month['monthly_total'];
    }
    
    return $total / count($recent_months) * 3; // Project for quarter
}

function calculateConfidenceLevel($historical_data) {
    if (count($historical_data) < 3) {
        return 50; // Low confidence with insufficient data
    }
    
    // Calculate coefficient of variation for confidence
    $amounts = array_column($historical_data, 'monthly_total');
    $mean = array_sum($amounts) / count($amounts);
    $variance = 0;
    
    foreach ($amounts as $amount) {
        $variance += pow($amount - $mean, 2);
    }
    
    $std_dev = sqrt($variance / count($amounts));
    $cv = ($std_dev / $mean) * 100;
    
    // Convert to confidence level (inverse relationship)
    $confidence = max(60, 100 - $cv);
    return round(min(95, $confidence));
}

function analyzeTrends($historical_data) {
    if (count($historical_data) < 2) {
        return [];
    }
    
    $trends = [];
    
    // Overall trend
    $first_half = array_slice($historical_data, 0, ceil(count($historical_data) / 2));
    $second_half = array_slice($historical_data, ceil(count($historical_data) / 2));
    
    $first_avg = array_sum(array_column($first_half, 'monthly_total')) / count($first_half);
    $second_avg = array_sum(array_column($second_half, 'monthly_total')) / count($second_half);
    
    $overall_change = (($second_avg - $first_avg) / $first_avg) * 100;
    
    $trends[] = [
        'category' => 'Overall Budget',
        'trend' => $overall_change > 5 ? 'increasing' : ($overall_change < -5 ? 'decreasing' : 'stable'),
        'percentage' => abs(round($overall_change, 1)),
        'description' => $overall_change > 0 ? 'Budget requirements are increasing' : 'Budget requirements are stabilizing'
    ];
    
    return $trends;
}

function generateRecommendations($trends, $forecast) {
    $recommendations = [];
    
    foreach ($trends as $trend) {
        if ($trend['trend'] === 'increasing' && $trend['percentage'] > 10) {
            $recommendations[] = "Consider increasing budget allocation for upcoming quarter";
        } elseif ($trend['trend'] === 'decreasing' && $trend['percentage'] > 10) {
            $recommendations[] = "Opportunity to optimize budget allocation";
        }
    }
    
    return $recommendations;
}
?>