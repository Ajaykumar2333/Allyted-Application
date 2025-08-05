<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new learning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purpose']) && empty($_POST['learning_id'])) {
    $purpose = trim($_POST['purpose']);
    $links = trim($_POST['links']);
    $duration = trim($_POST['duration']);
    if (!empty($purpose)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM learnings WHERE purpose = ?");
        $check->bind_param("s", $purpose);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Learning already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO learnings (purpose, links, duration) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $purpose, $links, $duration);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Learning added successfully!";
        }
        header("Location: admin_dashboard.php?page=learnings");
        exit;
    }
}

// Update learning
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['learning_id'])) {
    $id = intval($_POST['learning_id']);
    $purpose = trim($_POST['purpose']);
    $links = trim($_POST['links']);
    $duration = trim($_POST['duration']);
    if (!empty($purpose)) {
        $stmt = $mysqli->prepare("UPDATE learnings SET purpose = ?, links = ?, duration = ? WHERE id = ?");
        $stmt->bind_param("sssi", $purpose, $links, $duration, $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Learning updated!";
        header("Location: admin_dashboard.php?page=learnings");
        exit;
    }
}

// Delete learning
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM learnings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Learning deleted!";
    header("Location: admin_dashboard.php?page=learnings");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM learnings");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$stmt = $mysqli->prepare("SELECT * FROM learnings ORDER BY id DESC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Learnings</h2>
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
<div class="modal" id="learningModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Learning</h3>
        <form method="POST">
            <input type="hidden" name="learning_id" id="learning_id">
            <input type="text" name="purpose" id="purpose" placeholder="Enter Purpose" required>
            <input type="text" name="links" id="links" placeholder="Enter Links">
            <input type="text" name="duration" id="duration" placeholder="Enter Duration (e.g., 1 hour)">
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this learning?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<table>
    <thead>
        <tr>
            <th>S.No.</th>
            <th>Purpose</th>
            <th>Links</th>
            <th>Duration</th>
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
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                    <td><?= htmlspecialchars($row['links']) ?></td>
                    <td><?= htmlspecialchars($row['duration']) ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td class="actions">
                        <i class="fas fa-edit" onclick="editLearning(<?= $row['id'] ?>, '<?= htmlspecialchars($row['purpose'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['links'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['duration'], ENT_QUOTES) ?>')"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No learnings found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=learnings&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=learnings&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=learnings&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Learning";
    document.getElementById('learning_id').value = '';
    document.getElementById('purpose').value = '';
    document.getElementById('links').value = '';
    document.getElementById('duration').value = '';
    document.getElementById('learningModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('learningModal').style.display = 'none';
}
function editLearning(id, purpose, links, duration) {
    document.getElementById('modalTitle').innerText = "Edit Learning";
    document.getElementById('learning_id').value = id;
    document.getElementById('purpose').value = purpose;
    document.getElementById('links').value = links;
    document.getElementById('duration').value = duration;
    document.getElementById('learningModal').style.display = 'flex';
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=learnings&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('learningModal');
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