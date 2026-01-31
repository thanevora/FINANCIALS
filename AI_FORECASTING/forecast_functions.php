<?php
session_start();
include("../API_gateway.php");

class BudgetForecast {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Simple Linear Regression for forecasting
     */
    private function linearRegression($x, $y) {
        $n = count($x);
        if ($n < 2) return ['slope' => 0, 'intercept' => 0, 'r_squared' => 0];
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += ($x[$i] * $y[$i]);
            $sumX2 += ($x[$i] * $x[$i]);
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Calculate R-squared
        $yMean = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $yPredicted = $slope * $x[$i] + $intercept;
            $ssTotal += pow($y[$i] - $yMean, 2);
            $ssResidual += pow($y[$i] - $yPredicted, 2);
        }
        
        $rSquared = ($ssTotal == 0) ? 0 : 1 - ($ssResidual / $ssTotal);
        
        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared
        ];
    }
    
    /**
     * Moving Average for forecasting
     */
    private function movingAverage($data, $periods = 3) {
        $n = count($data);
        if ($n < $periods) return end($data);
        
        $sum = 0;
        for ($i = $n - $periods; $i < $n; $i++) {
            $sum += $data[$i];
        }
        
        return $sum / $periods;
    }
    
    /**
     * Exponential Smoothing
     */
    private function exponentialSmoothing($data, $alpha = 0.3) {
        $n = count($data);
        if ($n < 2) return end($data);
        
        $forecast = $data[0];
        for ($i = 1; $i < $n; $i++) {
            $forecast = $alpha * $data[$i] + (1 - $alpha) * $forecast;
        }
        
        return $forecast;
    }
    
    /**
     * Forecast annual budget from history
     */
    public function forecastAnnualBudget($monthsToForecast = 12) {
        // Get historical budget data from budget_allocations
        $query = "SELECT 
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    SUM(amount) as total_amount
                  FROM budget_allocations 
                  WHERE status = 'approved'
                  GROUP BY YEAR(created_at), MONTH(created_at)
                  ORDER BY year, month";
        
        $result = mysqli_query($this->conn, $query);
        $historicalData = [];
        $x = []; // Time periods
        $y = []; // Amounts
        
        $period = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            $historicalData[] = [
                'period' => $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT),
                'amount' => (float)$row['total_amount']
            ];
            $x[] = $period;
            $y[] = (float)$row['total_amount'];
            $period++;
        }
        
        if (count($x) < 3) {
            return $this->getFallbackForecast($y, 'annual_budget');
        }
        
        // Use multiple forecasting methods
        $lr = $this->linearRegression($x, $y);
        $nextPeriod = count($x) + 1;
        $lrForecast = $lr['slope'] * $nextPeriod + $lr['intercept'];
        $maForecast = $this->movingAverage($y);
        $esForecast = $this->exponentialSmoothing($y);
        
        // Weighted average of methods
        $forecast = ($lrForecast * 0.5 + $maForecast * 0.3 + $esForecast * 0.2);
        
        // Ensure forecast is reasonable
        $avgHistorical = array_sum($y) / count($y);
        $forecast = max($forecast, $avgHistorical * 0.5);
        
        // Calculate confidence score
        $confidence = min(100, max(50, $lr['r_squared'] * 100));
        
        // Determine trend
        $trend = 'stable';
        if ($lr['slope'] > 1000) $trend = 'increasing';
        elseif ($lr['slope'] < -1000) $trend = 'decreasing';
        
        return [
            'success' => true,
            'forecast_amount' => round($forecast, 2),
            'confidence_score' => round($confidence, 2),
            'algorithm_used' => 'ensemble(linear_regression,moving_average,exponential_smoothing)',
            'historical_data_points' => count($x),
            'trend_direction' => $trend,
            'next_period' => $nextPeriod,
            'historical_data' => $historicalData,
            'linear_regression_params' => $lr
        ];
    }
    
    /**
     * Forecast budget allocations
     */
    public function forecastBudgetAllocations() {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as request_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                  FROM budget_allocations 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month";
        
        $result = mysqli_query($this->conn, $query);
        $data = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'month' => $row['month'],
                'request_count' => (int)$row['request_count'],
                'total_amount' => (float)$row['total_amount'],
                'avg_amount' => (float)$row['avg_amount']
            ];
        }
        
        if (count($data) < 3) {
            return $this->getFallbackForecast(array_column($data, 'total_amount'), 'allocations');
        }
        
        // Forecast next month
        $amounts = array_column($data, 'total_amount');
        $counts = array_column($data, 'request_count');
        
        $lrAmounts = $this->linearRegression(range(1, count($amounts)), $amounts);
        $lrCounts = $this->linearRegression(range(1, count($counts)), $counts);
        
        $nextPeriod = count($amounts) + 1;
        $forecastAmount = $lrAmounts['slope'] * $nextPeriod + $lrAmounts['intercept'];
        $forecastCount = $lrCounts['slope'] * $nextPeriod + $lrCounts['intercept'];
        
        // Adjust based on seasonal factors (simple version)
        $currentMonth = date('n');
        $seasonalFactor = $this->getSeasonalFactor($currentMonth);
        $forecastAmount *= $seasonalFactor;
        
        $confidence = ($lrAmounts['r_squared'] + $lrCounts['r_squared']) / 2 * 100;
        
        return [
            'success' => true,
            'forecast_amount' => round(max($forecastAmount, 0), 2),
            'forecast_request_count' => round(max($forecastCount, 1)),
            'confidence_score' => round($confidence, 2),
            'algorithm_used' => 'linear_regression_with_seasonality',
            'historical_months' => count($data),
            'avg_amount_per_request' => round(array_sum($amounts) / array_sum($counts), 2),
            'seasonal_factor' => $seasonalFactor,
            'historical_data' => $data
        ];
    }
    
    /**
     * Forecast budget disbursement
     */
    public function forecastBudgetDisbursement() {
        // Assuming you have a disbursement table or using budget_allocations with status
        $query = "SELECT 
                    ba.department,
                    DATE_FORMAT(ba.created_at, '%Y-%m') as month,
                    SUM(ba.amount) as allocated_amount,
                    COUNT(*) as allocation_count
                  FROM budget_allocations ba
                  WHERE ba.status IN ('approved', 'for_compliance')
                  AND ba.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY ba.department, DATE_FORMAT(ba.created_at, '%Y-%m')
                  ORDER BY month, department";
        
        $result = mysqli_query($this->conn, $query);
        $departmentData = [];
        $monthlyData = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $dept = $row['department'];
            $month = $row['month'];
            $amount = (float)$row['allocated_amount'];
            
            if (!isset($departmentData[$dept])) {
                $departmentData[$dept] = [];
            }
            $departmentData[$dept][] = $amount;
            
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = 0;
            }
            $monthlyData[$month] += $amount;
        }
        
        if (empty($monthlyData)) {
            return ['success' => false, 'message' => 'Insufficient data for forecasting'];
        }
        
        // Forecast next month total disbursement
        $months = array_keys($monthlyData);
        $amounts = array_values($monthlyData);
        
        $lr = $this->linearRegression(range(1, count($amounts)), $amounts);
        $nextPeriod = count($amounts) + 1;
        $forecastTotal = $lr['slope'] * $nextPeriod + $lr['intercept'];
        
        // Forecast by department
        $deptForecasts = [];
        foreach ($departmentData as $dept => $deptAmounts) {
            if (count($deptAmounts) >= 3) {
                $deptLr = $this->linearRegression(range(1, count($deptAmounts)), $deptAmounts);
                $deptForecast = $deptLr['slope'] * $nextPeriod + $deptLr['intercept'];
                $deptForecasts[$dept] = round(max($deptForecast, 0), 2);
            } else {
                $deptForecasts[$dept] = round(array_sum($deptAmounts) / count($deptAmounts), 2);
            }
        }
        
        // Normalize department forecasts to match total
        $currentSum = array_sum($deptForecasts);
        if ($currentSum > 0) {
            foreach ($deptForecasts as $dept => $amount) {
                $deptForecasts[$dept] = round(($amount / $currentSum) * $forecastTotal, 2);
            }
        }
        
        return [
            'success' => true,
            'total_forecast' => round(max($forecastTotal, 0), 2),
            'department_forecasts' => $deptForecasts,
            'confidence_score' => round($lr['r_squared'] * 100, 2),
            'algorithm_used' => 'departmental_linear_regression',
            'historical_months' => count($months),
            'avg_monthly_disbursement' => round(array_sum($amounts) / count($amounts), 2)
        ];
    }
    
    /**
     * Forecast collections
     */
    public function forecastCollections() {
        $query = "SELECT 
                    DATE_FORMAT(collection_date, '%Y-%m') as month,
                    SUM(amount) as total_collected,
                    COUNT(*) as collection_count,
                    service_type,
                    AVG(amount) as avg_collection
                  FROM collections 
                  WHERE collection_date IS NOT NULL
                  AND collection_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(collection_date, '%Y-%m'), service_type
                  ORDER BY month, service_type";
        
        $result = mysqli_query($this->conn, $query);
        $monthlyData = [];
        $serviceData = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $month = $row['month'];
            $service = $row['service_type'] ?: 'other';
            $amount = (float)$row['total_collected'];
            
            if (!isset($monthlyData[$month])) {
                $monthlyData[$month] = 0;
            }
            $monthlyData[$month] += $amount;
            
            if (!isset($serviceData[$service])) {
                $serviceData[$service] = [];
            }
            $serviceData[$service][] = $amount;
        }
        
        if (empty($monthlyData)) {
            return ['success' => false, 'message' => 'Insufficient collection data'];
        }
        
        // Forecast next month
        $months = array_keys($monthlyData);
        $amounts = array_values($monthlyData);
        
        $lr = $this->linearRegression(range(1, count($amounts)), $amounts);
        $nextPeriod = count($amounts) + 1;
        $forecastTotal = $lr['slope'] * $nextPeriod + $lr['intercept'];
        
        // Forecast by service type
        $serviceForecasts = [];
        foreach ($serviceData as $service => $serviceAmounts) {
            if (count($serviceAmounts) >= 3) {
                $serviceLr = $this->linearRegression(range(1, count($serviceAmounts)), $serviceAmounts);
                $serviceForecast = $serviceLr['slope'] * $nextPeriod + $serviceLr['intercept'];
                $serviceForecasts[$service] = round(max($serviceForecast, 0), 2);
            } else {
                $serviceForecasts[$service] = round(array_sum($serviceAmounts) / count($serviceAmounts), 2);
            }
        }
        
        // Adjust for seasonality
        $currentMonth = date('n');
        $seasonalFactor = $this->getSeasonalFactor($currentMonth, true);
        $forecastTotal *= $seasonalFactor;
        
        // Normalize service forecasts
        $currentSum = array_sum($serviceForecasts);
        if ($currentSum > 0) {
            foreach ($serviceForecasts as $service => $amount) {
                $serviceForecasts[$service] = round(($amount / $currentSum) * $forecastTotal, 2);
            }
        }
        
        return [
            'success' => true,
            'forecast_amount' => round(max($forecastTotal, 0), 2),
            'service_type_forecasts' => $serviceForecasts,
            'confidence_score' => round($lr['r_squared'] * 100, 2),
            'algorithm_used' => 'linear_regression_with_service_breakdown',
            'historical_months' => count($months),
            'avg_monthly_collection' => round(array_sum($amounts) / count($amounts), 2),
            'seasonal_factor' => $seasonalFactor
        ];
    }
    
    /**
     * Get seasonal factor based on month
     */
    private function getSeasonalFactor($month, $forCollections = false) {
        // Simple seasonal factors (can be customized based on business patterns)
        $factors = [
            1 => 1.1,  // January - New year budget
            2 => 1.0,
            3 => 1.0,
            4 => 0.9,  // April - End of Q1
            5 => 1.0,
            6 => 1.1,  // June - Mid-year
            7 => 1.0,
            8 => 1.0,
            9 => 1.2,  // September - Q3 end
            10 => 1.0,
            11 => 1.1, // November - Year end planning
            12 => 1.3  // December - Year end
        ];
        
        if ($forCollections) {
            // Different pattern for collections
            $factors = [
                1 => 0.9,   // Post-holiday slowdown
                2 => 1.0,
                3 => 1.1,   // Q1 end
                4 => 1.0,
                5 => 1.0,
                6 => 1.2,   // Mid-year
                7 => 1.0,
                8 => 1.0,
                9 => 1.3,   // Q3 end
                10 => 1.1,
                11 => 1.0,
                12 => 1.4   // Year-end collections
            ];
        }
        
        return $factors[$month] ?? 1.0;
    }
    
    /**
     * Fallback forecast when insufficient data
     */
    private function getFallbackForecast($data, $type) {
        if (empty($data)) {
            $defaults = [
                'annual_budget' => 1000000,
                'allocations' => 100000,
                'disbursement' => 50000,
                'collections' => 75000
            ];
            $forecast = $defaults[$type] ?? 50000;
        } else {
            $forecast = $this->movingAverage($data, min(2, count($data)));
        }
        
        return [
            'success' => true,
            'forecast_amount' => round($forecast, 2),
            'confidence_score' => 50.0,
            'algorithm_used' => 'fallback_average',
            'historical_data_points' => count($data),
            'trend_direction' => 'stable',
            'note' => 'Limited historical data, using simple average'
        ];
    }
    
    /**
     * Save forecast to history
     */
    public function saveForecastHistory($type, $period, $amount, $confidence, $algorithm, $dataPoints, $trend, $notes = null) {
        $user = $_SESSION['username'] ?? 'system';
        
        $query = "INSERT INTO forecast_history 
                  (forecast_type, period, forecasted_amount, confidence_score, algorithm_used, 
                   historical_data_points, trend_direction, notes, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ssddssiss", 
            $type, $period, $amount, $confidence, $algorithm, 
            $dataPoints, $trend, $notes, $user);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get forecast history
     */
    public function getForecastHistory($limit = 20, $type = null) {
        $query = "SELECT * FROM forecast_history ";
        if ($type) {
            $query .= " WHERE forecast_type = ? ";
        }
        $query .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        if ($type) {
            mysqli_stmt_bind_param($stmt, "si", $type, $limit);
        } else {
            mysqli_stmt_bind_param($stmt, "i", $limit);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        return $history;
    }
}
?>