<?php

$token = $_POST["token"];
$token_hash = hash("sha256", $token);

$mysqli = require __DIR__ . "/db/config.php";

// Check in admins table
$sql_admin = "SELECT * FROM admins WHERE reset_token = ?";
$stmt_admin = $mysqli->prepare($sql_admin);
$stmt_admin->bind_param("s", $token_hash);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$admin = $result_admin->fetch_assoc();

// Check in employees table
$sql_emp = "SELECT * FROM credentials WHERE reset_token = ?";
$stmt_emp = $mysqli->prepare($sql_emp);
$stmt_emp->bind_param("s", $token_hash);
$stmt_emp->execute();
$result_emp = $stmt_emp->get_result();
$employee = $result_emp->fetch_assoc();

// Determine which user (if any) matches the token
if ($admin !== null) {
    $user = $admin;
    $user_type = "admin";
} elseif ($employee !== null) {
    $user = $employee;
    $user_type = "employee";
} else {
    die("Token not found");
}

// Check if token has expired
if (strtotime($user["token_expiry"]) <= time()) {
    die("Token has expired");
}

// Password validation
if (strlen($_POST["password"]) < 8) {
    die("Password must be at least 8 characters");
}
if (!preg_match("/[a-z]/i", $_POST["password"])) {
    die("Password must contain at least one letter");
}
if (!preg_match("/[0-9]/", $_POST["password"])) {
    die("Password must contain at least one number");
}
if ($_POST["password"] !== $_POST["password_confirmation"]) {
    die("Passwords must match");
}

// Hash the new password
$password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);

// Update password and clear token
if ($user_type === "admin") {
    $sql_update = "UPDATE admins
                   SET password = ?, reset_token = NULL, token_expiry = NULL
                   WHERE id = ?";
    $stmt_update = $mysqli->prepare($sql_update);
    $stmt_update->bind_param("si", $password_hash, $user["id"]);
} else {
    $sql_update = "UPDATE credentials
                   SET password = ?, reset_token = NULL, token_expiry = NULL
                   WHERE employee_id = ?";
    $stmt_update = $mysqli->prepare($sql_update);
    $stmt_update->bind_param("si", $password_hash, $user["employee_id"]);
}

$stmt_update->execute();

echo "✅ Password updated. You can now login.";
