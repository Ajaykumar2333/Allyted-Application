<?php
require_once 'db/config.php';

$email = "ajayallyted@gmail.com";
$password = "admin@123";

// Hash the password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Insert into DB
$stmt = $mysqli->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $hashed);
$stmt->execute();

echo "Admin inserted!";
?>
