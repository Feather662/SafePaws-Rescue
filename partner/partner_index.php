<?php
// partner/partner_index.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$error = '';
$success = '';

// Handle partner organisation deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $org_id = filter_input(INPUT_POST, 'org_id', FILTER_VALIDATE_INT);
    if ($org_id) {
        try {
            $stmt = $dbh->prepare("DELETE FROM partner_organisations WHERE organisation_id = :id");
            $stmt->execute([':id' => $org_id]);
            $success = "Partner organisation deleted successfully.";
        } catch (PDOException $e) {
            error_log("Partner delete error: " . $e->getMessage());
            $error = "Failed to delete partner organisation.";
        }
    }
}

// Retrieve filter criteria
$search = trim($_GET['search'] ?? '');
$type_filter = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM partner_organisations WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE :s OR contact_person LIKE :s OR address LIKE :s)";
    $params[':s'] = "%" . $search . "%";
}

if (!empty($type_filter)) {
    $sql .= " AND organisation_type = :type";
    $params[':type'] = $type_filter;
}

$sql .= " ORDER BY organisation_id DESC";

try {
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $organisations = $stmt->fetchAll();

    // Fetch distinct types for dynamic dropdown filtering
    $types = $dbh->query("SELECT DISTINCT organisation_type FROM partner_organisations ORDER BY organisation_type")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Partner fetch error: " . $e->getMessage());
    $organisations = [];
    $types = [];
    $error = "Failed to load partner organisations.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Organisations - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Partner Organisations</h1>
            <p class="text-muted small mb-0">Manage veterinary clinics, shelters, and supply partners.</p>
        </div>
        <a href="partner_update.php" class="btn btn-primary btn-sm">+ Add Partner</a>
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
            <form method="GET" action="partner_index.php" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Search by name, contact, or address..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="type">
                        <option value="">All Organisation Types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" <?= ($type_filter === $t) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm flex-grow-1">Filter</button>
                    <a href="partner_index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
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
                    <th>Organisation</th>
                    <th>Type</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Website</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($organisations)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No partner organisations found matching your criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($organisations as $org): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$org['organisation_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($org['organisation_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($org['contact_person'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small">
                                <?= htmlspecialchars($org['phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                <a href="mailto:<?= htmlspecialchars($org['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none"><?= htmlspecialchars($org['email'], ENT_QUOTES, 'UTF-8') ?></a>
                            </td>
                            <td>
                                <?php if (!empty($org['website'])): ?>
                                    <a href="<?= htmlspecialchars($org['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm py-0">Visit Site</a>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="partner_view.php?id=<?= $org['organisation_id'] ?>" class="btn btn-outline-secondary btn-sm py-0">Inspect</a>
                                <a href="partner_update.php?id=<?= $org['organisation_id'] ?>" class="btn btn-outline-dark btn-sm py-0">Edit</a>
                                <form method="POST" action="partner_index.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this organisation?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="org_id" value="<?= $org['organisation_id'] ?>">
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