<?php
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: Redirecting to login");
    header("Location: ../index.php?error=Please login first");
    exit;
}

require_once '../db/config.php';

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

// Organization section (employee_details)
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

// Basic section (employee_details2)
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

// Education section (employee_education)
$education_complete = !empty($education_details) && count(array_filter($education_details, fn($edu) => 
    !empty($edu['graduation']) && 
    !empty($edu['branch']) && 
    !empty($edu['score']) && 
    !empty($edu['passed_out_year'])
)) > 0;
if ($education_complete) $progress += 25;

// Experience section (employee_experience)
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

// Reset dashboard_unlocked if profile is incomplete
if (!$is_profile_complete) {
    unset($_SESSION['dashboard_unlocked']);
    error_log("Profile incomplete: dashboard_unlocked unset, percentage=$completion_percentage");
} else {
    $_SESSION['dashboard_unlocked'] = true; // Automatically unlock if profile is complete
}

error_log("Completion: Org=$organization_complete, Basic=$basic_complete, Edu=$education_complete, Exp=$experience_complete, Total=$completion_percentage");

$mysqli->close();
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
                        <a href="http://localhost/allyted%20project/Employee/resignation.php" class="card">
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
                        <a href="http://localhost/allyted%20project/Employee/letter.php" class="card">
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
                            <img src="../assets/ID Card 1.png" alt="ID Card">
                        </a>
                        <p class="label">ID Card Request</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
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
                    console.error('Fetch error:', error);
                    alert('Failed to unlock dashboard. Please try again.');
                });
            } else {
                console.log('Unlock blocked: Profile incomplete');
                alert('Please complete your profile to unlock the dashboard.');
            }
        }
    </script>
</body>
</html>