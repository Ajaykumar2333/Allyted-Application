<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// Start session and log status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Session started, ID: " . session_id());
} else {
    error_log("Session already active, ID: " . session_id());
}

try {
    require_once 'db/config.php';

    // Generate a session token for AJAX requests
    if (!isset($_SESSION['ajax_token'])) {
        try {
            $_SESSION['ajax_token'] = bin2hex(random_bytes(16));
            error_log("Generated ajax_token: " . $_SESSION['ajax_token']);
        } catch (Exception $e) {
            error_log("Token generation failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error']);
            exit;
        }
    }
    $ajax_token = $_SESSION['ajax_token'];

    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        ob_start();
        $output = ob_get_contents();
        if (!empty($output)) {
            error_log("Unexpected output before JSON: " . $output);
        }
        ob_clean();
        header('Content-Type: application/json');

        if ($_POST['action'] === 'save_authority') {
            error_log("Received save_authority POST: " . print_r($_POST, true));
            $response = ['success' => false, 'message' => ''];

            // Validate AJAX token
            if (!isset($_POST['ajax_token']) || $_POST['ajax_token'] !== $_SESSION['ajax_token']) {
                $response['message'] = "Invalid AJAX token";
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            $template_type = trim($_POST['template_type'] ?? '');
            $level_1_department_id = !empty($_POST['level_1_department_id']) ? intval($_POST['level_1_department_id']) : null;
            $level_1_role_id = !empty($_POST['level_1_role_id']) ? intval($_POST['level_1_role_id']) : null;
            $level_1_employee_id = !empty($_POST['level_1_employee_id']) ? trim($_POST['level_1_employee_id']) : null;
            $level_2_department_id = !empty($_POST['level_2_department_id']) ? intval($_POST['level_2_department_id']) : null;
            $level_2_role_id = !empty($_POST['level_2_role_id']) ? intval($_POST['level_2_role_id']) : null;
            $level_2_employee_id = !empty($_POST['level_2_reporting_user_id']) ? trim($_POST['level_2_reporting_user_id']) : null;

            // Validate required fields
            $validation_errors = [];
            if (empty($template_type) || $template_type === '0') {
                $validation_errors[] = "Valid template type is required.";
            }
            if (empty($level_1_department_id) || empty($level_1_role_id) || empty($level_1_employee_id)) {
                $validation_errors[] = "Level 1 department, role, and employee are required.";
            }

            // Check for duplicate employee IDs across levels
            $employee_ids = array_filter([$level_1_employee_id, $level_2_employee_id]);
            if (count($employee_ids) > count(array_unique($employee_ids))) {
                $response['message'] = "Duplicate employee IDs selected across levels.";
                error_log("Duplicate employee IDs: " . json_encode($employee_ids));
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            // Validate employee IDs
            if (!empty($employee_ids)) {
                $placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
                $stmt = $mysqli->prepare("SELECT employee_id FROM employee_details WHERE employee_id IN ($placeholders) AND is_active = 1");
                if (!$stmt) {
                    $response['message'] = "Database error: " . $mysqli->error;
                    error_log("Prepare employee validation failed: " . $mysqli->error);
                    echo json_encode($response);
                    ob_end_flush();
                    exit;
                }
                $stmt->bind_param(str_repeat('s', count($employee_ids)), ...$employee_ids);
                $stmt->execute();
                $result = $stmt->get_result();
                $valid_employees = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $valid_employee_ids = array_column($valid_employees, 'employee_id');
                foreach ($employee_ids as $emp_id) {
                    if (!in_array($emp_id, $valid_employee_ids)) {
                        $response['message'] = "Invalid employee ID for template $template_type: $emp_id. Ensure the employee exists and is active.";
                        error_log("Invalid employee ID: $emp_id");
                        echo json_encode($response);
                        ob_end_flush();
                        exit;
                    }
                }
            }

            if (!empty($validation_errors)) {
                $response['message'] = "Validation failed: " . implode(", ", $validation_errors);
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            // Start transaction for Level 1 and Level 2
            $mysqli->begin_transaction();

            try {
                // Save Level 1
                $stmt = $mysqli->prepare("SELECT template_type FROM level_1_authorities WHERE template_type = ?");
                $stmt->bind_param("s", $template_type);
                $stmt->execute();
                $exists_level_1 = $stmt->fetch();
                $stmt->close();

                if ($exists_level_1) {
                    $stmt = $mysqli->prepare("
                        UPDATE level_1_authorities 
                        SET department_id = ?, role_id = ?, employee_id = ?, is_saved = TRUE
                        WHERE template_type = ?
                    ");
                    $stmt->bind_param("iiss", $level_1_department_id, $level_1_role_id, $level_1_employee_id, $template_type);
                } else {
                    $stmt = $mysqli->prepare("
                        INSERT INTO level_1_authorities (template_type, department_id, role_id, employee_id, is_saved, created_at)
                        VALUES (?, ?, ?, ?, TRUE, NOW())
                    ");
                    $stmt->bind_param("siis", $template_type, $level_1_department_id, $level_1_role_id, $level_1_employee_id);
                }
                if (!$stmt->execute()) {
                    throw new Exception("Error saving Level 1: " . $stmt->error);
                }
                $stmt->close();

                // Save Level 2 (if provided)
                if ($level_2_department_id || $level_2_role_id || $level_2_employee_id) {
                    $stmt = $mysqli->prepare("SELECT id FROM level_2_authorities WHERE template_type = ?");
                    $stmt->bind_param("s", $template_type);
                    $stmt->execute();
                    $exists_level_2 = $stmt->fetch();
                    $stmt->close();

                    if ($exists_level_2) {
                        $stmt = $mysqli->prepare("
                            UPDATE level_2_authorities 
                            SET department_id = ?, role_id = ?, employee_id = ?, is_saved = TRUE
                            WHERE template_type = ?
                        ");
                        $stmt->bind_param("iiss", $level_2_department_id, $level_2_role_id, $level_2_employee_id, $template_type);
                    } else {
                        $stmt = $mysqli->prepare("
                            INSERT INTO level_2_authorities (template_type, department_id, role_id, employee_id, is_saved, created_at)
                            VALUES (?, ?, ?, ?, TRUE, NOW())
                        ");
                        $stmt->bind_param("siis", $template_type, $level_2_department_id, $level_2_role_id, $level_2_employee_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception("Error saving Level 2: " . $stmt->error);
                    }
                    $stmt->close();
                }

                $mysqli->commit();
                $response['success'] = true;
                $response['message'] = "Authority saved successfully!";
                error_log("Authority saved for template_type: $template_type");
            } catch (Exception $e) {
                $mysqli->rollback();
                $response['message'] = "Error saving authority: " . $e->getMessage();
                error_log("Error saving authority: " . $e->getMessage());
            }

            echo json_encode($response);
            ob_end_flush();
            exit;
        } elseif ($_POST['action'] === 'get_authorities') {
            error_log("Fetching authorities, session token: " . ($_SESSION['ajax_token'] ?? 'undefined'));
            if (!isset($_POST['ajax_token']) || $_POST['ajax_token'] !== $_SESSION['ajax_token']) {
                error_log("Invalid AJAX token in get_authorities");
                echo json_encode(['success' => false, 'message' => 'Invalid AJAX token']);
                ob_end_flush();
                exit;
            }

            $result = $mysqli->query("
                SELECT l1.template_type, 
                       d1.name AS level_1_dept, r1.name AS level_1_role, CONCAT(e1.first_name, ' ', e1.last_name) AS level_1_user,
                       d2.name AS level_2_dept, r2.name AS level_2_role, CONCAT(e2.first_name, ' ', e2.last_name) AS level_2_user
                FROM level_1_authorities l1
                LEFT JOIN departments d1 ON l1.department_id = d1.id
                LEFT JOIN roles r1 ON l1.role_id = r1.id
                LEFT JOIN employee_details e1 ON l1.employee_id = e1.employee_id
                LEFT JOIN level_2_authorities l2 ON l1.template_type = l2.template_type
                LEFT JOIN departments d2 ON l2.department_id = d2.id
                LEFT JOIN roles r2 ON l2.role_id = r2.id
                LEFT JOIN employee_details e2 ON l2.employee_id = e2.employee_id
                WHERE l1.is_saved = TRUE
            ");
            if (!$result) {
                error_log("Get authorities failed: " . $mysqli->error);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $mysqli->error]);
                ob_end_flush();
                exit;
            }
            $authorities = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Authorities fetched, rows: " . count($authorities));
            echo json_encode(['success' => true, 'data' => $authorities]);
            ob_end_flush();
            exit;
        }
    }

    // Handle GET AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        ob_start();
        header('Content-Type: application/json');

        if ($_GET['action'] === 'get_roles_by_department') {
            error_log("Fetching roles for department: " . ($_GET['department_id'] ?? 'undefined'));
            $department_id = intval($_GET['department_id']);
            $stmt = $mysqli->prepare("SELECT id, name FROM roles WHERE department_id = ?");
            if (!$stmt) {
                error_log("Prepare failed for roles: " . $mysqli->error);
                echo json_encode(['error' => 'Database error']);
                ob_end_flush();
                exit;
            }
            $stmt->bind_param("i", $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $roles = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Roles fetched for department $department_id, count: " . count($roles));
            echo json_encode($roles);
            $stmt->close();
            ob_end_flush();
            exit;
        } elseif ($_GET['action'] === 'get_employees_by_department_and_role') {
            error_log("Fetching employees for department: " . ($_GET['department_id'] ?? 'undefined') . ", role: " . ($_GET['role_id'] ?? 'undefined'));
            $department_id = intval($_GET['department_id']);
            $role_id = intval($_GET['role_id']);
            $stmt = $mysqli->prepare("
                SELECT DISTINCT employee_id, CONCAT(first_name, ' ', last_name) AS name 
                FROM employee_details 
                WHERE department_id = ? AND role_id = ? AND is_active = 1
            ");
            if (!$stmt) {
                error_log("Prepare failed for employees: " . $mysqli->error);
                echo json_encode(['error' => 'Database error']);
                ob_end_flush();
                exit;
            }
            $stmt->bind_param("ii", $department_id, $role_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $employees = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Employees fetched for department $department_id, role $role_id, count: " . count($employees));
            echo json_encode($employees);
            $stmt->close();
            ob_end_flush();
            exit;
        }
    }

    // Fetch all departments
    $departments = [];
    $departments_error = '';
    $departments_result = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
    if ($departments_result) {
        $departments = $departments_result->fetch_all(MYSQLI_ASSOC);
        error_log("Departments fetched, count: " . count($departments));
    } else {
        $departments_error = "Error fetching departments: " . $mysqli->error;
        error_log("Departments query failed: " . $mysqli->error);
    }

    $success = $_SESSION['success_message'] ?? '';
    $error = $_SESSION['error_message'] ?? '';
    unset($_SESSION['success_message'], $_SESSION['error_message']);
    error_log("Rendered ajax_token: " . $ajax_token);
} catch (Exception $e) {
    error_log("Fatal error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>

<link rel="stylesheet" href="css/content.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
/* Minimal inline styles for popup and form */
.popup {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    border: 1px solid #e0e0e0;
}

.popup-content {
    position: relative;
    text-align: left;
}

.popup-content .close {
    position: absolute;
    top: -8px;
    right: 4px;
    font-size: 22px;
    cursor: pointer;
    color: #333;
}

.popup-content h3 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
}

.popup-content .form-group {
    margin-bottom: 10px;
}

.popup-content .form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin-bottom: 5px;
}

.popup-content select, .popup-content input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #f9f9f9;
    font-size: 14px;
    outline: none;
}

.popup-content select:focus, .popup-content input:focus {
    border-color: #0656ad;
}

.popup-content select:disabled {
    background: #e9ecef;
    cursor: not-allowed;
}

.popup-content .level-section {
    margin: 10px 0;
    padding: 8px;
    border-left: 2px solid #0656ad;
}

.popup-content .level-section h4 {
    font-size: 15px;
    font-weight: 600;
    color: #0656ad;
    margin-bottom: 8px;
}

.popup-content .form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.popup-content .error-message {
    color: #dc3545;
    font-size: 13px;
    margin-bottom: 10px;
}

.popup-content .submit-btn {
    width: 100%;
    background: #0656ad;
    padding: 8px;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    margin-top: 10px;
}

.popup-content .submit-btn:hover {
    background: #0056b3;
}

.popup-content .submit-btn.loading .spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #fff;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 0.5s linear infinite;
    margin-right: 5px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

table .level-group {
    background: #2f364c;
}

.button-container {
    text-align: left;
    margin-top: 10px;
}
</style>

<div class="header">
    <h2>Authorities</h2>
    <button class="add-btn" id="addAuthorityBtn">+ Add Authorities</button>
    <?php if (!empty($success)): ?>
        <div class="success-popup">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <span class="close-popup">×</span>
            <div class="progress-bar"></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="error-popup">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <span class="close-popup">×</span>
            <div class="progress-bar"></div>
        </div>
    <?php endif; ?>
</div>

<table id="authoritiesTable">
    <thead>
        <tr>
            <th rowspan="2">Template</th>
            <th colspan="3" class="level-group">Level 1</th>
            <th colspan="3" class="level-group">Level 2</th>
        </tr>
        <tr>
            <th>Department</th>
            <th>Role</th>
            <th>User</th>
            <th>Department</th>
            <th>Role</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<div class="popup" id="authorityPopup">
    <div class="popup-content">
        <span class="close" onclick="closePopup()">×</span>
        <h3>Assign Authority</h3>
        <?php if ($departments_error): ?>
            <div class="error-message"><?= htmlspecialchars($departments_error) ?></div>
        <?php endif; ?>
        <form id="authorityForm" method="POST">
            <input type="hidden" name="action" value="save_authority">
            <input type="hidden" id="ajax_token" name="ajax_token" value="<?= htmlspecialchars($ajax_token) ?>">
            <div class="form-group">
                <label for="template_type">Template <span style="color: #dc3545">*</span></label>
                <input type="text" name="template_type" id="template_type" required placeholder="Enter template type">
            </div>

            <div class="level-section">
                <h4>Level 1 (Role)</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="level_1_department_id">Department</label>
                        <select name="level_1_department_id" id="level_1_department_id" onchange="updateDropdowns('level_1')" required>
                            <option value="">Select Department</option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= $department['id'] ?>"><?= htmlspecialchars($department['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No departments</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level_1_role_id">Role</label>
                        <select name="level_1_role_id" id="level_1_role_id" disabled required>
                            <option value="">Select Role</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level_1_employee_id">Name</label>
                        <select name="level_1_employee_id" id="level_1_employee_id" disabled required>
                            <option value="">Select User</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="level-section">
                <h4>Level 2</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="level_2_department_id">Department</label>
                        <select name="level_2_department_id" id="level_2_department_id" onchange="updateDropdowns('level_2')">
                            <option value="">Select Department</option>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= $department['id'] ?>"><?= htmlspecialchars($department['name']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No departments</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level_2_role_id">Role</label>
                        <select name="level_2_role_id" id="level_2_role_id" disabled>
                            <option value="">Select Role</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="level_2_reporting_user_id">Name</label>
                        <select name="level_2_reporting_user_id" id="level_2_reporting_user_id" disabled>
                            <option value="">Select User</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="button" class="submit-btn" onclick="submitForm()">
                <span class="spinner" style="display: none;"></span>
                Save Authority
            </button>
        </form>
    </div>
</div>

<script>
function openPopup() {
    console.log('openPopup called');
    const popup = document.getElementById('authorityPopup');
    if (popup) {
        popup.style.display = 'block';
        const form = document.getElementById('authorityForm');
        if (form) {
            form.reset();
            console.log('Form reset');
        } else {
            console.error('Form not found');
        }
        ['level_1', 'level_2'].forEach(level => {
            const roleSelect = document.getElementById(`${level}_role_id`);
            const userSelect = document.getElementById(`${level}_employee_id`) || document.getElementById(`${level}_reporting_user_id`);
            if (roleSelect) {
                roleSelect.innerHTML = '<option value="">Select Role</option>';
                roleSelect.disabled = true;
                console.log(`Cleared role select for ${level}`);
            }
            if (userSelect) {
                userSelect.innerHTML = '<option value="">Select User</option>';
                userSelect.disabled = true;
                console.log(`Cleared user select for ${level}`);
            }
        });
    } else {
        console.error('Popup not found');
    }
}

function closePopup() {
    console.log('closePopup called');
    const popup = document.getElementById('authorityPopup');
    if (popup) {
        popup.style.display = 'none';
        console.log('Popup closed');
    } else {
        console.error('Popup not found');
    }
}

function updateDropdowns(level) {
    console.log(`updateDropdowns called for ${level}`);
    const departmentId = document.getElementById(`${level}_department_id`).value;
    const roleSelect = document.getElementById(`${level}_role_id`);
    const userSelect = document.getElementById(`${level}_employee_id`) || document.getElementById(`${level}_reporting_user_id`);
    const token = document.getElementById('ajax_token').value;

    if (!roleSelect || !userSelect) {
        console.error(`Dropdowns for ${level} not found`);
        return;
    }

    roleSelect.innerHTML = '<option value="">Select Role</option>';
    userSelect.innerHTML = '<option value="">Select User</option>';
    roleSelect.disabled = true;
    userSelect.disabled = true;

    if (departmentId) {
        console.log(`Fetching roles for department_id: ${departmentId}, level: ${level}, token: ${token}`);
        fetch(`admin_dashboard.php?page=authorities&action=get_roles_by_department&department_id=${departmentId}&ajax_token=${token}`)
            .then(response => {
                console.log(`Roles response status for ${level}: ${response.status}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(roles => {
                if (roles.error) throw new Error(roles.error);
                if (roles.length === 0) {
                    roleSelect.innerHTML = '<option value="">No roles available</option>';
                } else {
                    roleSelect.disabled = false;
                    roles.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.name;
                        roleSelect.appendChild(option);
                    });
                    console.log(`Loaded ${roles.length} roles for ${level}`);
                }
            })
            .catch(error => {
                console.error(`Error fetching roles for ${level}: ${error.message}`);
                roleSelect.innerHTML = '<option value="">Error loading roles</option>';
            });
    }
}

function updateUserDropdown(level) {
    console.log(`updateUserDropdown called for ${level}`);
    const departmentId = document.getElementById(`${level}_department_id`).value;
    const roleId = document.getElementById(`${level}_role_id`).value;
    const userSelect = document.getElementById(`${level}_employee_id`) || document.getElementById(`${level}_reporting_user_id`);
    const token = document.getElementById('ajax_token').value;

    if (!userSelect) {
        console.error(`User dropdown for ${level} not found`);
        return;
    }

    userSelect.innerHTML = '<option value="">Select User</option>';
    userSelect.disabled = true;

    if (departmentId && roleId) {
        console.log(`Fetching employees for department_id: ${departmentId}, role_id: ${roleId}, level: ${level}, token: ${token}`);
        fetch(`admin_dashboard.php?page=authorities&action=get_employees_by_department_and_role&department_id=${departmentId}&role_id=${roleId}&ajax_token=${token}`)
            .then(response => {
                console.log(`Employees response status for ${level}: ${response.status}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(employees => {
                if (employees.error) throw new Error(employees.error);
                if (employees.length === 0) {
                    userSelect.innerHTML = '<option value="">No users available</option>';
                } else {
                    userSelect.disabled = false;
                    const uniqueEmployees = Array.from(new Set(employees.map(e => e.employee_id)))
                        .map(id => employees.find(e => e.employee_id === id));
                    uniqueEmployees.forEach(employee => {
                        const option = document.createElement('option');
                        option.value = employee.employee_id;
                        option.textContent = employee.name;
                        userSelect.appendChild(option);
                    });
                    console.log(`Loaded ${uniqueEmployees.length} employees for ${level}`);
                }
            })
            .catch(error => {
                console.error(`Error fetching employees for ${level}: ${error.message}`);
                userSelect.innerHTML = '<option value="">Error loading users</option>';
            });
    }
}

function loadAuthorities() {
    console.log('loadAuthorities called');
    const token = document.getElementById('ajax_token');
    if (!token || !token.value) {
        console.error('No AJAX token found');
        const tbody = document.querySelector('#authoritiesTable tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7">Error: No AJAX token</td></tr>';
        }
        return;
    }
    console.log('Loading authorities with token:', token.value);
    const tbody = document.querySelector('#authoritiesTable tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    }

    fetch('admin_dashboard.php?page=authorities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_authorities&ajax_token=${encodeURIComponent(token.value)}`
    })
        .then(response => {
            console.log('Authorities response status:', response.status);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('Authorities parsed data:', data);
            if (tbody) {
                tbody.innerHTML = '';
                if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                    data.data.forEach(row => {
                        console.log('Rendering row:', row);
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${row.template_type || '-'}</td>
                            <td>${row.level_1_dept || '-'}</td>
                            <td>${row.level_1_role || '-'}</td>
                            <td>${row.level_1_user || '-'}</td>
                            <td>${row.level_2_dept || '-'}</td>
                            <td>${row.level_2_role || '-'}</td>
                            <td>${row.level_2_user || '-'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    console.log(`Rendered ${data.data.length} rows`);
                } else {
                    console.log('No authorities found or invalid data:', data);
                    tbody.innerHTML = `<tr><td colspan="7">${data.message || 'No authorities found'}</td></tr>`;
                }
            }
        })
        .catch(error => {
            console.error('Error loading authorities:', error.message);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="7">Error loading data: ${error.message}</td></tr>`;
            }
        });
}

function submitForm() {
    console.log('submitForm called');
    const form = document.getElementById('authorityForm');
    const formData = new FormData(form);
    console.log('Form data:', Object.fromEntries(formData));
    const submitBtn = document.querySelector('.submit-btn');
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;

    fetch('admin_dashboard.php?page=authorities', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Save response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Save parsed JSON:', data);
            const header = document.querySelector('.header');
            if (data.success) {
                const successPopup = document.createElement('div');
                successPopup.className = 'success-popup';
                successPopup.innerHTML = `
                    <i class="fas fa-check-circle"></i> ${data.message}
                    <span class="close-popup">×</span>
                    <div class="progress-bar"></div>
                `;
                header.appendChild(successPopup);
                closePopup();
                loadAuthorities();
                setTimeout(() => {
                    successPopup.style.display = 'none';
                    successPopup.remove();
                }, 5000);
            } else {
                const errorPopup = document.createElement('div');
                errorPopup.className = 'error-popup';
                errorPopup.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i> ${data.message}
                    <span class="close-popup">×</span>
                    <div class="progress-bar"></div>
                `;
                header.appendChild(errorPopup);
                setTimeout(() => {
                    errorPopup.style.display = 'none';
                    errorPopup.remove();
                }, 5000);
            }
        })
        .catch(error => {
            console.error('Save fetch error:', error.message);
            const errorPopup = document.createElement('div');
            errorPopup.className = 'error-popup';
            errorPopup.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> An error occurred: ${error.message}
                <span class="close-popup">×</span>
                <div class="progress-bar"></div>
            `;
            document.querySelector('.header').appendChild(errorPopup);
            setTimeout(() => {
                errorPopup.style.display = 'none';
                errorPopup.remove();
            }, 5000);
        })
        .finally(() => {
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded fired');
    try {
        const addBtn = document.getElementById('addAuthorityBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                console.log('Add button clicked');
                openPopup();
            });
            console.log('Add button event listener bound');
        } else {
            console.error('Add button not found');
        }

        loadAuthorities();

        const level1Role = document.getElementById('level_1_role_id');
        const level2Role = document.getElementById('level_2_role_id');

        if (level1Role) {
            const newLevel1Role = level1Role.cloneNode(true);
            level1Role.parentNode.replaceChild(newLevel1Role, level1Role);
            newLevel1Role.addEventListener('change', () => updateUserDropdown('level_1'));
            console.log('Level 1 role event listener bound');
        }
        if (level2Role) {
            const newLevel2Role = level2Role.cloneNode(true);
            level2Role.parentNode.replaceChild(newLevel2Role, level2Role);
            newLevel2Role.addEventListener('change', () => updateUserDropdown('level_2'));
            console.log('Level 2 role event listener bound');
        }
    } catch (e) {
        console.error('Error in DOMContentLoaded:', e.message);
    }
});
</script>