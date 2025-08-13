<?php
// Start output buffering to prevent premature output
ob_start();

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/php_errors.log');
error_reporting(E_ALL);

// Log request start
error_log("Starting holidays.php POST request processing");

require_once 'db/config.php';

// Check database connection
if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ["Database connection failed: " . $mysqli->connect_error]]);
    ob_end_flush();
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Predefined holiday list (case-sensitive)
$fixed_holidays = [
    'New Year',
    'Sankranti',
    'Republic Day',
    'Ugadi',
    'Good Friday',
    "Ambedkar Jayanthi",
    'Ramzan',
    'MAY Day',
    'Telangana Formation Day',
    'Bakrid',
    'Independence day',
    'Ganesh Chaturthi',
    'Eid Milad un Nabi',
    'Mahatma Gandhi Jayanthi',
    'Vijayadashami',
    'Deepavali',
    'Christmas'
];

// Handle bulk save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['holidays'])) {
    try {
        $holidays = json_decode($_POST['holidays'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in holidays: " . json_last_error_msg());
        }
        $errors = [];
        $success_count = 0;

        error_log("Received holidays: " . print_r($holidays, true));

        foreach ($holidays as $index => $holiday) {
            error_log("Processing holiday at index $index: " . print_r($holiday, true));
            $id = isset($holiday['id']) ? intval($holiday['id']) : 0;
            $name = trim($holiday['name'] ?? '');
            $date = trim($holiday['date'] ?? '');
            $type = trim($holiday['type'] ?? '');
            $status = trim($holiday['status'] ?? '');
            $action = $holiday['action'] ?? '';

            // Validate inputs
            if ($action !== 'delete') {
                if (empty($name) || empty($date) || empty($type) || empty($status)) {
                    $errors[] = "All fields (name, date, type, status) are required for holiday: $name at index $index";
                    error_log("Validation failed for holiday: $name (ID: $id) - missing fields");
                    continue;
                }
                if (!in_array($type, ['Optional', 'Mandatory'])) {
                    $errors[] = "Invalid holiday type for holiday: $name at index $index";
                    error_log("Invalid holiday type: $type for holiday: $name at index $index");
                    continue;
                }
                if (!in_array($status, ['Activate', 'Deactivate'])) {
                    $errors[] = "Invalid status for holiday: $name at index $index";
                    error_log("Invalid status: $status for holiday: $name at index $index");
                    continue;
                }
            }

            // Validate date format
            if ($date && !DateTime::createFromFormat('Y-m-d', $date)) {
                $errors[] = "Invalid date format for holiday: $name at index $index";
                error_log("Invalid date format for holiday: $name (Date: $date)");
                continue;
            }

            // Validate holiday name
            if (!in_array($name, $fixed_holidays)) {
                $errors[] = "Invalid holiday name: $name at index $index";
                error_log("Invalid holiday name: $name at index $index");
                continue;
            }

            // Calculate day from date
            $day = $date ? date('l', strtotime($date)) : '';

            if ($action === 'delete' && $id > 0) {
                $stmt = $mysqli->prepare("DELETE FROM holidays WHERE id = ?");
                if (!$stmt) {
                    $errors[] = "Database error preparing delete query for holiday ID $id at index $index: " . $mysqli->error;
                    error_log("Database error preparing delete query: " . $mysqli->error);
                    continue;
                }
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success_count++;
                    error_log("Successfully deleted holiday ID $id at index $index");
                } else {
                    $errors[] = "Failed to delete holiday with ID $id at index $index: " . $stmt->error;
                    error_log("Failed to delete holiday ID $id at index $index: " . $stmt->error);
                }
                $stmt->close();
            } elseif ($id > 0) {
                // Update existing holiday
                $stmt = $mysqli->prepare("UPDATE holidays SET date = ?, day = ?, type = ?, status = ? WHERE id = ?");
                if (!$stmt) {
                    $errors[] = "Database error preparing update query for holiday: $name at index $index: " . $mysqli->error;
                    error_log("Database error preparing update query: " . $mysqli->error);
                    continue;
                }
                $stmt->bind_param("ssssi", $date, $day, $type, $status, $id);
                if ($stmt->execute()) {
                    $success_count++;
                    error_log("Successfully updated holiday: $name (ID: $id) at index $index");
                } else {
                    $errors[] = "Failed to update holiday: $name at index $index: " . $stmt->error;
                    error_log("Failed to update holiday: $name (ID: $id) at index $index: " . $stmt->error);
                }
                $stmt->close();
            } else {
                // Add new holiday
                $check = $mysqli->prepare("SELECT COUNT(*) FROM holidays WHERE name = ? AND date = ?");
                if (!$check) {
                    $errors[] = "Database error preparing check query for holiday: $name at index $index: " . $mysqli->error;
                    error_log("Database error preparing check query: " . $mysqli->error);
                    continue;
                }
                $check->bind_param("ss", $name, $date);
                if (!$check->execute()) {
                    $errors[] = "Database error checking existing holiday: $name at index $index: " . $check->error;
                    error_log("Database error checking existing holiday: " . $check->error);
                    $check->close();
                    continue;
                }
                $check->bind_result($count);
                $check->fetch();
                $check->close();

                if ($count > 0) {
                    $errors[] = "Holiday '$name' on '$date' already exists at index $index!";
                    error_log("Duplicate holiday detected: $name on $date at index $index");
                    continue;
                }

                $stmt = $mysqli->prepare("INSERT INTO holidays (name, date, day, type, status) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $errors[] = "Database error preparing insert query for holiday: $name at index $index: " . $mysqli->error;
                    error_log("Database error preparing insert query: " . $mysqli->error);
                    continue;
                }
                $stmt->bind_param("sssss", $name, $date, $day, $type, $status);
                if ($stmt->execute()) {
                    $success_count++;
                    error_log("Successfully added holiday: $name at index $index");
                } else {
                    $errors[] = "Failed to add holiday: $name at index $index: " . $stmt->error;
                    error_log("Failed to add holiday: $name at index $index: " . $stmt->error);
                }
                $stmt->close();
            }
        }

        error_log("Completed processing $success_count holidays with " . count($errors) . " errors");

        if ($success_count > 0) {
            $_SESSION['success_message'] = "$success_count holiday(s) saved successfully!";
            error_log("Set success message: $success_count holiday(s) saved successfully!");
        }
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode("<br>", $errors);
            error_log("Set error message: " . implode("; ", $errors));
        }

        header('Content-Type: application/json');
        http_response_code($success_count > 0 || empty($errors) ? 200 : 400);
        ob_end_clean();
        echo json_encode([
            'success' => $success_count > 0,
            'success_count' => $success_count,
            'errors' => $errors
        ], JSON_THROW_ON_ERROR);
        exit;
    } catch (Exception $e) {
        error_log("Exception in POST handling: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        header('Content-Type: application/json');
        http_response_code(500);
        ob_end_clean();
        echo json_encode(['success' => false, 'errors' => ["Server error: " . $e->getMessage()]]);
        exit;
    } catch (Error $e) {
        error_log("Fatal error in POST handling: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        header('Content-Type: application/json');
        http_response_code(500);
        ob_end_clean();
        echo json_encode(['success' => false, 'errors' => ["Fatal server error: " . $e->getMessage()]]);
        exit;
    }
}

// Log GET request
error_log("Processing holidays.php GET request");

// Fetch all holidays from database
$result = $mysqli->query("SELECT * FROM holidays");
$holiday_data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $holiday_data[$row['name']] = $row;
    }
    $result->free();
} else {
    error_log("Failed to fetch holidays: " . $mysqli->error);
}

// Prepare data for display (merge fixed holidays with database data)
$display_holidays = [];
foreach ($fixed_holidays as $holiday) {
    $display_holidays[] = [
        'id' => isset($holiday_data[$holiday]['id']) ? $holiday_data[$holiday]['id'] : 0,
        'name' => $holiday,
        'date' => isset($holiday_data[$holiday]['date']) ? $holiday_data[$holiday]['date'] : '',
        'day' => isset($holiday_data[$holiday]['day']) ? $holiday_data[$holiday]['day'] : '',
        'type' => isset($holiday_data[$holiday]['type']) ? $holiday_data[$holiday]['type'] : '',
        'status' => isset($holiday_data[$holiday]['status']) ? $holiday_data[$holiday]['status'] : ''
    ];
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;
$total = count($display_holidays);
$total_pages = ceil($total / $limit);

// Slice holidays for current page
$paginated_holidays = array_slice($display_holidays, $offset, $limit);

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">
<style>
    .header { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        margin-bottom: 24px; 
        padding: 16px;
        border-radius: 8px;
        color: white;
    }
    .header h2 { margin: 0; font-size: 24px; font-weight: 500; }
    .save-btn { 
        padding: 10px 24px; 
        background-color: #28a745; 
        color: white; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        font-size: 16px; 
        font-weight: 500; 
        transition: background-color 0.3s, transform 0.2s; 
    }
    .save-btn:hover { 
        background-color: #218838; 
        transform: translateY(-2px); 
    }
    .save-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }
    table { 
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
        border-radius: 12px; 
        overflow: hidden; 
        background: #fff; 
        box-shadow: 0 6px 20px rgba(0,0,0,0.1); 
        margin-bottom: 20px; 
    }
    th, td { 
        padding: 16px; 
        text-align: left; 
        border-bottom: 1px solid #e9ecef; 
        font-size: 14px; 
    }
    th { 
        background: #343a40; 
        color: white; 
        font-weight: 600; 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
    }
    tr { transition: background-color 0.3s; }
    tr:hover { background-color: #f1f3f5; }
    .editable input, .editable select { 
        width: 100%; 
        padding: 10px; 
        box-sizing: border-box; 
        border: 1px solid #ced4da; 
        border-radius: 6px; 
        background: #fff; 
        font-size: 14px; 
        transition: border-color 0.3s, box-shadow 0.3s; 
    }
    .editable input:focus, .editable select:focus { 
        border-color: #007bff; 
        box-shadow: 0 0 0 3px rgba(0,123,255,0.15); 
        outline: none; 
    }
    .editable select { 
        appearance: none; 
        background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%23333" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center; 
        padding-right: 30px; 
    }
    .success-popup, .error-popup { 
        position: fixed;
        top: 20px;
        left: 1200px;
        z-index: 1000;
        padding: 12px 20px; 
        border-radius: 8px; 
        font-size: 14px; 
        font-weight: 500; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        max-width: 400px; 
        transition: opacity 0.5s ease-out; 
    }
    .success-popup { 
         background: #28a745;
         color: white;
         border: 1px solid #c3e6cb; 
         /* position: relative;
         left: 100px; */
    }
    .error-popup { 
        background-color: #f8d7da; 
        color: #721c24; 
        border: 1px solid #f5c6cb; 
    }
    .close-popup { 
        position: absolute; 
        right: 12px; 
        top: 12px; 
        cursor: pointer; 
        font-size: 16px; 
        color: #495057; 
        transition: color 0.3s; 
    }
    .close-popup:hover { color: #212529; }
    .actions i { 
        cursor: pointer; 
        color: #dc3545; 
        font-size: 16px; 
        transition: color 0.3s, transform 0.2s; 
    }
    .actions i:hover { 
        color: #bd2130; 
        transform: scale(1.2); 
    }
    .delete-modal { 
        display: none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.5); 
        align-items: center; 
        justify-content: center; 
    }
    .delete-modal-content { 
        background: #fff; 
        padding: 24px; 
        border-radius: 8px; 
        box-shadow: 0 6px 20px rgba(0,0,0,0.2); 
        max-width: 400px; 
        width: 90%; 
        text-align: center; 
    }
    .delete-modal-content p { 
        margin: 0 0 20px; 
        font-size: 16px; 
    }
    .btn-cancel, .btn-delete { 
        padding: 10px 20px; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
        font-size: 14px; 
        margin: 0 10px; 
        transition: background-color 0.3s, transform 0.2s; 
    }
    .btn-cancel { 
        background: #6c757d; 
        color: white; 
    }
    .btn-cancel:hover { 
        background: #5a6268; 
        transform: translateY(-2px); 
    }
    .btn-delete { 
        background: #dc3545; 
        color: white; 
    }
    .btn-delete:hover { 
        background: #bd2130; 
        transform: translateY(-2px); 
    }
    .close { 
        position: absolute; 
        top: 12px; 
        right: 12px; 
        cursor: pointer; 
        font-size: 18px; 
        color: #495057; 
    }
    .close:hover { color: #212529; }
</style>

<div class="header">
    <h2>Holidays Management</h2>
    <button class="save-btn" id="saveButton" onclick="saveChanges()">Save Changes</button>
</div>

<?php if (!empty($success)): ?>
    <div class="success-popup" id="successPopup">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="error-popup" id="errorPopup">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
    </div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this holiday?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<form id="holidaysForm">
    <table>
        <thead>
            <tr>
                <th>S.No.</th>
                <th>Holiday Name</th>
                <th>Date</th>
                <th>Day</th>
                <th>Holiday Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="holidaysBody">
            <?php $sno = $offset + 1; ?>
            <?php foreach ($paginated_holidays as $holiday): ?>
                <tr data-id="<?= $holiday['id'] ?>">
                    <td><?= $sno++ ?></td>
                    <td data-field="name"><?= htmlspecialchars($holiday['name']) ?></td>
                    <td class="editable" data-field="date">
                        <input type="date" value="<?= htmlspecialchars($holiday['date']) ?>" data-field="date" onchange="updateDay(this)">
                    </td>
                    <td class="day"><?= htmlspecialchars($holiday['day']) ?></td>
                    <td class="editable" data-field="type">
                        <select data-field="type">
                            <option value="" disabled <?= empty($holiday['type']) ? 'selected' : '' ?>>Select</option>
                            <option value="Optional" <?= $holiday['type'] === 'Optional' ? 'selected' : '' ?>>Optional</option>
                            <option value="Mandatory" <?= $holiday['type'] === 'Mandatory' ? 'selected' : '' ?>>Mandatory</option>
                        </select>
                    </td>
                    <td class="editable" data-field="status">
                        <select data-field="status">
                            <option value="" disabled <?= empty($holiday['status']) ? 'selected' : '' ?>>Select</option>
                            <option value="Activate" <?= $holiday['status'] === 'Activate' ? 'selected' : '' ?>>Activate</option>
                            <option value="Deactivate" <?= $holiday['status'] === 'Deactivate' ? 'selected' : '' ?>>Deactivate</option>
                        </select>
                    </td>
                    <td class="actions">
                        <?php if ($holiday['id'] > 0): ?>
                            <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $holiday['id'] ?>)"></i>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=holidays&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=holidays&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=holidays&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</form>

<script>
let changedRows = new Set();

function updateDay(input) {
    const row = input.closest('tr');
    const date = input.value;
    if (date) {
        const day = new Date(date).toLocaleString('en-US', { weekday: 'long' });
        row.querySelector('.day').textContent = day;
        changedRows.add(row);
    } else {
        row.querySelector('.day').textContent = '';
    }
}

function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            changedRows.add(row);
            row.dataset.action = 'delete';
            saveChanges();
        }
    };
    deleteModal.style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function saveChanges() {
    const saveButton = document.getElementById('saveButton');
    saveButton.disabled = true;
    const form = document.getElementById('holidaysForm');
    const formData = new FormData();
    const holidays = [];
    const rows = document.querySelectorAll('#holidaysBody tr');

    rows.forEach((row, index) => {
        const nameCell = row.querySelector('[data-field="name"]');
        const dateInput = row.querySelector('[data-field="date"] input');
        const typeSelect = row.querySelector('[data-field="type"] select');
        const statusSelect = row.querySelector('[data-field="status"] select');
        const action = row.dataset.action || '';

        if (changedRows.has(row) || row.dataset.id !== '0' || action === 'delete') {
            const name = nameCell ? nameCell.textContent.trim() : '';
            const date = dateInput ? dateInput.value.trim() : '';
            const type = typeSelect ? typeSelect.value.trim() : '';
            const status = statusSelect ? statusSelect.value.trim() : '';

            if (!name && !date && !type && !status && action !== 'delete') {
                console.log(`Skipping row ${index} due to empty data`);
                return;
            }

            const holiday = {
                id: row.dataset.id || 0,
                name: name,
                date: date,
                type: type,
                status: status,
                action: action
            };

            holidays.push(holiday);
        }
    });

    if (holidays.length > 0) {
        console.log('Sending holidays:', holidays);
        formData.append('holidays', JSON.stringify(holidays));
        fetch('admin_dashboard.php?page=holidays', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Server response:', data);
            if (data.success) {
                window.location.reload();
            } else {
                const errorPopup = document.createElement('div');
                errorPopup.className = 'error-popup';
                errorPopup.id = 'errorPopup';
                errorPopup.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i> Failed to save changes: ${data.errors.join('; ') || 'Unknown error'}
                    <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
                `;
                document.body.appendChild(errorPopup);
                setTimeout(() => {
                    errorPopup.style.opacity = '0';
                    setTimeout(() => errorPopup.style.display = 'none', 500);
                }, 5000);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            const errorPopup = document.createElement('div');
            errorPopup.className = 'error-popup';
            errorPopup.id = 'errorPopup';
            errorPopup.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i> Failed to save changes: ${error.message}
                <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
            `;
            document.body.appendChild(errorPopup);
            setTimeout(() => {
                errorPopup.style.opacity = '0';
                setTimeout(() => errorPopup.style.display = 'none', 500);
            }, 5000);
        })
        .finally(() => {
            saveButton.disabled = false;
        });
    } else {
        console.log('No holidays to save');
        const errorPopup = document.createElement('div');
        errorPopup.className = 'error-popup';
        errorPopup.id = 'errorPopup';
        errorPopup.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i> No changes to save.
            <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
        `;
        document.body.appendChild(errorPopup);
        setTimeout(() => {
            errorPopup.style.opacity = '0';
            setTimeout(() => errorPopup.style.display = 'none', 500);
        }, 5000);
        saveButton.disabled = false;
    }
}

window.onclick = function(e) {
    const deleteModal = document.getElementById('deleteModal');
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
};

window.onload = function () {
    if (document.querySelector('.success-popup') || document.querySelector('.error-popup')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const successPopup = document.getElementById('successPopup');
        const errorPopup = document.getElementById('errorPopup');
        if (successPopup) {
            setTimeout(() => {
                successPopup.style.opacity = '0';
                setTimeout(() => successPopup.style.display = 'none', 500);
            }, 5000);
        }
        if (errorPopup) {
            setTimeout(() => {
                errorPopup.style.opacity = '0';
                setTimeout(() => errorPopup.style.display = 'none', 500);
            }, 5000);
        }
    }
};

document.getElementById('holidaysBody').addEventListener('input', function(e) {
    const row = e.target.closest('tr');
    if (row) {
        changedRows.add(row);
    }
});

document.querySelectorAll('input[data-field="date"]').forEach(input => {
    if (input.value) {
        updateDay(input);
    }
});
</script>