<?php
session_start();
require_once 'db/config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate
    if (empty($email) || empty($password)) {
        echo "Email and password are required.";
        exit;
    }

    // Use prepared statements to prevent SQL injection
    $stmt = $mysqli->prepare("SELECT id, email, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password'])) {
            // Login success
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            header("Location: admin_dashboard.php");
            exit;
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "No admin found with that email.";
    }

    $stmt->close();
    $mysqli->close();
} else {
    echo "Invalid request.";
}
?>
