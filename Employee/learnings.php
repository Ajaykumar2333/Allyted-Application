<?php
require_once '../db/config.php';
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: Redirecting to login");
    header("Location: ../index.php?error=Please login first");
    exit;
}

$full_name = $_SESSION['employee_name'] ?? 'Unknown';

// Fetch data from learnings table
try {
    $test_query = $mysqli->query("SELECT 1");
    if ($test_query) {
        error_log("Database connection successful");
    }

    $table_check = $mysqli->query("SHOW TABLES LIKE 'learnings'");
    if ($table_check->num_rows == 0) {
        error_log("Table 'learnings' does not exist");
        $error_message = "Table 'learnings' does not exist. Please create it with columns: id, purpose, links, duration, created_at.";
        $learnings = [];
    } else {
        // Check total records
        $total_query = $mysqli->query("SELECT COUNT(*) as total FROM learnings");
        $total_row = $total_query->fetch_assoc();
        $total_records = $total_row['total'];
        error_log("Total records in learnings table: $total_records");

        // Fetch all records
        $query = "SELECT id, purpose, links, duration, created_at FROM learnings ORDER BY created_at ASC";
        $result = $mysqli->query($query);
        $learnings = [];
        while ($row = $result->fetch_assoc()) {
            $learnings[] = $row;
        }
        error_log("Fetched " . count($learnings) . " records from learnings table");
        if (!empty($learnings)) {
            error_log("Sample record: " . json_encode($learnings[0]));
        } else {
            error_log("No records found in learnings table. Total records: $total_records");
            $error_message = "No learning records found. The table has $total_records records. Please add records or contact your administrator.";
        }
        $result->free();
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage() . ". Please check db/config.php.";
    $learnings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
            background: #f9fafb;
        }
        .navbar {
            background: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 12px 24px;
        }
        .nav-link {
            transition: all 0.3s ease;
            position: relative;
        }
        .nav-link:hover::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #d4af37;
        }
        .dropdown-menu {
            transition: all 0.3s ease;
            transform-origin: top;
            transform: scaleY(0);
            opacity: 0;
        }
        .group:hover .dropdown-menu {
            transform: scaleY(1);
            opacity: 1;
        }
        .employee-button {
            transition: all 0.3s ease;
        }
        .employee-button:hover {
            background: #f3f4f6;
            transform: scale(1.02);
        }
        .logo-img {
            max-height: 60px;
            width: auto;
        }
        /* Professional Table Styles */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modern-table-container {
            max-height: calc(100vh - 220px);
            overflow: hidden;
            padding: 16px;
        }
        .modern-table {
            width: 100%;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            animation: fadeIn 0.5s ease-out;
        }
        .modern-table th {
            background: #374151;
            color: #ffffff;
            font-weight: 600;
            padding: 12px 16px;
            text-align: left;
            font-size: 15px;
            text-transform: capitalize;
            letter-spacing: 0.05em;
        }
        .modern-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.3s ease;
        }
        .modern-table tr:last-child td {
            border-bottom: none;
        }
        .modern-table tr {
            transition: background 0.3s ease;
        }
        .modern-table tr:hover {
            background: #f3f4f6;
        }
        .modern-table td.purpose, .modern-table td.links {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .modern-table td.links a {
            color: #d4af37;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .modern-table td.links a:hover {
            color: #b8972e;
            text-decoration: underline;
        }
        .debug-message {
            color: #dc2626;
            font-size: 14px;
            margin-top: 12px;
            background: #fef2f2;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .table-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #374151;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="navbar fixed top-0 left-0 w-full flex justify-between items-center z-30">
        <div class="flex items-center">
            <img src="../media/allyted-logo2 (2).png" alt="Allyted Logo" class="logo-img">
        </div>
        <div class="flex items-center space-x-6">
            <div class="relative group">
                <button class="employee-button flex items-center px-3 py-2 rounded-lg text-gray-600 hover:text-yellow-600">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <span class="text-lg font-semibold"><?php echo htmlspecialchars($full_name); ?></span>
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg">
                    <a href="my_profile.php" class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Profile
                    </a>
                    <a href="../logout.php" class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h3a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex-1 mt-20 flex flex-col items-center px-4 sm:px-6">
        <div class="w-full max-w-7xl">
            <div class="table-header">
                <h1 class="table-title">Learning Records</h1>
            </div>
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Purpose</th>
                            <th>Links</th>
                            <th>Duration</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($learnings)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 py-8">No learning records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($learnings as $learning): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($learning['id']); ?></td>
                                    <td class="purpose" title="<?php echo htmlspecialchars($learning['purpose'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($learning['purpose'] ?? ''); ?>
                                    </td>
                                    <td class="links" title="<?php echo htmlspecialchars($learning['links'] ?? 'No link'); ?>">
                                        <?php if ($learning['links']): ?>
                                            <a href="<?php echo htmlspecialchars($learning['links']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($learning['links']); ?>
                                            </a>
                                        <?php else: ?>
                                            No link
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($learning['duration'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($learning['created_at'] ? date('d M Y H:i', strtotime($learning['created_at'])) : ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (isset($error_message)): ?>
                <p class="debug-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>