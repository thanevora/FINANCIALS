<?php
session_start();
include("API_gateway.php");

$baseUrl = isset($_SERVER['HTTPS']) ? "https://" : "http://";
$baseUrl .= $_SERVER['HTTP_HOST'] . 'localhost';

// Check if image exists
$imagePath = '../images/hotel3.jpg';
$imageExists = file_exists($imagePath);

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

    $mail->setFrom('Soliera_Hotel&Restaurant@gmail.com', 'Soliera 2FA Authenticator');
    $mail->addAddress($email);
    $mail->Subject = 'Soliera 2FA Verification Code';

    // Email content
    $header = "<h2 style='color:#4CAF50; font-family: Arial, sans-serif;'>Soliera Hotel & Restaurant</h2>
               <hr style='border:1px solid #ddd;'>";
    $message = "<p style='font-family: Arial, sans-serif; font-size:14px;'>
                    <br>
                    We received a request to verify your login to <strong>Soliera Hotel & Restaurant</strong>.
                    Please use the one-time verification code below to complete your login:
                </p>
                <p style='font-size:22px; font-weight:bold; color:#333; letter-spacing:2px;'>
                    $otp
                </p>
                <p style='font-family: Arial, sans-serif; font-size:14px; color:#555;'>
                    This code will expire in <strong>5 minutes</strong> for your security.
                    If you did not request this code, please ignore this email or contact our support team immediately.
                </p>";
    $footer = "<hr style='border:1px solid #ddd;'>
               <p style='font-size:12px; color:#777; font-family: Arial, sans-serif;'>
                    Thank you for choosing Soliera.<br>
                    📞 Hotline: +63-900-123-4567 | 📧 support@soliera.com<br>
                    <em>This is an automated message. Please do not reply directly to this email.</em>
               </p>";

    $mail->isHTML(true);
    $mail->Body = $header . $message . $footer;

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
    <title>Soliera Hotel - Department Login</title>
    
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if ($isOnline): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    
    <style>
        .bg-fallback {
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .puzzle-box {
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
        }
        .offline-notice {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .puzzle-refresh {
            color: #F7B32B;
            cursor: pointer;
            transition: all 0.3s;
        }
        .puzzle-refresh:hover {
            color: #EDB886;
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
   
   <section class="relative w-full h-screen">
        <!-- Background image with overlay -->
        <div class="absolute inset-0 bg-cover bg-center z-0" style="background-image: url('<?php echo $imageExists ? $imagePath : ''; ?>');">
            <!-- Fallback in case image doesn't load -->
            <?php if (!$imageExists): ?>
                <div class="bg-fallback w-full h-full">
                    <div>Soliera Hotel & Restaurant</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="absolute inset-0 bg-black/40 z-10"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/70 z-10"></div>
  
        <!-- Centered Login Form -->
        <div class="relative z-10 w-full h-full flex justify-center items-center p-4">
            <div class="w-full max-w-md">
                <div class="bg-white/10 backdrop-blur-lg p-6 rounded-xl shadow-2xl border border-white/20">
                  
                    
                    <!-- Login Form -->
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <!-- Employee ID Input -->
                        <div class="mb-4">
                            <label class="block text-white/90 text-sm font-medium mb-2" for="employee_id">
                                Employee ID
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class='bx bx-user text-white/50'></i>
                                </div>
                                <input 
                                    id="employee_id" 
                                    type="text" 
                                    class="w-full pl-10 pr-3 py-3 bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent placeholder-white/50" 
                                    placeholder="Your Employee ID"
                                    required
                                    name="employee_id"
                                    value="<?php echo htmlspecialchars($employee_ID); ?>"
                                >
                            </div>
                        </div>
                        
                        <!-- Password Input -->
                        <div class="mb-4">
                            <label class="block text-white/90 text-sm font-medium mb-2" for="password">
                                Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class='bx bx-key text-white/50'></i>
                                </div>
                                <input 
                                    id="password" 
                                    type="password" 
                                    class="w-full pl-10 pr-10 py-3 bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent placeholder-white/50" 
                                    placeholder="Password"
                                    required
                                    name="password"
                                >
                                <button 
                                    type="button" 
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-white/50 hover:text-white focus:outline-none"
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
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-white/90 text-sm font-medium">
                                    Security Puzzle
                                </label>
                                <button type="button" onclick="refreshPuzzle()" class="puzzle-refresh text-xs flex items-center gap-1">
                                    <i class='bx bx-refresh'></i> New Puzzle
                                </button>
                            </div>
                            <div class="puzzle-box">
                                <p id="puzzle-question" class="text-white font-semibold mb-2"><?php echo htmlspecialchars($_SESSION['puzzle_question'] ?? 'Solve the puzzle'); ?></p>
                                <input 
                                    type="text" 
                                    name="puzzle_answer" 
                                    id="puzzle_answer"
                                    class="w-full px-3 py-2 bg-white/10 border border-white/20 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent placeholder-white/50"
                                    placeholder="Your answer"
                                    required
                                >
                                <p class="text-white/70 text-xs mt-2">Solve this puzzle to continue (case-insensitive)</p>
                            </div>
                        </div>
                        
                        <!-- CAPTCHA -->
                        <?php if ($isOnline): ?>
                        <div class="mb-4">
                            <div class="g-recaptcha" data-sitekey="6Ld4W8ArAAAAAK3qsDWjdvj6MNiXFJDPMgHGfhrw"></div>
                        </div>
                        <?php else: ?>
                        
                        <?php endif; ?>
                        
                        <!-- Login Button -->
                        <button 
                            type="submit" 
                            class="w-full bg-gradient-to-r from-[#EDB886] to-[#F7B32B] hover:opacity-90 text-white font-bold py-3 px-4 rounded-lg transition duration-300 mt-2"
                        >
                            <i class='bx bx-log-in mr-2'></i> Login
                        </button>
                    </form>
                    
                    <!-- Footer Links -->
                    <div class="mt-6 pt-6 border-t border-white/20 text-center">
                        <div class="text-sm text-white/70">
                            <a href="javascript:void(0)" onclick="toggleForgotModal(true)" class="hover:text-white transition">
                                <i class='bx bx-help-circle mr-1'></i> Forgot Password?
                            </a>
                        </div>
                        <div class="mt-4 text-xs text-white/50">
                            Build By: BSIT - 4102 | Cluster 2
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div id="forgot-modal" class="fixed inset-0 bg-black/20 backdrop-blur-sm hidden items-center justify-center z-50">
            <div class="bg-white/90 backdrop-blur-md rounded-xl p-6 w-full max-w-md shadow-xl border border-white/20">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Reset Password</h2>
                    <button onclick="toggleForgotModal(false)" class="text-gray-500 hover:text-gray-700">
                        <i class='bx bx-x text-xl'></i>
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
    </section>

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
                    // Show success message
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
                // Reset button
                refreshBtn.innerHTML = '<i class="bx bx-refresh"></i> New Puzzle';
                refreshBtn.disabled = false;
            });
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast-message');
            existingToasts.forEach(toast => toast.remove());
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast-message fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-600' : 'bg-red-600'
            } text-white animate-fade-in`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.classList.add('animate-fade-out');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        // Add CSS animations for toast
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);
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