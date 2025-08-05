<?php
session_start();
require_once '../db/config.php';

if (!isset($_SESSION['employee_id']) || !isset($_SESSION['authority_level'])) {
    header("Location: ../employee_login.php");
    exit;
}

$page = $_GET['page'] ?? 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Allyted</title>
    <link href="../css/navbar.css" rel="stylesheet">
    <script src="../js/navbar.js" defer></script>
</head>
<body>
    <?php include '../header.php'; ?>

    <main>
        <?php
        if ($page === 'profile') {
            include '../templates/profile.php';
        } elseif ($page === 'notifications') {
            include '../templates/notifications.php';
        } else {
            // Default Home Content
            echo "<h1>Welcome, " . htmlspecialchars($_SESSION['employee_name']) . "!</h1>";
            echo "<p>Your dashboard content will appear here. Role: " . htmlspecialchars($_SESSION['authority_level']) . "</p>";
            echo '<div class="dashboard-content">';
            echo '<p>Content specific to your role will be added here (e.g., tabs for leave management).</p>';
            echo '</div>';
        }
        ?>
    </main>
</body>
</html>