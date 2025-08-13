<?php
require_once '../db/config.php';
if (!$mysqli) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Please contact the administrator.");
}
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: employee_email or employee_id not set");
    header("Location: ../index.php?error=Please login first");
    exit;
}

$full_name = $_SESSION['employee_name'] ?? 'Unknown';

try {
    $test_query = $mysqli->query("SELECT 1");
    if ($test_query === false) {
        throw new Exception("Test query failed: " . $mysqli->error);
    }
    error_log("Database connection successful");

    $table_check = $mysqli->query("SHOW TABLES LIKE 'onboardings'");
    if ($table_check->num_rows == 0) {
        error_log("Table 'onboardings' does not exist");
        $error_message = "Table 'onboardings' does not exist. Please create it with columns: id, purpose, links, duration, created_at.";
        $onboardings = [];
    } else {
        $total_query = $mysqli->query("SELECT COUNT(*) as total FROM onboardings");
        if ($total_query === false) {
            throw new Exception("Total query failed: " . $mysqli->error);
        }
        $total_row = $total_query->fetch_assoc();
        $total_records = $total_row['total'];
        error_log("Total records in onboardings table: $total_records");

        $query = "SELECT id, purpose, links, duration FROM onboardings ORDER BY id ASC";
        $result = $mysqli->query($query);
        if ($result === false) {
            throw new Exception("Fetch query failed: " . $mysqli->error);
        }
        $onboardings = [];
        while ($row = $result->fetch_assoc()) {
            $onboardings[] = $row;
        }
        error_log("Fetched " . count($onboardings) . " records from onboardings table");
        if (!empty($onboardings)) {
            error_log("Sample record: " . json_encode($onboardings[0]));
        } else {
            error_log("No records found in onboardings table. Total records: $total_records");
            $error_message = "No onboarding records found. Please add records or contact your administrator.";
        }
        $result->free();
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage() . ". Please check db/config.php.";
    $onboardings = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
            background: #f8f9fc;
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

        .table-container {
            max-width: 1200px;
            margin: 20px auto;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            background: transparent;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        thead {
            background-color: #e6ebf3;
        }

        thead th {
            text-align: left;
            padding: 16px;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            border: 1px solid #d1d5db;
            background-color: #e6ebf3;
            white-space: nowrap;
        }

        thead th:nth-child(1), tbody td:nth-child(1) { width: 10%; }
        thead th:nth-child(2), tbody td:nth-child(2) { width: 30%; }
        thead th:nth-child(3), tbody td:nth-child(3) { width: 45%; }
        thead th:nth-child(4), tbody td:nth-child(4) { width: 15%; }

        tbody td {
            padding: 16px;
            font-size: 14px;
            color: #333;
            border: 1px solid #d1d5db;
            background: transparent;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        table tbody td a {
            color: #1e73be !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        table tbody td a:hover {
            text-decoration: underline;
        }

        .debug-message {
            color: #dc2626;
            font-size: 14px;
            margin: 12px auto;
            max-width: 1200px;
            background: #fef2f2;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 24px;
            font-weight: bold;
            margin: 20px auto;
            max-width: 1200px;
            background: -webkit-linear-gradient(#0093D2, #212688);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .heading img {
            width: 35px;
            height: 35px;
        }

        #book {
            width: 70px;
            height: 30px;
        }

        @media (max-width: 768px) {
            .table-container {
                max-width: 100%;
            }
            table {
                min-width: 600px;
            }
            thead th, tbody td {
                font-size: 12px;
                padding: 8px;
            }
            thead th:nth-child(1), tbody td:nth-child(1) { width: 12%; }
            thead th:nth-child(2), tbody td:nth-child(2) { width: 32%; }
            thead th:nth-child(3), tbody td:nth-child(3) { width: 40%; }
            thead th:nth-child(4), tbody td:nth-child(4) { width: 16%; }
        }
    </style>
</head>

<body class="min-h-screen">
    <nav class="navbar fixed top-0 left-0 w-full flex justify-between items-center z-30">
        <div class="flex items-center">
            <img src="../media/allyted-logo2 (2).png" alt="Allyted Logo" class="logo-img" onerror="this.src='https://via.placeholder.com/60'; console.error('Logo image not found');">
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

    <div class="mt-32 px-4 sm:px-24">
        <div class="heading">
            Onboarding
            <img src="../assets/Books.png" alt="icon" id="book" onerror="this.src='https://via.placeholder.com/70x30'; console.error('Book icon not found');">
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>Purpose</th>
                        <th>Links</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($onboardings) || !is_array($onboardings)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-gray-500 py-8">No onboarding records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php $row_number = 1; ?>
                        <?php foreach ($onboardings as $index => $onboarding): ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td><?php echo htmlspecialchars($onboarding['purpose'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $url = $onboarding['links'] ?? '';
                                    if ($url && !preg_match('/^https?:\/\//i', $url)) {
                                        $url = 'https://' . $url;
                                    }
                                    if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                                        error_log("Row $row_number URL valid: $url");
                                    ?>
                                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                                            🔗 <?php echo htmlspecialchars($url); ?>
                                        </a>
                                    <?php } else {
                                        error_log("Row $row_number URL invalid: " . ($onboarding['links'] ?? 'empty'));
                                    ?>
                                        No link
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($onboarding['duration'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php $row_number++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($error_message)): ?>
            <p class="debug-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>