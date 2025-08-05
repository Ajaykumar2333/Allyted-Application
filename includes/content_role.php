<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new roles (supporting multiple role names)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_names']) && isset($_POST['department_id']) && empty($_POST['role_id'])) {
    $department_id = intval($_POST['department_id']);
    $role_names = array_filter(array_map('trim', $_POST['role_names'])); // Trim and remove empty role names

    if ($department_id > 0 && !empty($role_names)) {
        $success_count = 0;
        $error_messages = [];

        foreach ($role_names as $name) {
            if (empty($name)) continue; // Skip empty names

            // Check if role already exists for this department
            $check = $mysqli->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND department_id = ?");
            $check->bind_param("si", $name, $department_id);
            $check->execute();
            $check->bind_result($count);
            $check->fetch();
            $check->close();

            if ($count > 0) {
                $error_messages[] = "Role '$name' already exists for this department!";
            } else {
                $stmt = $mysqli->prepare("INSERT INTO roles (name, department_id) VALUES (?, ?)");
                $stmt->bind_param("si", $name, $department_id);
                $stmt->execute();
                $stmt->close();
                $success_count++;
            }
        }

        if ($success_count > 0) {
            $_SESSION['success_message'] = "$success_count role(s) added successfully!";
        }
        if (!empty($error_messages)) {
            $_SESSION['error_message'] = implode(' ', $error_messages);
        }
        header("Location: admin_dashboard.php?page=role");
        exit;
    } else {
        $_SESSION['error_message'] = "Please provide at least one role name and select a department!";
        header("Location: admin_dashboard.php?page=role");
        exit;
    }
}

// Update role (unchanged, as this still handles a single role)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['role_id'])) {
    $id = intval($_POST['role_id']);
    $name = trim($_POST['role_name']);
    $department_id = intval($_POST['department_id']);
    if (!empty($name) && $department_id > 0) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND department_id = ? AND id != ?");
        $check->bind_param("sii", $name, $department_id, $id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Role already exists for this department!";
        } else {
            $stmt = $mysqli->prepare("UPDATE roles SET name = ?, department_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $name, $department_id, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Role updated!";
        }
        header("Location: admin_dashboard.php?page=role");
        exit;
    } else {
        $_SESSION['error_message'] = "Please provide a valid role name and department!";
        header("Location: admin_dashboard.php?page=role");
        exit;
    }
}

// Delete role
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Role deleted!";
    header("Location: admin_dashboard.php?page=role");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM roles");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data with department name
$stmt = $mysqli->prepare("
    SELECT r.id, r.name, r.created_at, d.name AS department_name, r.department_id 
    FROM roles r 
    JOIN departments d ON r.department_id = d.id 
    ORDER BY r.id DESC 
    LIMIT ?, ?
");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all departments for dropdown
$departments_result = $mysqli->query("SELECT id, name FROM departments ORDER BY name");
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Roles</h2>
    <button class="add-btn" onclick="openModal()">+ Add</button>
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
    <?php if (empty($departments)): ?>
        <div class="error-popup">
            <i class="fas fa-exclamation-triangle"></i> No departments found. Please add a department first!
            <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
            <div class="progress-bar"></div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="roleModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Role</h3>
        <form method="POST">
            <input type="hidden" name="role_id" id="role_id">
            <select name="department_id" id="department_id" required>
                <option value="" disabled selected>Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="roleFields">
                <!-- Default Role Name Field -->
                <div class="role-field">
                    <input type="text" name="role_names[]" placeholder="Enter Role Name" required>
                </div>
            </div>
            <button type="button" class="add-role-btn" onclick="addRoleField()">+ Add Another Role</button>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this role?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Role Name</th>
            <th>Department</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $sno = $offset + 1; ?>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td class="actions">
                        <i class="fas fa-edit" onclick="editRole(<?= $row['id'] ?>, <?= json_encode(htmlspecialchars($row['name'], ENT_QUOTES)) ?>, <?= $row['department_id'] ?>)"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No roles found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=role&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=role&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=role&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Role";
    document.getElementById('role_id').value = '';
    document.getElementById('department_id').value = '';
    document.getElementById('roleFields').innerHTML = `
        <div class="role-field">
            <input type="text" name="role_names[]" placeholder="Enter Role Name" required>
        </div>
    `;
    document.getElementById('roleModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('roleModal').style.display = 'none';
}
function editRole(id, name, department_id) {
    document.getElementById('modalTitle').innerText = "Edit Role";
    document.getElementById('role_id').value = id;
    document.getElementById('department_id').value = department_id;
    document.getElementById('roleFields').innerHTML = `
        <div class="role-field">
            <input type="text" name="role_name" value="${name}" placeholder="Enter Role Name" required>
        </div>
    `;
    document.getElementById('roleModal').style.display = 'flex';
}
function addRoleField() {
    const roleFields = document.getElementById('roleFields');
    const newField = document.createElement('div');
    newField.className = 'role-field';
    newField.innerHTML = `
        <input type="text" name="role_names[]" placeholder="Enter Role Name">
        <button type="button" class="remove-role-btn" onclick="removeRoleField(this)">−</button>
    `;
    roleFields.appendChild(newField);
}
function removeRoleField(button) {
    button.parentElement.remove();
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=role&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('roleModal');
    const deleteModal = document.getElementById('deleteModal');
    if (e.target === modal) {
        closeModal();
    }
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
};
window.onload = function () {
    if (document.querySelector('.success-popup') || document.querySelector('.error-popup')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
</script>

<style>
.role-field {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.role-field input {
    flex: 1;
}
.add-role-btn {
    background: #0656ad;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 15px;
    transition: background 0.2s;
}
.add-role-btn:hover {
    background: #0056b3;
}
.remove-role-btn {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 5px 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.remove-role-btn:hover {
    background: #c82333;
}
</style>