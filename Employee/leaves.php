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

$is_profile_complete = isset($_SESSION['dashboard_unlocked']) && $_SESSION['dashboard_unlocked'] === true;

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Leaves</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #2a002a, #000814);
            margin: 0;
            min-height: 100vh;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .content-blur {
            filter: blur(8px);
            pointer-events: none;
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
        .nav-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dashboard {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            grid-gap: 20px;
            padding: 20px;
            width: 100%;
            max-width: 1800px;
            overflow-x: auto;
        }
        .dashboard-card {
            width: 100%;
            height: 200px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-end;
            color: white;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.7);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            padding: 20px;
            min-width: 180px;
        }
        .dashboard-card img {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
        }
        .dashboard-card::before {
            content: "";
            position: absolute;
            top: -30%;
            left: -30%;
            width: 160%;
            height: 160%;
            background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
            filter: blur(40px);
            z-index: 0;
        }
        .dashboard-card .card-content {
            position: relative;
            z-index: 1;
            text-align: right;
        }
        .dashboard-card .card-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .dashboard-card .card-description {
            font-size: 12px;
            font-weight: normal;
            color: rgba(255, 255, 255, 0.8);
        }
        .dashboard-card:hover {
            transform: scale(1.05);
            box-shadow: 0px 0px 25px rgba(255,255,255,0.3);
            border: 2px solid rgba(255,255,255,0.4);
        }
        /* Colors with gradients */
        .green {
            background: linear-gradient(135deg, #28c76f, #009432);
        }
        @media (max-width: 1600px) {
            .dashboard {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 640px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
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
            <a href="<?php echo $is_profile_complete ? '../attendance.php' : '#'; ?>" class="nav-link text-gray-600 hover:text-red-600 text-lg font-semibold <?php echo !$is_profile_complete ? 'disabled' : ''; ?>" <?php echo !$is_profile_complete ? 'title="Complete your profile to unlock"' : ''; ?>>Attendance</a>
            <a href="<?php echo $is_profile_complete ? 'leaves.php' : '#'; ?>" class="nav-link text-gray-600 hover:text-red-600 text-lg font-semibold <?php echo !$is_profile_complete ? 'disabled' : ''; ?>" <?php echo !$is_profile_complete ? 'title="Complete your profile to unlock"' : ''; ?>>Leave</a>
            <div class="relative group">
                <button class="employee-button flex items-center px-3 py-2 rounded-lg text-gray-600 hover:text-red-600">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <span class="text-lg font-semibold"><?php echo htmlspecialchars($full_name); ?></span>
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg">
                    <a href="../my_profile.php" class="flex items-center px-6 py-3 text-sm text-gray-600 hover:bg-gray-100">
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
    <div class="flex-1 mt-20 p-8 flex flex-col items-center relative" id="main-content">
        <?php if (!$is_profile_complete): ?>
            <!-- Profile Completion Card -->
            <div class="profile-card w-full max-w-3xl rounded-2xl p-10 flex flex-col items-center bg-white" id="profile-card">
                <h4 class="text-3xl font-bold text-gray-700 mb-6">Profile Completion Required</h4>
                <p class="text-red-600 text-center font-medium text-lg mb-6">Complete your profile to access leave features!</p>
                <a href="../my_profile.php" class="cta-button px-8 py-3 rounded-full text-white font-semibold text-lg bg-[#cf0a2c] hover:bg-[#b00924] hover:transform hover:scale-105 hover:shadow-lg">Complete Profile Now</a>
            </div>
            <!-- Blur Overlay -->
            <div class="absolute inset-0 content-blur bg-black bg-opacity-10 z-10"></div>
        <?php else: ?>
            <!-- Leaves Content -->
            <div class="w-full max-w-6xl" id="leaves-content">
                <h4 class="text-3xl font-bold text-white mb-6">Hi <?php echo htmlspecialchars($full_name); ?> 👋</h4>
                <p class="text-gray-300 text-lg mb-8">Manage your leave requests below.</p>
                <div class="dashboard">
                    <!-- Apply Leave Card -->
                    <a href="#" class="dashboard-card green">
                        <img src="../placeholder.png" alt="Apply Leave">
                        <div class="card-content">
                            <h3 class="card-title">Apply Leave</h3>
                            <p class="card-description">Submit a new leave request</p>
                        </div>
                    </a>
                    <!-- Leave Status Card -->
                    <a href="#" class="dashboard-card green">
                        <img src="../placeholder.png" alt="Leave Status">
                        <div class="card-content">
                            <h3 class="card-title">Leave Status</h3>
                            <p class="card-description">Check the status of your leave requests</p>
                        </div>
                    </a>
                    <!-- Leave History Card -->
                    <a href="#" class="dashboard-card green">
                        <img src="../placeholder.png" alt="Leave History">
                        <div class="card-content">
                            <h3 class="card-title">Leave History</h3>
                            <p class="card-description">View your past leave records</p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>