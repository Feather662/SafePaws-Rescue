<?php
// applications/application_index.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$error = '';
$success = '';

// Handle status workflow updates directly from list actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $app_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['New', 'Under review', 'Approved', 'Rejected'];

    if ($app_id && in_array($new_status, $allowed_statuses, true)) {
        try {
            $stmt = $dbh->prepare("UPDATE adoption_applications SET status = :status WHERE application_id = :id");
            $stmt->execute([':status' => $new_status, ':id' => $app_id]);
            $success = "Application #{$app_id} status updated to '{$new_status}'.";
        } catch (PDOException $e) {
            error_log("Application status update error: " . $e->getMessage());
            $error = "Failed to update application status.";
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$animal_filter = filter_input(INPUT_GET, 'animal_id', FILTER_VALIDATE_INT);

$sql = "SELECT aa.*, a.name AS animal_name, a.status AS animal_status
        FROM adoption_applications aa
        JOIN animals a ON aa.animal_id = a.animal_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (aa.applicant_name LIKE :s OR aa.email LIKE :s OR aa.phone LIKE :s OR a.name LIKE :s)";
    $params[':s'] = "%" . $search . "%";
}

if (!empty($status_filter) && in_array($status_filter, ['New', 'Under review', 'Approved', 'Rejected'], true)) {
    $sql .= " AND aa.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($animal_filter)) {
    $sql .= " AND aa.animal_id = :animal_id";
    $params[':animal_id'] = $animal_filter;
}

$sql .= " ORDER BY aa.application_date DESC";

try {
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Applications fetch error: " . $e->getMessage());
    $applications = [];
    $error = "Failed to load adoption applications.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoption Applications - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5">
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Adoption Applications</h1>
        <p class="text-muted small mb-0">Review public adoption submissions and update candidate workflow statuses.</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="application_index.php" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Search applicant name, email, or animal..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Review Statuses</option>
                        <option value="New" <?= ($status_filter === 'New') ? 'selected' : '' ?>>New</option>
                        <option value="Under review" <?= ($status_filter === 'Under review') ? 'selected' : '' ?>>Under review</option>
                        <option value="Approved" <?= ($status_filter === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= ($status_filter === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm flex-grow-1">Filter</button>
                    <a href="application_index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Date Submitted</th>
                    <th>Applicant</th>
                    <th>Target Animal</th>
                    <th>Contact Details</th>
                    <th>Housing</th>
                    <th>Workflow Status</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No adoption applications found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string)$app['application_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars(date('d M Y, H:i', strtotime($app['application_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($app['applicant_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>
                                <a href="../animals/animal_view.php?id=<?= $app['animal_id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= htmlspecialchars($app['animal_name'], ENT_QUOTES, 'UTF-8') ?> &nearr;
                                </a>
                            </td>
                            <td class="small">
                                <?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                <a href="mailto:<?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none"><?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?></a>
                                (<?= htmlspecialchars($app['suburb'], ENT_QUOTES, 'UTF-8') ?>)
                            </td>
                            <td class="small"><?= htmlspecialchars($app['housing_type'] . ' (' . $app['home_ownership'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $badgeClass = match($app['status']) {
                                    'New' => 'bg-primary',
                                    'Under review' => 'bg-warning text-dark',
                                    'Approved' => 'bg-success',
                                    'Rejected' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="application_view.php?id=<?= $app['application_id'] ?>" class="btn btn-outline-primary btn-sm py-0">Review</a>
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