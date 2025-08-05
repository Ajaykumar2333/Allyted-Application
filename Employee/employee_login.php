<?php
session_start();

// Verify config.php exists
if (!file_exists('../db/config.php')) {
    error_log("config.php not found in C:\\xampp\\htdocs\\Allyted Project\\db");
    header("Location: ../index.html?error=Server configuration error: config.php missing in db folder");
    exit();
}
require_once '../db/config.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    error_log("Login attempt: Email = $email");

    // Prepare and execute query
    $stmt = $mysqli->prepare("SELECT c.employee_id, c.email, c.password, e.full_name FROM credentials c LEFT JOIN employee_details e ON c.employee_id = e.employee_id WHERE c.email = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        header("Location: ../index.html?error=Database error");
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        header("Location: ../index.html?error=Database error");
        exit();
    }
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        error_log("User found: Employee ID = " . $row['employee_id']);
        // Verify password
        if (password_verify($password, $row['password'])) {
            $_SESSION['employee_id'] = $row['employee_id'];
            $_SESSION['employee_email'] = $row['email'];
            $_SESSION['employee_name'] = $row['full_name'] ?: 'Employee';
            error_log("Login successful for: " . $row['email']);
            header("Location: dashboard.php");
            exit();
        } else {
            error_log("Password verification failed for: $email");
            header("Location: ../index.html?error=Invalid email or password");
            exit();
        }
    } else {
        error_log("No user found with email: $email");
        header("Location: ../index.html?error=Invalid email or password");
        exit();
    }

    $stmt->close();
    $mysqli->close();
} else {
    error_log("Invalid request method");
    header("Location: ../index.html?error=Invalid request");
    exit();
}
?>