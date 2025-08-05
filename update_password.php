<?php
require_once 'db/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['token'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        die("Passwords do not match.");
    }

    // Hash new password
    $hashed = password_hash($new, PASSWORD_DEFAULT);

    // Update password where token matches and not expired
    $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("ss", $hashed, $token);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo "✅ Password updated successfully.";
    } else {
        echo "❌ Invalid or expired token.";
    }

    $stmt->close();
    $conn->close();
}
?>
