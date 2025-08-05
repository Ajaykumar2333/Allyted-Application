<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brand_name']) && empty($_POST['brand_id'])) {
    $name = trim($_POST['brand_name']);
    if (!empty($name)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM brands WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Brand already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO brands (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Brand added successfully!";
        }
        header("Location: admin_dashboard.php?page=brand");
        exit;
    }
}

// Update brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['brand_id'])) {
    $id = intval($_POST['brand_id']);
    $name = trim($_POST['brand_name']);
    if (!empty($name)) {
        $stmt = $mysqli->prepare("UPDATE brands SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Brand updated!";
        header("Location: admin_dashboard.php?page=brand");
        exit;
    }
}

// Delete brand
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Brand deleted!";
    header("Location: admin_dashboard.php?page=brand");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM brands");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$stmt = $mysqli->prepare("SELECT * FROM brands ORDER BY id DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">


<div class="header">
    <h2>Brands</h2>
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
<div class="modal" id="brandModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Brand</h3>
        <form method="POST">
            <input type="hidden" name="brand_id" id="brand_id">
            <input type="text" name="brand_name" id="brand_name" placeholder="Enter Brand Name" required>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this brand?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Brand Name</th>
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
                        <i class="fas fa-edit" onclick="editBrand(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No brands found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=brand&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=brand&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=brand&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Brand";
    document.getElementById('brand_id').value = '';
    document.getElementById('brand_name').value = '';
    document.getElementById('brandModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('brandModal').style.display = 'none';
}
function editBrand(id, name) {
    document.getElementById('modalTitle').innerText = "Edit Brand";
    document.getElementById('brand_id').value = id;
    document.getElementById('brand_name').value = name;
    document.getElementById('brandModal').style.display = 'flex';
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=brand&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('brandModal');
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