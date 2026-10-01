<?php
// animals/animal_view.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$animal_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$animal_id) {
    header('Location: animal_index.php');
    exit;
}

try {
    // Retrieve comprehensive profile details with related species, breed, and foster placement
    $stmt = $dbh->prepare("
        SELECT a.*, b.breed_name, s.species_name,
               fc.first_name AS carer_first, fc.last_name AS carer_last,
               fc.phone AS carer_phone, fc.email AS carer_email, fc.suburb AS carer_suburb
        FROM animals a
        JOIN breeds b ON a.breed_id = b.breed_id
        JOIN species s ON b.species_id = s.species_id
        LEFT JOIN foster_carers fc ON a.foster_carer_id = fc.foster_carer_id
        WHERE a.animal_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $animal_id]);
    $animal = $stmt->fetch();

    if (!$animal) {
        header('Location: animal_index.php');
        exit;
    }

    // Retrieve full list of applications targeting this animal
    $app_stmt = $dbh->prepare("
        SELECT application_id, applicant_name, email, phone, suburb,
               housing_type, home_ownership, application_date, status
        FROM adoption_applications
        WHERE animal_id = :id
        ORDER BY application_date DESC
    ");
    $app_stmt->execute([':id' => $animal_id]);
    $applications = $app_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Animal View Error: " . $e->getMessage());
    die("Database error while retrieving animal profile.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect Animal - <?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 960px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="animal_index.php" class="text-decoration-none small">&larr; Back to Animals</a>
            <h1 class="h3 fw-bold mt-2"><?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="badge bg-secondary"><?= htmlspecialchars($animal['species_name'] . ' - ' . $animal['breed_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="animal_update.php?id=<?= $animal['animal_id'] ?>" class="btn btn-outline-dark btn-sm">Edit Profile</a>
    </div>

    <div class="row g-4 mb-4">
        <!-- Photo and Carer Placement Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($animal['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $animal['profile_image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($animal['profile_image'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($animal['name'], ENT_QUOTES, 'UTF-8') ?>"
                             class="img-fluid rounded shadow-sm object-fit-cover w-100" style="max-height: 240px;">
                    <?php else: ?>
                        <div class="bg-light text-muted p-5 rounded">No photo registered</div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <span class="badge bg-success px-3 py-2 fs-6"><?= htmlspecialchars($animal['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>

            <!-- Foster Placement Block -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-semibold">Current Accommodation</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($animal['foster_carer_id'])): ?>
                        <p class="mb-1"><strong>Foster Carer:</strong></p>
                        <!-- Corrected relative path to ../foster_carer/ -->
                        <p class="mb-2">
                            <a href="../foster_carer/foster_carer_view.php?id=<?= $animal['foster_carer_id'] ?>" class="fw-semibold">
                                <?= htmlspecialchars($animal['carer_first'] . ' ' . $animal['carer_last'], ENT_QUOTES, 'UTF-8') ?> &nearr;
                            </a>
                        </p>
                        <p class="small text-muted mb-1">Phone: <?= htmlspecialchars($animal['carer_phone'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="small text-muted mb-1">Email: <?= htmlspecialchars($animal['carer_email'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="small text-muted mb-0">Suburb: <?= htmlspecialchars($animal['carer_suburb'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Currently housed at the <strong>Main Shelter Facility</strong>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Specifications Card -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-semibold">Animal Specifications</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                        <tr><th style="width: 30%;">Animal ID</th><td>#<?= htmlspecialchars((string)$animal['animal_id'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Sex / Desexed</th><td><?= htmlspecialchars($animal['sex'], ENT_QUOTES, 'UTF-8') ?> / <?= $animal['desexed'] ? 'Desexed' : 'Not Desexed' ?></td></tr>
                        <tr><th>Estimated Birth Date</th><td><?= htmlspecialchars($animal['date_of_birth'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Shelter Admission</th><td><?= htmlspecialchars($animal['date_admitted'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Temperament & Notes</th><td><?= nl2br(htmlspecialchars($animal['description'], ENT_QUOTES, 'UTF-8')) ?></td></tr>
                        <tr><th>Medical Records</th><td><?= nl2br(htmlspecialchars($animal['medical_notes'] ?? 'None recorded', ENT_QUOTES, 'UTF-8')) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Adoption Applications -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold">Target Adoption Applications</h5>
            <span class="badge bg-primary"><?= count($applications) ?> Submitted</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>App ID</th>
                    <th>Applicant Name</th>
                    <th>Contact</th>
                    <th>Housing Context</th>
                    <th>Date</th>
                    <th>Workflow Status</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No adoption applications registered for this animal.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>#<?= htmlspecialchars((string)$app['application_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($app['applicant_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td class="small">
                                <?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                <a href="mailto:<?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?></a>
                            </td>
                            <td class="small"><?= htmlspecialchars($app['housing_type'] . ' (' . $app['home_ownership'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars(date('d M Y', strtotime($app['application_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $appBadge = match($app['status']) {
                                    'New' => 'bg-primary',
                                    'Under review' => 'bg-warning text-dark',
                                    'Approved' => 'bg-success',
                                    'Rejected' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $appBadge ?>"><?= htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="../application/application_view.php?id=<?= $app['application_id'] ?>" class="btn btn-outline-primary btn-sm py-0">Review</a>
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