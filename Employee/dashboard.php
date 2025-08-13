<?php
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: Redirecting to login");
    header("Location: ../index.php?error=Please login first");
    exit;
}

require_once '../db/config.php';

// Set default timezone to IST
date_default_timezone_set('Asia/Kolkata');

$email = $_SESSION['employee_email'];
$employee_id = $_SESSION['employee_id'];
$full_name = $_SESSION['employee_name'] ?? 'Unknown';

// Handle unlock request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock']) && $_POST['unlock'] === 'true') {
    $_SESSION['dashboard_unlocked'] = true;
    error_log("Dashboard unlocked for employee_id: $employee_id");
    echo 'Success';
    exit;
}

// Handle clock-in/clock-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_action'])) {
    $action = $_POST['clock_action'];
    $selfie_data = $_POST['selfie_data'] ?? null;
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $timestamp = $_POST['timestamp'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    error_log("Clock action received: $action, Employee ID: $employee_id, Timestamp: $timestamp, Latitude: $latitude, Longitude: $longitude, Selfie: " . ($selfie_data ? 'present' : 'missing'));

    // Validate selfie_data and timestamp
    if (!$selfie_data || !$timestamp) {
        error_log("Missing required data: selfie_data=" . ($selfie_data ? 'present' : 'missing') . ", timestamp=$timestamp");
        http_response_code(400);
        echo json_encode(['error' => 'Selfie and timestamp are required']);
        exit;
    }

    // Convert UTC timestamp to IST
    try {
        $utc_datetime = new DateTime($timestamp, new DateTimeZone('UTC'));
        $utc_datetime->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $ist_timestamp = $utc_datetime->format('Y-m-d H:i:s');
        $formatted_timestamp = $utc_datetime->format('h:i A');
        $date = $utc_datetime->format('Y-m-d');
        error_log("Converted UTC timestamp $timestamp to IST: $ist_timestamp, Formatted: $formatted_timestamp");
    } catch (Exception $e) {
        error_log("Invalid timestamp format: $timestamp, Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid timestamp format']);
        exit;
    }

    $hours_worked = null;
    $correct_clock_in_count = 0;
    $late_clock_in_count = 0;
    $late_clock_in_minutes = 0;
    $correct_clock_out_count = 0;
    $early_clock_out_count = 0;
    $early_clock_out_minutes = 0;

    // Define standard working hours (IST)
    $standard_clock_in = '09:30:00'; // 9:30 AM IST
    $standard_clock_out = '18:30:00'; // 6:30 PM IST

    // Check if record exists for the date
    $stmt = $mysqli->prepare("SELECT clock_in_time, clock_out_time FROM attendance_register WHERE employee_id = ? AND date = ?");
    if (!$stmt) {
        error_log("Prepare failed for checking record: " . $mysqli->error);
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    $stmt->bind_param("ss", $employee_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_record = $result->fetch_assoc();
    $stmt->close();

    if ($action === 'clock_in') {
        if ($existing_record && $existing_record['clock_in_time']) {
            error_log("Clock-in already recorded for $date: " . $existing_record['clock_in_time']);
            http_response_code(400);
            echo json_encode(['error' => 'Already clocked in for today']);
            exit;
        }
        $clock_in_time = $ist_timestamp;
        $clock_time = new DateTime($ist_timestamp, new DateTimeZone('Asia/Kolkata'));
        $standard_time = new DateTime($date . ' ' . $standard_clock_in, new DateTimeZone('Asia/Kolkata'));
        if ($clock_time <= $standard_time) {
            $correct_clock_in_count = 1;
        } else {
            $late_clock_in_count = 1;
            $interval = $standard_time->diff($clock_time);
            $late_clock_in_minutes = ($interval->h * 60) + $interval->i;
        }

        if ($existing_record) {
            // Update existing record with clock-in data
            $stmt = $mysqli->prepare("UPDATE attendance_register SET clock_in_time = ?, clock_in_image = ?, clock_in_latitude = ?, clock_in_longitude = ?, notes = ?, correct_clock_in_count = ?, late_clock_in_count = ?, late_clock_in_minutes = ? WHERE employee_id = ? AND date = ?");
            if (!$stmt) {
                error_log("Prepare failed for updating clock-in: " . $mysqli->error);
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
            $stmt->bind_param("ssddsiiiss", $clock_in_time, $selfie_data, $latitude, $longitude, $notes, $correct_clock_in_count, $late_clock_in_count, $late_clock_in_minutes, $employee_id, $date);
        } else {
            // Insert new record for clock-in
            $stmt = $mysqli->prepare("INSERT INTO attendance_register (employee_id, date, clock_in_time, clock_in_image, clock_in_latitude, clock_in_longitude, notes, correct_clock_in_count, late_clock_in_count, late_clock_in_minutes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                error_log("Prepare failed for inserting clock-in: " . $mysqli->error);
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
            $stmt->bind_param("ssssddsiii", $employee_id, $date, $clock_in_time, $selfie_data, $latitude, $longitude, $notes, $correct_clock_in_count, $late_clock_in_count, $late_clock_in_minutes);
        }
    } elseif ($action === 'clock_out') {
        if (!$existing_record || !$existing_record['clock_in_time']) {
            error_log("No clock-in record found for $date");
            http_response_code(400);
            echo json_encode(['error' => 'Must clock in before clocking out']);
            exit;
        }
        if ($existing_record['clock_out_time']) {
            error_log("Clock-out already recorded for $date: " . $existing_record['clock_out_time']);
            http_response_code(400);
            echo json_encode(['error' => 'Already clocked out for today']);
            exit;
        }
        $clock_out_time = $ist_timestamp;
        $clock_in_datetime = new DateTime($existing_record['clock_in_time'], new DateTimeZone('Asia/Kolkata'));
        $clock_out_datetime = new DateTime($ist_timestamp, new DateTimeZone('Asia/Kolkata'));
        $interval = $clock_in_datetime->diff($clock_out_datetime);
        $hours_worked = $interval->h + ($interval->i / 60) + ($interval->s / 3600);

        // Check for early clock-out
        $standard_time = new DateTime($date . ' ' . $standard_clock_out, new DateTimeZone('Asia/Kolkata'));
        if ($clock_out_datetime >= $standard_time) {
            $correct_clock_out_count = 1;
        } else {
            $early_clock_out_count = 1;
            $interval = $clock_out_datetime->diff($standard_time);
            $early_clock_out_minutes = ($interval->h * 60) + $interval->i;
        }

        // Update existing record with clock-out data
        $stmt = $mysqli->prepare("UPDATE attendance_register SET clock_out_time = ?, clock_out_image = ?, clock_out_latitude = ?, clock_out_longitude = ?, hours_worked = ?, notes = ?, correct_clock_out_count = ?, early_clock_out_count = ?, early_clock_out_minutes = ? WHERE employee_id = ? AND date = ?");
        if (!$stmt) {
            error_log("Prepare failed for updating clock-out: " . $mysqli->error);
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
            exit;
        }
        $stmt->bind_param("ssdddsiiiss", $clock_out_time, $selfie_data, $latitude, $longitude, $hours_worked, $notes, $correct_clock_out_count, $early_clock_out_count, $early_clock_out_minutes, $employee_id, $date);
    }

    if ($stmt->execute()) {
        $_SESSION['clock_status'] = $action === 'clock_in' ? 'in' : 'out';
        $_SESSION['clock_in_time'] = $action === 'clock_in' ? $ist_timestamp : null;
        error_log("Attendance recorded: $action, Employee ID: $employee_id, Date: $date, IST Timestamp: $ist_timestamp, Formatted: $formatted_timestamp");
        echo json_encode([
            'success' => true,
            'clock_status' => $_SESSION['clock_status'],
            'timestamp' => $ist_timestamp,
            'formatted_timestamp' => $formatted_timestamp,
            'hours_worked' => $hours_worked,
            'correct_clock_in_count' => $correct_clock_in_count,
            'late_clock_in_count' => $late_clock_in_count,
            'late_clock_in_minutes' => $late_clock_in_minutes,
            'correct_clock_out_count' => $correct_clock_out_count,
            'early_clock_out_count' => $early_clock_out_count,
            'early_clock_out_minutes' => $early_clock_out_minutes
        ]);
    } else {
        error_log("Update/Insert failed for attendance_register: " . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record attendance: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// Fetch latest clock status
$clock_status = 'out';
$last_timestamp = null;
$last_formatted_timestamp = '00:00:00';
$stmt = $mysqli->prepare("SELECT clock_in_time, clock_out_time FROM attendance_register WHERE employee_id = ? AND date = CURDATE() ORDER BY clock_in_time DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $clock_status = $row['clock_in_time'] && !$row['clock_out_time'] ? 'in' : 'out';
        $last_timestamp = $row['clock_out_time'] ?? $row['clock_in_time'];
        if ($last_timestamp) {
            $last_datetime = new DateTime($last_timestamp, new DateTimeZone('Asia/Kolkata'));
            $last_formatted_timestamp = $last_datetime->format('h:i A');
        }
        if ($clock_status === 'in') {
            $_SESSION['clock_in_time'] = $row['clock_in_time'];
        }
        error_log("Fetched clock status: $clock_status, Last timestamp: $last_timestamp, Formatted: $last_formatted_timestamp");
    } else {
        error_log("No attendance records found for employee_id: $employee_id on today");
    }
    $_SESSION['clock_status'] = $clock_status;
    $stmt->close();
} else {
    error_log("Prepare failed for fetching clock status: " . $mysqli->error);
}

// Fetch profile details
$stmt = $mysqli->prepare("SELECT full_name, phone, date_of_joining, department_id, role_id, location_id, bond_years FROM employee_details WHERE employee_id = ?");
if (!$stmt) {
    error_log("Prepare failed for employee_details: " . $mysqli->error);
    die("Database error: Unable to fetch profile details.");
}
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->num_rows == 1 ? $result->fetch_assoc() : [];
$stmt->close();

// Fetch employee_details2
$stmt = $mysqli->prepare("SELECT date_of_birth, gender, marital_status, blood_group, emergency_contact, emergency_phone, alternate_phone, alternate_email, address_current, address_permanent, photo, aadhar, pancard FROM employee_details2 WHERE employee_id = ?");
if (!$stmt) {
    error_log("Prepare failed for employee_details2: " . $mysqli->error);
    die("Database error: Unable to fetch employee data.");
}
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$profile2 = $result->num_rows == 1 ? $result->fetch_assoc() : [];
$stmt->close();

// Fetch education_details
$education_details = [];
$stmt = $mysqli->prepare("SELECT graduation, branch, score, passed_out_year, upload_certificate FROM employee_education WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $education_details[] = $row;
    }
    $stmt->close();
} else {
    error_log("Prepare failed for employee_education: " . $mysqli->error);
    die("Database error: Unable to fetch education data.");
}

// Fetch experience_details
$experience_details = [];
$is_fresher = false;
$stmt = $mysqli->prepare("SELECT company, role, ctc, start_date, end_date, document, is_fresher FROM employee_experience WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $experience_details[] = $row;
        if (isset($row['is_fresher']) && $row['is_fresher'] == 1) {
            $is_fresher = true;
        }
    }
    $stmt->close();
} else {
    error_log("Prepare failed for employee_experience: " . $mysqli->error);
    die("Database error: Unable to fetch experience data.");
}

// Calculate profile completion
$progress = 0;
$organization_complete = !empty($profile) && (
    !empty($profile['full_name']) &&
    !empty($profile['phone']) &&
    !empty($profile['date_of_joining']) &&
    !empty($profile['department_id']) &&
    !empty($profile['role_id']) &&
    !empty($profile['location_id']) &&
    !empty($profile['bond_years'])
);
if ($organization_complete) $progress += 25;

$basic_complete = !empty($profile2) && 
                  !empty($profile2['date_of_birth']) && 
                  !empty($profile2['gender']) && 
                  !empty($profile2['marital_status']) && 
                  !empty($profile2['blood_group']) && 
                  !empty($profile2['emergency_contact']) && 
                  !empty($profile2['emergency_phone']) && 
                  !empty($profile2['alternate_phone']) && 
                  !empty($profile2['alternate_email']) && 
                  !empty($profile2['address_current']) && 
                  !empty($profile2['address_permanent']);
if ($basic_complete) $progress += 25;

$education_complete = !empty($education_details) && count(array_filter($education_details, fn($edu) => 
    !empty($edu['graduation']) && 
    !empty($edu['branch']) && 
    !empty($edu['score']) && 
    !empty($edu['passed_out_year'])
)) > 0;
if ($education_complete) $progress += 25;

$experience_complete = $is_fresher || (!empty($experience_details) && count(array_filter($experience_details, fn($exp) => 
    !empty($exp['company']) && 
    !empty($exp['role']) && 
    !empty($exp['ctc']) && 
    !empty($exp['start_date']) && 
    !empty($exp['end_date'])
)) > 0);
if ($experience_complete) $progress += 25;

$completion_percentage = $progress;
$is_profile_complete = $completion_percentage == 100;

if (!$is_profile_complete) {
    unset($_SESSION['dashboard_unlocked']);
    error_log("Profile incomplete: dashboard_unlocked unset, percentage=$completion_percentage");
} else {
    $_SESSION['dashboard_unlocked'] = true;
}

error_log("Completion: Org=$organization_complete, Basic=$basic_complete, Edu=$education_complete, Exp=$experience_complete, Total=$completion_percentage");
error_log("Session clock_status: {$_SESSION['clock_status']}, clock_in_time: " . ($_SESSION['clock_in_time'] ?? 'not set'));

$mysqli->close();
session_write_close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/dashboard.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .clock-card {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 30px;
            padding: 10px 20px;
            gap: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: fit-content;
            cursor: pointer;
        }
        .clock-text {
            display: flex;
            flex-direction: column;
        }
        .clock-text h4 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }
        .clock-text span {
            font-size: 12px;
            color: #000;
        }
        .clock-text span.clock-in {
            color: #0000FF;
        }
        .clock-icon {
            width: 28px;
            height: 28px;
            background-color: #4b49ac;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }
        .clock-icon svg {
            width: 16px;
            height: 16px;
            fill: white;
        }
        #selfie-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
        }
        .selfie-modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        #selfie-video {
            display: none;
            width: 100%;
            max-width: 320px;
            height: auto;
            margin: 10px 0;
        }
        #notes-input {
            width: 100%;
            margin: 10px 0;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
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
            <div class="clock-card" id="clock-button">
                <div class="clock-text">
                    <h4><?php echo $clock_status === 'in' ? 'Clock Out' : 'Clock In'; ?></h4>
                    <span class="last-swiped" id="last-swiped">
                        <?php echo $clock_status === 'in' ? 'Time Worked: 00:00:00' : 'Last Swiped: ' . $last_formatted_timestamp; ?>
                    </span>
                </div>
                <div class="clock-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 1a11 11 0 1 0 11 11A11.013 11.013 0 0 0 12 1zm0 20a9 9 0 1 1 9-9a9.01 9.01 0 0 1-9 9zm.5-9.793V6a1 1 0 0 0-2 0v6a1 1 0 0 0 .293.707l3.5 3.5a1 1 0 0 0 1.414-1.414z"/>
                    </svg>
                </div>
            </div>
            <div class="relative group">
                <button class="employee-button flex items-center px-3 py-2 rounded-lg text-gray-600 hover:text-red-600">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <span class="text-lg font-semibold"><?php echo htmlspecialchars($full_name); ?></span>
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg">
                    <a href="my_profile.php" class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Profile
                    </a>
                    <a href="../logout.php" class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-100">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h3a3 3 0 013 3v1"></path></svg>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Selfie Modal -->
    <div id="selfie-modal">
        <div id="selfie-modal-content" class="selfie-modal-content">
            <h3 id="modal-title" class="text-lg font-semibold mb-4"></h3>
            <video id="selfie-video" autoplay></video>
            <textarea id="notes-input" placeholder="Enter any notes (optional)"></textarea>
            <button id="capture-selfie" class="px-4 py-2 bg-green-600 text-white rounded">Capture Selfie</button>
            <button id="close-modal" class="px-4 py-2 bg-red-600 text-white rounded mt-2">Cancel</button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 mt-14 main-content flex flex-col items-center relative" id="main-content">
        <?php if (!$is_profile_complete): ?>
            <!-- Profile Completion Card -->
            <div class="profile-card w-full max-w-3xl rounded-2xl p-10 flex flex-col items-center" id="profile-card">
                <h4 class="text-3xl font-bold text-gray-700 mb-6">Profile Completion</h4>
                <div class="relative mb-6">
                    <svg class="w-32 h-32" viewBox="0 0 100 100">
                        <defs>
                            <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#facc15;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#f97316;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#e5e7eb" stroke-width="10" />
                        <circle cx="50" cy="50" r="45" fill="none" class="progress-bar" stroke-width="10" transform="rotate(-90 50 50)" />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-2xl font-semibold text-gray-800"><?php echo number_format($completion_percentage, 0); ?>%</span>
                </div>
                <p class="text-red-600 text-center font-medium text-lg mb-6">Complete your profile to unlock all features!</p>
                <a href="my_profile.php" class="cta-button px-8 py-3 rounded-full text-white font-semibold text-lg">Complete Profile Now</a>
                <button onclick="unlockDashboard()" class="unlock-button mt-6 px-8 py-3 rounded-full text-white font-semibold text-lg" <?php echo !$is_profile_complete ? 'disabled' : ''; ?>>
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 018 0v4m-9 2h10a2 2 0 012 2v5a2 2 0 01-2 2H7a2 2 0 01-2-2v-5a2 2 0 012-2z"></path>
                    </svg>
                    Unlock Dashboard
                </button>
            </div>
            <!-- Blur Overlay -->
            <div class="absolute inset-0 content-blur bg-black bg-opacity-10 z-10"></div>
        <?php endif; ?>

        <!-- Dashboard Content -->
        <div class="w-full <?php echo !$is_profile_complete ? 'hidden' : ''; ?>" id="dashboard-content">
            <div class="container">
                <div class="dashboard-header">
                    <h1>Hi <?php echo htmlspecialchars($full_name); ?> 👋</h1>
                    <p>Access your HR Services with ease</p>
                </div>
                <div class="grid">
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/holidays.php" class="card">
                            <img src="../assets/Holiday.png" alt="Holidays">
                        </a>
                        <p class="label">Holidays</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/attendance.php" class="card">
                            <img src="../assets/Attendance (1).png" alt="Attendance">
                        </a>
                        <p class="label">Attendance</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/leaves.php" class="card">
                            <img src="../assets/Leaves.png" alt="Leaves">
                        </a>
                        <p class="label">Leaves</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/work_from_home.php" class="card">
                            <img src="../assets/WFH (1).png" alt="Work From Home">
                        </a>
                        <p class="label">Work From Home</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/asset.php" class="card">
                            <img src="../assets/Reimbursment.png" alt="Asset">
                        </a>
                        <p class="label">Reimbursement</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/learnings.php" class="card">
                            <img src="../assets/Asset (1).png" alt="Asset">
                        </a>
                        <p class="label">Asset</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/learnings.php" class="card">
                            <img src="../assets/Learnings.png" alt="Learnings">
                        </a>
                        <p class="label">Learnings</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/onboarding.php" class="card">
                            <img src="../assets/Recognitions.png" alt="Recognitions">
                        </a>
                        <p class="label">Recognitions</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/onboardings.php" class="card">
                            <img src="../assets/Onboarding (1).png" alt="Onboarding">
                        </a>
                        <p class="label">Onboarding</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/letter.php" class="card">
                            <img src="../assets/Letters.png" alt="Letter">
                        </a>
                        <p class="label">Letters</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/resignation.php" class="card">
                            <img src="../assets/Resignation1.png" alt="Resignation">
                        </a>
                        <p class="label">Resignation</p>
                    </div>
                    <div class="card-wrapper">
                        <a href="http://localhost/allyted%20project/Employee/letter.php" class="card">
                            <img src="../assets/ID Card.png" alt="ID Card">
                        </a>
                        <p class="label">ID Card Request</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('Initial clock status: <?php echo $_SESSION['clock_status']; ?>');
        console.log('Initial clock_in_time: <?php echo $_SESSION['clock_in_time'] ?? 'not set'; ?>');

        function unlockDashboard() {
            const isProfileComplete = <?php echo json_encode($is_profile_complete); ?>;
            if (isProfileComplete) {
                console.log('Unlocking dashboard: Profile is complete');
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'unlock=true'
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        });
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Unlock response:', text);
                    if (text.trim() === 'Success') {
                        const profileCard = document.getElementById('profile-card');
                        const blurOverlay = document.querySelector('.content-blur');
                        const dashboardContent = document.getElementById('dashboard-content');
                        if (profileCard && blurOverlay && dashboardContent) {
                            profileCard.style.display = 'none';
                            blurOverlay.style.display = 'none';
                            dashboardContent.classList.remove('hidden');
                            console.log('Profile card and blur hidden, dashboard content shown');
                        } else {
                            console.error('Missing elements:', { profileCard, blurOverlay, dashboardContent });
                            alert('An error occurred while unlocking the dashboard.');
                        }
                    } else {
                        console.error('Unexpected response:', text);
                        alert('Failed to unlock dashboard. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error.message);
                    alert('Failed to unlock dashboard: ' + error.message);
                });
            } else {
                console.log('Unlock blocked: Profile incomplete');
                alert('Please complete your profile to unlock the dashboard.');
            }
        }

        const clockButton = document.getElementById('clock-button');
        let lastSwiped = document.getElementById('last-swiped');
        const selfieModal = document.getElementById('selfie-modal');
        const selfieVideo = document.getElementById('selfie-video');
        const captureSelfie = document.getElementById('capture-selfie');
        const closeModal = document.getElementById('close-modal');
        const modalTitle = document.getElementById('modal-title');
        const notesInput = document.getElementById('notes-input');
        let currentStream = null;
        let clockAction = '<?php echo $clock_status === 'in' ? 'clock_out' : 'clock_in'; ?>';
        let timerInterval = null;

        function formatTime(seconds) {
            const hrs = Math.floor(seconds / 3600).toString().padStart(2, '0');
            const mins = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
            const secs = (seconds % 60).toString().padStart(2, '0');
            return `${hrs}:${mins}:${secs}`;
        }

        function startTimer(startTime) {
            console.log('Starting timer with startTime:', startTime);
            if (!startTime || isNaN(new Date(startTime).getTime())) {
                console.error('Invalid start time for timer:', startTime);
                lastSwiped.textContent = 'Time Worked: 00:00:00';
                return;
            }
            if (timerInterval) clearInterval(timerInterval);
            const start = new Date(startTime).getTime();
            timerInterval = setInterval(() => {
                const now = Date.now();
                const elapsed = Math.floor((now - start) / 1000);
                lastSwiped.textContent = `Time Worked: ${formatTime(elapsed)}`;
                if (elapsed > 0) {
                    lastSwiped.classList.add('clock-in');
                }
            }, 1000);
        }

        function stopTimer() {
            console.log('Stopping timer');
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            lastSwiped.textContent = 'Last Swiped: 00:00:00';
            lastSwiped.classList.remove('clock-in');
        }

        // Initialize clock button and timer
        const initialClockStatus = '<?php echo $clock_status; ?>';
        const initialClockInTime = '<?php echo $_SESSION['clock_in_time'] ?? ''; ?>';
        if (initialClockStatus === 'in' && initialClockInTime) {
            console.log('Initializing timer on page load with clock_in_time:', initialClockInTime);
            clockButton.querySelector('h4').textContent = 'Clock Out';
            startTimer(initialClockInTime);
        } else {
            console.log('No active clock-in session, setting to Clock In');
            clockButton.querySelector('h4').textContent = 'Clock In';
            lastSwiped.textContent = 'Last Swiped: <?php echo $last_formatted_timestamp; ?>';
            lastSwiped.classList.remove('clock-in');
            stopTimer();
        }

        clockButton.addEventListener('click', () => {
            console.log('Clock button clicked, action:', clockAction);
            modalTitle.textContent = clockAction === 'clock_in' ? 'Clock In - Take Selfie' : 'Clock Out - Take Selfie';

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
                .then(stream => {
                    console.log('Camera access granted');
                    currentStream = stream;
                    selfieVideo.srcObject = stream;
                    selfieVideo.style.display = 'block';
                    selfieModal.style.display = 'flex';
                    selfieVideo.play().catch(err => {
                        console.error('Video play error:', err);
                        alert('Failed to start camera. Please ensure camera permissions are granted and try again.');
                    });
                })
                .catch(err => {
                    console.error('Camera access error:', err);
                    alert('Unable to access camera. Please ensure camera permissions are granted and try again.');
                });
        });

        captureSelfie.addEventListener('click', () => {
            console.log('Capture selfie clicked');
            if (!selfieVideo.srcObject || !selfieVideo.videoWidth || !selfieVideo.videoHeight) {
                console.error('Video stream not ready or not initialized');
                alert('Camera not ready. Please ensure the camera is active and try again.');
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = selfieVideo.videoWidth;
            canvas.height = selfieVideo.videoHeight;
            const context = canvas.getContext('2d');
            if (!context) {
                console.error('Failed to get canvas context');
                alert('Failed to capture selfie. Please try again.');
                return;
            }
            context.drawImage(selfieVideo, 0, 0);
            const selfieData = canvas.toDataURL('image/jpeg', 0.8);

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const { latitude, longitude } = position.coords;
                        const timestamp = new Date().toISOString();
                        const notes = notesInput.value;
                        console.log('Geolocation acquired:', { latitude, longitude, timestamp, notes });

                        const formData = new FormData();
                        formData.append('clock_action', clockAction);
                        formData.append('selfie_data', selfieData);
                        formData.append('latitude', latitude);
                        formData.append('longitude', longitude);
                        formData.append('timestamp', timestamp);
                        formData.append('notes', notes);

                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log('Fetch response status:', response.status);
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Fetch response data:', data);
                            if (data.success) {
                                if (lastSwiped) lastSwiped.remove();
                                const lastSwipedSpan = document.createElement('span');
                                lastSwipedSpan.id = 'last-swiped';
                                lastSwipedSpan.className = 'last-swiped';
                                if (data.clock_status === 'in') {
                                    lastSwipedSpan.textContent = 'Time Worked: 00:00:00';
                                    startTimer(data.timestamp);
                                    lastSwipedSpan.classList.add('clock-in');
                                    clockButton.querySelector('h4').textContent = 'Clock Out';
                                    clockAction = 'clock_out';
                                } else {
                                    lastSwipedSpan.textContent = 'Last Swiped: ' + data.formatted_timestamp;
                                    stopTimer();
                                    clockButton.querySelector('h4').textContent = 'Clock In';
                                    clockAction = 'clock_in';
                                    if (data.early_clock_out_minutes > 0) {
                                        alert(`Early clock-out detected: ${data.early_clock_out_minutes} minutes early`);
                                    }
                                }
                                if (data.late_clock_in_minutes > 0) {
                                    alert(`Late clock-in detected: ${data.late_clock_in_minutes} minutes late`);
                                }
                                clockButton.querySelector('.clock-text').appendChild(lastSwipedSpan);
                                lastSwiped = lastSwipedSpan;
                                selfieModal.style.display = 'none';
                                notesInput.value = '';
                                stopCamera();
                            } else {
                                console.error('Attendance recording failed:', data.error);
                                alert(data.error || 'Failed to record attendance. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error.message);
                            alert('Failed to record attendance: ' + error.message);
                        });
                    },
                    error => {
                        console.error('Geolocation error:', error);
                        alert('Geolocation failed. Proceeding without location data.');
                        const timestamp = new Date().toISOString();
                        const notes = notesInput.value;
                        console.log('Proceeding without geolocation:', { timestamp, notes });

                        const formData = new FormData();
                        formData.append('clock_action', clockAction);
                        formData.append('selfie_data', selfieData);
                        formData.append('timestamp', timestamp);
                        formData.append('notes', notes);

                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log('Fetch response status (no geo):', response.status);
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Fetch response data (no geo):', data);
                            if (data.success) {
                                if (lastSwiped) lastSwiped.remove();
                                const lastSwipedSpan = document.createElement('span');
                                lastSwipedSpan.id = 'last-swiped';
                                lastSwipedSpan.className = 'last-swiped';
                                if (data.clock_status === 'in') {
                                    lastSwipedSpan.textContent = 'Time Worked: 00:00:00';
                                    startTimer(data.timestamp);
                                    lastSwipedSpan.classList.add('clock-in');
                                    clockButton.querySelector('h4').textContent = 'Clock Out';
                                    clockAction = 'clock_out';
                                } else {
                                    lastSwipedSpan.textContent = 'Last Swiped: ' + data.formatted_timestamp;
                                    stopTimer();
                                    clockButton.querySelector('h4').textContent = 'Clock In';
                                    clockAction = 'clock_in';
                                    if (data.early_clock_out_minutes > 0) {
                                        alert(`Early clock-out detected: ${data.early_clock_out_minutes} minutes early`);
                                    }
                                }
                                if (data.late_clock_in_minutes > 0) {
                                    alert(`Late clock-in detected: ${data.late_clock_in_minutes} minutes late`);
                                }
                                clockButton.querySelector('.clock-text').appendChild(lastSwipedSpan);
                                lastSwiped = lastSwipedSpan;
                                selfieModal.style.display = 'none';
                                notesInput.value = '';
                                stopCamera();
                            } else {
                                console.error('Attendance recording failed (no geo):', data.error);
                                alert(data.error || 'Failed to record attendance. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error (no geo):', error.message);
                            alert('Failed to record attendance: ' + error.message);
                        });
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                console.error('Geolocation not supported');
                alert('Geolocation not supported. Proceeding without location data.');
                const timestamp = new Date().toISOString();
                const notes = notesInput.value;
                console.log('Geolocation not supported, proceeding:', { timestamp, notes });

                const formData = new FormData();
                formData.append('clock_action', clockAction);
                formData.append('selfie_data', selfieData);
                formData.append('timestamp', timestamp);
                formData.append('notes', notes);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Fetch response status (no geo support):', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Fetch response data (no geo support):', data);
                    if (data.success) {
                        if (lastSwiped) lastSwiped.remove();
                        const lastSwipedSpan = document.createElement('span');
                        lastSwipedSpan.id = 'last-swiped';
                        lastSwipedSpan.className = 'last-swiped';
                        if (data.clock_status === 'in') {
                            lastSwipedSpan.textContent = 'Time Worked: 00:00:00';
                            startTimer(data.timestamp);
                            lastSwipedSpan.classList.add('clock-in');
                            clockButton.querySelector('h4').textContent = 'Clock Out';
                            clockAction = 'clock_out';
                        } else {
                            lastSwipedSpan.textContent = 'Last Swiped: ' + data.formatted_timestamp;
                            stopTimer();
                            clockButton.querySelector('h4').textContent = 'Clock In';
                            clockAction = 'clock_in';
                            if (data.early_clock_out_minutes > 0) {
                                alert(`Early clock-out detected: ${data.early_clock_out_minutes} minutes early`);
                            }
                        }
                        if (data.late_clock_in_minutes > 0) {
                            alert(`Late clock-in detected: ${data.late_clock_in_minutes} minutes late`);
                        }
                        clockButton.querySelector('.clock-text').appendChild(lastSwipedSpan);
                        lastSwiped = lastSwipedSpan;
                        selfieModal.style.display = 'none';
                        notesInput.value = '';
                        stopCamera();
                    } else {
                        console.error('Attendance recording failed (no geo support):', data.error);
                        alert(data.error || 'Failed to record attendance. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Fetch error (no geo support):', error.message);
                    alert('Failed to record attendance: ' + error.message);
                });
            }
        });

        closeModal.addEventListener('click', () => {
            console.log('Closing selfie modal');
            selfieModal.style.display = 'none';
            notesInput.value = '';
            stopCamera();
        });

        function stopCamera() {
            if (currentStream) {
                console.log('Stopping camera stream');
                currentStream.getTracks().forEach(track => track.stop());
                selfieVideo.srcObject = null;
                selfieVideo.style.display = 'none';
                currentStream = null;
            }
        }

        // Ensure dropdown menu toggles on click
        const employeeButton = document.querySelector('.employee-button');
        const dropdownMenu = document.querySelector('.dropdown-menu');
        employeeButton.addEventListener('click', () => {
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!employeeButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                dropdownMenu.classList.add('hidden');
            }
        });

        // Update progress bar
        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            const percentage = <?php echo $completion_percentage; ?>;
            const circumference = 2 * Math.PI * 45;
            const offset = circumference - (percentage / 100) * circumference;
            progressBar.style.stroke = 'url(#progressGradient)';
            progressBar.style.strokeDasharray = `${circumference} ${circumference}`;
            progressBar.style.strokeDashoffset = offset;
        }
    </script>
</body>
</html>