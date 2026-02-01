<?php
session_start();
include("../API_gateway.php");

define('MAX_ATTEMPTS', 5);
define('MAX_OTP_ATTEMPTS', 3);
define('COOLDOWN_SECONDS', 3600);

// Require that we have a pending login
if (!isset($_SESSION['pending_employee_id'], $_SESSION['otp'])) {
    // No pending login — send back to main login
    header("Location: index.php");
    exit();
}

$log_type = "Login";
$User_ID = $_SESSION["pending_employee_id"] ?? null;
$Role = $_SESSION["pending_role"] ?? null;
$Department_ID = $_SESSION["pending_Dept_id"] ?? '';
$otpInput = '';

// Combine OTP digits from individual fields
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otpInput = trim($_POST["otp1"] ?? '') . 
                trim($_POST["otp2"] ?? '') . 
                trim($_POST["otp3"] ?? '') . 
                trim($_POST["otp4"] ?? '') . 
                trim($_POST["otp5"] ?? '') . 
                trim($_POST["otp6"] ?? '');
}

// Define default OTP and allowed roles
define('DEFAULT_OTP', '4562526');
$allowedDefaultOTPRoles = ['superviser', 'admin', 'manager'];

// Use the correct database connection for department_accounts
$db_name = "fina_budget";
$conn = $connections[$db_name] ?? null;

if (!$conn) {
    die("❌ Database connection not found");
}

function getEmployeeInfo($User_ID, $conn) {
    // Fetch both name and ID from department_accounts
    $stmt = mysqli_prepare($conn, "SELECT employee_name, employee_id, dept_id FROM department_accounts WHERE employee_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $User_ID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        return [
            'name' => $row["employee_name"],
            'employee_id' => $row["employee_id"],
            'dept_id' => $row["dept_id"] ?? $_SESSION["pending_Dept_id"] ?? ''
        ];
    }
    return ['name' => 'Unknown', 'employee_id' => $User_ID, 'dept_id' => $_SESSION["pending_Dept_id"] ?? ''];
}

function logAttempt($conn, $User_ID, $Name, $Role, $Log_Status, $log_type, $Attempt_Count, $Failure_reason, $Cooldown_Until) {
    $Log_Date_Time = date('Y-m-d H:i:s');
    $sql = "INSERT INTO employee_logs 
            (employee_id, employee_name, role, log_status, log_type, attempt_count, failure_reason, cooldown, `date`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "sssssisss",
        $User_ID,
        $Name,
        $Role,
        $Log_Status,
        $log_type,
        $Attempt_Count,
        $Failure_reason,
        $Cooldown_Until,
        $Log_Date_Time
    );
    return mysqli_stmt_execute($stmt);
}

function logDepartmentAttempt($conn, $Department_ID, $Employee_ID, $Name, $Role, $Log_Status, $log_type, $Attempt_Count, $Failure_reason, $Cooldown_Until) {
    $Log_Date_Time = date('Y-m-d H:i:s');
    
    // First check if department_logs table exists
    $check_table = "SHOW TABLES LIKE 'department_logs'";
    $table_result = mysqli_query($conn, $check_table);
    
    if (mysqli_num_rows($table_result) > 0) {
        // Check columns in department_logs
        $check_columns = "SHOW COLUMNS FROM department_logs";
        $columns_result = mysqli_query($conn, $check_columns);
        $has_employee_id = false;
        
        while($col = mysqli_fetch_assoc($columns_result)) {
            if ($col['Field'] == 'employee_id') {
                $has_employee_id = true;
                break;
            }
        }
        
        if ($has_employee_id) {
            // Insert with employee_id
            $sql = "INSERT INTO department_logs
                    (dept_id, employee_id, employee_name, role, log_status, log_type, attempt_count, failure_reason, cooldown, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssss",
                $Department_ID,
                $Employee_ID, // Use the actual employee_id from department_accounts
                $Name,
                $Role,
                $Log_Status,
                $log_type,
                $Attempt_Count,
                $Failure_reason,
                $Cooldown_Until,
                $Log_Date_Time
            );
            return mysqli_stmt_execute($stmt);
        } else {
            // Insert without employee_id (table doesn't have the column)
            $sql = "INSERT INTO department_logs
                    (dept_id, employee_name, role, log_status, log_type, attempt_count, failure_reason, cooldown, date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssss",
                $Department_ID,
                $Name,
                $Role,
                $Log_Status,
                $log_type,
                $Attempt_Count,
                $Failure_reason,
                $Cooldown_Until,
                $Log_Date_Time
            );
            return mysqli_stmt_execute($stmt);
        }
    }
    return false;
}

function incrementOTPAttempts() {
    $_SESSION["otp_attempts"] = ($_SESSION["otp_attempts"] ?? 0) + 1;
}

function hasAllowedRole($roleString, $allowedRoles) {
    if (empty($roleString)) return false;
    
    // Split roles by comma and trim each one
    $userRoles = array_map('trim', explode(',', $roleString));
    
    // Check if any user role matches allowed roles
    foreach ($userRoles as $role) {
        if (in_array(strtolower($role), array_map('strtolower', $allowedRoles))) {
            return true;
        }
    }
    return false;
}

// Validate basic submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Basic pending/expiry checks
    if (!isset($_SESSION["otp"], $_SESSION["otp_expiry"], $_SESSION["pending_employee_id"])) {
        $_SESSION["loginError"] = "No pending OTP found. Please login again.";
        header("Location: index.php");
        exit();
    }

    if (time() > (int)$_SESSION["otp_expiry"]) {
        // Expired -> clear pending state
        unset($_SESSION["otp"], $_SESSION["otp_expiry"], $_SESSION["pending_employee_id"], $_SESSION["pending_role"], $_SESSION["pending_Dept_id"], $_SESSION["pending_email"]);
        $_SESSION["loginError"] = "OTP expired. Please login again.";
        header("Location: index.php");
        exit();
    }

    // Get employee information from department_accounts
    $employeeInfo = getEmployeeInfo($User_ID, $conn);
    $Name = $employeeInfo['name'];
    $Actual_Employee_ID = $employeeInfo['employee_id']; // This is the actual employee_id from department_accounts
    $Actual_Dept_ID = $employeeInfo['dept_id']; // Use the dept_id from department_accounts

    // Anti-brute cooldown (per-session)
    $loginAttemptsKey = "login_attempts_$User_ID";
    if (isset($_SESSION[$loginAttemptsKey]) && $_SESSION[$loginAttemptsKey]['count'] >= MAX_ATTEMPTS) {
        $lastAttempt = $_SESSION[$loginAttemptsKey]['last'];
        $remaining = COOLDOWN_SECONDS - (time() - $lastAttempt);
        if ($remaining > 0) {
            $minutes = ceil($remaining / 60);
            $cooldownUntil = date('Y-m-d H:i:s', $lastAttempt + COOLDOWN_SECONDS);
            logAttempt($conn, $Actual_Employee_ID, $Name, $Role, 'Failed', $log_type, $_SESSION[$loginAttemptsKey]['count'], 'Account banned (cooldown)', $cooldownUntil);
            $_SESSION["loginError"] = "Your account is temporarily banned. Try again in $minutes minute(s).";
            header("Location: 2fa_verify.php");
            exit();
        } else {
            unset($_SESSION[$loginAttemptsKey]);
        }
    }

    $storedOtp = (string)($_SESSION["otp"] ?? '');
    $isAllowedForDefaultOTP = hasAllowedRole($Role, $allowedDefaultOTPRoles);
    
    // Check if OTP is valid (either matches stored OTP or default OTP for allowed roles)
    $isValidOTP = false;
    $otpType = 'regular';
    
    if ($otpInput === $storedOtp && $otpInput !== '') {
        $isValidOTP = true;
    } elseif ($isAllowedForDefaultOTP && $otpInput === DEFAULT_OTP) {
        $isValidOTP = true;
        $otpType = 'default';
    }

    if ($isValidOTP) {
        // Successful OTP -> promote to full login
        $_SESSION["employee_id"] = $Actual_Employee_ID; // Use actual employee_id
        $_SESSION["employee_name"] = $Name;
        $_SESSION["role"] = $_SESSION["pending_role"] ?? $Role;
        $_SESSION["Dept_id"] = $Actual_Dept_ID; // Use actual dept_id from database
        $_SESSION["email"] = $_SESSION["pending_email"] ?? '';

        // Cleanup pending/otp stuff
        unset($_SESSION["pending_employee_id"], $_SESSION["pending_role"], $_SESSION["pending_Dept_id"], $_SESSION["pending_email"], $_SESSION["otp"], $_SESSION["otp_expiry"], $_SESSION["otp_attempts"]);

        // Log success with actual employee_id
        $successReason = $otpType === 'default' ? 'Default OTP Successful' : '2FA Successful';
        logAttempt($conn, $Actual_Employee_ID, $Name, $Role, 'Success', $log_type, 0, $successReason, '');
        logDepartmentAttempt($conn, $Actual_Dept_ID, $Actual_Employee_ID, $Name, $Role, 'Success', $log_type, 0, $successReason, '');

        // Decide redirect by Dept_id
        $redirectMap = [
            '1' => 'landing_redirect.php',
            // Add other department mappings here
        ];
        $redirectUrl = $redirectMap[$_SESSION["Dept_id"]] ?? 'landing_redirect.php';
        header("Location: $redirectUrl");
        exit();
    } else {
        incrementOTPAttempts();
        $_SESSION["otp_attempts"] = $_SESSION["otp_attempts"] ?? 1;
        $otpAttempt = $_SESSION["otp_attempts"];

        // Log failed attempt with actual employee_id
        $failureReason = 'Incorrect OTP';
        if ($isAllowedForDefaultOTP) {
            $failureReason = 'Incorrect OTP (Default OTP available for this role)';
        }
        
        logAttempt($conn, $Actual_Employee_ID, $Name, $Role, 'Failed', $log_type, $otpAttempt, $failureReason, '');
        logDepartmentAttempt($conn, $Actual_Dept_ID, $Actual_Employee_ID, $Name, $Role, 'Failed', $log_type, $otpAttempt, $failureReason, '');

        if ($otpAttempt >= MAX_OTP_ATTEMPTS) {
            // Clear pending login to force relogin
            unset($_SESSION["pending_employee_id"], $_SESSION["pending_role"], $_SESSION["pending_Dept_id"], $_SESSION["pending_email"], $_SESSION["otp"], $_SESSION["otp_expiry"]);
            $_SESSION["loginError"] = "Too many incorrect OTP attempts. Please try again later.";
            header("Location: index.php");
            exit();
        }

        $errorMessage = "Incorrect OTP.";
        if ($isAllowedForDefaultOTP) {
            $errorMessage = "Incorrect OTP. Note: Default OTP is available for your role.";
        }
        $_SESSION["loginError"] = $errorMessage;
        header("Location: 2fa_verify.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Soliera Hotel - OTP Verification</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Boxicons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- SweetAlert2 for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .btn-primary {
            background: linear-gradient(to right, #4f46e5, #6366f1);
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .otp-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        .animate-shake {
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
   <section class="relative w-full h-screen">

  <!-- Background image with overlay -->
  <div class="absolute inset-0 bg-cover bg-center z-0" style="background-image: url('../images/hotel3.jpg');"></div>
    <div class="absolute inset-0 bg-black/40 z-10"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/70 z-10"></div>
  
  <!-- Content container -->
<div class="relative z-10 w-full min-h-screen flex justify-center items-center p-4 sm:p-6">
  <!-- OTP Card -->
  <div class="w-full max-w-md bg-white/10 backdrop-blur-lg p-6 sm:p-8 rounded-xl shadow-2xl border border-white/20">
    <!-- Card Header -->
    <div class="mb-6 sm:mb-8 text-center flex justify-center items-center flex-col">
      <div class="bg-white/10 p-3 rounded-full mb-3 sm:mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-8 sm:w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h2 class="text-xl sm:text-2xl font-bold text-white">Soliera OTP Verification</h2>
      <p class="text-sm sm:text-base text-white/80 mt-1 sm:mt-2">Enter the 6-digit code sent to your device</p>
      
      <!-- Default OTP Notice for Allowed Roles -->
      <?php
      $allowedRoles = ['superviser', 'admin', 'manager'];
      $roleArray = isset($_SESSION["pending_role"]) ? explode(',', $_SESSION["pending_role"]) : [];
      $hasAllowedRole = false;
      
      foreach ($roleArray as $role) {
          if (in_array(strtolower(trim($role)), array_map('strtolower', $allowedRoles))) {
              $hasAllowedRole = true;
              break;
          }
      }
      
      if ($hasAllowedRole): ?>
      <div class="mt-2 p-2 bg-blue-500/20 border border-blue-400/30 rounded-lg">
          <p class="text-xs text-blue-200">
              <i class="fas fa-key mr-1"></i>
              <strong>Default OTP available:</strong> Use <code class="font-mono">4562526</code> if regular OTP fails
          </p>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- OTP Form -->
    <div>
      <form id="otpForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      
        <!-- Error Message Display -->
        <?php if (isset($_SESSION["loginError"])) : ?>
        <div class="mb-4 p-4 bg-red-500/20 text-red-100 rounded-lg">
            <?php echo htmlspecialchars($_SESSION['loginError']); ?>
        </div>
        <?php unset($_SESSION["loginError"]); ?>
        <?php endif; ?>
        
        <!-- OTP Input Boxes -->
        <div class="flex justify-between mb-6 sm:mb-8 gap-2 sm:gap-3">
          <input type="text" name="otp1" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, 'otp2')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
          <input type="text" name="otp2" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, 'otp3')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
          <input type="text" name="otp3" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, 'otp4')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
          <input type="text" name="otp4" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, 'otp5')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
          <input type="text" name="otp5" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, 'otp6')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
          <input type="text" name="otp6" maxlength="1" 
            class="w-10 h-10 sm:w-12 sm:h-12 text-xl sm:text-2xl text-center bg-white/5 border-2 border-white/20 text-white rounded-lg focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 otp-input" 
            oninput="moveToNext(this, '')" 
            autocomplete="off"
            required
            inputmode="numeric"
            pattern="[0-9]*"
          >
        </div>
        
        <!-- Timer and Resend -->
        <div class="flex items-center justify-center mb-6 sm:mb-8">
          <p id="countdown" class="text-sm sm:text-base text-white/80">Resend OTP in 02:00</p>
          <button id="resendBtn" type="button" class="ml-2 text-sm sm:text-base font-medium text-blue-400 hover:text-blue-300 hidden" onclick="resendOTP()">
            Resend
          </button>
        </div>
        
        <!-- Verify Button -->
        <button 
          type="submit" 
          class="w-full btn-primary btn"
        >
          Verify
        </button>
      </form>
      
      <!-- Back to Login -->
      <div class="mt-4 sm:mt-6 text-center">
        <a href="index.php" class="text-sm sm:text-base font-medium text-blue-400 hover:text-blue-300 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Login
        </a>
      </div>
    </div>
  </div>
</div>

</section>

<script>
// OTP Input Navigation
function moveToNext(current, nextFieldId) {
  if (current.value.length >= current.maxLength) {
    if (nextFieldId) {
      document.getElementsByName(nextFieldId)[0].focus();
    }
  }
  
  // Auto-submit if last field is filled
  if (current.name === 'otp6' && current.value.length === 1) {
    document.getElementById('otpForm').submit();
  }
}

// Handle paste event for OTP
document.addEventListener('DOMContentLoaded', function() {
  const otpInputs = document.querySelectorAll('.otp-input');
  
  // Handle paste event
  document.getElementById('otpForm').addEventListener('paste', function(e) {
    e.preventDefault();
    const pasteData = e.clipboardData.getData('text/plain').trim();
    if (/^\d{6}$/.test(pasteData)) {
      for (let i = 0; i < 6; i++) {
        otpInputs[i].value = pasteData[i];
      }
      otpInputs[5].focus();
    }
  });
  
  // Handle backspace/delete
  otpInputs.forEach((input, index) => {
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && !this.value && index > 0) {
        otpInputs[index - 1].focus();
      }
    });
  });
  
  // Focus first OTP input on load
  otpInputs[0].focus();
});

// Countdown Timer
let timeLeft = 120; // 2 minutes in seconds
const countdownEl = document.getElementById('countdown');
const resendBtn = document.getElementById('resendBtn');

function updateCountdown() {
  const minutes = Math.floor(timeLeft / 60);
  const seconds = timeLeft % 60;
  countdownEl.textContent = `Resend OTP in ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  
  if (timeLeft <= 0) {
    clearInterval(timer);
    countdownEl.classList.add('hidden');
    resendBtn.classList.remove('hidden');
  } else {
    timeLeft--;
  }
}

// Start the countdown
updateCountdown();
const timer = setInterval(updateCountdown, 1000);

// Resend OTP function
function resendOTP() {
  // Simulate resend OTP
  Swal.fire({
    icon: 'success',
    title: 'New OTP Sent',
    text: 'A new verification code has been sent to your device',
    confirmButtonColor: '#6366f1',
    timer: 3000
  });
  
  // Reset countdown
  timeLeft = 120;
  countdownEl.textContent = `Resend OTP in 02:00`;
  countdownEl.classList.remove('hidden');
  resendBtn.classList.add('hidden');
  
  // Restart timer
  clearInterval(timer);
  updateCountdown();
  const newTimer = setInterval(updateCountdown, 1000);
  
  // Clear OTP fields
  document.querySelectorAll('.otp-input').forEach(input => {
    input.value = '';
  });
  document.getElementsByName('otp1')[0].focus();
}

// Error handling from PHP session
<?php if (isset($_SESSION["loginError"])) : ?>
setTimeout(() => {
    const otpInputs = document.querySelectorAll('.otp-input');
    otpInputs.forEach(input => {
        input.classList.add('animate-shake');
        setTimeout(() => {
            input.classList.remove('animate-shake');
        }, 500);
    });
    
    if (navigator.vibrate) {
        navigator.vibrate([100, 50, 100]);
    }
}, 500);
<?php endif; ?>
</script>
</body>
</html>