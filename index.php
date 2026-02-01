<?php
session_start();
include("API_gateway.php");

$baseUrl = isset($_SERVER['HTTPS']) ? "https://" : "http://";
$baseUrl .= $_SERVER['HTTP_HOST'] . 'localhost';

// Database connection - only using fina_budget
$cr2_usm = $connections["fina_budget"];

// Initialize variables
$employee_ID = trim($_POST["employee_id"] ?? '');
$password = trim($_POST["password"] ?? '');
$puzzle_answer = trim($_POST["puzzle_answer"] ?? '');
$loginAttemptsKey = "login_attempts_$employee_ID";

// Include puzzle functions
include("USM/puzzle_functions.php");

// === Function to check if online (for reCAPTCHA) ===
function isOnline() {
    $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

// Initialize puzzle if not set
if (!isset($_SESSION['puzzle_question']) || empty($_SESSION['puzzle_question'])) {
    $_SESSION['puzzle_question'] = generatePuzzle();
}

// === Function: Log user login attempts ===
function logAttempt($conn, $Employee_ID, $Employee_name, $Role, $Log_Status, $Attempt_Type, $Attempt_Count, $Failure_reason, $Cooldown) {
    $date = date('Y-m-d H:i:s');
    $sql = "INSERT INTO employee_logs (employee_id, employee_name, role, log_status, attempt_count, log_type, failure_reason, Cooldown, `date`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssss", $Employee_ID, $Employee_name, $Role, $Log_Status, $Attempt_Type, $Attempt_Count, $Failure_reason, $Cooldown, $date);
    return mysqli_stmt_execute($stmt);
}

function logDepartmentAttempt($conn, $Department_ID, $employee_ID, $Name, $Role, $Log_Status, $Attempt_type, $Attempt_Count, $Failure_reason, $Cooldown_Until) {
    $Log_Date_Time = date('Y-m-d H:i:s');
    $sql = "INSERT INTO department_logs (dept_id, employee_id, employee_name, role, log_status, log_type, attempt_count, failure_reason, cooldown, date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssisss", $Department_ID, $employee_ID, $Name, $Role, $Log_Status, $Attempt_type, $Attempt_Count, $Failure_reason, $Cooldown_Until, $Log_Date_Time);
    return mysqli_stmt_execute($stmt);
}

// === Function: Increment login attempts ===
function incrementLoginAttempts($employee_ID) {
    $key = "login_attempts_$employee_ID";
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'last' => time()];
    } else {
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last'] = time();
    }
}

// === Function: Send OTP via email ===
function sendOTP($email, $otp) {
    require_once 'PHPMailer/PHPMailerAutoload.php';
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'soliera.restaurant@gmail.com';
    $mail->Password = 'rpyo ncni ulhv lhpx';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('system@example.com', 'System Administrator');
    $mail->addAddress($email);
    $mail->Subject = 'Your Verification Code';

    // Default email content
    $mail->isHTML(true);
    $mail->Body = "Your OTP code is: <strong>$otp</strong><br>This code will expire in 5 minutes.";

    return $mail->send();
}

// === Cooldown enforcement ===
if ($employee_ID !== '' && isset($_SESSION[$loginAttemptsKey]) && $_SESSION[$loginAttemptsKey]['count'] >= 5) {
    $lastAttempt = $_SESSION[$loginAttemptsKey]['last'];
    $remaining = 3600 - (time() - $lastAttempt);
    if ($remaining > 0) {
        $minutes = ceil($remaining / 60);
        $cooldownUntil = date('Y-m-d H:i:s', $lastAttempt + 3600);
        $_SESSION["loginError"] = "Your account is temporarily banned. Try again in $minutes minute(s).";
        header("Location: index.php");
        exit();
    } else {
        unset($_SESSION[$loginAttemptsKey]);
    }
}

// === Check online status for reCAPTCHA ===
$isOnline = isOnline();

// === Main Login Logic ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && $employee_ID && $password) {
    // Step 1: Puzzle validation (always required)
    $submittedAnswer = strtolower(trim($puzzle_answer));
    $correctAnswer = isset($_SESSION['puzzle_answer']) ? strtolower(trim($_SESSION['puzzle_answer'])) : '';
    
    if (empty($submittedAnswer)) {
        $_SESSION["loginError"] = "Please answer the security puzzle.";
        header("Location: index.php");
        exit();
    }
    
    if ($submittedAnswer !== $correctAnswer) {
        $_SESSION["loginError"] = "Incorrect puzzle answer. Please try again.";
        // Generate new puzzle for next attempt
        $_SESSION['puzzle_question'] = generatePuzzle();
        header("Location: index.php");
        exit();
    }
    
    // Step 2: CAPTCHA validation (only if online)
    if ($isOnline) {
        $recaptcha_secret = "6Ld4W8ArAAAAAEX9YfG9ZzKGC9SiCVl5gwnpJZE-";
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        
        if (empty($recaptcha_response)) {
            $_SESSION["loginError"] = "Please complete the CAPTCHA verification.";
            header("Location: index.php");
            exit();
        }
        
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $captcha_success = json_decode($verify);

        if (!$captcha_success->success) {
            $_SESSION["loginError"] = "CAPTCHA verification failed. Please try again.";
            header("Location: index.php");
            exit();
        }
    } else {
        // Offline mode - log that CAPTCHA was skipped
        error_log("Login: CAPTCHA skipped - offline mode for employee ID: $employee_ID");
    }

    // Core 2 Check
    $stmt = mysqli_prepare($cr2_usm, "SELECT email, employee_name, password, Dept_id, employee_id, role FROM department_accounts WHERE employee_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $employee_ID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $Department_ID = $row["Dept_id"];
        $Role = $row["role"];
        $Name = $row["employee_name"];

        // Password check
        if ($password === $row["password"]) {
            // Generate OTP and store PENDING login state
            $otp = rand(100000, 999999);
            $_SESSION["otp"] = (string)$otp;
            $_SESSION["otp_expiry"] = time() + 300; // 5 minutes expiry

            // Store pending login info
            $_SESSION["pending_employee_id"] = $employee_ID;
            $_SESSION["pending_role"] = $Role;
            $_SESSION["pending_Dept_id"] = $row["Dept_id"];
            $_SESSION["pending_email"] = $row["email"];
            $_SESSION["otp_attempts"] = 0;
            $_SESSION["auth_method"] = "2FA";

            if (sendOTP($row["email"], $otp)) {
                logAttempt($cr2_usm, $employee_ID, $Name, $Role, 'Authenticating', 'Login', 0, 'Authenticating', '');
                logDepartmentAttempt($cr2_usm, $Department_ID, $employee_ID, $Name, $Role, 'Success', 'Login', 0, 'Login Successful', '');
                header("Location: USM/2fa_verify.php");
                exit();
            } else {
                logAttempt($cr2_usm, $employee_ID, $Name, $Role, 'Failed', 'Login', 0, 'Failed to send OTP email', '');
                $_SESSION["loginError"] = "Failed to send OTP email.";
                header("Location: index.php");
                exit();
            }
        } else {
            incrementLoginAttempts($employee_ID);
            logAttempt($cr2_usm, $employee_ID, $Name, $Role, 'Failed', 'Login', 0, 'Incorrect password', '');
            $_SESSION["loginError"] = "Incorrect password.";
            // Generate new puzzle after failed attempt
            $_SESSION['puzzle_question'] = generatePuzzle();
            header("Location: index.php");
            exit();
        }
    }

    // If we reach here — no user found
    $_SESSION["loginError"] = "Invalid employee ID or password.";
    // Generate new puzzle after failed attempt
    $_SESSION['puzzle_question'] = generatePuzzle();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Financial System Login</title>
    
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($isOnline): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }
        .puzzle-box {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
        }
        .offline-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .puzzle-refresh {
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        .puzzle-refresh:hover {
            color: #2d3748;
            transform: rotate(180deg);
        }
        .form-input {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .form-input:focus {
            background: white;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        .login-btn {
            background: blue;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }
        .logo-side {
            background: white;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .logo-img {
            max-height: 120px;
            width: auto;
            object-fit: contain;
            margin-bottom: 1.5rem;
        }
        .logo-title {
            font-size: 1.8rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .logo-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            text-align: center;
        }
        .form-side {
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fade-out {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
        .animate-fade-out {
            animation: fade-out 0.3s ease-out;
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .login-main-container {
                flex-direction: column;
            }
            .logo-side {
                padding: 1.5rem;
                min-height: 200px;
            }
            .logo-img {
                max-height: 80px;
            }
            .logo-title {
                font-size: 1.5rem;
            }
            .form-side {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
   
    <div class="w-full max-w-4xl">
        <div class="login-main-container flex flex-col md:flex-row">
            <!-- Left Side: Logo & Branding -->
            <div class="logo-side md:w-2/5">
                <div class="flex flex-col items-center justify-center h-full">
                    <img src="images/logo.jpg" alt="Financial System Logo" class="logo-img" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjQwIiBoZWlnaHQ9IjI0MCIgZmlsbD0iI2ZmZmZmZiIgcng9IjIwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjdFRUEiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIzMiIgZm9udC13ZWlnaHQ9ImJvbGQiPkY8L3RleHQ+PC9zdmc+'">
                    <div class="text-center">
                        <h1 class="logo-title">Financial System</h1>
                        <p class="logo-subtitle">Budget & Finance Department</p>
                    </div>
                    <div class="mt-6 text-center">
                        <p class="text-sm opacity-80">Secure access to financial management tools and resources.</p>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Login Form -->
            <div class="form-side md:w-3/5">
                <div class="w-full">
                   
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <?php if (!$isOnline): ?>
                        <div class="offline-notice">
                            <i class='bx bx-wifi-off text-yellow-600'></i>
                            <span>You are currently offline. CAPTCHA is disabled.</span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Employee ID Input -->
                        <div class="mb-5">
                            <label class="block text-gray-700 text-sm font-semibold mb-2" for="employee_id">
                                Employee ID
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class='bx bx-user text-gray-400'></i>
                                </div>
                                <input 
                                    id="employee_id" 
                                    type="text" 
                                    class="w-full pl-10 pr-3 py-3 form-input rounded-lg focus:outline-none text-gray-800 placeholder-gray-500" 
                                    placeholder="Enter your employee ID"
                                    required
                                    name="employee_id"
                                    value="<?php echo htmlspecialchars($employee_ID); ?>"
                                >
                            </div>
                        </div>
                        
                        <!-- Password Input -->
                        <div class="mb-5">
                            <label class="block text-gray-700 text-sm font-semibold mb-2" for="password">
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class='bx bx-key text-gray-400'></i>
                                </div>
                                <input 
                                    id="password" 
                                    type="password" 
                                    class="w-full pl-10 pr-10 py-3 form-input rounded-lg focus:outline-none text-gray-800 placeholder-gray-500" 
                                    placeholder="Enter your password"
                                    required
                                    name="password"
                                >
                                <button 
                                    type="button" 
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    onclick="togglePasswordVisibility()"
                                >
                                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <svg id="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                                        <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Puzzle Section -->
                        <div class="mb-5">
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-gray-700 text-sm font-semibold">
                                    Security Verification
                                </label>
                                <button type="button" onclick="refreshPuzzle()" class="puzzle-refresh text-xs flex items-center gap-1">
                                    <i class='bx bx-refresh'></i> New Puzzle
                                </button>
                            </div>
                            <div class="puzzle-box">
                                <p id="puzzle-question" class="text-gray-800 font-semibold mb-2"><?php echo htmlspecialchars($_SESSION['puzzle_question'] ?? 'Solve the puzzle'); ?></p>
                                <input 
                                    type="text" 
                                    name="puzzle_answer" 
                                    id="puzzle_answer"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 text-gray-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500"
                                    placeholder="Enter your answer"
                                    required
                                >
                                <p class="text-gray-600 text-xs mt-2">Solve this puzzle to continue (case-insensitive)</p>
                            </div>
                        </div>
                        
                        <!-- CAPTCHA -->
                        <?php if ($isOnline): ?>
                        <div class="mb-5">
                            <div class="g-recaptcha" data-sitekey="6Ld4W8ArAAAAAK3qsDWjdvj6MNiXFJDPMgHGfhrw"></div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Login Button -->
                        <button 
                            type="submit" 
                            class="w-full login-btn py-3 px-4 rounded-lg transition duration-300 mt-2"
                        >
                            <i class='bx bx-log-in mr-2'></i> Login to System
                        </button>
                    </form>
                    
                    <!-- Footer Links -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                <a href="javascript:void(0)" onclick="toggleForgotModal(true)" class="hover:text-blue-600 transition font-medium">
                                    <i class='bx bx-help-circle mr-1'></i> Forgot Password?
                                </a>
                            </div>
                            <div class="text-xs text-gray-500">
                                &copy; <?php echo date('Y'); ?> Financials Department
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgot-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Reset Password</h2>
                <button onclick="toggleForgotModal(false)" class="text-gray-500 hover:text-gray-700">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            
            <form action="forgot_password.php" method="POST">
                <div class="mb-4">
                    <label class="block mb-2 text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" name="email" required 
                           class="w-full border border-gray-300 px-3 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter your registered email">
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="toggleForgotModal(false)" 
                            class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
                        Send Reset Link
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeSlashIcon = document.getElementById('eye-slash-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeSlashIcon.classList.add('hidden');
            }
        }

        // Toggle forgot password modal
        function toggleForgotModal(show) {
            const modal = document.getElementById("forgot-modal");
            if (show) {
                modal.classList.remove("hidden");
                modal.classList.add("flex");
            } else {
                modal.classList.add("hidden");
                modal.classList.remove("flex");
            }
        }
        
        // Refresh puzzle via AJAX
        function refreshPuzzle() {
            const puzzleQuestion = document.getElementById('puzzle-question');
            const puzzleAnswerInput = document.getElementById('puzzle_answer');
            const refreshBtn = event.currentTarget;
            
            // Add loading state
            refreshBtn.innerHTML = '<i class="bx bx-loader-alt animate-spin"></i> Loading...';
            refreshBtn.disabled = true;
            
            // Clear the answer input
            puzzleAnswerInput.value = '';
            
            // Make AJAX request to refresh puzzle
            fetch('refresh_puzzle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    puzzleQuestion.textContent = data.question;
                    showToast('New puzzle loaded!', 'success');
                } else {
                    showToast('Failed to load new puzzle. Please refresh page.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading puzzle. Please refresh page.', 'error');
            })
            .finally(() => {
                refreshBtn.innerHTML = '<i class="bx bx-refresh"></i> New Puzzle';
                refreshBtn.disabled = false;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const existingToasts = document.querySelectorAll('.toast-message');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast-message fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white animate-fade-in`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('animate-fade-out');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        // Check if logo image exists, fallback to SVG
        document.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.querySelector('.logo-img');
            logoImg.onerror = function() {
                this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQwIiBoZWlnaHQ9IjI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjQwIiBoZWlnaHQ9IjI0MCIgZmlsbD0iI2ZmZmZmZiIgcng9IjIwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZpbGw9IiM2NjdFRUEiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIzMiIgZm9udC13ZWlnaHQ9ImJvbGQiPkY8L3RleHQ+PC9zdmc+';
                this.alt = 'Financial System Logo';
            };
        });
    </script>
    
    <?php if (isset($_SESSION["loginError"])): ?>
        <script>
            alert('<?= addslashes($_SESSION["loginError"]); ?>');
        </script>
    <?php 
        unset($_SESSION["loginError"]);
    endif; ?>
</body>
</html>