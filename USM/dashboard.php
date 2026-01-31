<?php
// dashboard.php
session_start();
require_once '../main_connection.php';

// Check if user is logged in
if(!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Fetch user data
$employee_id = $_SESSION['employee_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM deprtmanet_accounts WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session with latest data
    if($user_data) {
        $_SESSION['fullname'] = $user_data['fullname'] ?? $_SESSION['employee_id'];
        $_SESSION['department'] = $user_data['department'] ?? '';
        $_SESSION['position'] = $user_data['position'] ?? '';
    }
} catch(PDOException $e) {
    // Silently fail, use session data
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .session-status {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .status-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .status-icon i {
            font-size: 36px;
            color: white;
        }
        
        h1 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .employee-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .label {
            color: #666;
            font-weight: 500;
        }
        
        .value {
            color: #333;
            font-weight: 600;
        }
        
        .timer-display {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            font-family: 'Courier New', monospace;
            font-size: 1.8rem;
            font-weight: bold;
            color: #856404;
            text-align: center;
        }
        
        .instructions {
            font-size: 0.9rem;
            color: #666;
            margin: 1rem 0;
            line-height: 1.5;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .activity-pulse {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        
        .db-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Activity Indicator -->
    <div class="activity-pulse" id="activityIndicator"></div>
    
    <!-- Session Status Card -->
    <div class="session-status">
        <div class="status-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['fullname']); ?></h1>
        <p style="color: #666; margin-bottom: 1.5rem;">Session is active</p>
        
        <div class="employee-info">
            <div class="info-item">
                <span class="label">Employee ID:</span>
                <span class="value"><?php echo htmlspecialchars($_SESSION['employee_id']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Department:</span>
                <span class="value"><?php echo htmlspecialchars($_SESSION['department']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Position:</span>
                <span class="value"><?php echo htmlspecialchars($_SESSION['position']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Login Time:</span>
                <span class="value"><?php echo date('h:i A', $_SESSION['login_time']); ?></span>
            </div>
        </div>
        
        <div class="timer-display" id="countdown">02:00</div>
        
        <div class="instructions">
            <i class="fas fa-info-circle" style="color: #17a2b8; margin-right: 5px;"></i>
            Move your mouse or press any key to reset the inactivity timer.
        </div>
        
        <button onclick="logout()" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
        
        <div class="db-info">
            <i class="fas fa-database"></i> Connected to: rest_core_2_usm.deprtmanet_accounts
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
    <script>
        // Inactivity timer variables
        let inactivityTimer;
        let logoutTimer;
        const warningTime = 5000; // 5 seconds warning before logout
        const logoutTime = 120000; // 2 minutes in milliseconds
        let countdownInterval;
        let totalSeconds = logoutTime / 1000;

        // Function to reset the inactivity timer
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(logoutTimer);
            
            // Reset countdown display
            totalSeconds = logoutTime / 1000;
            updateCountdownDisplay(totalSeconds);
            
            // Set new inactivity timer
            inactivityTimer = setTimeout(showLogoutWarning, logoutTime - warningTime);
            
            // Update activity indicator
            document.getElementById('activityIndicator').style.background = '#28a745';
            document.getElementById('activityIndicator').style.animation = 'pulse 2s infinite';
        }

        // Function to update countdown display
        function updateCountdownDisplay(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            const display = `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            
            document.getElementById('countdown').textContent = display;
        }

        // Function to show logout warning
        function showLogoutWarning() {
            let secondsLeft = 5;
            
            // Update activity indicator
            document.getElementById('activityIndicator').style.background = '#ffc107';
            document.getElementById('activityIndicator').style.animation = 'none';
            
            swal({
                title: "⏰ Inactivity Alert",
                html: `<div style="text-align: center; padding: 20px 0;">
                          <div style="font-size: 48px; color: #ff6b6b; margin: 20px 0;">
                              <i class="fas fa-hourglass-end"></i>
                          </div>
                          <h3 style="color: #333; margin-bottom: 10px;">You have been inactive for 2 minutes</h3>
                          <p style="color: #666; margin-bottom: 20px;">You will be automatically logged out in:</p>
                          <div style="font-size: 42px; font-weight: bold; color: #ff4757; margin: 20px 0;" id="logoutCountdown">5</div>
                          <p style="color: #666;">Click "Stay Logged In" to continue your session</p>
                      </div>`,
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: '<i class="fas fa-check mr-2"></i> Stay Logged In',
                cancelButtonText: '<i class="fas fa-sign-out-alt mr-2"></i> Logout Now',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showLoaderOnConfirm: false,
                closeOnConfirm: false,
                onOpen: () => {
                    // Start countdown for automatic logout
                    countdownInterval = setInterval(() => {
                        secondsLeft--;
                        const countdownElement = document.getElementById('logoutCountdown');
                        if(countdownElement) {
                            countdownElement.textContent = secondsLeft;
                            
                            // Color change when getting close to 0
                            if(secondsLeft <= 2) {
                                countdownElement.style.color = '#ff0000';
                            } else if(secondsLeft <= 3) {
                                countdownElement.style.color = '#ff6b6b';
                            }
                        }
                        
                        if(secondsLeft <= 0) {
                            clearInterval(countdownInterval);
                            forceLogout();
                        }
                    }, 1000);
                }
            }).then((result) => {
                clearInterval(countdownInterval);
                
                if (result.value) {
                    // User clicked "Stay Logged In"
                    resetInactivityTimer();
                    swal({
                        title: "Session Extended!",
                        text: "Your session has been extended for another 2 minutes.",
                        type: "success",
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    // User clicked "Logout Now" or closed
                    forceLogout();
                }
            });
        }

        // Function to force logout
        function forceLogout() {
            swal({
                title: "",
                html: `<div style="text-align: center; padding: 30px 20px;">
                          <div style="margin-bottom: 20px;">
                              <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #4facfe;"></i>
                          </div>
                          <h2 style="color: #333; margin-bottom: 15px; font-size: 24px;">You have been 2 minutes inactivity</h2>
                          <p style="color: #666; font-size: 16px; margin-bottom: 25px;">Please wait while we log you out...</p>
                          <div style="width: 100%; height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden; margin: 20px 0;">
                              <div id="logoutProgress" style="height: 100%; width: 0%; background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%); transition: width 2s;"></div>
                          </div>
                      </div>`,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                onOpen: () => {
                    // Animate progress bar
                    setTimeout(() => {
                        document.getElementById('logoutProgress').style.width = '100%';
                    }, 100);
                    
                    // After 2 seconds, show "Okay" button
                    setTimeout(() => {
                        swal({
                            title: "Logout Required",
                            text: "Your session has expired due to inactivity. Please login again.",
                            type: "warning",
                            showCancelButton: false,
                            confirmButtonText: '<i class="fas fa-check mr-2"></i> Okay',
                            confirmButtonColor: "#3085d6",
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            closeOnConfirm: false
                        }).then(() => {
                            window.location.href = 'logout.php';
                        });
                    }, 2000);
                }
            });
        }

        // Manual logout function
        function logout() {
            swal({
                title: "Confirm Logout",
                text: "Are you sure you want to logout of your session?",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: '<i class="fas fa-sign-out-alt mr-2"></i> Yes, logout',
                cancelButtonText: '<i class="fas fa-times mr-2"></i> Cancel'
            }).then((result) => {
                if (result.value) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Activity detection
        function setupActivityListeners() {
            const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart', 'mousedown'];
            events.forEach(event => {
                document.addEventListener(event, resetInactivityTimer);
            });
        }

        // Initialize countdown display
        function startCountdown() {
            clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                if(totalSeconds > 0) {
                    totalSeconds--;
                    updateCountdownDisplay(totalSeconds);
                    
                    // Change color when time is running low
                    const countdownElement = document.getElementById('countdown');
                    if(totalSeconds <= 30) {
                        countdownElement.style.color = '#dc3545';
                        countdownElement.style.borderColor = '#dc3545';
                        countdownElement.style.background = '#f8d7da';
                    } else if(totalSeconds <= 60) {
                        countdownElement.style.color = '#e0a800';
                        countdownElement.style.borderColor = '#e0a800';
                        countdownElement.style.background = '#fff3cd';
                    }
                }
            }, 1000);
        }

        // Show initial warning on page load
        window.onload = function() {
            // Show initial warning with SweetAlert
            swal({
                title: "⚠️ Important Notice",
                html: `<div style="text-align: left; max-width: 400px;">
                          <div style="text-align: center; margin-bottom: 20px;">
                              <i class="fas fa-exclamation-triangle" style="font-size: 60px; color: #ffc107;"></i>
                          </div>
                          <h3 style="color: #333; font-size: 22px; margin-bottom: 15px; text-align: center;">Inactivity Timer Activated</h3>
                          <p style="color: #666; font-size: 16px; line-height: 1.5; margin-bottom: 20px; text-align: center;">
                              Inactivity of user for the next 2 minutes will automatically logout
                          </p>
                          <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin: 20px 0;">
                              <div style="display: flex; align-items: flex-start;">
                                  <i class="fas fa-info-circle" style="color: #856404; margin-right: 10px; margin-top: 2px;"></i>
                                  <div>
                                      <p style="color: #856404; margin: 0; font-size: 14px;">
                                          Please stay active (move mouse or press keys) to maintain your session
                                      </p>
                                  </div>
                              </div>
                          </div>
                      </div>`,
                icon: "warning",
                buttons: {
                    confirm: {
                        text: '<i class="fas fa-check mr-2"></i> I Understand',
                        value: true,
                        visible: true,
                        closeModal: true
                    }
                },
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                // Setup timers and listeners after user acknowledges
                resetInactivityTimer();
                setupActivityListeners();
                startCountdown();
            });
        };

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                resetInactivityTimer();
            }
        });

        // Handle beforeunload to cleanup
        window.addEventListener('beforeunload', function() {
            clearTimeout(inactivityTimer);
            clearTimeout(logoutTimer);
            clearInterval(countdownInterval);
        });
    </script>
</body>
</html>