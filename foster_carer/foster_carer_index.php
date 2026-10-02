<?php
// foster_carer/foster_carer_index.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$error = '';
$success = '';

// Toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $carer_id = filter_input(INPUT_POST, 'carer_id', FILTER_VALIDATE_INT);
    if ($carer_id) {
        try {
            $stmt = $dbh->prepare("UPDATE foster_carers SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE foster_carer_id = :id");
            $stmt->execute([':id' => $carer_id]);
            $success = "Carer status toggled successfully.";
        } catch (PDOException $e) {
            error_log("Status toggle error: " . $e->getMessage());
            $error = "Failed to update carer status.";
        }
    }
}

// Delete foster carer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $carer_id = filter_input(INPUT_POST, 'carer_id', FILTER_VALIDATE_INT);
    if ($carer_id) {
        try {
            $stmt = $dbh->prepare("DELETE FROM foster_carers WHERE foster_carer_id = :id");
            $stmt->execute([':id' => $carer_id]);
            $success = "Foster carer deleted successfully.";
        } catch (PDOException $e) {
            error_log("Carer delete error: " . $e->getMessage());
            $error = "Failed to delete foster carer.";
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Compute dynamic remaining capacity via LEFT JOIN count
$sql = "SELECT fc.*, COUNT(a.animal_id) AS assigned_count,
        (fc.capacity - COUNT(a.animal_id)) AS remaining_capacity
        FROM foster_carers fc
        LEFT JOIN animals a ON fc.foster_carer_id = a.foster_carer_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (fc.first_name LIKE :s OR fc.last_name LIKE :s OR fc.suburb LIKE :s OR fc.preferred_animal_type LIKE :s)";
    $params[':s'] = "%" . $search . "%";
}

if (!empty($status_filter) && in_array($status_filter, ['Active', 'Inactive'])) {
    $sql .= " AND fc.status = :status";
    $params[':status'] = $status_filter;
}

$sql .= " GROUP BY fc.foster_carer_id ORDER BY fc.foster_carer_id DESC";

try {
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $carers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Carer fetch error: " . $e->getMessage());
    $carers = [];
    $error = "Failed to retrieve foster carers.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foster Carers - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Foster Carers</h1>
            <p class="text-muted small mb-0">Track foster carers, capacity allowances, and active placements.</p>
        </div>
        <a href="foster_carer_update.php" class="btn btn-primary btn-sm">+ Add Foster Carer</a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="foster_carer_index.php" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Search by name, suburb, or preferred animal..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Statuses</option>
                        <option value="Active" <?= ($status_filter === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($status_filter === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm flex-grow-1">Filter</button>
                    <a href="foster_carer_index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Suburb</th>
                    <th>Preference</th>
                    <th class="text-center">Capacity</th>
                    <th class="text-center">Assigned</th>
                    <th class="text-center">Remaining</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($carers)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No foster carers found matching criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($carers as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$c['foster_carer_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td class="small">
                                <?= htmlspecialchars($c['phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                <a href="mailto:<?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none"><?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?></a>
                            </td>
                            <td><?= htmlspecialchars($c['suburb'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['preferred_animal_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-center"><?= htmlspecialchars((string)$c['capacity'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center fw-bold"><?= htmlspecialchars((string)$c['assigned_count'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-center">
                                <?php if ($c['remaining_capacity'] <= 0): ?>
                                    <span class="badge bg-danger">Full (<?= htmlspecialchars((string)$c['remaining_capacity'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= htmlspecialchars((string)$c['remaining_capacity'], ENT_QUOTES, 'UTF-8') ?> available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= ($c['status'] === 'Active') ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="foster_carer_view.php?id=<?= $c['foster_carer_id'] ?>" class="btn btn-outline-secondary btn-sm py-0">Inspect</a>
                                <a href="foster_carer_update.php?id=<?= $c['foster_carer_id'] ?>" class="btn btn-outline-dark btn-sm py-0">Edit</a>
                                <form method="POST" action="foster_carer_index.php" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="carer_id" value="<?= $c['foster_carer_id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-sm py-0 text-dark">Toggle</button>
                                </form>
                                <form method="POST" action="foster_carer_index.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this carer?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="carer_id" value="<?= $c['foster_carer_id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>