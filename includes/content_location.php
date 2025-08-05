<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_name']) && empty($_POST['location_id'])) {
    $name = trim($_POST['location_name']);
    if (!empty($name)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM locations WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Location already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO locations (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Location added successfully!";
        }
        header("Location: admin_dashboard.php?page=location");
        exit;
    }
}

// Update location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['location_id'])) {
    $id = intval($_POST['location_id']);
    $name = trim($_POST['location_name']);
    if (!empty($name)) {
        $stmt = $mysqli->prepare("UPDATE locations SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Location updated!";
        header("Location: admin_dashboard.php?page=location");
        exit;
    }
}

// Delete location
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM locations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Location deleted!";
    header("Location: admin_dashboard.php?page=location");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM locations");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$stmt = $mysqli->prepare("SELECT * FROM locations ORDER BY id DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Locations</h2>
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
<div class="modal" id="locationModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Location</h3>
        <form method="POST">
            <input type="hidden" name="location_id" id="location_id">
            <input type="text" name="location_name" id="location_name" placeholder="Enter Location Name" required>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this location?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Location Name</th>
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
                        <i class="fas fa-edit" onclick="editLocation(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No locations found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=location&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=location&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=location&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Location";
    document.getElementById('location_id').value = '';
    document.getElementById('location_name').value = '';
    document.getElementById('locationModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('locationModal').style.display = 'none';
}
function editLocation(id, name) {
    document.getElementById('modalTitle').innerText = "Edit Location";
    document.getElementById('location_id').value = id;
    document.getElementById('location_name').value = name;
    document.getElementById('locationModal').style.display = 'flex';
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=location&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('locationModal');
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