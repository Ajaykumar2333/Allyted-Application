<?php
session_start();
if (!isset($_SESSION['employee_email']) || !isset($_SESSION['employee_id'])) {
    error_log("Invalid session: Redirecting to login");
    header("Location: ../index.php?error=Please login first");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$full_name = $_SESSION['employee_name'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
    <div class="flex-1 mt-20 flex flex-col items-center">
        <div class="w-full max-w-6xl">
        
            <!-- Add your holiday-related content here -->
        </div>
    </div>
</body>
</html>