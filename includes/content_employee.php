<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\Allyted Project\admin\php_errors.log');
error_reporting(E_ALL);

// Log script start
file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "admin_dashboard.php started: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

require_once 'C:\xampp\htdocs\Allyted Project\db\config.php';

// Check database connection
if (!$mysqli || $mysqli->connect_error) {
    $error_msg = "Database connection failed: " . ($mysqli ? $mysqli->connect_error : "No mysqli object");
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', $error_msg . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red;'>Database connection error. Check debug.log for details.</p>";
    exit;
}

// Check required tables
$required_tables = ['employee_details', 'departments', 'roles', 'locations', 'templates'];
foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Table $table does not exist at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        echo "<p style='color: red;'>Table $table does not exist. Please create the table.</p>";
        exit;
    }
    $result->free();
}

// Verify essential columns for employee_details
$essential_columns = ['employee_id', 'full_name', 'email', 'phone', 'date_of_joining', 'department_id', 'role_id', 'location_id', 'status'];
$missing_columns = [];
$result = $mysqli->query("SHOW COLUMNS FROM employee_details");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}
foreach ($essential_columns as $column) {
    if (!in_array($column, $existing_columns)) {
        $missing_columns[] = $column;
    }
}
$result->free();
if (!empty($missing_columns)) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Missing essential columns in employee_details: " . implode(', ', $missing_columns) . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    echo "<p style='color: red;'>Missing essential columns in employee_details: " . implode(', ', $missing_columns) . "</p>";
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Session start failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        echo "<p style='color: red;'>Session error.</p>";
        exit;
    }
}

// Handle employee deletion
if (isset($_GET['delete'])) {
    $employee_id = trim($_GET['delete']);
    if (empty($employee_id)) {
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Invalid employee ID provided for deletion: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $_SESSION['error_message'] = "Invalid employee ID provided.";
        header("Location: admin_dashboard.php?page=employee&tab=" . urlencode($_GET['tab'] ?? 'active') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''));
        exit;
    }

    try {
        $stmt = $mysqli->prepare("SELECT employee_id FROM employee_details WHERE employee_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for employee check: " . $mysqli->error);
        }
        $stmt->bind_param("s", $employee_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for employee check: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();

        if (!$employee) {
            file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Employee with ID $employee_id not found: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            $_SESSION['error_message'] = "Employee with ID $employee_id not found.";
            header("Location: admin_dashboard.php?page=employee&tab=" . urlencode($_GET['tab'] ?? 'active') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''));
            exit;
        }

        $mysqli->begin_transaction();
        $result = $mysqli->query("SHOW TABLES LIKE 'credentials'");
        if ($result->num_rows > 0) {
            $stmt = $mysqli->prepare("DELETE FROM credentials WHERE employee_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for credentials deletion: " . $mysqli->error);
            }
            $stmt->bind_param("s", $employee_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for credentials deletion: " . $stmt->error);
            }
            $stmt->close();
        }
        $result->free();

        $stmt = $mysqli->prepare("DELETE FROM employee_details WHERE employee_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for employee deletion: " . $mysqli->error);
        }
        $stmt->bind_param("s", $employee_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed for employee deletion: " . $stmt->error);
        }
        $stmt->close();

        $mysqli->commit();
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Employee $employee_id deleted successfully: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $_SESSION['success_message'] = "Employee deleted successfully!";
        header("Location: admin_dashboard.php?page=employee&tab=" . urlencode($_GET['tab'] ?? 'active') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''));
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Deletion error: " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $_SESSION['error_message'] = "Error deleting employee: " . $e->getMessage();
        header("Location: admin_dashboard.php?page=employee&tab=" . urlencode($_GET['tab'] ?? 'active') . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''));
        exit;
    }
}

// Set up pagination and filtering for Active/Inactive tabs
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['active', 'inactive']) ? $_GET['tab'] : 'active';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 8;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

$total = 0;
$total_pages = 1;
$employee_data = [];

try {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Attempting pagination query for tab: $tab, search: '$search' at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $count_query = "SELECT COUNT(*) as total FROM employee_details e LEFT JOIN roles r ON e.role_id = r.id WHERE ";
    $params = [];
    $types = '';
    
    $status_filter = $tab === 'active' ? 'Active' : 'Inactive';
    $count_query .= "e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
    
    if (!empty($search)) {
        $count_query .= " AND e.full_name LIKE ?";
        $search_param = "%$search%";
        $params[] = $search_param;
        $types .= 's';
    }
    
    $stmt = $mysqli->prepare($count_query);
    if (!$stmt) {
        throw new Exception("Prepare failed for pagination count: " . $mysqli->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for pagination count: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $total_row = $result->fetch_assoc();
    $total = (int)($total_row['total'] ?? 0);
    $stmt->close();
    $total_pages = ceil($total / $limit);
    $total_pages = max(1, $total_pages);
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Pagination query successful for tab: $tab, total records: $total, pages: $total_pages at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Pagination error for tab $tab, search: '$search': " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $total = 0;
    $total_pages = 1;
    $page = 1;
    $offset = 0;
}

// Fetch employee data
try {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Attempting data retrieval query for tab: $tab, search: '$search', offset: $offset, limit: $limit at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $query = "
        SELECT e.employee_id, e.full_name, e.email, e.phone, e.date_of_joining, e.status,
               COALESCE(d.name, 'Unknown') AS department_name,
               COALESCE(r.name, 'Unknown') AS role_name,
               COALESCE(l.name, 'Unknown') AS location_name
        FROM employee_details e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN roles r ON e.role_id = r.id
        LEFT JOIN locations l ON e.location_id = l.id
        WHERE ";
    $params = [];
    $types = '';
    
    $status_filter = $tab === 'active' ? 'Active' : 'Inactive';
    $query .= "e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
    
    if (!empty($search)) {
        $query .= " AND e.full_name LIKE ?";
        $search_param = "%$search%";
        $params[] = $search_param;
        $types .= 's';
    }
    
    $query .= " ORDER BY e.employee_id DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed for employee data retrieval: " . $mysqli->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for employee data retrieval: " . $stmt->error);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $employee_data[] = $row;
    }
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Data retrieval successful for tab: $tab, records fetched: " . count($employee_data) . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $stmt->close();
} catch (Exception $e) {
    file_put_contents('C:\xampp\htdocs\Allyted Project\admin\debug.log', "Data retrieval error for tab $tab, search: '$search': " . $e->getMessage() . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $employee_data = [];
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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/employee_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/content.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <style>
        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            padding: 15px;
        }
        .employee-card {
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e0e0e0;
            padding: 10px;
            min-height: 260px;
            display: flex;
            flex-direction: column;
        }
        .employee-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .employee-card.deleting {
            animation: fadeOut 0.5s ease forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(0.95); }
        }
        .delete-btn {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 28px;
            height: 28px;
            background: #dc3545;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
            z-index: 1;
        }
        .employee-card:hover .delete-btn {
            opacity: 1;
        }
        .delete-btn i {
            font-size: 0.9rem;
        }
        .delete-btn:hover {
            background: #a71d2a;
        }
        .card-top {
            display: flex;
            justify-content: flex-end;
            padding-bottom: 8px;
        }
        .status-text {
            font-size: 0.7rem;
            font-weight: 500;
            color: #155724;
            background: #d4edda;
            padding: 3px 8px;
            border-radius: 10px;
        }
        .status-text.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .card-middle {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 5px 0;
            flex-grow: 1;
            position: relative;
        }
        .image-container {
            display: flex;
            justify-content: flex-end;
            width: 100%;
            padding-right: 10px;
            margin-bottom: 5px;
        }
        .employee-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #6c757d;
            border: 2px solid #4a90e2;
            transition: border-color 0.3s ease;
            position: relative;
            right: 70px;
        }
        .employee-card:hover .employee-image {
            border-color: #28a745;
        }
        .employee-image::before {
            content: '👤';
        }
        .employee-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #000000;
            margin: 5px 0;
            text-align: center;
        }
        .employee-role {
            font-size: 0.85rem;
            font-weight: 400;
            color: #1e90ff;
            margin: 5px 0;
            text-align: center;
            position: relative;
            top: -10px;
        }
        .card-bottom {
            background: #e6f0fa;
            padding: 10px;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .bottom-columns {
            display: flex;
            gap: 15px;
            justify-content: space-between;
        }
        .bottom-rows {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .column {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
        }
        .column-heading {
            font-size: 0.75rem;
            font-weight: 600;
            color: #333;
        }
        .column-value {
            font-size: 0.7rem;
            color: #555;
        }
        .row {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            color: #555;
            word-break: break-word;
        }
        .row i {
            font-size: 0.8rem;
            color: #1e90ff;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #777;
            font-size: 1.5rem;
            font-weight: 600;
            grid-column: span 4;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .no-data i {
            font-size: 3rem;
            color: #6c757d;
        }
        .success-popup, .error-popup {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            animation: slideIn 0.5s ease-in-out;
        }
        .success-popup {
            background-color: #d4edda;
            color: #155724;
        }
        .error-popup {
            background-color: #f8d7da;
            color: #721c24;
        }
        .success-popup i, .error-popup i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background-color: #28a745;
            width: 100%;
            animation: progress 5s linear forwards;
        }
        .error-popup .progress-bar {
            background-color: #dc3545;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-container {
            position: relative;
        }
        .search-input {
            padding: 8px 8px 8px 30px;
            font-size: 0.9rem;
            border: none;
            border-bottom: 2px solid #4a90e2;
            background: transparent;
            outline: none;
            width: 200px;
            transition: border-bottom-color 0.3s ease;
        }
        .search-input:focus {
            border-bottom-color: #28a745;
        }
        .search-input::placeholder {
            color: #999;
        }
        .search-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #4a90e2;
            font-size: 0.9rem;
        }
        .add-btn {
            padding: 8px 15px;
            background: #4a90e2;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .add-btn:hover {
            background: #357abd;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        @keyframes progress {
            from { width: 100%; }
            to { width: 0%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Employees</h2>
        <div class="header-container">
            <div class="search-container">
                <form class="search-form" method="GET" action="admin_dashboard.php">
                    <input type="hidden" name="page" value="employee">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search by employee name..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
            <a href="/Allyted%20Project/admin/add_employee.php" class="add-btn" target="_blank">+ Add Employee</a>
        </div>
        <?php if (!empty($success)): ?>
            <div class="success-popup">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <div class="progress-bar"></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="error-popup">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <div class="progress-bar"></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="tabs" id="tabs">
        <a href="?page=employee&tab=active<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="tab <?= $tab === 'active' ? 'active' : '' ?>" data-tab="active">Active Employees <span>🟢</span></a>
        <a href="?page=employee&tab=inactive<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="tab <?= $tab === 'inactive' ? 'active' : '' ?>" data-tab="inactive">Inactive Employees <span>🔴</span></a>
        <div class="tab-underline"></div>
    </div>

    <div class="delete-modal" id="deleteModal">
        <div class="delete-modal-content">
            <span class="close" onclick="closeDeleteModal()">×</span>
            <p>Are you sure you want to delete this employee?</p>
            <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-delete" id="confirmDelete">Delete</button>
        </div>
    </div>

    <div class="card-container">
        <?php if (!empty($employee_data)): ?>
            <?php foreach ($employee_data as $row): ?>
                <div class="employee-card" data-employee-id="<?= htmlspecialchars($row['employee_id']) ?>">
                    <div class="delete-btn" onclick="openDeleteModal('<?= htmlspecialchars($row['employee_id']) ?>')">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="card-top">
                        <span class="status-text <?= strtolower($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </div>
                    <div class="card-middle">
                        <div class="image-container">
                            <a href="employee_details.php?employee_id=<?= urlencode($row['employee_id']) ?>">
                                <div class="employee-image"></div>
                            </a>
                        </div>
                        <div class="employee-name"><?= htmlspecialchars($row['full_name']) ?></div>
                        <div class="employee-role"><?= htmlspecialchars($row['role_name']) ?></div>
                    </div>
                    <div class="card-bottom">
                        <div class="bottom-columns">
                            <div class="column">
                                <div class="column-heading">Department</div>
                                <div class="column-value"><?= htmlspecialchars($row['department_name']) ?></div>
                            </div>
                            <div class="column">
                                <div class="column-heading">Hired Date</div>
                                <div class="column-value"><?= htmlspecialchars($row['date_of_joining']) ?></div>
                            </div>
                        </div>
                        <hr style="border: 0.2px dashed; opacity: 0.3;">
                        <div class="bottom-rows">
                            <div class="row">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($row['email']) ?></span>
                            </div>
                            <div class="row">
                                <i class="fas fa-phone"></i>
                                <span><?= htmlspecialchars($row['phone']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-info-circle"></i>
                No data available
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($employee_data)): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=employee&tab=<?= urlencode($tab) ?>&page_num=<?= $page - 1 ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="prev">« Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=employee&tab=<?= urlencode($tab) ?>&page_num=<?= $i ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages && $total > $offset + $limit): ?>
                <a href="?page=employee&tab=<?= urlencode($tab) ?>&page_num=<?= $page + 1 ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" class="next">Next »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
    function openDeleteModal(employee_id) {
        const deleteModal = document.getElementById('deleteModal');
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const card = document.querySelector(`.employee-card[data-employee-id="${employee_id}"]`);
        
        confirmDeleteBtn.onclick = function() {
            card.classList.add('deleting');
            setTimeout(() => {
                window.location.href = '?page=employee&tab=<?= urlencode($tab) ?>&delete=' + encodeURIComponent(employee_id) + '<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>';
            }, 500);
        };
        deleteModal.style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    function updateTabUnderline() {
        const tabsContainer = document.getElementById('tabs');
        const activeTab = tabsContainer.querySelector('.tab.active');
        const underline = tabsContainer.querySelector('.tab-underline');
        
        if (activeTab) {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = tabsContainer.getBoundingClientRect();
            underline.style.width = `${tabRect.width}px`;
            underline.style.left = `${tabRect.left - containerRect.left}px`;
            underline.style.transform = 'scaleX(1)';
        } else {
            underline.style.transform = 'scaleX(0)';
        }
    }

    window.onclick = function(e) {
        const deleteModal = document.getElementById('deleteModal');
        if (e.target === deleteModal) {
            closeDeleteModal();
        }
    };

    window.onload = function() {
        if (document.querySelector('.success-popup') || document.querySelector('.error-popup')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        updateTabUnderline();

        const searchInput = document.querySelector('.search-input');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    };

    window.onresize = updateTabUnderline;

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            updateTabUnderline();
            window.location.href = this.href;
        });
    });
    </script>
</body>
</html>