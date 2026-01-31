<?php
session_start();
include("../main_connection.php"); // your DB connection

$db_name = "rest_core_2_usm"; 

if (!isset($connections[$db_name])) {
    die("❌ Connection not found for $db_name");
}

$conn = $connections[$db_name]; 

function sendResetLink($email, $resetLink) {
    require_once '../PHPMailer/PHPMailerAutoload.php';
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // Gmail account you control
    $mail->Username = 'VehicleReservationManagement@gmail.com';
    $mail->Password = 'fzja ezgo ojdu fobc'; // Gmail App Password
    $mail->SMTPSecure = 'tls'; 
    $mail->Port = 587;

    // ✅ Must match the authenticated Gmail
    $mail->setFrom('VehicleReservationManagement@gmail.com', 'Soliera Password Reset');
    $mail->addAddress($email);

    $mail->Subject = 'Password Reset Request - Soliera Hotel';

    $header = "<h2 style='color:#4CAF50; font-family: Arial, sans-serif;'>Soliera Hotel & Restaurant</h2>
               <hr style='border:1px solid #ddd;'>";

    $message = "<p style='font-family: Arial, sans-serif; font-size:14px;'>
                    We received a request to reset the password for your <strong>Soliera Hotel & Restaurant</strong> account.
                    Click the button below to reset your password:
                </p>
                <p style='text-align:center; margin:20px 0;'>
                    <a href='$resetLink' style='background:#F7B32B; color:#fff; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px;'>
                        Reset Password
                    </a>
                </p>
                <p style='font-family: Arial, sans-serif; font-size:13px; color:#555;'>
                    This link will expire in <strong>1 hour</strong>. 
                    If you did not request this change, please ignore this email or contact support.
                </p>";

    $footer = "<hr style='border:1px solid #ddd;'>
               <p style='font-size:12px; color:#777; font-family: Arial, sans-serif;'>
                    Thank you for choosing Soliera.<br>
                    📞 Hotline: +63-900-123-4567 | 📧 support@soliera.com<br>
                    <em>This is an automated message. Please do not reply directly to this email.</em>
               </p>";

    $mail->isHTML(true);
    $mail->Body = $header . $message . $footer;

    if(!$mail->send()) {
        error_log("Mailer Error: " . $mail->ErrorInfo); // log instead of echo
        return false;
    }
    return true;
}

// === Forgot Password Handler ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT employee_id FROM department_accounts WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // ✅ Store token
        $stmt = $conn->prepare("INSERT INTO password_resets (employee_id, token, expired_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user['employee_id'], $token, $expires);
        $stmt->execute();

        $resetLink = "https://restaurant.soliera-hotel-restaurant.com/USM/reset_password.php?token=" . urlencode($token);

        if (sendResetLink($email, $resetLink)) {
            // ✅ Redirect with success message
            header("Location: index.php?status=reset_link_sent");
            exit;
        } else {
            header("Location: index.php.php?status=reset_failed");
            exit;
        }
    } else {
        header("Location: index.php.php?status=no_account");
        exit;
    }
}
?>
