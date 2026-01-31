<?php
session_start();
include("../main_connection.php"); // your DB connection

// Redirect to login_main.php if not accessing the reset password functionality
if (!isset($_GET['token'])) {
    header("Location: index.php");
    exit();
}

$db_name = "rest_core_2_usm"; 
if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}
$conn = $connections[$db_name]; 

$token = $_GET['token'];

// Check if token exists and is valid
$stmt = $conn->prepare("SELECT pr.employee_id, pr.expired_at, da.email 
                        FROM password_resets pr
                        JOIN department_accounts da ON pr.employee_id = da.employee_id
                        WHERE pr.token = ?
                        LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: index.php.php?error=invalid_token");
    exit();
}

// Check if token is expired
if (strtotime($user['expired_at']) < time()) {
    header("Location: index.php.php?error=expired_token");
    exit();
}

// Handle form submission
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update the user's password
        $update = $conn->prepare("UPDATE department_accounts SET password = ? WHERE employee_id = ?");
        $update->bind_param("ss", $hashed_password, $user['employee_id']);
        
        if ($update->execute()) {
            // Delete the token so it can't be reused
            $del = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $del->bind_param("s", $token);
            $del->execute();
            
            header("Location: index.php.php?success=password_reset");
            exit();
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: url('hotel2.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 31, 84, 0.85) 0%, rgba(14, 62, 138, 0.8) 100%);
            z-index: 0;
        }
        
        .container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #F7B32B;
            box-shadow: 0 4px 15px rgba(247, 179, 43, 0.4);
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 31, 84, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: white;
        }
        
        .reset-card h2 {
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 28px;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #E6F0FF;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: #F7B32B;
            box-shadow: 0 0 0 3px rgba(247, 179, 43, 0.3);
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #F7B32B;
            font-size: 18px;
        }
        
        .password-requirements {
            background: rgba(0, 31, 84, 0.4);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #F7B32B;
        }
        
        .password-requirements p {
            margin-bottom: 8px;
            color: #E6F0FF;
            font-weight: 500;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 5px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            color: #A4C5F4;
        }
        
        .password-requirements li i {
            margin-right: 8px;
            font-size: 12px;
            color: #F7B32B;
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #F7B32B 0%, #F9C74F 100%);
            color: #001f54;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(247, 179, 43, 0.3);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(247, 179, 43, 0.4);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #A4C5F4;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: #F7B32B;
        }
        
        .back-link i {
            margin-right: 8px;
        }
        
        .strength-meter {
            height: 5px;
            border-radius: 5px;
            margin-top: 8px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0;
            border-radius: 5px;
            transition: width 0.3s ease, background 0.3s ease;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .reset-card {
                padding: 25px 20px;
            }
            
            .reset-card h2 {
                font-size: 24px;
            }
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #FECACA;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            <?php echo !empty($error) ? 'display: block;' : 'display: none;'; ?>
        }
        
        .success-message {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #BBF7D0;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8cmVzdGF1cmFudHxlbnwwfHwwfHx8MA%3D%3D&w=1000&q=80" alt="Restaurant Logo">
        </div>
        
        <div class="reset-card">
            <h2>Reset Your Password</h2>
            
            <?php if (!empty($error)): ?>
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i> Password successfully reset
            </div>
            
            <form id="resetForm" method="POST" action="">
                <div class="input-group">
                    <label for="password">New Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your new password" required>
                    <div class="strength-meter">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm your new password" required>
                </div>
                
                <div class="password-requirements">
                    <p>Password must include:</p>
                    <ul>
                        <li><i class="fas fa-check"></i> At least 6 characters</li>
                        <li><i class="fas fa-check"></i> Matching confirmation</li>
                    </ul>
                </div>
                
                <button type="submit" id="resetButton">Change Password</button>
            </form>
            
            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const strengthFill = document.getElementById('strengthFill');
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                let strength = 0;
                
                if (password.length >= 6) strength += 50;
                if (password === confirmPasswordInput.value && password.length > 0) strength += 50;
                
                strengthFill.style.width = strength + '%';
                
                if (strength < 50) {
                    strengthFill.style.background = '#ef4444'; // red
                } else if (strength < 100) {
                    strengthFill.style.background = '#f59e0b'; // amber
                } else {
                    strengthFill.style.background = '#10b981'; // green
                }
            });
            
            // Confirm password input listener
            confirmPasswordInput.addEventListener('input', function() {
                // Update strength when confirm password changes
                passwordInput.dispatchEvent(new Event('input'));
            });
            
            // Form submission - client-side validation
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Hide messages
                if (errorMessage) errorMessage.style.display = 'none';
                if (successMessage) successMessage.style.display = 'none';
                
                // Validate passwords
                if (password !== confirmPassword) {
                    if (errorMessage) {
                        errorMessage.textContent = 'Passwords do not match';
                        errorMessage.style.display = 'block';
                    }
                    e.preventDefault();
                    return;
                }
                
                // Validate password length
                if (password.length < 6) {
                    if (errorMessage) {
                        errorMessage.textContent = 'Password must be at least 6 characters long';
                        errorMessage.style.display = 'block';
                    }
                    e.preventDefault();
                    return;
                }
                
                // If all validations pass, allow form submission to PHP
                if (successMessage) {
                    successMessage.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html>