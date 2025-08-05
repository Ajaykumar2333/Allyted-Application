<?php
session_start();

// Log all errors to php_errors.log for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// Extend session lifetime to prevent expiration during AJAX requests
ini_set('session.gc_maxlifetime', 86400);

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    if (isset($_GET['action']) && in_array($_GET['action'], ['get_roles_by_department', 'get_employees_by_department_and_role'])) {
        // For AJAX requests, return a JSON error instead of redirecting
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Session expired. Please log in again.']);
        exit;
    } else {
        header("Location: index.php");
        exit;
    }
}

require_once 'db/config.php';

// Generate a session token for AJAX requests to maintain session integrity
if (!isset($_SESSION['ajax_token'])) {
    $_SESSION['ajax_token'] = bin2hex(random_bytes(16));
}
$ajax_token = $_SESSION['ajax_token'];

// Handle AJAX requests for dependent dropdowns
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Validate the AJAX token to ensure the request is legitimate
    if (!isset($_GET['ajax_token']) || $_GET['ajax_token'] !== $ajax_token) {
        echo json_encode(['error' => 'Invalid AJAX token']);
        exit;
    }

    if ($_GET['action'] === 'get_roles_by_department') {
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        error_log("Fetching roles for department_id: $department_id");

        if ($department_id <= 0) {
            echo json_encode(['error' => 'Invalid department ID']);
            exit;
        }

        // Check database connection
        if (!$mysqli->ping()) {
            echo json_encode(['error' => 'Database connection error: ' . $mysqli->error]);
            exit;
        }
        error_log("Database connection alive");

        $stmt = $mysqli->prepare("SELECT id, name FROM roles WHERE department_id = ? ORDER BY name");
        if (!$stmt) {
            echo json_encode(['error' => 'Database prepare error: ' . $mysqli->error]);
            exit;
        }

        $stmt->bind_param("i", $department_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Database execute error: ' . $stmt->error]);
            exit;
        }

        $result = $stmt->get_result();
        $roles = $result->fetch_all(MYSQLI_ASSOC);
        error_log("Roles fetched: " . json_encode($roles));
        $stmt->close();

        echo json_encode($roles);
        exit;
    }

    if ($_GET['action'] === 'get_employees_by_department_and_role') {
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        $role_id = isset($_GET['role_id']) ? intval($_GET['role_id']) : 0;
        error_log("Fetching employees for department_id: $department_id, role_id: $role_id");

        if ($department_id <= 0 || $role_id <= 0) {
            echo json_encode(['error' => 'Invalid department or role ID']);
            exit;
        }

        if (!$mysqli->ping()) {
            echo json_encode(['error' => 'Database connection error: ' . $mysqli->error]);
            exit;
        }

        $stmt = $mysqli->prepare("
            SELECT employee_id, CONCAT(first_name, ' ', last_name) AS name 
            FROM employees 
            WHERE department_id = ? AND role_id = ? AND status = 'Active'
            ORDER BY first_name
        ");
        if (!$stmt) {
            echo json_encode(['error' => 'Database prepare error: ' . $mysqli->error]);
            exit;
        }

        $stmt->bind_param("ii", $department_id, $role_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'Database execute error: ' . $stmt->error]);
            exit;
        }

        $result = $stmt->get_result();
        $employees = $result->fetch_all(MYSQLI_ASSOC);
        error_log("Employees fetched: " . json_encode($employees));
        $stmt->close();

        echo json_encode($employees);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = ['dashboard', 'employee', 'assets', 'location', 'department', 'role', 'brand', 'templates', 'holidays','learnings','onboardings'];

// Handle AJAX form submissions without rendering HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($page, $allowed_pages)) {
        include "includes/content_$page.php";
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid page']);
        exit;
    }
}

// If not a POST request, render the full HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="logo">
            <img src="./media/allyted-logo2 (2).png" alt="YourCompany Logo" style="height: 80px; vertical-align: middle;">
        </div>
        <div class="admin-info">
            <span>Admin</span>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="admin_dashboard.php?page=dashboard"><i class="fas fa-home"></i> Dashboard</a>
        <a href="admin_dashboard.php?page=employee"><i class="fas fa-users"></i> Employee</a>
        <a href="admin_dashboard.php?page=assets"><i class="fas fa-boxes"></i> Assets</a>
        <!-- Master Dropdown -->
        <div class="dropdown">
            <div class="dropdown-toggle"><i class="fas fa-layer-group"></i> Master <i class="fas fa-chevron-down" style="margin-left:auto;"></i></div>
            <div class="dropdown-content vertical">
                <a href="admin_dashboard.php?page=location"><i class="fas fa-map-marker-alt"></i> Location</a>
                <a href="admin_dashboard.php?page=department"><i class="fas fa-building"></i> Department</a>
                <a href="admin_dashboard.php?page=role"><i class="fas fa-user-tag"></i> Role</a>
                <a href="admin_dashboard.php?page=brand"><i class="fas fa-tags"></i> Brand</a>
                <a href="admin_dashboard.php?page=templates"><i class="fas fa-file-alt"></i> Templates</a>
                <a href="admin_dashboard.php?page=holidays"><i class="fas fa-umbrella-beach"></i> Holidays</a>
                <a href="admin_dashboard.php?page=learnings"><i class="fas fa-book"></i>Learnings</a>
                <a href="admin_dashboard.php?page=onboardings"><i class="fas fa-file-signature"></i>Onboardings</a>
            </div>
        </div>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php
        if (in_array($page, $allowed_pages)) {
            include "includes/content_$page.php";
        } else {
            echo "<h2>Page not found</h2>";
        }
        ?>
    </div>
</body>
</html>