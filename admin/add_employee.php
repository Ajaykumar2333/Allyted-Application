<?php
ini_set('display_errors', 1); // Temporary for debugging
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\Allyted Project\admin\php_errors.log');
error_reporting(E_ALL);

// Log script start
file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "add_employee.php started: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Check for db/config.php
$base_path = 'C:\xampp\htdocs\Allyted Project\admin';
if (!file_exists('C:\xampp\htdocs\Allyted Project\db\config.php')) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "db/config.php not found at C:\xampp\htdocs\Allyted Project\db\config.php: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: db/config.php not found.</p>";
    exit;
}
require_once 'C:\xampp\htdocs\Allyted Project\db\config.php';

// Check for mailer.php
$mailer_path = 'C:\xampp\htdocs\Allyted Project\mailer.php';
if (!file_exists($mailer_path)) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "mailer.php not found at $mailer_path: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: mailer.php not found at $mailer_path.</p>";
    exit;
}
require_once $mailer_path;
file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "mailer.php loaded from $mailer_path at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Check database connection
if (!$mysqli || $mysqli->connect_error) {
    $error_msg = "Database connection failed: " . ($mysqli ? $mysqli->connect_error : "No mysqli object");
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Database connection error.</p>";
    exit;
}

// Check required tables
$required_tables = ['employee_details', 'credentials', 'departments', 'roles', 'locations'];
foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Table $table does not exist at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: Table $table does not exist. Please create the table.</p>";
        exit;
    }
    $result->free();
}

// Check employee_details columns
$required_columns = ['employee_id', 'full_name', 'email', 'phone', 'date_of_joining', 'department_id', 'role_id', 'location_id', 'bond_years', 'created_at', 'status'];
$result = $mysqli->query("SHOW COLUMNS FROM employee_details");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}
$result->free();
$missing_columns = array_diff($required_columns, $existing_columns);
if (!empty($missing_columns)) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Missing columns in employee_details: " . implode(', ', $missing_columns) . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: Missing columns in employee_details: " . implode(', ', $missing_columns) . ". Please update the schema.</p>";
    exit;
}

// Check credentials columns
$required_cred_columns = ['employee_id', 'email', 'password'];
$result = $mysqli->query("SHOW COLUMNS FROM credentials");
$existing_cred_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_cred_columns[] = $row['Field'];
}
$result->free();
$missing_cred_columns = array_diff($required_cred_columns, $existing_cred_columns);
if (!empty($missing_cred_columns)) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Missing columns in credentials: " . implode(', ', $missing_cred_columns) . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: Missing columns in credentials: " . implode(', ', $missing_cred_columns) . ". Please update the schema.</p>";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Session start failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Session error.</p>";
        exit;
    }
}

// Generate employee ID starting from AS0045
define('ID_PREFIX', 'AS'); // Configurable prefix
function generateEmployeeID($mysqli) {
    $prefix = ID_PREFIX;
    $prefix_length = strlen($prefix);
    
    // Query to get the last employee_id with the specified prefix
    $query = "SELECT employee_id FROM employee_details WHERE employee_id LIKE ? ORDER BY CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) DESC LIMIT 1";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Prepare failed for employee_id query: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return $prefix . '0045'; // Fallback to starting ID AS0045
    }
    $like_pattern = $prefix . '%';
    $substring_start = $prefix_length + 1; // Assign expression to a variable
    $stmt->bind_param("si", $like_pattern, $substring_start);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['employee_id'];
        // Extract numeric part after prefix
        $number = (int)substr($last_id, $prefix_length);
        $new_number = $number + 1;
    } else {
        $new_number = 45; // Start from AS0045
    }
    
    $stmt->close();
    $result->free();
    return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

$generated_employee_id = generateEmployeeID($mysqli);

// Generate random password
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $employee_id = $generated_employee_id; // Use generated ID
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_joining = trim($_POST['date_of_joining'] ?? '');
    $department_id = intval($_POST['department'] ?? 0);
    $role_id = intval($_POST['role'] ?? 0);
    $location_id = intval($_POST['reporting_office'] ?? 0);
    $bond_years = intval($_POST['bond'] ?? 0);
    $status = 'Active';

    $errors = [];
    if (empty($full_name)) $errors[] = "Full Name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid Email is required";
    if (empty($phone) || !preg_match('/^\d{10}$/', $phone)) $errors[] = "Phone must be exactly 10 digits";
    if (empty($date_of_joining)) {
        $errors[] = "Date of Joining is required";
    } elseif (!DateTime::createFromFormat('Y-m-d', $date_of_joining)) {
        $errors[] = "Invalid Date of Joining format. Use YYYY-MM-DD";
    } elseif (strtotime($date_of_joining) > time()) {
        $errors[] = "Date of Joining cannot be in the future";
    }
    if ($department_id <= 0) $errors[] = "Department is required";
    if ($role_id <= 0) $errors[] = "Role is required";
    if ($location_id <= 0) $errors[] = "Reporting Office is required";
    if ($bond_years < 1) $errors[] = "Bond (years) must be a positive number";

    // Check for duplicate email or phone
    $checkDup = $mysqli->prepare("SELECT COUNT(*) FROM employee_details WHERE email = ? OR phone = ?");
    if (!$checkDup) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Prepare failed for duplicate check: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $error = "Database error during duplicate check.";
    } else {
        $checkDup->bind_param("ss", $email, $phone);
        $checkDup->execute();
        $checkDup->bind_result($count);
        $checkDup->fetch();
        $checkDup->close();
        if ($count > 0) $errors[] = "Email or Phone already exists";
    }

    // Check for duplicate employee_id
    $checkIdDup = $mysqli->prepare("SELECT COUNT(*) FROM employee_details WHERE employee_id = ?");
    if (!$checkIdDup) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Prepare failed for employee_id duplicate check: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $error = "Database error during employee ID duplicate check.";
    } else {
        $checkIdDup->bind_param("s", $employee_id);
        $checkIdDup->execute();
        $checkIdDup->bind_result($count);
        $checkIdDup->fetch();
        $checkIdDup->close();
        if ($count > 0) {
            $errors[] = "Employee ID $employee_id already exists";
            $generated_employee_id = generateEmployeeID($mysqli); // Regenerate to avoid conflicts
        }
    }

    if (empty($errors)) {
        $raw_password = generateRandomPassword();
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        try {
            $mysqli->begin_transaction();

            // Insert into employee_details
            $stmt = $mysqli->prepare("
                INSERT INTO employee_details (employee_id, full_name, email, phone, date_of_joining, department_id, role_id, location_id, bond_years, created_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            if (!$stmt) {
                throw new Exception("Prepare failed for employee insert: " . $mysqli->error);
            }
            $stmt->bind_param("sssssiiiss", $employee_id, $full_name, $email, $phone, $date_of_joining, $department_id, $role_id, $location_id, $bond_years, $status);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for employee insert: " . $stmt->error);
            }
            $stmt->close();

            // Insert into credentials
            $stmt = $mysqli->prepare("INSERT INTO credentials (employee_id, email, password) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for credentials insert: " . $mysqli->error);
            }
            $stmt->bind_param("sss", $employee_id, $email, $hashed_password);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for credentials insert: " . $stmt->error);
            }
            $stmt->close();

            // Send email
            $subject = "Your Company Portal Login Credentials";
            $message = "
                <div style='font-family: Inter, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background: #f4f7fa; border-radius: 10px;'>
                    <h2 style='color: #6366f1;'>Welcome, $full_name!</h2>
                    <p>Your account has been created. Below are your login credentials:</p>
                    <p><strong>Employee ID:</strong> $employee_id</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Password:</strong> $raw_password</p>
                    <p>Please contact the HR department for login instructions and change your password after your first login.</p>
                    <p style='color: #ff0066; font-weight: 500;'>Note: Do not share your credentials with anyone.</p>
                    <p style='margin-top: 20px; font-size: 12px; color: #777;'>© Company Team</p>
                </div>
            ";
            try {
                if (function_exists('sendMail')) {
                    sendMail($email, $full_name, $subject, $message, true);
                    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Email sent successfully to $email for employee $employee_id at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                } else {
                    throw new Exception("sendMail function not found in mailer.php");
                }
            } catch (Exception $e) {
                file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Email sending failed for $email: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                // Proceed with commit despite email failure
            }

            $mysqli->commit();
            file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Employee $employee_id added successfully at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            $_SESSION['success_message'] = "Employee added successfully! Credentials sent to $email.";
            $success = true;
            $generated_employee_id = generateEmployeeID($mysqli);
        } catch (Exception $e) {
            $mysqli->rollback();
            file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Error adding employee $employee_id: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            $error = "Error adding employee: " . $e->getMessage();
        }
    } else {
        $error = implode(", ", $errors);
    }
}

// Fetch data from database
$departments = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
if (!$departments) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Error fetching departments: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error fetching departments.</p>";
    exit;
}
$locations = $mysqli->query("SELECT id, name FROM locations ORDER BY name");
if (!$locations) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Error fetching locations: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error fetching locations.</p>";
    exit;
}

// Fetch roles based on selected department
$selected_dept_id = isset($_POST['department']) ? intval($_POST['department']) : ($departments->num_rows > 0 ? $departments->fetch_object()->id : 0);
$departments->data_seek(0);
$roles_query = "SELECT id, name FROM roles" . ($selected_dept_id ? " WHERE department_id = $selected_dept_id" : "") . " ORDER BY name";
$roles = $mysqli->query($roles_query);
if (!$roles) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Error fetching roles: " . $mysqli->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error fetching roles.</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <link rel="stylesheet" href="css/employee_style.css?v=<?php echo time(); ?>">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fbff, #e3f2fd);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px;
            max-width: 1100px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 40px;
            border-left: 4px solid #6366f1;
            padding-left: 16px;
        }

        .form-tag {
            display: inline-block;
            background: rgba(99, 102, 241, 0.1);
            color: #4338ca;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .form-header h2 {
            font-size: 28px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 15px;
            color: #475569;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }

        .form-group input,
        .form-group select {
            padding: 12px 14px 12px 42px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(203, 213, 225, 0.6);
            border-radius: 10px;
            width: 100%;
            transition: all 0.3s ease;
            color: #0f172a;
            appearance: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.9);
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-group input:read-only {
            background: rgba(200, 200, 200, 0.7);
            cursor: not-allowed;
        }

        .form-group input::placeholder,
        .form-group select::placeholder {
            color: #64748b;
        }

        .form-group .icon {
            position: absolute;
            top: 37px;
            left: 14px;
            font-size: 14px;
            color: #94a3b8;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }

        .form-group input:focus + .icon,
        .form-group select:focus + .icon {
            color: #6384f1;
            text-shadow: 0 0 6px rgba(99, 168, 241, 0.4);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .form-footer .note {
            font-size: 13px;
            color: #334155;
            flex: 1 1 auto;
        }

        .form-footer .buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: #ebecec;
            color: #334155;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-save {
            background: linear-gradient(to right, #6366f1, #3b82f6);
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
        }

        .btn-save:hover {
            background: #467be5;
        }

        .error-message {
            color: #721c24;
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .form-footer .buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-header">
            <span class="form-tag">Onboarding Form</span>
            <h2>Add New Employee</h2>
            <p>Fill in the employee’s details to begin the onboarding process.</p>
        </div>

        <form method="POST" action="" id="employeeForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID *</label>
                    <input type="text" name="employee_id" value="<?php echo htmlspecialchars($generated_employee_id); ?>" readonly required>
                    <i class="fas fa-id-badge icon"></i>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="Enter full name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    <i class="fas fa-user icon"></i>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    <i class="fas fa-envelope icon"></i>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    <i class="fas fa-phone icon"></i>
                </div>
                <div class="form-group">
                    <label>Date of Joining *</label>
                    <input type="date" name="date_of_joining" value="<?php echo htmlspecialchars($_POST['date_of_joining'] ?? ''); ?>" required>
                    <i class="fas fa-calendar icon"></i>
                </div>
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" onchange="this.form.submit()">
                        <option value="" disabled <?php echo !isset($_POST['department']) ? 'selected' : ''; ?>>Select department</option>
                        <?php $departments->data_seek(0); while ($dept = $departments->fetch_object()): ?>
                            <option value="<?php echo $dept->id; ?>" <?php echo (isset($_POST['department']) && $_POST['department'] == $dept->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept->name); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fas fa-building icon"></i>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role">
                        <option value="" disabled <?php echo (!isset($_POST['role']) || $_POST['role'] == '') ? 'selected' : ''; ?>>Select role</option>
                        <?php $roles->data_seek(0); while ($role = $roles->fetch_object()): ?>
                            <option value="<?php echo $role->id; ?>" <?php echo (isset($_POST['role']) && $_POST['role'] == $role->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role->name); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fas fa-briefcase icon"></i>
                </div>
                <div class="form-group">
                    <label>Reporting Office *</label>
                    <select name="reporting_office">
                        <option value="" disabled <?php echo (!isset($_POST['reporting_office']) || $_POST['reporting_office'] == '') ? 'selected' : ''; ?>>Select office</option>
                        <?php $locations->data_seek(0); while ($loc = $locations->fetch_object()): ?>
                            <option value="<?php echo $loc->id; ?>" <?php echo (isset($_POST['reporting_office']) && $_POST['reporting_office'] == $loc->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc->name); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <i class="fas fa-map-marker-alt icon"></i>
                </div>
                <div class="form-group">
                    <label>Bond (years) *</label>
                    <input type="number" name="bond" placeholder="Enter years" value="<?php echo htmlspecialchars($_POST['bond'] ?? ''); ?>" min="1" required>
                    <i class="fas fa-file-signature icon"></i>
                </div>
            </div>

            <div class="form-footer">
                <div class="note">
                    After saving, an email will be sent to the employee with their login credentials.
                </div>
                <div class="buttons">
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='http://localhost/Allyted%20Project/admin_dashboard.php?page=employee'">Cancel</button>
                    <button type="submit" name="save" class="btn btn-save">Save</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Show modern popup on success
        <?php if ($success): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?php echo addslashes($_SESSION['success_message'] ?? 'Employee added successfully.'); ?>',
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#ff0066',
                confirmButtonText: 'Add Another Employee',
                cancelButtonText: 'Back to Dashboard'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'add_employee.php';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.href = 'http://localhost/Allyted%20Project/admin_dashboard.php?page=employee';
                }
            });
        <?php endif; ?>

        // Show error popup
        <?php if (!empty($error)): ?>
            Swal.fire({
                title: 'Error!',
                text: '<?php echo addslashes($error); ?>',
                icon: 'error',
                confirmButtonColor: '#ff0066',
                confirmButtonText: 'Try Again'
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php
unset($_SESSION['success_message'], $_SESSION['error_message']);
$departments->free();
$locations->free();
$roles->free();
$mysqli->close();
?>