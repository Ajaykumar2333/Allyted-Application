see in below code upcoming there nah if two upcoming are there if tommoriw is there then green coming but there si no tommoorw then only two upcomings are there than use yeellow and other color orange or something like two same is not nice


<?php
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: Redirecting to login");
    header("Location: ../index.php?error=Please login first");
    exit;
}

require_once '../db/config.php';

$employee_id = $_SESSION['employee_id'];
$full_name = $_SESSION['employee_name'] ?? 'Unknown';

// Fetch holidays from database
$stmt = $mysqli->prepare("SELECT * FROM holidays ORDER BY date ASC");
if (!$stmt) {
    error_log("Prepare failed for holidays: " . $mysqli->error);
    die("Database error: Unable to fetch holiday data.");
}
$stmt->execute();
$result = $stmt->get_result();
$holidays = [];
while ($row = $result->fetch_assoc()) {
    $holidays[] = $row;
}
$stmt->close();
$mysqli->close();

// Log the number of holidays fetched and sample data
error_log("Fetched " . count($holidays) . " holidays from database");
if (!empty($holidays)) {
    error_log("Sample holiday data: " . json_encode($holidays[0]));
    // Log all holiday names for debugging
    $holiday_names = array_column($holidays, 'name');
    error_log("Holiday names: " . json_encode($holiday_names));
}

// Holiday image mapping with placeholders for all holidays (used in right section)
$holiday_images = [
    'New Year' => '../assets/new_year.png',
    'Sankranti' => '../assets/sankranti.png',
    'Republic Day' => '../assets/republic_day.png',
    'Ugadi' => '../assets/ugadi.png',
    'Good Friday' => '../assets/good_friday.png',
    "Ambedkar Jayanthi" => '../assets/ambedkar_birthday.png',
    'Ramzan' => '../assets/ramzan.png',
    'MAY Day' => '../assets/may_day.png',
    'Telangana Formation Day' => '../assets/telangana_formation_day.png',
    'Bakrid' => '../assets/bakrid.png',
    'Independence Day' => '../assets/independence_day.png',
    'Ganesh Chaturthi' => '../assets/ganesh_chaturthi.png',
    'Eid Milad un Nabi' => '../assets/eid_milad_un_nabi.png',
    'Mahatma Gandhi Jayanthi' => '../assets/gandhi_jayanthi.png',
    'Vijayadashami' => '../assets/sankranti.png',
    'Deepavali' => '../assets/deepavali.png',
    'Christmas' => '../assets/christmas.png',
];

// Check for unmapped holidays (for right section)
foreach ($holidays as $holiday) {
    if (!isset($holiday_images[$holiday['name']])) {
        error_log("No image mapped for holiday: " . $holiday['name']);
        $holiday_images[$holiday['name']] = '../assets/default_holiday.png'; // Assign default image for unmapped holidays
    }
}

// Fallback holidays if database is empty (for debugging)
if (empty($holidays)) {
    $holidays = [
        [
            'name' => 'New Year',
            'date' => '2025-01-01',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Sankranti',
            'date' => '2025-01-14',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Republic Day',
            'date' => '2025-01-26',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Ugadi',
            'date' => '2025-03-30',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Good Friday',
            'date' => '2025-04-18',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => "DR.B.R. Ambedkar 's Birthday",
            'date' => '2025-04-14',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Ramzan',
            'date' => '2025-03-31',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'MAY Day',
            'date' => '2025-05-01',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Telangana Formation Day',
            'date' => '2025-06-02',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Bakrid',
            'date' => '2025-06-06',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Independence Day',
            'date' => '2025-08-15',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Ganesh Chaturthi',
            'date' => '2025-08-27',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Eid Milad un Nabi',
            'date' => '2025-09-05',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Mahatma Gandhi Jayanthi',
            'date' => '2025-10-02',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Vijayadashami',
            'date' => '2025-10-12',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Deepavali',
            'date' => '2025-10-20',
            'type' => 'Optional',
            'status' => 'active'
        ],
        [
            'name' => 'Christmas',
            'date' => '2025-12-25',
            'type' => 'Optional',
            'status' => 'active'
        ],
    ];
    error_log("Using fallback holidays since database returned no results");
}

// Array of colors for holiday card borders
$border_colors = [
    '#ff5722', // Orange
    '#2196f3', // Blue
    '#4caf50', // Green
    '#e91e63', // Pink
    '#9c27b0', // Purple
    '#ffc107', // Amber
    '#00bcd4', // Cyan
    '#ff9800', // Deep Orange
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1000;
            /* Ensure navbar stays above content */
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
        }

        .container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 10px;
            width: 100%;
            max-width: calc(100% - 120px);
            margin-left: 60px;
            margin-right: 60px;
            gap: 130px;
            box-sizing: border-box;
            position: relative;
            /* Ensure container is positioned correctly */
        }

        @media (min-width: 1536px) {
            .container {
                max-width: 1366px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        @media (max-width: 768px) {
            .container {
                max-width: calc(100% - 40px);
                margin-left: 20px;
                margin-right: 20px;
                flex-direction: column;
            }
        }

        .left_content {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            max-width: calc(39% - 10px);
            overflow: hidden;
            position: relative;
            left: 170px;
            top: -60px;
        }

        .left_content p {
            font-family: "Lato", sans-serif;
            font-weight: 700;
            font-style: normal;
            text-align: left;
            margin-bottom: 10px;
            position: relative;
            left: 196px;
            bottom: -69px;
        }

        .left_content h1 {
            font-family: "Lato", sans-serif;
            font-weight: 7 00;
            font-style: normal;
            text-align: left;
            font-size: 54px;
            margin-bottom: 20px;
            word-wrap: break-word;
            position: relative;
            left: 170px;
            bottom: -46px;
        }

        .image img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .holiday-card {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
            /* Lighter shadow for performance */
            padding: 12px 16px;
            width: 84%;
            box-sizing: border-box;
            font-family: sans-serif;
            margin-bottom: 10px;
        }

        .holiday-image img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .holiday-content {
            flex-grow: 1;
            overflow: hidden;
        }

        .holiday-title {
            font-weight: 600;
            color: #3a4b6b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .holiday-date {
            color: #3a4b6b;
            margin-top: 4px;
        }

        .holiday-type {
            color: #3a4b6b;
            font-size: 14px;
            white-space: nowrap;
            padding-left: 20px;
        }

        .holiday-type span {
            font-weight: 500;
        }

        .right_content {
            flex: 1;
            max-height: 80vh;
            overflow-y: auto;
            padding: 0 5px 0 20px;
            max-width: calc(39% - 10px);
            box-sizing: border-box;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            will-change: transform;
            scrollbar-width: none;
            /* Hide scrollbar in Firefox */
            -ms-overflow-style: none;
            /* Hide scrollbar in IE/Edge */
        }

        .right_content::-webkit-scrollbar {
            display: none;
            /* Hide scrollbar in WebKit browsers */
        }

        @media (max-width: 768px) {
            .right_content {
                max-height: none;
                height: auto;
            }
        }

        .holiday-items-container {
            display: flex;
            gap: 20px;
        }

        .holiday-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            flex: 1;
        }

        .dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.green {
            background-color: #00c853;
        }

        .dot.yellow {
            background-color: #ffca28;
        }

        .dot.orange {
            background-color: #ff9800;
            /* Orange color for the dot */
        }

        .holiday-name.orange {
            color: #ff9800;
            /* Orange color for the holiday name */
        }

        .holiday-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            font-family: "Lato", sans-serif;
            font-weight: 700;
            font-style: normal;
        }

        .holiday-top {
            font-weight: bold;
            color: #3b4252;
        }

        .holiday-date {
            font-size: 0.85rem;
            color: #777;
            margin-left: 8px;
            font-weight: normal;
        }

        .holiday-name.green {
            color: #00c853;
            font-weight: bold;
        }

        .holiday-name.yellow {
            color: #ffb300;
            font-weight: bold;
        }

        .no-holidays {
            text-align: center;
            color: #3a4b6b;
            font-size: 16px;
            padding: 20px;
        }
    </style>
</head>

<body class="min-h-screen flex flex-col">
    <!-- Navbar -->
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

    <!-- Main Content -->
    <div class="flex-2 mt-20 flex flex-col">
        <div class="w-full mx-auto">
            <div class="container">
                <div class="left_content">
                    <p>BRIGHT AND JOYFUL</p>
                    <h1>Holidays</h1>
                    <div class="image">
                        <?php
                        $left_image = '../assets/holidays_left.png';
                        error_log("Left section image: $left_image");
                        ?>
                        <img src="<?php echo htmlspecialchars($left_image); ?>" alt="Holidays Left Image">
                    </div>
                    <div class="holiday-items-container">
                        <?php
                        if (empty($holidays)) {
                            echo '<p class="no-holidays">No upcoming holidays found.</p>';
                        } else {
                            $today = new DateTime();
                            $count = 0;
                            $upcoming_count = 0; // Track number of upcoming holidays (not tomorrow)
                            foreach ($holidays as $holiday) {
                                if ($count >= 2) break; // Limit to 2 holidays
                                $holiday_date = new DateTime($holiday['date']);
                                $diff = $today->diff($holiday_date);
                                $days_diff = $diff->days;
                                $is_tomorrow = ($days_diff == 1 && !$diff->invert);
                                $is_upcoming = ($days_diff > 1 && !$diff->invert);
                                if ($is_tomorrow || $is_upcoming) {
                                    $status = $is_tomorrow ? 'Tomorrow' : 'Upcoming';
                                    // Set color based on tomorrow or upcoming
                                    if ($is_tomorrow) {
                                        $dot_color = 'green'; // Green for tomorrow
                                    } else {
                                        $dot_color = ($upcoming_count == 0) ? 'yellow' : 'orange'; // Yellow for first upcoming, orange for second
                                        $upcoming_count++;
                                    }
                                    $formatted_date = $holiday_date->format('D, M d Y');
                        ?>
                                    <div class="holiday-item">
                                        <span class="dot <?php echo $dot_color; ?>"></span>
                                        <div class="holiday-text">
                                            <div class="holiday-top">
                                                <?php echo htmlspecialchars($status); ?> <span class="holiday-date"><?php echo htmlspecialchars($formatted_date); ?></span>
                                            </div>
                                            <span class="holiday-name <?php echo $dot_color; ?>"><?php echo htmlspecialchars($holiday['name']); ?></span>
                                        </div>
                                    </div>
                        <?php
                                    $count++;
                                }
                            }
                            if ($count == 0) {
                                echo '<p class="no-holidays">No upcoming holidays found.</p>';
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="right_content">
                    <?php
                    if (empty($holidays)) {
                        echo '<p class="no-holidays">No holidays available.</p>';
                    } else {
                        $index = 0;
                        foreach ($holidays as $holiday) {
                            $holiday_date = new DateTime($holiday['date']);
                            $formatted_date = $holiday_date->format('D, M d Y');
                            // Use holiday-specific image or fallback to default
                            $holiday_image = isset($holiday_images[$holiday['name']]) ? $holiday_images[$holiday['name']] : '../assets/default_holiday.png';
                            $alt_text = isset($holiday_images[$holiday['name']]) ? $holiday['name'] . ' Image' : 'Default Holiday Image';
                            // Assign border color cyclically
                            $border_color = $border_colors[$index % count($border_colors)];
                            error_log("Right section image for holiday '{$holiday['name']}': $holiday_image");
                    ?>
                            <div class="holiday-card" style="border-left: 5px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <div class="holiday-image">
                                    <img src="<?php echo htmlspecialchars($holiday_image); ?>" alt="<?php echo htmlspecialchars($alt_text); ?>">
                                </div>
                                <div class="holiday-content">
                                    <div class="holiday-title"><?php echo htmlspecialchars($holiday['name']); ?></div>
                                    <div class="holiday-date"><?php echo htmlspecialchars($formatted_date); ?></div>
                                </div>
                                <div class="holiday-type">
                                    Holiday Type: <span><?php echo htmlspecialchars($holiday['type']); ?></span>
                                </div>
                            </div>
                    <?php
                            $index++;
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>