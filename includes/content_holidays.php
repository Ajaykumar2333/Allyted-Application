<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle bulk save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['holidays'])) {
    $holidays = json_decode($_POST['holidays'], true);
    $errors = [];
    $success_count = 0;

    foreach ($holidays as $holiday) {
        $id = isset($holiday['id']) ? intval($holiday['id']) : 0;
        $name = trim($holiday['name']);
        $date = trim($holiday['date']);
        $type = trim($holiday['type']);
        $action = $holiday['action'] ?? '';

        if ($action === 'delete' && $id > 0) {
            $stmt = $mysqli->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_count++;
            }
            $stmt->close();
        } elseif ($id > 0) {
            // Update existing holiday
            if (empty($name) || empty($date) || empty($type)) {
                $errors[] = "All fields are required for holiday: $name";
                continue;
            }
            $stmt = $mysqli->prepare("UPDATE holidays SET name = ?, date = ?, type = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $date, $type, $id);
            if ($stmt->execute()) {
                $success_count++;
            }
            $stmt->close();
        } else {
            // Add new holiday
            if (empty($name) || empty($date) || empty($type)) {
                continue; // Skip empty rows
            }
            $check = $mysqli->prepare("SELECT COUNT(*) FROM holidays WHERE name = ? AND date = ?");
            $check->bind_param("ss", $name, $date);
            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();

            if ($count > 0) {
                $errors[] = "Holiday '$name' on '$date' already exists!";
            } else {
                $stmt = $mysqli->prepare("INSERT INTO holidays (name, date, type) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $date, $type);
                if ($stmt->execute()) {
                    $success_count++;
                }
                $stmt->close();
            }
        }
    }

    if ($success_count > 0) {
        $_SESSION['success_message'] = "$success_count holiday(s) saved successfully!";
    }
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    header("Location: admin_dashboard.php?page=holidays");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM holidays");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$stmt = $mysqli->prepare("SELECT * FROM holidays ORDER BY date DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">
<style>
    .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .save-btn { padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; }
    .save-btn:hover { background-color: #218838; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: 10px; overflow: hidden; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    th, td { padding: 14px; text-align: left; border-bottom: 1px solid #e9ecef; }
    th { background: #2f3640; color: white; font-weight: 600; text-transform: uppercase; font-size: 14px; }
    tr:hover { background-color: #f8f9fa; }
    .editable input, .editable select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ced4da; border-radius: 6px; background: #fff; font-size: 14px; transition: border-color 0.3s, box-shadow 0.3s; }
    .editable input:focus, .editable select:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); outline: none; }
    .editable input::placeholder { color: #6c757d; font-style: italic; }
    .editable select { appearance: none; background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path fill="%23333" d="M7 10l5 5 5-5z"/></svg>') no-repeat right 8px center; }
    .success-popup, .error-popup { padding: 12px 16px; margin: 10px 0; border-radius: 6px; font-size: 14px; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .success-popup { background-color: #d4edda; color: #155724; }
    .error-popup { background-color: #f8d7da; color: #721c24; }
    .close-popup { position: absolute; right: 12px; cursor: pointer; font-size: 16px; color: #495057; }
    .actions i { cursor: pointer; color: #dc3545; font-size: 16px; transition: color 0.3s; }
    .actions i:hover { color: #bd2130; }
    .add-row { text-align: center; }
    .add-row i { display: inline-block; width: 28px; height: 28px; line-height: 28px; background: #007bff; color: white; border-radius: 50%; font-size: 14px; cursor: pointer; transition: background-color 0.3s; }
    .add-row i:hover { background: #0056b3; }
</style>

<div class="header">
    <h2>Holidays</h2>
    <button class="save-btn" onclick="saveChanges()">Save Changes</button>
</div>

<?php if (!empty($success)): ?>
    <div class="success-popup">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
        <div class="progress-bar"></div>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="error-popup">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
        <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
        <div class="progress-bar"></div>
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
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Holiday Name</th>
            <th>Date</th>
            <th>Holiday Type</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="holidaysBody">
        <?php $sno = $offset + 1; ?>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td><?= $sno++ ?></td>
                    <td class="editable" data-field="name">
                        <input type="text" value="<?= htmlspecialchars($row['name']) ?>" data-field="name" placeholder="Enter holiday name">
                    </td>
                    <td class="editable" data-field="date">
                        <input type="date" value="<?= htmlspecialchars($row['date']) ?>" data-field="date">
                    </td>
                    <td class="editable" data-field="type">
                        <select data-field="type">
                            <option value="National" <?= $row['type'] === 'National' ? 'selected' : '' ?>>National</option>
                            <option value="Religious" <?= $row['type'] === 'Religious' ? 'selected' : '' ?>>Religious</option>
                            <option value="Company" <?= $row['type'] === 'Company' ? 'selected' : '' ?>>Company</option>
                            <option value="Other" <?= $row['type'] === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </td>
                    <td class="actions">
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        <?php 
        // Add empty rows to ensure at least 7 rows are displayed
        $rows_to_add = $result->num_rows > 0 ? max(0, 7 - $result->num_rows) : 7;
        for ($i = 0; $i < $rows_to_add - 1; $i++): ?>
            <tr>
                <td><?= $sno++ ?></td>
                <td class="editable" data-field="name">
                    <input type="text" data-field="name" placeholder="Enter holiday name">
                </td>
                <td class="editable" data-field="date">
                    <input type="date" data-field="date">
                </td>
                <td class="editable" data-field="type">
                    <select data-field="type">
                        <option value="National">National</option>
                        <option value="Religious">Religious</option>
                        <option value="Company">Company</option>
                        <option value="Other">Other</option>
                    </select>
                </td>
                <td class="actions"></td>
            </tr>
        <?php endfor; ?>
        <!-- Last row with Add Row icon -->
        <tr>
            <td><?= $sno++ ?></td>
            <td class="editable" data-field="name">
                <input type="text" data-field="name" placeholder="Enter holiday name">
            </td>
            <td class="editable" data-field="date">
                <input type="date" data-field="date">
            </td>
            <td class="editable" data-field="type">
                <select data-field="type">
                    <option value="National">National</option>
                    <option value="Religious">Religious</option>
                    <option value="Company">Company</option>
                    <option value="Other">Other</option>
                </select>
            </td>
            <td class="add-row">
                <i class="fas fa-plus" onclick="addNewRow()"></i>
            </td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">
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

<script>
let changedRows = new Set();

function addNewRow() {
    const tbody = document.getElementById('holidaysBody');
    const lastRow = tbody.querySelector('tr:last-child');
    const rowCount = tbody.querySelectorAll('tr').length + 1;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td>${rowCount}</td>
        <td class="editable" data-field="name"><input type="text" data-field="name" placeholder="Enter holiday name"></td>
        <td class="editable" data-field="date"><input type="date" data-field="date"></td>
        <td class="editable" data-field="type">
            <select data-field="type">
                <option value="National">National</option>
                <option value="Religious">Religious</option>
                <option value="Company">Company</option>
                <option value="Other">Other</option>
            </select>
        </td>
        <td class="add-row"><i class="fas fa-plus" onclick="addNewRow()"></i></td>
    `;
    tbody.insertBefore(newRow, lastRow);
    changedRows.add(newRow);
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
    const holidays = [];
    const rows = document.querySelectorAll('#holidaysBody tr');

    rows.forEach(row => {
        if (changedRows.has(row) || row.dataset.id) {
            const holiday = {
                id: row.dataset.id || 0,
                name: row.querySelector('[data-field="name"] input').value.trim(),
                date: row.querySelector('[data-field="date"] input').value.trim(),
                type: row.querySelector('[data-field="type"] select').value.trim(),
                action: row.dataset.action || ''
            };
            holidays.push(holiday);
        }
    });

    if (holidays.length > 0) {
        fetch('admin_dashboard.php?page=holidays', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'holidays=' + encodeURIComponent(JSON.stringify(holidays))
        }).then(() => {
            window.location.reload();
        });
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
    }
};

// Track changes
document.getElementById('holidaysBody').addEventListener('input', function(e) {
    const row = e.target.closest('tr');
    if (row) {
        changedRows.add(row);
    }
});
</script>