<?php
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'db/config.php';

// Get the employee ID from the URL
$employee_id = isset($_GET['employee_id']) ? trim($_GET['employee_id']) : '';
if (empty($employee_id)) {
    header("Location: admin_dashboard.php?page=employee");
    exit;
}

// Fetch employee details
$stmt = $mysqli->prepare("
    SELECT e.employee_id, e.first_name, e.last_name, e.email_id, e.phone_number, e.city, e.staying_location, e.date_of_joining, 
           e.location_id, l.name AS location_name, e.department_id, d.name AS department_name, e.role_id, r.name AS role_name, 
           e.experience, e.bond, e.highest_degree, e.specialization, e.year_of_pass, e.brand_id, b.name AS brand_name, 
           e.touch, a.asset_id AS asset_identifier, a.serial_number, e.status
    FROM employees e
    JOIN locations l ON e.location_id = l.id
    JOIN departments d ON e.department_id = d.id
    JOIN roles r ON e.role_id = r.id
    LEFT JOIN brands b ON e.brand_id = b.id
    LEFT JOIN assets a ON e.asset_ref_id = a.ID
    WHERE e.employee_id = ?
");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    header("Location: admin_dashboard.php?page=employee");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Details - <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f4f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .employee-details {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .employee-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #6c757d;
            border: 4px solid #4a90e2;
            flex-shrink: 0;
        }

        .employee-image::before {
            content: '👤';
        }

        .employee-info {
            flex: 1;
        }

        .employee-info h2 {
            font-size: 2rem;
            color: #333;
            margin: 0 0 10px;
            font-weight: 600;
        }

        .employee-info .role {
            font-size: 1.2rem;
            color: #6c757d;
            font-style: italic;
            margin-bottom: 20px;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-section h3 {
            font-size: 1.4rem;
            color: #4a90e2;
            margin: 0 0 10px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 5px;
        }

        .info-section p {
            margin: 8px 0;
            font-size: 1rem;
            color: #555;
        }

        .info-section p strong {
            color: #333;
            display: inline-block;
            width: 150px;
        }

        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .status.active {
            background: #d4edda;
            color: #155724;
        }

        .status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4a90e2;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            margin-bottom: 20px;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #357abd;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_dashboard.php?page=employee" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Employees</a>
        <div class="employee-details">
            <div class="employee-image"></div>
            <div class="employee-info">
                <h2><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                <div class="role"><?= htmlspecialchars($employee['role_name']) ?></div>
                <div class="status <?= strtolower($employee['status']) ?>"><?= htmlspecialchars($employee['status']) ?></div>

                <div class="info-section">
                    <h3>Personal Information</h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($employee['email_id']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($employee['phone_number']) ?></p>
                    <p><strong>City:</strong> <?= htmlspecialchars($employee['city']) ?></p>
                    <p><strong>Staying Location:</strong> <?= htmlspecialchars($employee['staying_location']) ?></p>
                </div>

                <div class="info-section">
                    <h3>Employment Details</h3>
                    <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id']) ?></p>
                    <p><strong>Reporting Office:</strong> <?= htmlspecialchars($employee['location_name']) ?></p>
                    <p><strong>Department:</strong> <?= htmlspecialchars($employee['department_name']) ?></p>
                    <p><strong>Role:</strong> <?= htmlspecialchars($employee['role_name']) ?></p>
                    <p><strong>Experience:</strong> <?= htmlspecialchars($employee['experience']) ?> years</p>
                    <p><strong>Bond:</strong> <?= htmlspecialchars($employee['bond']) ?></p>
                    <p><strong>Date of Joining:</strong> <?= htmlspecialchars($employee['date_of_joining']) ?></p>
                </div>

                <div class="info-section">
                    <h3>Education Details</h3>
                    <p><strong>Highest Degree:</strong> <?= htmlspecialchars($employee['highest_degree']) ?></p>
                    <p><strong>Specialization:</strong> <?= htmlspecialchars($employee['specialization']) ?></p>
                    <p><strong>Year of Pass:</strong> <?= htmlspecialchars($employee['year_of_pass']) ?></p>
                </div>

                <div class="info-section">
                    <h3>Asset Allocation</h3>
                    <p><strong>Brand:</strong> <?= htmlspecialchars($employee['brand_name'] ?? 'N/A') ?></p>
                    <p><strong>Touch:</strong> <?= htmlspecialchars($employee['touch'] ?? 'N/A') ?></p>
                    <p><strong>Asset ID:</strong> <?= htmlspecialchars($employee['asset_identifier'] ?? 'N/A') ?></p>
                    <p><strong>Serial Number:</strong> <?= htmlspecialchars($employee['serial_number'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>