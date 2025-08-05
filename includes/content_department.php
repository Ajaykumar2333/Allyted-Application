<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['dept_id'])) {
    $name = trim($_POST['dept_name']);
    if (!empty($name)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Department already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Department added successfully!";
        }
        header("Location: admin_dashboard.php?page=department");
        exit;
    }
}

// Update department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['dept_id'])) {
    $id = intval($_POST['dept_id']);
    $name = trim($_POST['dept_name']);
    if (!empty($name)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM departments WHERE name = ? AND id != ?");
        $check->bind_param("si", $name, $id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Department already exists!";
        } else {
            $stmt = $mysqli->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Department updated!";
        }
        header("Location: admin_dashboard.php?page=department");
        exit;
    }
}

// Delete department
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if there are roles associated with this department
    $check = $mysqli->prepare("SELECT COUNT(*) FROM roles WHERE department_id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $check->bind_result($role_count);
    $check->fetch();
    $check->close();

    if ($role_count > 0) {
        $_SESSION['dependency_error'] = $role_count;
        header("Location: admin_dashboard.php?page=department");
        exit;
    }

    $stmt = $mysqli->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Department deleted!";
    header("Location: admin_dashboard.php?page=department");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM departments");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$result = $mysqli->query("SELECT id, name, created_at FROM departments ORDER BY id DESC LIMIT $offset, $limit");

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
$dependency_error = $_SESSION['dependency_error'] ?? 0;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['dependency_error']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Departments</h2>
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
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="deptModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Department</h3>
        <form method="POST">
            <input type="hidden" name="dept_id" id="dept_id">
            <input type="text" name="dept_name" id="dept_name" placeholder="Enter Department Name" required>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this department?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Dependency Error Modal -->
<div class="modal dependency-modal" id="dependencyModal" style="<?php echo $dependency_error ? 'display: flex;' : 'display: none;'; ?>">
    <div class="modal-content dependency-modal-content">
        <span class="close" onclick="closeDependencyModal()">×</span>
        <h3>Unable to Delete Department</h3>
        <p>This department cannot be deleted because it has <strong><?php echo $dependency_error; ?> role(s)</strong> assigned. Please delete or reassign the roles first.</p>
        <button class="btn-cancel" onclick="closeDependencyModal()">Close</button>
        <button class="btn-action" onclick="window.location.href='admin_dashboard.php?page=role'">Go to Roles</button>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Department Name</th>
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
                    <td><?= $row['created_at'] ?></td>
                    <td class="actions">
                        <i class="fas fa-edit" onclick="editDept(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No departments found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=department&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=department&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=department&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Department";
    document.getElementById('dept_id').value = '';
    document.getElementById('dept_name').value = '';
    document.getElementById('deptModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('deptModal').style.display = 'none';
}
function editDept(id, name) {
    document.getElementById('modalTitle').innerText = "Edit Department";
    document.getElementById('dept_id').value = id;
    document.getElementById('dept_name').value = name;
    document.getElementById('deptModal').style.display = 'flex';
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=department&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
function closeDependencyModal() {
    document.getElementById('dependencyModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('deptModal');
    const deleteModal = document.getElementById('deleteModal');
    const dependencyModal = document.getElementById('dependencyModal');
    if (e.target === modal) {
        closeModal();
    }
    if (e.target === deleteModal) {
        closeDeleteModal();
    }
    if (e.target === dependencyModal) {
        closeDependencyModal();
    }
};
window.onload = function () {
    if (document.querySelector('.success-popup') || document.querySelector('.error-popup')) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};
</script>