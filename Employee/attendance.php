    <?php
    session_start();
    if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
        header("Location: ../index.php?error=Please login first");
        exit;
    }

    require_once '../db/config.php';

    // Set timezone to IST
    date_default_timezone_set('Asia/Kolkata');

    $employee_id = $_SESSION['employee_id'];
    $full_name = $_SESSION['employee_name'] ?? 'Unknown';

    // Calculate the start of the current week (Monday)
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $end_of_week = date('Y-m-d', strtotime('sunday this week'));

    // Fetch summary statistics for the current week
    $stmt = $mysqli->prepare("
        SELECT 
            SUM(correct_clock_in_count) AS on_time_count,
            COUNT(clock_in_time) AS clock_in_count,
            COUNT(clock_out_time) AS clock_out_count,
            SUM(late_clock_in_count) AS late_clock_in_count,
            SUM(early_clock_out_count) AS early_clock_out_count,
            SUM(CASE WHEN clock_in_time IS NULL THEN 1 ELSE 0 END) AS absent_count
        FROM attendance_register 
        WHERE employee_id = ? AND date BETWEEN ? AND ?
    ");
    if (!$stmt) {
        error_log("Prepare failed for summary stats: " . $mysqli->error);
        die("Database error: Unable to fetch summary statistics.");
    }
    $stmt->bind_param("sss", $employee_id, $start_of_week, $end_of_week);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Default values for summary
    $summary = [
        'on_time_count' => $summary['on_time_count'] ?? 0,
        'clock_in_count' => $summary['clock_in_count'] ?? 0,
        'clock_out_count' => $summary['clock_out_count'] ?? 0,
        'late_clock_in_count' => $summary['late_clock_in_count'] ?? 0,
        'early_clock_out_count' => $summary['early_clock_out_count'] ?? 0,
        'absent_count' => $summary['absent_count'] ?? 0
    ];

    // Fetch attendance records
    $stmt = $mysqli->prepare("
        SELECT date, clock_in_time, clock_out_time, hours_worked, notes, 
            clock_in_image, clock_in_latitude, clock_in_longitude 
        FROM attendance_register 
        WHERE employee_id = ? 
        ORDER BY date DESC
    ");
    if (!$stmt) {
        error_log("Prepare failed for fetching attendance: " . $mysqli->error);
        die("Database error: Unable to fetch attendance records.");
    }
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance_records = [];
    while ($row = $result->fetch_assoc()) {
        // Convert times to 12-hour format
        if ($row['clock_in_time']) {
            $row['clock_in_time_formatted'] = (new DateTime($row['clock_in_time'], new DateTimeZone('Asia/Kolkata')))->format('h:i A');
        } else {
            $row['clock_in_time_formatted'] = '-';
        }
        if ($row['clock_out_time']) {
            $row['clock_out_time_formatted'] = (new DateTime($row['clock_out_time'], new DateTimeZone('Asia/Kolkata')))->format('h:i A');
        } else {
            $row['clock_out_time_formatted'] = '-';
        }
        // Generate location link
        if ($row['clock_in_latitude'] && $row['clock_in_longitude']) {
            $row['location_link'] = "https://maps.google.com/?q={$row['clock_in_latitude']},{$row['clock_in_longitude']}";
        } else {
            $row['location_link'] = '-';
        }
        $attendance_records[] = $row;
        error_log("Fetched record for date {$row['date']}: Clock In: {$row['clock_in_time_formatted']}, Clock Out: {$row['clock_out_time_formatted']}");
    }
    $stmt->close();
    $mysqli->close();
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Attendance Summary</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Montserrat:wght@900&display=family" rel="stylesheet">
        <link rel="stylesheet" href="styles/dashboard.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            /* Aggressive reset to eliminate inherited styles */
            *, *:before, *:after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                background: none !important;
                background-color: transparent !important;
            }

            html, body {
                font-family: 'Inter', sans-serif;
                background-color: #ffffff !important;
                color: #333;
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                overflow-x: hidden;
            }

            .main-content, .container, .summary-container, .summary-card, table, thead, tbody, tr, th, td {
                background: #ffffff !important;
                background-color: #ffffff !important;
            }

            .main-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                padding: 20px;
                margin-top: 40px;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                width: 100%;
            }

            .description {
                font-family: 'Inter', sans-serif;
                font-size: 0.9rem; /* Smaller font size for paragraph */
                font-weight: 400;
                color: #4b49ac;
                margin-top: 2px;
                margin-bottom: 25px;
                background: linear-gradient(90deg, #6b7280, #4b49ac);
                -webkit-background-clip: text;
        
            }

            /* Summary Boxes */
            .summary-container {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
            }

            .summary-card {
                border-radius: 10px;
                padding: 15px;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                flex: 1;
            }

            .summary-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
            }

            .summary-stats {
                display: flex;
                gap: 10px;
            }

            .stat-box {
                background: #1e73be !important;
                color: white;
                padding: 10px;
                border-radius: 8px;
                text-align: center;
                flex: 1;
            }

            .stat-number {
                font-size: 18px;
                font-weight: bold;
            }

            /* Table */
            table {
                width: 100%;
                border-collapse: collapse;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                border: 1px solid #DBE3EE; /* Stroke border for table */
            }

            thead {
                background: #f0f3fa !important;
                border-bottom: 2px solid #DBE3EE; /* Header border color */
            }

            th, td {
                padding: 12px;
                text-align: left;
                border: 1px solid #DBE3EE; /* Stroke borders for cells */
                font-size: 14px;
                color: #333;
            }

            th {
                color: #333;
                font-weight: 600;
            }

            tbody tr:hover {
                background-color: #f9f9f9 !important;
            }

            .image-link img {
                width: 50px;
                height: 50px;
                object-fit: cover;
                border-radius: 5px;
                background: none !important;
            }

            /* Navbar styles */
            .navbar {
                background: rgba(255, 255, 255, 0.95) !important;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                z-index: 1000;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                padding: 10px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
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
                background: #cf0a2c;
            }

            .dropdown-menu {
                transition: all 0.3s ease;
                transform-origin: top;
                transform: scaleY(0);
                opacity: 0;
            }

            #heading {
                font-size: 1.5rem;
                font-weight: 700; /* Bold heading */
                background: linear-gradient(90deg, #1e73be, #4b49ac); /* Blue gradient for heading */
                -webkit-background-clip: text;
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
                transform: scale(1.05);
            }

            .logo-img {
                max-height: 70px;
                width: auto;
                background: none !important;
            }

            /* Override dark mode or theme conflicts */
            html[style], body[style], .dark-mode, [data-theme="dark"], [class*="dark"] {
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #333 !important;
            }

            [data-theme="dark"], .dark, [class*="bg-dark"], [class*="bg-gray-900"] {
                background: #ffffff !important;
                background-color: #ffffff !important;
            }
        </style>
    </head>
    <body class="min-h-screen flex flex-col">
        <nav class="navbar fixed top-0 left-0 w-full py-1 px-6 flex justify-between items-center z-30">
            <div class="flex items-center">
                <img src="../media/allyted-logo2 (2).png" alt="Allyted Logo" class="logo-img">
            </div>
            <div class="flex items-center space-x-6">
                <div class="relative group">
                    <button class="employee-button flex items-center px-3 py-2 rounded-lg text-gray-600 hover:text-red-600">
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

        <div class="main-content">
            <div class="container">
                <h1 id="heading">Attendance</h1>
                <p class="description">Get summary of your weekly attendance here.</p>

                <div class="summary-container">
                    <div class="summary-card">
                        <div class="summary-title">Present Summary</div>
                        <div class="summary-stats">
                            <div class="stat-box">
                                <div>On time</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['on_time_count']); ?></div>
                            </div>
                            <div class="stat-box">
                                <div>Clock-In</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['clock_in_count']); ?></div>
                            </div>
                            <div class="stat-box">
                                <div>Clock-Out</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['clock_out_count']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-title">Not-Present Summary</div>
                        <div class="summary-stats">
                            <div class="stat-box">
                                <div>No. of Absent</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['absent_count']); ?></div>
                            </div>
                            <div class="stat-box">
                                <div>Late Clock-In</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['late_clock_in_count']); ?></div>
                            </div>
                            <div class="stat-box">
                                <div>Early Clock-Out</div>
                                <div class="stat-number"><?php echo htmlspecialchars($summary['early_clock_out_count']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>🕒 Clock-In</th>
                            <th>🔴 Clock-Out</th>
                            <th>📷 Clock-In Picture</th>
                            <th>📍 Location</th>
                            <th>📝 Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['clock_in_time_formatted']); ?></td>
                                <td><?php echo htmlspecialchars($record['clock_out_time_formatted']); ?></td>
                                <td>
                                    <?php if ($record['clock_in_image']): ?>
                                        <a href="<?php echo htmlspecialchars($record['clock_in_image']); ?>" class="image-link" target="_blank">
                                            <img src="<?php echo htmlspecialchars($record['clock_in_image']); ?>" alt="Clock-In Selfie">
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['location_link'] !== '-'): ?>
                                        <a href="<?php echo htmlspecialchars($record['location_link']); ?>" target="_blank">View Location</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
    </html>