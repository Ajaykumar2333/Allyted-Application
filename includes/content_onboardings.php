<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session variables for form data to prevent blank forms
$_SESSION['form_data'] = $_SESSION['form_data'] ?? [];

// Add new onboarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purpose']) && empty($_POST['onboarding_id'])) {
    $purpose = trim($_POST['purpose']);
    $links = trim($_POST['links'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    if (!empty($purpose)) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM onboardings WHERE purpose = ?");
        if (!$check) {
            $_SESSION['error_message'] = "Database error: " . $mysqli->error;
            header("Location: admin_dashboard.php?page=onboardings");
            exit;
        }
        $check->bind_param("s", $purpose);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Onboarding with this purpose already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO onboardings (purpose, links, duration) VALUES (?, ?, ?)");
            if (!$stmt) {
                $_SESSION['error_message'] = "Database error: " . $mysqli->error;
                header("Location: admin_dashboard.php?page=onboardings");
                exit;
            }
            $stmt->bind_param("sss", $purpose, $links, $duration);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Onboarding added successfully!";
            unset($_SESSION['form_data']);
        }
        header("Location: admin_dashboard.php?page=onboardings");
        exit;
    } else {
        $_SESSION['error_message'] = "Purpose is required!";
        $_SESSION['form_data'] = $_POST;
        header("Location: admin_dashboard.php?page=onboardings");
        exit;
    }
}

// Update onboarding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['onboarding_id'])) {
    $id = intval($_POST['onboarding_id']);
    $purpose = trim($_POST['purpose']);
    $links = trim($_POST['links'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    if (!empty($purpose)) {
        $stmt = $mysqli->prepare("UPDATE onboardings SET purpose = ?, links = ?, duration = ? WHERE id = ?");
        if (!$stmt) {
            $_SESSION['error_message'] = "Database error: " . $mysqli->error;
            header("Location: admin_dashboard.php?page=onboardings");
            exit;
        }
        $stmt->bind_param("sssi", $purpose, $links, $duration, $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Onboarding updated!";
        unset($_SESSION['form_data']);
        header("Location: admin_dashboard.php?page=onboardings");
        exit;
    } else {
        $_SESSION['error_message'] = "Purpose is required!";
        $_SESSION['form_data'] = $_POST;
        header("Location: admin_dashboard.php?page=onboardings");
        exit;
    }
}

// Delete onboarding
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $mysqli->prepare("DELETE FROM onboardings WHERE id = ?");
    if (!$stmt) {
        $_SESSION['error_message'] = "Database error: " . $mysqli->error;
        header("Location: admin_dashboard.php?page=onboardings");
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success_message'] = "Onboarding deleted!";
    header("Location: admin_dashboard.php?page=onboardings");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM onboardings");
if (!$total_result) {
    $_SESSION['error_message'] = "Database error: " . $mysqli->error;
    header("Location: admin_dashboard.php?page=onboardings");
    exit;
}
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data
$stmt = $mysqli->prepare("SELECT * FROM onboardings ORDER BY id DESC LIMIT ?, ?");
if (!$stmt) {
    $_SESSION['error_message'] = "Database error: " . $mysqli->error;
    header("Location: admin_dashboard.php?page=onboardings");
    exit;
}
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['form_data']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Onboardings</h2>
    <button class="add-btn" onclick="openModal()">+ Add</button>
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
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="onboardingModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Onboarding</h3>
        <form method="POST">
            <input type="hidden" name="onboarding_id" id="onboarding_id" value="<?= htmlspecialchars($form_data['onboarding_id'] ?? '') ?>">
            <input type="text" name="purpose" id="purpose" placeholder="Enter Purpose" required value="<?= htmlspecialchars($form_data['purpose'] ?? '') ?>">
            <input type="text" name="links" id="links" placeholder="Enter Links (optional)" value="<?= htmlspecialchars($form_data['links'] ?? '') ?>">
            <input type="text" name="duration" id="duration" placeholder="Enter Duration (e.g., 1 hour, optional)" value="<?= htmlspecialchars($form_data['duration'] ?? '') ?>">
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this onboarding?</p>
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
                    <td><?= htmlspecialchars($row['links'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['duration'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td class="actions">
                        <i class="fas fa-edit" onclick="editOnboarding(<?= $row['id'] ?>, '<?= htmlspecialchars($row['purpose'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['links'] ?: '', ENT_QUOTES) ?>', '<?= htmlspecialchars($row['duration'] ?: '', ENT_QUOTES) ?>')"></i>
                        <i class="fas fa-trash-alt" onclick="openDeleteModal(<?= $row['id'] ?>)"></i>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No onboardings found.</td></tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=onboardings&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=onboardings&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=onboardings&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </tfoot>
</table>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Onboarding";
    document.getElementById('onboarding_id').value = '';
    document.getElementById('purpose').value = '';
    document.getElementById('links').value = '';
    document.getElementById('duration').value = '';
    document.getElementById('onboardingModal').style.display = 'flex';
    document.getElementById('purpose').focus();
}
function closeModal() {
    document.getElementById('onboardingModal').style.display = 'none';
}
function editOnboarding(id, purpose, links, duration) {
    document.getElementById('modalTitle').innerText = "Edit Onboarding";
    document.getElementById('onboarding_id').value = id;
    document.getElementById('purpose').value = purpose;
    document.getElementById('links').value = links;
    document.getElementById('duration').value = duration;
    document.getElementById('onboardingModal').style.display = 'flex';
    document.getElementById('purpose').focus();
}
function openDeleteModal(id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=onboardings&delete=' + id;
    };
    deleteModal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
window.onclick = function(e) {
    const modal = document.getElementById('onboardingModal');
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
    // Ensure modal data is populated if form data exists
    <?php if (!empty($form_data)): ?>
        document.getElementById('modalTitle').innerText = "<?= !empty($form_data['onboarding_id']) ? 'Edit Onboarding' : 'Add New Onboarding' ?>";
        document.getElementById('onboarding_id').value = '<?= htmlspecialchars($form_data['onboarding_id'] ?? '') ?>';
        document.getElementById('purpose').value = '<?= htmlspecialchars($form_data['purpose'] ?? '') ?>';
        document.getElementById('links').value = '<?= htmlspecialchars($form_data['links'] ?? '') ?>';
        document.getElementById('duration').value = '<?= htmlspecialchars($form_data['duration'] ?? '') ?>';
        document.getElementById('onboardingModal').style.display = 'flex';
    <?php endif; ?>
};
</script>