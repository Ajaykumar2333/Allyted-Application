<?php
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add new asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asset_id']) && empty($_POST['edit_asset_id'])) {
    $asset_id = trim($_POST['asset_id']);
    $brand_id = intval($_POST['brand_id']);
    $date_of_allocation = $_POST['date_of_allocation'] ?: null;
    $condition = $_POST['condition'];
    $touch_availability = $_POST['touch_availability'];
    $serial_number = trim($_POST['serial_number']);
    $status = 'Not Allocated'; // Default for new assets

    if (!empty($asset_id) && $brand_id > 0 && in_array($condition, ['Working', 'Not Working', 'Under Repair']) && in_array($touch_availability, ['Yes', 'No'])) {
        $check = $mysqli->prepare("SELECT COUNT(*) FROM assets WHERE asset_id = ?");
        $check->bind_param("s", $asset_id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Asset ID already exists!";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO assets (asset_id, brand_id, date_of_allocation, `condition`, touch_availability, `status`, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssss", $asset_id, $brand_id, $date_of_allocation, $condition, $touch_availability, $status, $serial_number);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_message'] = "Asset added successfully!";
        }
    } else {
        $_SESSION['error_message'] = "Please fill all required fields correctly!";
    }
    header("Location: admin_dashboard.php?page=assets");
    exit;
}

// Update asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['edit_asset_id'])) {
    $asset_id = trim($_POST['edit_asset_id']);
    $brand_id = intval($_POST['brand_id']);
    $date_of_allocation = $_POST['date_of_allocation'] ?: null;
    $condition = $_POST['condition'];
    $touch_availability = $_POST['touch_availability'];
    $serial_number = trim($_POST['serial_number']);

    if ($brand_id > 0 && in_array($condition, ['Working', 'Not Working', 'Under Repair']) && in_array($touch_availability, ['Yes', 'No'])) {
        $stmt = $mysqli->prepare("UPDATE assets SET brand_id = ?, date_of_allocation = ?, `condition` = ?, touch_availability = ?, serial_number = ? WHERE asset_id = ?");
        $stmt->bind_param("isssss", $brand_id, $date_of_allocation, $condition, $touch_availability, $serial_number, $asset_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Asset updated successfully!";
    } else {
        $_SESSION['error_message'] = "Please fill all required fields correctly!";
    }
    header("Location: admin_dashboard.php?page=assets");
    exit;
}

// Delete asset
if (isset($_GET['delete'])) {
    $asset_id = trim($_GET['delete']);
    // Check if the asset is allocated
    $check = $mysqli->prepare("SELECT `status` FROM assets WHERE asset_id = ?");
    $check->bind_param("s", $asset_id);
    $check->execute();
    $result = $check->get_result();
    $asset = $result->fetch_assoc();
    $check->close();

    if ($asset && $asset['status'] === 'Allocated') {
        $_SESSION['error_message'] = "Cannot delete an allocated asset!";
    } else {
        $stmt = $mysqli->prepare("DELETE FROM assets WHERE asset_id = ?");
        $stmt->bind_param("s", $asset_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_message'] = "Asset deleted successfully!";
    }
    header("Location: admin_dashboard.php?page=assets");
    exit;
}

// Pagination logic
$limit = 7;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $limit;

// Get total count
$total_result = $mysqli->query("SELECT COUNT(*) as total FROM assets");
$total_row = $total_result->fetch_assoc();
$total = $total_row['total'];
$total_pages = ceil($total / $limit);

// Fetch paginated data with brand names
$stmt = $mysqli->prepare("
    SELECT a.ID, a.asset_id, a.brand_id, a.date_of_allocation, a.`condition`, a.touch_availability, a.`status`, a.serial_number, b.name AS brand_name
    FROM assets a
    JOIN brands b ON a.brand_id = b.id
    ORDER BY a.ID DESC
    LIMIT ?, ?
");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all brands for dropdown
$brands_result = $mysqli->query("SELECT id, name FROM brands ORDER BY name");
$brands = $brands_result->fetch_all(MYSQLI_ASSOC);

$success = $_SESSION['success_message'] ?? '';
$error = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<link rel="stylesheet" href="css/content.css">

<div class="header">
    <h2>Assets</h2>
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
    <?php if (empty($brands)): ?>
        <div class="error-popup">
            <i class="fas fa-exclamation-triangle"></i> No brands found. Please add a brand first!
            <span class="close-popup" onclick="this.parentElement.style.display='none'">×</span>
            <div class="progress-bar"></div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal" id="assetModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 id="modalTitle">Add New Asset</h3>
        <form method="POST">
            <input type="hidden" name="edit_asset_id" id="edit_asset_id">
            <input type="text" name="asset_id" id="asset_id" placeholder="Enter Asset ID" required>
            <select name="brand_id" id="brand_id" required>
                <option value="" disabled selected>Select Brand</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_of_allocation" id="date_of_allocation" placeholder="Select Date of Allocation">
            <select name="condition" id="condition" required>
                <option value="" disabled selected>Select Condition</option>
                <option value="Working">Working</option>
                <option value="Not Working">Not Working</option>
                <option value="Under Repair">Under Repair</option>
            </select>
            <select name="touch_availability" id="touch_availability" required>
                <option value="" disabled selected>Select Touch Availability</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
            <input type="text" name="serial_number" id="serial_number" placeholder="Enter Serial Number">
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-modal" id="deleteModal">
    <div class="delete-modal-content">
        <span class="close" onclick="closeDeleteModal()">×</span>
        <p>Are you sure you want to delete this asset?</p>
        <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn-delete" id="confirmDelete">Delete</button>
    </div>
</div>

<!-- Table -->
<div style="overflow-x: auto; -ms-overflow-style: none; scrollbar-width: none; width: 100%;">
    <table style="min-width: 1000px; white-space: nowrap;">
        <thead>
            <tr>
                <th style="width: 80px;">S.No.</th>
                <th style="width: 120px;">Asset ID</th>
                <th style="width: 100px;">Brand</th>
                <th style="width: 150px;">Date of Allocation</th>
                <th style="width: 120px;">Condition</th>
                <th style="width: 120px;">Status</th>
                <th style="width: 150px;">Touch Availability</th>
                <th style="width: 120px;">Serial Number</th>
                <th style="width: 80px; position: sticky; right: 0; background: #2f364c; z-index: 1; box-shadow: -2px 0 4px rgba(0,0,0,0.1);">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $sno = $offset + 1; ?>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="width: 80px;"><?= $sno++ ?></td>
                        <td style="width: 120px;"><?= htmlspecialchars($row['asset_id']) ?></td>
                        <td style="width: 100px;"><?= htmlspecialchars($row['brand_name']) ?></td>
                        <td style="width: 150px;"><?= $row['date_of_allocation'] ?: '-' ?></td>
                        <td style="width: 120px;"><?= htmlspecialchars($row['condition']) ?></td>
                        <td style="width: 120px;"><?= htmlspecialchars($row['status']) ?></td>
                        <td style="width: 150px;"><?= htmlspecialchars($row['touch_availability']) ?></td>
                        <td style="width: 120px;"><?= htmlspecialchars($row['serial_number']) ?: '-' ?></td>
                        <td style="width: 80px; position: sticky; right: 0; background: #fff; z-index: 1; box-shadow: -2px 0 4px rgba(0,0,0,0.1);" class="actions">
                            <i class="fas fa-edit" onclick='editAsset(<?= json_encode($row) ?>)'></i>
                            <i class="fas fa-trash-alt" onclick="openDeleteModal('<?= htmlspecialchars($row['asset_id']) ?>')"></i>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="9">No assets found.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=assets&page_num=<?= $page - 1 ?>" class="prev">« Prev</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=assets&page_num=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=assets&page_num=<?= $page + 1 ?>" class="next">Next »</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<style>
div::-webkit-scrollbar { display: none; }
</style>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = "Add New Asset";
    document.getElementById('edit_asset_id').value = '';
    document.getElementById('asset_id').value = '';
    document.getElementById('brand_id').value = '';
    document.getElementById('date_of_allocation').value = '';
    document.getElementById('condition').value = '';
    document.getElementById('touch_availability').value = '';
    document.getElementById('serial_number').value = '';
    document.getElementById('assetModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('assetModal').style.display = 'none';
}

function editAsset(asset) {
    document.getElementById('modalTitle').innerText = "Edit Asset";
    document.getElementById('edit_asset_id').value = asset.asset_id;
    document.getElementById('asset_id').value = asset.asset_id;
    document.getElementById('asset_id').setAttribute('readonly', 'readonly');
    document.getElementById('brand_id').value = asset.brand_id;
    document.getElementById('date_of_allocation').value = asset.date_of_allocation || '';
    document.getElementById('condition').value = asset.condition;
    document.getElementById('touch_availability').value = asset.touch_availability;
    document.getElementById('serial_number').value = asset.serial_number || '';
    document.getElementById('assetModal').style.display = 'flex';
}

function openDeleteModal(asset_id) {
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    confirmDeleteBtn.onclick = function() {
        window.location.href = '?page=assets&delete=' + encodeURIComponent(asset_id);
    };
    deleteModal.style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

window.onclick = function(e) {
    const modal = document.getElementById('assetModal');
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