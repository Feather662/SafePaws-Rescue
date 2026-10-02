<?php
// applications/application_view.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$app_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$app_id) {
    header('Location: application_index.php');
    exit;
}

$error = '';
$success = '';

// Update application workflow status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_status') {
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['New', 'Under review', 'Approved', 'Rejected'];

    if (in_array($new_status, $allowed_statuses, true)) {
        try {
            $stmt = $dbh->prepare("UPDATE adoption_applications SET status = :status WHERE application_id = :id");
            $stmt->execute([':status' => $new_status, ':id' => $app_id]);
            $success = "Application status successfully updated to '{$new_status}'.";
        } catch (PDOException $e) {
            error_log("Application status update error: " . $e->getMessage());
            $error = "Failed to update status.";
        }
    } else {
        $error = "Invalid status selected.";
    }
}

try {
    $stmt = $dbh->prepare("
        SELECT aa.*,
               a.name AS animal_name, a.sex AS animal_sex, a.status AS animal_status,
               a.profile_image, a.date_of_birth, b.breed_name, s.species_name
        FROM adoption_applications aa
        JOIN animals a ON aa.animal_id = a.animal_id
        JOIN breeds b ON a.breed_id = b.breed_id
        JOIN species s ON b.species_id = s.species_id
        WHERE aa.application_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $app_id]);
    $application = $stmt->fetch();

    if (!$application) {
        header('Location: application_index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Application view query error: " . $e->getMessage());
    die("Database error while loading application details.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Application #<?= htmlspecialchars((string)$application['application_id'], ENT_QUOTES, 'UTF-8') ?> - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 900px;">
    <div class="mb-4">
        <a href="application_index.php" class="text-decoration-none small">&larr; Back to Applications</a>
        <h1 class="h3 fw-bold mt-2">Application #<?= htmlspecialchars((string)$application['application_id'], ENT_QUOTES, 'UTF-8') ?> Review</h1>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Workflow Status Controller -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="POST" action="application_view.php?id=<?= $application['application_id'] ?>" class="row g-2 align-items-center">
                <input type="hidden" name="action" value="save_status">
                <div class="col-auto">
                    <label for="status" class="col-form-label fw-semibold small">Current Workflow Status:</label>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="status" name="status">
                        <option value="New" <?= ($application['status'] === 'New') ? 'selected' : '' ?>>New</option>
                        <option value="Under review" <?= ($application['status'] === 'Under review') ? 'selected' : '' ?>>Under review</option>
                        <option value="Approved" <?= ($application['status'] === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= ($application['status'] === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Update Workflow Status</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <!-- Target Animal Summary Card -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-semibold">Target Animal</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($application['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $application['profile_image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($application['profile_image'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($application['animal_name'], ENT_QUOTES, 'UTF-8') ?>"
                             class="img-fluid rounded mb-3" style="max-height: 180px; object-fit: cover;">
                    <?php else: ?>
                        <div class="p-4 bg-light text-muted small rounded mb-3">No photo available</div>
                    <?php endif; ?>

                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($application['animal_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($application['species_name'] . ' (' . $application['breed_name'] . ')', ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="badge bg-secondary mb-3"><?= htmlspecialchars($application['animal_status'], ENT_QUOTES, 'UTF-8') ?></span>

                    <div class="d-grid">
                        <a href="../animals/animal_view.php?id=<?= $application['animal_id'] ?>" class="btn btn-outline-secondary btn-sm">
                            Inspect Animal Profile &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Questionnaire Card -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-semibold">Applicant Questionnaire</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                        <tr><th style="width: 35%;">Submission Date</th><td class="small"><?= htmlspecialchars($application['application_date'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Applicant Name</th><td><strong><?= htmlspecialchars($application['applicant_name'], ENT_QUOTES, 'UTF-8') ?></strong></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= htmlspecialchars($application['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($application['email'], ENT_QUOTES, 'UTF-8') ?></a></td></tr>
                        <tr><th>Phone</th><td><?= htmlspecialchars($application['phone'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Suburb</th><td><?= htmlspecialchars($application['suburb'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Housing Type</th><td><?= htmlspecialchars($application['housing_type'] . ' (' . $application['home_ownership'] . ')', ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Existing Pets</th><td><?= nl2br(htmlspecialchars($application['other_pets'] ?? 'None', ENT_QUOTES, 'UTF-8')) ?></td></tr>
                        <tr><th>Adoption Motive</th><td class="small"><?= nl2br(htmlspecialchars($application['reason_for_adoption'], ENT_QUOTES, 'UTF-8')) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>