<?php
// Enable debug mode for development (set to 0 in production)
$debug_mode = 1;
ini_set('display_errors', $debug_mode);
ini_set('display_startup_errors', $debug_mode);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\Allyted Project\admin\php_errors.log');
error_reporting(E_ALL);

// Log script start
$log_file = 'C:\xampp\htdocs\Allyted Project\admin\debug.log';
file_put_contents($log_file, "content_templates.php started: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration
require_once 'C:\xampp\htdocs\Allyted Project\db\config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check database connection
if (!$mysqli) {
    $error_msg = "Database connection failed: No mysqli object initialized";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection error: No database connection established. Please check config.php and MySQL server status.']);
    exit;
}
if ($mysqli->connect_error) {
    $error_msg = "Database connection failed: " . $mysqli->connect_error . " (Error Code: " . $mysqli->connect_errno . ")";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => "Database connection error: {$mysqli->connect_error}. Check config.php or MySQL server."]);
    exit;
}

// Check required tables
$required_tables = ['departments', 'roles', 'employee_details', 'level_1_authorities', 'level_2_authorities'];
foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $error_msg = "Table '$table' does not exist in the database";
        file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => "Database error: Table '$table' is missing. Please create the required table."]);
        exit;
    }
    $result->free();
}

// Fetch roles
$roles_query = "SELECT id, name, department_id FROM roles ORDER BY name";
$roles = $mysqli->query($roles_query);
if (!$roles) {
    $error_msg = "Error fetching roles: " . $mysqli->error . " (Query: $roles_query)";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $rolesData = [];
} else {
    $rolesData = $roles->fetch_all(MYSQLI_ASSOC);
}
$rolesJson = json_encode($rolesData);

// Fetch employees
$employees_query = "SELECT employee_id, full_name, department_id, role_id FROM employee_details WHERE LOWER(status) = 'active' ORDER BY full_name";
$employees = $mysqli->query($employees_query);
if (!$employees) {
    $error_msg = "Error fetching employees: " . $mysqli->error . " (Query: $employees_query)";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $employeesData = [];
} else {
    $employeesData = $employees->fetch_all(MYSQLI_ASSOC);
}
$employeesJson = json_encode($employeesData);
file_put_contents($log_file, "Employees Data: " . json_encode($employeesData) . "\n", FILE_APPEND);

// Pagination settings
$items_per_page = 7;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Fetch total number of records for pagination
$total_query = "SELECT COUNT(*) as total FROM level_1_authorities WHERE is_saved = TRUE";
$total_result = $mysqli->query($total_query);
$total_records = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $items_per_page);

// Fetch authorities with pagination
$authorities_query = "
    SELECT l1.template_type AS Template, 
           d1.name AS Department, r1.name AS Role, e1.full_name AS Person,
           d2.name AS Department_2, r2.name AS Role_2, e2.full_name AS Person_2,
           l1.created_at
    FROM level_1_authorities l1
    LEFT JOIN departments d1 ON l1.department_id = d1.id
    LEFT JOIN roles r1 ON l1.role_id = r1.id
    LEFT JOIN employee_details e1 ON l1.employee_id = e1.employee_id
    LEFT JOIN level_2_authorities l2 ON l1.template_type = l2.template_type
    LEFT JOIN departments d2 ON l2.department_id = d2.id
    LEFT JOIN roles r2 ON l2.role_id = r2.id
    LEFT JOIN employee_details e2 ON l2.employee_id = e2.employee_id
    WHERE l1.is_saved = TRUE
    ORDER BY l1.created_at DESC
    LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($authorities_query);
$stmt->bind_param("ii", $items_per_page, $offset);
$stmt->execute();
$authorities = $stmt->get_result();
if (!$authorities) {
    $error_msg = "Error fetching authorities: " . $mysqli->error . " (Query: $authorities_query)";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $authoritiesData = [];
} else {
    $authoritiesData = $authorities->fetch_all(MYSQLI_ASSOC);
    // Assign SNO for display purposes
    foreach ($authoritiesData as $index => &$authority) {
        $authority['SNO'] = $offset + $index + 1;
    }
}
$authoritiesJson = json_encode($authoritiesData);
$stmt->close();

// Fetch departments
$departments_query = "SELECT id, name FROM departments ORDER BY name";
$departments = $mysqli->query($departments_query);
if (!$departments) {
    $error_msg = "Error fetching departments: " . $mysqli->error . " (Query: $departments_query)";
    file_put_contents($log_file, $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => "Error fetching departments: {$mysqli->error}. Check database schema."]);
    exit;
}
$departmentsData = $departments->fetch_all(MYSQLI_ASSOC);

// Handle form submission (Add/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_authorities'])) {
    $log_data = json_encode($_POST, JSON_PRETTY_PRINT);
    file_put_contents($log_file, "Received AJAX save request:\n$log_data\n", FILE_APPEND);

    $templates = json_decode($_POST['templates'], true);
    $response = ['success' => true, 'snos' => [], 'messages' => [], 'errors' => []];

    foreach ($templates as $index => $template) {
        $template_name = trim($template['template_name'] ?? '');
        $level1_department_id = !empty($template['level1_department']) ? intval($template['level1_department']) : null;
        $level1_role_id = !empty($template['level1_role']) ? intval($template['level1_role']) : null;
        $level1_employee_id = !empty($template['level1_person']) ? trim($template['level1_person']) : null;
        $level2_department_id = !empty($template['level2_department']) ? intval($template['level2_department']) : null;
        $level2_role_id = !empty($template['level2_role']) ? intval($template['level2_role']) : null;
        $level2_employee_id = !empty($template['level2_person']) ? trim($template['level2_person']) : null;

        file_put_contents($log_file, "Parsed input values for template $index: " . json_encode([
            'template_name' => $template_name,
            'level1_department_id' => $level1_department_id,
            'level1_role_id' => $level1_role_id,
            'level1_employee_id' => $level1_employee_id,
            'level2_department_id' => $level2_department_id,
            'level2_role_id' => $level2_role_id,
            'level2_employee_id' => $level2_employee_id
        ]) . "\n", FILE_APPEND);

        $success = true;
        $errors = [];

        if (empty($template_name) || $template_name === '0') {
            $errors[] = "Template name is required and cannot be '0' for template $index.";
            $success = false;
            file_put_contents($log_file, "Form error: Empty or invalid template name for template $index at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        }

        // Validate Level 1 fields
        if (!$level1_department_id || !$level1_role_id || !$level1_employee_id) {
            $errors[] = "Level 1 Department, Role, and Person are required for template $index.";
            $success = false;
            file_put_contents($log_file, "Form error: Missing Level 1 fields for template $index at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        } else {
            // Validate Level 1 Department
            $stmt = $mysqli->prepare("SELECT 1 FROM departments WHERE id = ?");
            $stmt->bind_param("i", $level1_department_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                $errors[] = "Invalid Level 1 Department ID ($level1_department_id) for template $index.";
                $success = false;
            }
            $stmt->close();

            // Validate Level 1 Role
            $stmt = $mysqli->prepare("SELECT 1 FROM roles WHERE id = ?");
            $stmt->bind_param("i", $level1_role_id);
            $stmt->execute();
            if (!$stmt->get_result()->num_rows) {
                $errors[] = "Invalid Level 1 Role ID ($level1_role_id) for template $index.";
                $success = false;
            }
            $stmt->close();

            // Validate Level 1 Employee
            $cleaned_employee_id = trim($level1_employee_id);
            $stmt = $mysqli->prepare("SELECT employee_id, full_name FROM employee_details WHERE TRIM(employee_id) = ? AND LOWER(status) = 'active'");
            $stmt->bind_param("s", $cleaned_employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $hex_value = bin2hex($cleaned_employee_id);
            file_put_contents($log_file, "Person_1 validation for template $index: employee_id='$cleaned_employee_id', Length: " . strlen($cleaned_employee_id) . ", Hex: $hex_value, Rows: " . $result->num_rows . ", Found: " . json_encode($row) . "\n", FILE_APPEND);
            if (!$result->num_rows) {
                $errors[] = "Invalid Level 1 Employee ID ($cleaned_employee_id) for template $index does not exist in employee_details or is not active.";
                $success = false;
            } else {
                $level1_employee_id = $row['employee_id'];
            }
            $stmt->close();
        }

        // Validate Level 2 fields
        if ($level2_department_id || $level2_role_id || $level2_employee_id) {
            if (!$level2_department_id || !$level2_role_id || !$level2_employee_id) {
                $errors[] = "All Level 2 fields (Department, Role, Person) must be provided together or all left empty for template $index.";
                $success = false;
                file_put_contents($log_file, "Validation error: Incomplete Level 2 fields for template $index\n", FILE_APPEND);
            } else {
                // Validate Level 2 Department
                $stmt = $mysqli->prepare("SELECT 1 FROM departments WHERE id = ?");
                $stmt->bind_param("i", $level2_department_id);
                $stmt->execute();
                if (!$stmt->get_result()->num_rows) {
                    $errors[] = "Invalid Level 2 Department ID ($level2_department_id) for template $index.";
                    $success = false;
                }
                $stmt->close();

                // Validate Level 2 Role
                $stmt = $mysqli->prepare("SELECT 1 FROM roles WHERE id = ?");
                $stmt->bind_param("i", $level2_role_id);
                $stmt->execute();
                if (!$stmt->get_result()->num_rows) {
                    $errors[] = "Invalid Level 2 Role ID ($level2_role_id) for template $index.";
                    $success = false;
                }
                $stmt->close();

                // Validate Level 2 Employee
                $cleaned_employee_id = trim($level2_employee_id);
                $stmt = $mysqli->prepare("SELECT employee_id, full_name FROM employee_details WHERE TRIM(employee_id) = ? AND LOWER(status) = 'active'");
                $stmt->bind_param("s", $cleaned_employee_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $hex_value = bin2hex($cleaned_employee_id);
                file_put_contents($log_file, "Person_2 validation for template $index: employee_id='$cleaned_employee_id', Length: " . strlen($cleaned_employee_id) . ", Hex: $hex_value, Rows: " . $result->num_rows . ", Found: " . json_encode($row) . "\n", FILE_APPEND);
                if (!$result->num_rows) {
                    $errors[] = "Invalid Level 2 Employee ID ($cleaned_employee_id) for template $index does not exist in employee_details or is not active.";
                    $success = false;
                } else {
                    $level2_employee_id = $row['employee_id'];
                }
                $stmt->close();
            }
        } else {
            $level2_department_id = null;
            $level2_role_id = null;
            $level2_employee_id = null;
            file_put_contents($log_file, "Level 2 fields set to NULL for template $index\n", FILE_APPEND);
        }

        // Check for duplicate template name
        if ($template_name && $success) {
            $query = "SELECT 1 FROM level_1_authorities WHERE template_type = ?";
            $stmt = $mysqli->prepare($query);
            if (!$stmt) {
                $errors[] = "Prepare failed for duplicate check: " . $mysqli->error . " for template $index";
                $success = false;
                file_put_contents($log_file, "Prepare failed for duplicate check: " . $mysqli->error . " for template $index\n", FILE_APPEND);
            } else {
                $stmt->bind_param("s", $template_name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = "Template name '$template_name' already exists for template $index.";
                    $success = false;
                }
                $stmt->close();
            }
        }

        // Check for duplicate employee IDs
        $employee_ids = array_filter([$level1_employee_id, $level2_employee_id]);
        if (count($employee_ids) > count(array_unique($employee_ids))) {
            $errors[] = "Duplicate employee IDs selected across levels for template $index.";
            $success = false;
            file_put_contents($log_file, "Validation error: Duplicate employee IDs for template $index: " . json_encode($employee_ids) . "\n", FILE_APPEND);
        }

        if ($success) {
            try {
                $mysqli->begin_transaction();

                $logData = [
                    'Template' => $template_name,
                    'Department' => $level1_department_id ?? 'NULL',
                    'Role' => $level1_role_id ?? 'NULL',
                    'Person' => $level1_employee_id ?? 'NULL',
                    'Department_2' => $level2_department_id ?? 'NULL',
                    'Role_2' => $level2_role_id ?? 'NULL',
                    'Person_2' => $level2_employee_id ?? 'NULL'
                ];
                file_put_contents($log_file, "Processing save for template $index: " . json_encode($logData) . "\n", FILE_APPEND);

                // Save Level 1
                $stmt = $mysqli->prepare("SELECT template_type FROM level_1_authorities WHERE template_type = ?");
                $stmt->bind_param("s", $template_name);
                $stmt->execute();
                $exists_level_1 = $stmt->fetch();
                $stmt->close();

                if ($exists_level_1) {
                    $query = "UPDATE level_1_authorities 
                              SET department_id = ?, role_id = ?, employee_id = ?, is_saved = TRUE
                              WHERE template_type = ?";
                    $stmt = $mysqli->prepare($query);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for Level 1 UPDATE: " . $mysqli->error);
                    }
                    $stmt->bind_param("iiss", $level1_department_id, $level1_role_id, $level1_employee_id, $template_name);
                } else {
                    $query = "INSERT INTO level_1_authorities (template_type, department_id, role_id, employee_id, is_saved, created_at)
                              VALUES (?, ?, ?, ?, TRUE, NOW())";
                    $stmt = $mysqli->prepare($query);
                    if (!$stmt) {
                        throw new Exception("Prepare failed for Level 1 INSERT: " . $mysqli->error);
                    }
                    $stmt->bind_param("siis", $template_name, $level1_department_id, $level1_role_id, $level1_employee_id);
                }
                if (!$stmt->execute()) {
                    throw new Exception("Level 1 query execution failed: " . $stmt->error);
                }
                $stmt->close();

                // Save Level 2 (if provided)
                if ($level2_department_id && $level2_role_id && $level2_employee_id) {
                    $stmt = $mysqli->prepare("SELECT id FROM level_2_authorities WHERE template_type = ?");
                    $stmt->bind_param("s", $template_name);
                    $stmt->execute();
                    $exists_level_2 = $stmt->fetch();
                    $stmt->close();

                    if ($exists_level_2) {
                        $query = "UPDATE level_2_authorities 
                                  SET department_id = ?, role_id = ?, employee_id = ?, is_saved = TRUE
                                  WHERE template_type = ?";
                        $stmt = $mysqli->prepare($query);
                        if (!$stmt) {
                            throw new Exception("Prepare failed for Level 2 UPDATE: " . $mysqli->error);
                        }
                        $stmt->bind_param("iiss", $level2_department_id, $level2_role_id, $level2_employee_id, $template_name);
                    } else {
                        $query = "INSERT INTO level_2_authorities (template_type, department_id, role_id, employee_id, is_saved, created_at)
                                  VALUES (?, ?, ?, ?, TRUE, NOW())";
                        $stmt = $mysqli->prepare($query);
                        if (!$stmt) {
                            throw new Exception("Prepare failed for Level 2 INSERT: " . $mysqli->error);
                        }
                        $stmt->bind_param("siis", $template_name, $level2_department_id, $level2_role_id, $level2_employee_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception("Level 2 query execution failed: " . $stmt->error);
                    }
                    $stmt->close();
                }

                $mysqli->commit();
                file_put_contents($log_file, "Successfully saved template $index with template_type: $template_name\n", FILE_APPEND);
                $response['snos'][] = $index + 1; // Use index for SNO simulation
                $response['messages'][] = "Template $index saved successfully!";
            } catch (Exception $e) {
                $mysqli->rollback();
                $error_msg = "Error saving template $index: " . $e->getMessage();
                file_put_contents($log_file, "Error saving for template $index: " . $e->getMessage() . "\n", FILE_APPEND);
                if (stripos($e->getMessage(), 'foreign key constraint fails') !== false) {
                    $error_msg = "Invalid employee ID selected for template $index. Please ensure the selected Level 1 (ID: $level1_employee_id) or Level 2 (ID: $level2_employee_id) employee exists and is active.";
                }
                $errors[] = $error_msg;
                $response['success'] = false;
                $response['errors'] = array_merge($response['errors'], $errors);
            }
        } else {
            $response['success'] = false;
            $response['errors'] = array_merge($response['errors'], $errors);
        }
    }

    $_SESSION['success_message'] = implode("\n", $response['messages']);
    $_SESSION['error_message'] = implode("\n", $response['errors']);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_authority'])) {
    $template_type = trim($_POST['template_type'] ?? '');
    $errors = [];

    if (empty($template_type)) {
        $errors[] = "Invalid template type provided.";
        file_put_contents($log_file, "Delete error: Invalid template_type ($template_type) at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $mysqli->begin_transaction();
        $query = "DELETE FROM level_2_authorities WHERE template_type = ?";
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for Level 2 DELETE: " . $mysqli->error);
        }
        $stmt->bind_param("s", $template_type);
        if (!$stmt->execute()) {
            throw new Exception("Level 2 query execution failed: " . $stmt->error);
        }
        $stmt->close();

        $query = "DELETE FROM level_1_authorities WHERE template_type = ?";
        $stmt = $mysqli->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed for Level 1 DELETE: " . $mysqli->error);
        }
        $stmt->bind_param("s", $template_type);
        if (!$stmt->execute()) {
            throw new Exception("Level 1 query execution failed: " . $stmt->error);
        }
        $stmt->close();

        $mysqli->commit();
        file_put_contents($log_file, "Successfully deleted template_type: $template_type\n", FILE_APPEND);
        $_SESSION['success_message'] = "Template deleted successfully!";
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $_SESSION['success_message']]);
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors[] = "Error deleting template: " . $e->getMessage();
        file_put_contents($log_file, "Delete error: " . $e->getMessage() . "\n", FILE_APPEND);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
    }
    exit;
}

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorities Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/content.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Fallback for jQuery if CDN fails
        if (typeof jQuery === 'undefined') {
            document.write('<script src="/Allyted%20Project/js/jquery-3.6.0.min.js"><\/script>');
        }
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            margin: 0;
            padding-top: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 60px;
        }
        .header h2 {
            font-size: 1.8rem;
            color: #1f2937;
        }
        .add-btn {
            background: #0656ad;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .add-btn:hover {
            background: #053f7e;
            transform: translateY(-2px);
        }
        .add-template-btn {
            background: #22c55e;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 36px;
        }
        .add-template-btn:hover {
            background: #16a34a;
            transform: translateY(-2px);
        }
        .remove-btn {
            background: #ef4444;
            color: #ffffff;
            padding: 5px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-left: 10px;
        }
        .remove-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
        .authorities-container {
            position: relative;
            width: fit-content;
            margin: 0 auto;
        }
        .authorities-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            background: #ffffff;
            color: #1f2937;
            font-size: 14px;
            table-layout: fixed;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }
        .authorities-table th {
            background: #2f3640;
            color: #ffffffff;
            font-weight: 500;
            padding: 12px 8px;
            text-align: center;
            border-bottom: 2px solid #1f2937;
            white-space: nowrap;
        }
        .authorities-table td {
            background: #ffffff;
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .authorities-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .authorities-table tr:hover {
            background: #f1f5f9;
        }
        .authorities-table tr:first-child th:first-child {
            border-top-left-radius: 8px;
        }
        .authorities-table tr:first-child th:last-child {
            border-top-right-radius: 8px;
        }
        .authorities-table tr:last-child td:first-child {
            border-bottom-left-radius: 8px;
        }
        .authorities-table tr:last-child td:last-child {
            border-bottom-right-radius: 8px;
        }
        .sno-cell {
            width: 50px;
        }
        .template-cell,
        .level1-cell,
        .level2-cell,
        .action-cell {
            width: 120px;
        }
        .pagination-row td {
            padding: 10px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .page-btn {
            width: 32px;
            height: 32px;
            line-height: 32px;
            border: 2px solid #0656ad;
            background: #0656ad;
            color: #ffffff;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            transition: background 0.3s ease, transform 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .page-btn:hover:not(:disabled) {
            background: #053f7e;
            transform: scale(1.1);
        }
        .page-btn.active {
            background: #053f7e;
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
        }
        .page-btn:disabled {
            border-color: #6b7280;
            background: transparent;
            color: #6b7280;
            box-shadow: none;
            cursor: not-allowed;
        }
        .page-btn.nav-btn {
            background: transparent;
            color: #0656ad;
            font-size: 1rem;
            font-weight: 600;
            width: 48px;
        }
        .page-btn.nav-btn:hover:not(:disabled) {
            background: transparent;
            color: #053f7e;
            transform: scale(1.1);
        }
        .page-btn.nav-btn:disabled {
            border-color: #6b7280;
            color: #6b7280;
            box-shadow: none;
            cursor: not-allowed;
        }
        .action-btn {
            padding: 0;
            width: 32px;
            height: 32px;
            line-height: 32px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            transition: background 0.3s ease, transform 0.2s ease;
            margin: 0 5px;
        }
        .edit-btn {
            background: #f59e0b;
            color: #ffffff;
        }
        .delete-btn {
            background: #ef4444;
            color: #ffffff;
        }
        .edit-btn:hover {
            background: #d97706;
            transform: scale(1.1);
        }
        .delete-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            width: 90%;
            max-width: 700px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            padding: 20px;
            position: relative;
            animation: fadeIn 0.3s ease-in-out;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
        }
        .modal-content h3 {
            margin: 0 0 15px;
            padding: 12px;
            color: #ffffff;
            font-size: 1.4rem;
            font-weight: 700;
            text-align: center;
            background: #0656ad;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .modal-content .close {
            position: absolute;
            top: 12px;
            right: 15px;
            font-size: 1.5rem;
            color: #1f2937;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .modal-content .close:hover {
            color: #ef4444;
        }
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            height: 38px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #ffffff;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .level-box.level1 .form-group select:focus,
        .level-box.level1 .form-group input:focus {
            border-color: #0656ad;
            box-shadow: 0 0 0 2px rgba(6, 86, 173, 0.2);
        }
        .level-box.level2 .form-group select:focus,
        .level-box.level2 .form-group input:focus {
            border-color: #053f7e;
            box-shadow: 0 0 0 2px rgba(5, 63, 126, 0.2);
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            padding-right: 28px;
        }
        .level-box {
            border: 1px solid #a8c7f7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            background: #e6f0ff;
        }
        .level-box.level1 {
            background: rgba(6, 86, 173, 0.1);
        }
        .level-box.level2 {
            background: rgba(5, 63, 126, 0.1);
        }
        .level-box h4 {
            grid-column: 1 / -1;
            margin: 0 0 12px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .level-box.level1 h4 {
            color: #0656ad;
        }
        .level-box.level2 h4 {
            color: #053f7e;
        }
        .employee-id-display {
            font-size: 0.9rem;
            color: #555;
            font-weight: normal;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 20px;
        }
        .modal-content button[type="submit"] {
            background: #0656ad;
            color: #ffffff;
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            position: relative;
            top: -65px;
        }
        .modal-content button[type="submit"]:hover {
            background: #053f7e;
            transform: translateY(-2px);
        }
        .success-popup, .error-popup, .submitting-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
        }
        .success-popup {
            background: #22c55e;
        }
        .error-popup {
            background: #ef4444;
        }
        .submitting-popup {
            background: #6b7280;
        }
        .success-popup .close-popup, .error-popup .close-popup, .submitting-popup .close-popup {
            cursor: pointer;
            font-size: 1rem;
        }
        .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            animation: progress 5s linear forwards;
        }
        .template-forms-wrapper {
            max-height: 400px;
            overflow-y: auto;
            scroll-snap-type: y mandatory;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        .template-form {
            scroll-snap-align: start;
            padding: 10px 0;
            min-height: 400px;
        }
        .template-form:not(:last-child) {
            margin-bottom: 20px;
        }
        .template-forms-wrapper::-webkit-scrollbar {
            width: 0;
            background: transparent;
        }
        .template-forms-wrapper {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @keyframes progress {
            from { width: 100%; }
            to { width: 0; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: none; }
        }
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .authorities-container {
                width: 100%;
            }
            .authorities-table th, .authorities-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            .sno-cell {
                width: 40px;
            }
            .template-cell,
            .level1-cell,
            .level2-cell,
            .action-cell {
                width: 100px;
            }
            .action-btn {
                width: 28px;
                height: 28px;
                font-size: 0.9rem;
            }
            .modal-content {
                width: 95%;
                padding: 15px;
            }
            .form-group input, .form-group select {
                height: 34px;
                font-size: 0.85rem;
            }
            .level-box {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
            .page-btn {
                width: 28px;
                height: 28px;
                line-height: 28px;
                font-size: 0.85rem;
            }
            .page-btn.nav-btn {
                width: 40px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Authorities Management</h2>
        <button class="add-btn" onclick="openModal('add')">+ Add Template</button>
        <?php if (!empty($success)): ?>
            <div class="success-popup">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
                <div class="progress-bar"></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-popup">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
                <div class="progress-bar"></div>
            </div>
        <?php endif; ?>
        <div class="submitting-popup" id="submittingPopup" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i> Submitting template...
            <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
            <div class="progress-bar"></div>
        </div>
    </div>

    <div class="authorities-container">
        <table class="authorities-table">
            <thead>
                <tr>
                    <th>SNO</th>
                    <th>TEMPLATE NAME</th>
                    <th>LV1 DEPARTMENT</th>
                    <th>LV1 ROLE</th>
                    <th>LV1 PERSON</th>
                    <th>LV2 DEPARTMENT</th>
                    <th>LV2 ROLE</th>
                    <th>LV2 PERSON</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody id="savedDataBody">
                <?php foreach ($authoritiesData as $authority): ?>
                    <tr data-template-type="<?= htmlspecialchars($authority['Template']) ?>">
                        <td class="sno-cell"><?= htmlspecialchars($authority['SNO']) ?></td>
                        <td class="template-cell"><?= htmlspecialchars($authority['Template']) ?></td>
                        <td class="level1-cell"><?= htmlspecialchars($authority['Department'] ?? '-') ?></td>
                        <td class="level1-cell"><?= htmlspecialchars($authority['Role'] ?? '-') ?></td>
                        <td class="level1-cell"><?= htmlspecialchars($authority['Person'] ?? '-') ?></td>
                        <td class="level2-cell"><?= htmlspecialchars($authority['Department_2'] ?? '-') ?></td>
                        <td class="level2-cell"><?= htmlspecialchars($authority['Role_2'] ?? '-') ?></td>
                        <td class="level2-cell"><?= htmlspecialchars($authority['Person_2'] ?? '-') ?></td>
                        <td class="action-cell">
                            <button class="action-btn edit-btn" onclick="openModal('edit', <?= htmlspecialchars(json_encode($authority)) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="deleteAuthority('<?= htmlspecialchars($authority['Template']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($total_records > 7): ?>
                    <tr class="pagination-row">
                        <td colspan="9">
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <button class="page-btn nav-btn" onclick="goToPage(<?= $page - 1 ?>)" <?= $page <= 1 ? 'disabled' : '' ?>>«</button>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <button class="page-btn <?= $i === $page ? 'active' : '' ?>" onclick="goToPage(<?= $i ?>)"><?= $i ?></button>
                                <?php endfor; ?>
                                <?php if ($total_records > 7 && $page < $total_pages): ?>
                                    <button class="page-btn nav-btn" onclick="goToPage(<?= $page + 1 ?>)" <?= $page >= $total_pages ? 'disabled' : '' ?>>»</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal" id="authoritiesModal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h3 id="modalTitle">Add New Template</h3>
            <form id="authoritiesForm" method="POST">
                <div class="template-forms-wrapper">
                    <div id="templateFormsContainer">
                        <div class="template-form" data-template-index="0">
                            <input type="hidden" name="templates[0][sno]" class="sno">
                            <div class="form-group">
                                <label for="template_name_0">Template Name</label>
                                <input type="text" name="templates[0][template_name]" class="template_name" id="template_name_0" placeholder="Enter template name" required>
                            </div>
                            <div class="level-box level1">
                                <h4>Level 1 <span class="employee-id-display level1_employee_id" id="level1_employee_id_0"></span></h4>
                                <div class="form-group">
                                    <label for="level1_department_0">Department</label>
                                    <select name="templates[0][level1_department]" class="level1_department" id="level1_department_0" onchange="updateRoles('level1', 0)" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departmentsData as $dept): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="level1_role_0">Role</label>
                                    <select name="templates[0][level1_role]" class="level1_role" id="level1_role_0" onchange="updatePersons('level1', 0)" required>
                                        <option value="">Select Role</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="level1_person_0">Person</label>
                                    <select name="templates[0][level1_person]" class="level1_person" id="level1_person_0" onchange="updateEmployeeIdDisplay('level1', 0)" required>
                                        <option value="">Select Person</option>
                                    </select>
                                </div>
                            </div>
                            <div class="level-box level2">
                                <h4>Level 2 <span class="employee-id-display level2_employee_id" id="level2_employee_id_0"></span></h4>
                                <div class="form-group">
                                    <label for="level2_department_0">Department</label>
                                    <select name="templates[0][level2_department]" class="level2_department" id="level2_department_0" onchange="updateRoles('level2', 0)">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departmentsData as $dept): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="level2_role_0">Role</label>
                                    <select name="templates[0][level2_role]" class="level2_role" id="level2_role_0" onchange="updatePersons('level2', 0)">
                                        <option value="">Select Role</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="level2_person_0">Person</label>
                                    <select name="templates[0][level2_person]" class="level2_person" id="level2_person_0" onchange="updateEmployeeIdDisplay('level2', 0)">
                                        <option value="">Select Person</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="add-template-btn" onclick="addTemplateForm()">+ Add Another Template</button>
                <div class="form-actions">
                    <button type="submit">Save Templates</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    try {
        const rolesData = <?php echo $rolesJson; ?>;
        const employeesData = <?php echo $employeesJson; ?>.map(employee => ({
            ...employee,
            employee_id: String(employee.employee_id).trim()
        }));
        const logFile = 'C:\\xampp\\htdocs\\Allyted Project\\admin\\debug.log';

        function logToFile(message) {
            if (typeof $ === 'undefined') {
                console.error('jQuery not loaded, cannot log to file');
                return;
            }
            $.ajax({
                url: '/Allyted%20Project/includes/log_to_file.php',
                type: 'POST',
                data: { message: message },
                async: false,
                error: function(xhr, status, error) {
                    console.error("Log AJAX error:", { status, error, responseText: xhr.responseText });
                }
            });
        }

        function showSubmittingPopup() {
            const popup = document.getElementById('submittingPopup');
            if (popup) {
                popup.style.display = 'flex';
            }
        }

        function hideSubmittingPopup() {
            const popup = document.getElementById('submittingPopup');
            if (popup) {
                popup.style.display = 'none';
            }
        }

        function updateEmployeeIdDisplay(level, index) {
            const personSelect = document.getElementById(`${level}_person_${index}`);
            const employeeIdSpan = document.getElementById(`${level}_employee_id_${index}`);
            if (!personSelect || !employeeIdSpan) {
                console.error(`Elements not found for ${level}, index ${index}:`, { personSelect, employeeIdSpan });
                logToFile(`Error: Elements not found for ${level}, index ${index} in updateEmployeeIdDisplay`);
                return;
            }
            const selectedId = personSelect.value;
            employeeIdSpan.textContent = selectedId ? `(ID: ${selectedId})` : '';
            logToFile(`Updated ${level} employee ID display for index ${index}: ${selectedId || 'none'}`);
        }

        function updateRoles(level, index) {
            const deptSelect = document.getElementById(`${level}_department_${index}`);
            const roleSelect = document.getElementById(`${level}_role_${index}`);
            const personSelect = document.getElementById(`${level}_person_${index}`);
            const employeeIdSpan = document.getElementById(`${level}_employee_id_${index}`);
            const deptId = deptSelect ? parseInt(deptSelect.value) : '';

            if (!roleSelect || !personSelect || !employeeIdSpan) {
                console.error(`Elements not found for ${level}, index ${index}:`, { roleSelect, personSelect, employeeIdSpan });
                logToFile(`Error: Role, Person select, or Employee ID span not found for ${level}, index ${index}`);
                return;
            }

            roleSelect.innerHTML = '<option value="">Select Role</option>';
            personSelect.innerHTML = '<option value="">Select Person</option>';
            employeeIdSpan.textContent = '';

            if (deptId && rolesData.length > 0) {
                const filteredRoles = rolesData.filter(role => parseInt(role.department_id) === deptId);
                logToFile(`Filtered roles for ${level}, index ${index}: ${JSON.stringify(filteredRoles)}`);
                filteredRoles.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = role.name || 'Unnamed Role';
                    roleSelect.appendChild(option);
                });
            }
        }

        function updatePersons(level, index) {
            const deptSelect = document.getElementById(`${level}_department_${index}`);
            const roleSelect = document.getElementById(`${level}_role_${index}`);
            const personSelect = document.getElementById(`${level}_person_${index}`);
            const employeeIdSpan = document.getElementById(`${level}_employee_id_${index}`);
            const deptId = deptSelect ? parseInt(deptSelect.value) : '';
            const roleId = roleSelect ? parseInt(roleSelect.value) : '';

            if (!personSelect || !employeeIdSpan) {
                console.error(`Person select or Employee ID span not found for ${level}, index ${index}`);
                logToFile(`Error: Person select or Employee ID span not found for ${level}, index ${index}`);
                return;
            }

            personSelect.innerHTML = '<option value="">Select Person</option>';
            employeeIdSpan.textContent = '';

            if (deptId && roleId && employeesData.length > 0) {
                const filteredEmployees = employeesData.filter(employee => 
                    parseInt(employee.department_id) === deptId && parseInt(employee.role_id) === roleId
                );
                logToFile(`Filtered employees for ${level}, index ${index}: ${JSON.stringify(filteredEmployees)}`);
                if (filteredEmployees.length === 0) {
                    console.warn(`No employees found for ${level}, index ${index} with deptId=${deptId}, roleId=${roleId}`);
                    logToFile(`No employees found for ${level}, index ${index} with deptId=${deptId}, roleId=${roleId}`);
                    alert(`No active employees found for the selected ${level} department and role in template ${index}.`);
                }
                filteredEmployees.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.employee_id;
                    option.textContent = employee.full_name || 'Unnamed Employee';
                    personSelect.appendChild(option);
                });
            } else {
                logToFile(`No employees filtered for ${level}, index ${index}: deptId=${deptId}, roleId=${roleId}, employeesDataLength=${employeesData.length}`);
            }
        }

        function addTemplateForm() {
            const container = document.getElementById('templateFormsContainer');
            const templateCount = container.getElementsByClassName('template-form').length;
            const newTemplate = document.getElementsByClassName('template-form')[0].cloneNode(true);
            newTemplate.dataset.templateIndex = templateCount;

            newTemplate.querySelector('.sno').name = `templates[${templateCount}][sno]`;
            newTemplate.querySelector('.sno').value = '';
            newTemplate.querySelector('.template_name').name = `templates[${templateCount}][template_name]`;
            newTemplate.querySelector('.template_name').value = '';
            newTemplate.querySelector('.template_name').id = `template_name_${templateCount}`;
            newTemplate.querySelector('.level1_department').name = `templates[${templateCount}][level1_department]`;
            newTemplate.querySelector('.level1_department').value = '';
            newTemplate.querySelector('.level1_department').id = `level1_department_${templateCount}`;
            newTemplate.querySelector('.level1_department').setAttribute('onchange', `updateRoles('level1', ${templateCount})`);
            newTemplate.querySelector('.level1_role').name = `templates[${templateCount}][level1_role]`;
            newTemplate.querySelector('.level1_role').innerHTML = '<option value="">Select Role</option>';
            newTemplate.querySelector('.level1_role').id = `level1_role_${templateCount}`;
            newTemplate.querySelector('.level1_role').setAttribute('onchange', `updatePersons('level1', ${templateCount})`);
            newTemplate.querySelector('.level1_person').name = `templates[${templateCount}][level1_person]`;
            newTemplate.querySelector('.level1_person').innerHTML = '<option value="">Select Person</option>';
            newTemplate.querySelector('.level1_person').id = `level1_person_${templateCount}`;
            newTemplate.querySelector('.level1_person').setAttribute('onchange', `updateEmployeeIdDisplay('level1', ${templateCount})`);
            newTemplate.querySelector('.level1_employee_id').id = `level1_employee_id_${templateCount}`;
            newTemplate.querySelector('.level1_employee_id').textContent = '';
            newTemplate.querySelector('.level2_department').name = `templates[${templateCount}][level2_department]`;
            newTemplate.querySelector('.level2_department').value = '';
            newTemplate.querySelector('.level2_department').id = `level2_department_${templateCount}`;
            newTemplate.querySelector('.level2_department').setAttribute('onchange', `updateRoles('level2', ${templateCount})`);
            newTemplate.querySelector('.level2_role').name = `templates[${templateCount}][level2_role]`;
            newTemplate.querySelector('.level2_role').innerHTML = '<option value="">Select Role</option>';
            newTemplate.querySelector('.level2_role').id = `level2_role_${templateCount}`;
            newTemplate.querySelector('.level2_role').setAttribute('onchange', `updatePersons('level2', ${templateCount})`);
            newTemplate.querySelector('.level2_person').name = `templates[${templateCount}][level2_person]`;
            newTemplate.querySelector('.level2_person').innerHTML = '<option value="">Select Person</option>';
            newTemplate.querySelector('.level2_person').id = `level2_person_${templateCount}`;
            newTemplate.querySelector('.level2_person').setAttribute('onchange', `updateEmployeeIdDisplay('level2', ${templateCount})`);
            newTemplate.querySelector('.level2_employee_id').id = `level2_employee_id_${templateCount}`;
            newTemplate.querySelector('.level2_employee_id').textContent = '';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.innerHTML = '<i class="fas fa-minus"></i>';
            removeBtn.onclick = () => removeTemplateForm(templateCount);
            newTemplate.querySelector('.form-actions')?.remove();
            const formActions = document.createElement('div');
            formActions.className = 'form-actions';
            formActions.appendChild(removeBtn);
            newTemplate.appendChild(formActions);

            container.appendChild(newTemplate);
            toggleRemoveButtons();

            const wrapper = document.querySelector('.template-forms-wrapper');
            const newForm = container.querySelector(`.template-form[data-template-index="${templateCount}"]`);
            if (wrapper && newForm) {
                newForm.scrollIntoView({ behavior: 'smooth' });
            }

            logToFile(`Added new template form with index ${templateCount}`);
        }

        function removeTemplateForm(index) {
            const container = document.getElementById('templateFormsContainer');
            const templateForm = container.querySelector(`.template-form[data-template-index="${index}"]`);
            if (templateForm) {
                templateForm.remove();
                toggleRemoveButtons();
                logToFile(`Removed template form with index ${index}`);
            }
        }

        function toggleRemoveButtons() {
            const container = document.getElementById('templateFormsContainer');
            const templateForms = container.getElementsByClassName('template-form');
            for (let i = 0; i < templateForms.length; i++) {
                const removeBtn = templateForms[i].querySelector('.remove-btn');
                if (i === 0) {
                    if (removeBtn) removeBtn.remove();
                } else {
                    if (!removeBtn) {
                        const newRemoveBtn = document.createElement('button');
                        newRemoveBtn.type = 'button';
                        newRemoveBtn.className = 'remove-btn';
                        newRemoveBtn.innerHTML = '<i class="fas fa-minus"></i>';
                        newRemoveBtn.onclick = () => removeTemplateForm(i);
                        const formActions = templateForms[i].querySelector('.form-actions') || document.createElement('div');
                        if (!formActions.className) {
                            formActions.className = 'form-actions';
                            templateForms[i].appendChild(formActions);
                        }
                        formActions.appendChild(newRemoveBtn);
                    }
                }
            }
        }

        function openModal(mode, data = null) {
            console.log('openModal called with mode:', mode, 'data:', data);
            logToFile('openModal called with mode: ' + mode + ', data: ' + JSON.stringify(data));
            const modal = document.getElementById('authoritiesModal');
            const container = document.getElementById('templateFormsContainer');
            const modalTitle = document.getElementById('modalTitle');
            const wrapper = document.querySelector('.template-forms-wrapper');

            if (!modal || !container || !modalTitle) {
                console.error('Modal elements not found:', { modal, container, modalTitle });
                logToFile('Error: Modal elements not found in openModal');
                return;
            }

            while (container.getElementsByClassName('template-form').length > 1) {
                container.removeChild(container.lastChild);
            }
            const form = container.getElementsByClassName('template-form')[0];
            form.querySelector('.sno').value = '';
            form.querySelector('.template_name').value = '';
            form.querySelector('.level1_department').value = '';
            form.querySelector('.level1_role').innerHTML = '<option value="">Select Role</option>';
            form.querySelector('.level1_person').innerHTML = '<option value="">Select Person</option>';
            form.querySelector('.level1_employee_id').textContent = '';
            form.querySelector('.level2_department').value = '';
            form.querySelector('.level2_role').innerHTML = '<option value="">Select Role</option>';
            form.querySelector('.level2_person').innerHTML = '<option value="">Select Person</option>';
            form.querySelector('.level2_employee_id').textContent = '';

            if (mode === 'edit' && data) {
                modalTitle.innerText = 'Edit Template';
                form.querySelector('.sno').value = data.SNO || '';
                form.querySelector('.template_name').value = data.Template || '';
                form.querySelector('.level1_department').value = data.Department || '';
                updateRoles('level1', 0);
                form.querySelector('.level1_role').value = data.Role || '';
                updatePersons('level1', 0);
                form.querySelector('.level1_person').value = data.Person || '';
                form.querySelector('.level1_employee_id').textContent = data.Person ? `(ID: ${data.Person})` : '';
                form.querySelector('.level2_department').value = data.Department_2 || '';
                updateRoles('level2', 0);
                form.querySelector('.level2_role').value = data.Role_2 || '';
                updatePersons('level2', 0);
                form.querySelector('.level2_person').value = data.Person_2 || '';
                form.querySelector('.level2_employee_id').textContent = data.Person_2 ? `(ID: ${data.Person_2})` : '';
                logToFile(`Edit mode: Set Level 1 ID to ${data.Person || 'none'}, Level 2 ID to ${data.Person_2 || 'none'}`);
            } else {
                modalTitle.innerText = 'Add New Template';
            }

            toggleRemoveButtons();
            modal.style.display = 'flex';
            console.log('Modal display set to flex');
            logToFile('Modal display set to flex');

            if (wrapper) {
                wrapper.scrollTop = 0;
            }
        }

        function deleteAuthority(template_type) {
            if (!confirm('Are you sure you want to delete this template?')) {
                return;
            }
            showSubmittingPopup();
            $.ajax({
                url: '/Allyted%20Project/includes/content_templates.php',
                type: 'POST',
                data: { ajax_delete_authority: 1, template_type: template_type },
                dataType: 'json',
                success: function(response) {
                    hideSubmittingPopup();
                    console.log("Delete response:", response);
                    logToFile("Delete response: " + JSON.stringify(response));
                    if (response.success) {
                        document.querySelector(`tr[data-template-type="${template_type}"]`).remove();
                        alert('Template deleted successfully!');
                        window.location.reload();
                    } else {
                        alert('Error deleting template:\n' + (response.errors ? response.errors.join('\n') : response.message || 'An unexpected error occurred.'));
                    }
                },
                error: function(xhr, status, error) {
                    hideSubmittingPopup();
                    console.error("Delete AJAX error:", { status, error, responseText: xhr.responseText });
                    logToFile("Delete AJAX error: status=" + status + ", error=" + error + ", responseText=" + xhr.responseText);
                    let errorMsg = 'Failed to delete template: ';
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        errorMsg += jsonResponse.error || jsonResponse.message || 'Server error: ' + xhr.status;
                        if (jsonResponse.errors) {
                            errorMsg = jsonResponse.errors.join('\n');
                        }
                    } catch (e) {
                        errorMsg += 'Server error: ' + xhr.status + ' (Invalid response: ' + xhr.responseText + ')';
                    }
                    alert(errorMsg);
                }
            });
        }

        function handleFormSubmit(e) {
            e.preventDefault();
            const container = document.getElementById('templateFormsContainer');
            const templateForms = container.getElementsByClassName('template-form');
            const templates = [];

            for (let i = 0; i < templateForms.length; i++) {
                const form = templateForms[i];
                const templateName = form.querySelector('.template_name').value.trim();
                const level1Person = form.querySelector('.level1_person').value.trim();
                const level2Department = form.querySelector('.level2_department').value.trim();
                const level2Role = form.querySelector('.level2_role').value.trim();
                const level2Person = form.querySelector('.level2_person').value.trim();

                if (!templateName) {
                    alert(`Template name is required for template ${i}.`);
                    logToFile(`Client validation failed: Empty template name for template ${i}`);
                    return;
                }
                if (!level1Person) {
                    alert(`Level 1 Person is required for template ${i}.`);
                    logToFile(`Client validation failed: Empty Level 1 Person for template ${i}`);
                    return;
                }
                if (level2Department || level2Role || level2Person) {
                    if (!level2Department || !level2Role || !level2Person) {
                        alert(`All Level 2 fields (Department, Role, Person) must be provided together or all left empty for template ${i}.`);
                        logToFile(`Client validation failed: Incomplete Level 2 fields for template ${i}`);
                        return;
                    }
                }

                templates.push({
                    sno: form.querySelector('.sno').value,
                    template_name: templateName,
                    level1_department: form.querySelector('.level1_department').value,
                    level1_role: form.querySelector('.level1_role').value,
                    level1_person: level1Person,
                    level2_department: level2Department,
                    level2_role: level2Role,
                    level2_person: level2Person
                });
            }

            const formData = new FormData();
            formData.append('ajax_save_authorities', '1');
            formData.append('templates', JSON.stringify(templates));

            console.log("FormData entries:", templates);
            logToFile("FormData entries: " + JSON.stringify(templates));

            if (!confirm('Are you sure you want to save these templates?')) {
                return;
            }

            showSubmittingPopup();
            $.ajax({
                url: '/Allyted%20Project/includes/content_templates.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    hideSubmittingPopup();
                    console.log("Save response:", response);
                    logToFile("Save response: " + JSON.stringify(response));
                    if (response.success) {
                        alert('Templates saved successfully!');
                        window.location.reload();
                    } else {
                        alert('Error saving templates:\n' + (response.errors ? response.errors.join('\n') : response.message || 'An unexpected error occurred.'));
                    }
                },
                error: function(xhr, status, error) {
                    hideSubmittingPopup();
                    console.error("Save AJAX error:", { status, error, responseText: xhr.responseText });
                    logToFile("Save AJAX error: status=" + status + ", error=" + error + ", responseText=" + xhr.responseText);
                    let errorMsg = 'Failed to save templates: ';
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        errorMsg += jsonResponse.error || jsonResponse.message || 'Server error: ' + xhr.status;
                        if (jsonResponse.errors) {
                            errorMsg = jsonResponse.errors.join('\n');
                        }
                    } catch (e) {
                        errorMsg += 'Server error: ' + xhr.status + ' (Invalid response: ' + xhr.responseText + ')';
                    }
                    alert(errorMsg);
                }
            });
        }

        function goToPage(page) {
            if (page < 1 || page > <?= $total_pages ?>) return;
            showSubmittingPopup();
            window.location.href = `?page=${page}`;
        }

        window.onclick = function(e) {
            const modal = document.getElementById('authoritiesModal');
            if (e.target === modal) {
                closeModal();
            }
        };

        window.onload = function () {
            console.log('Window loaded, employeesData length:', employeesData.length);
            logToFile('Window loaded, employeesData length: ' + employeesData.length);
            const form = document.getElementById('authoritiesForm');
            if (form) {
                form.addEventListener('submit', handleFormSubmit);
            } else {
                console.error('Authorities form not found');
                logToFile('Error: Authorities form not found');
            }
            if (document.querySelector('.success-popup') || document.querySelector('.error-popup')) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => {
                    const popups = document.querySelectorAll('.success-popup, .error-popup');
                    popups.forEach(popup => popup.style.display = 'none');
                }, 5000);
            }
        };
    } catch (e) {
        console.error('Script error:', e);
        logToFile('Script error: ' + e.message);
    }
    </script>
</body>
</html>
<?php
$departments->free();
$roles->free();
$employees->free();
$authorities->free();
$mysqli->close();
?>