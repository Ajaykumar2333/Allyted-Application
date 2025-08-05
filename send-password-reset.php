<?php
// Include the mailer and database configuration
require_once __DIR__ . "/mailer.php";
require_once __DIR__ . "/db/config.php";

// Get user email
$email = $_POST["email"] ?? "";

// Validate email
if (empty($email)) {
    echo "❌ Please provide an email address.";
    exit;
}

// Generate reset token and expiry
$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 30); // 30 minutes from now

file_put_contents('admin/debug.log', "Generated token: $token, hash: $token_hash, expiry: $expiry for email: $email at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Flag to track user type
$user_type = null;

// Check admins table
$sql_admins = "UPDATE admins SET reset_token = ?, token_expiry = ? WHERE email = ?";
$stmt_admins = $mysqli->prepare($sql_admins);
$stmt_admins->bind_param("sss", $token_hash, $expiry, $email);
$stmt_admins->execute();
$affected_admins = $stmt_admins->affected_rows;
if ($affected_admins > 0) {
    $user_type = "admin";
    file_put_contents('admin/debug.log', "Updated admins table for $email, affected_rows: $affected_admins at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
} else {
    file_put_contents('admin/debug.log', "No update in admins table for $email, affected_rows: $affected_admins at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// If not found in admins, check credentials table
if (!$user_type) {
    $sql_cred = "UPDATE credentials SET reset_token = ?, token_expiry = ? WHERE email = ?";
    $stmt_cred = $mysqli->prepare($sql_cred);
    $stmt_cred->bind_param("sss", $token_hash, $expiry, $email);
    $stmt_cred->execute();
    $affected_cred = $stmt_cred->affected_rows;
    if ($affected_cred > 0) {
        $user_type = "employee";
        file_put_contents('admin/debug.log', "Updated credentials table for $email, affected_rows: $affected_cred at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    } else {
        file_put_contents('admin/debug.log', "No update in credentials table for $email, affected_rows: $affected_cred at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }
}

$stmt_admins->close();
if (isset($stmt_cred)) $stmt_cred->close();

// Check if email was found in either table
if ($user_type) {
    // Create the reset link based on user type
    $resetLink = "http://localhost/Allyted%20Project/reset-password.php?token=" . urlencode($token) . "&type=" . $user_type;

    // Prepare the email content (HTML)
    $subject = "Password Reset - Allyted";
    $message = <<<END
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #007BFF;">Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset your password. Click the button below to proceed. This link will expire in 30 minutes.</p>
        <p style="text-align: center;">
            <a href="$resetLink" style="display: inline-block; padding: 10px 20px; background: #007BFF; color: white; text-decoration: none; border-radius: 5px;">Reset Password</a>
        </p>
        <p>If you did not request a password reset, you can ignore this email.</p>
        <p>Best regards,<br>Allyted Support</p>
    </div>
</body>
</html>
END;

    try {
        sendMail($email, $email, $subject, $message, true); // Set isHtml to true
        echo "✅ Reset link sent. Please check your inbox.";
    } catch (Exception $e) {
        echo "❌ Mail error: " . $e->getMessage();
    }
} else {
    echo "❌ No account found with that email address.";
}

$mysqli->close();
?>