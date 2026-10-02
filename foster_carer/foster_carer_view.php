<?php
// foster_carer/foster_carer_view.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$carer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$carer_id) {
    header('Location: foster_carer_index.php');
    exit;
}

try {
    $stmt = $dbh->prepare("SELECT * FROM foster_carers WHERE foster_carer_id = :id LIMIT 1");
    $stmt->execute([':id' => $carer_id]);
    $carer = $stmt->fetch();

    if (!$carer) {
        header('Location: foster_carer_index.php');
        exit;
    }

    // Retrieve all animals actively placed under this carer's responsibility
    $animal_stmt = $dbh->prepare("
        SELECT a.animal_id, a.name, a.sex, a.status, a.date_admitted, b.breed_name, s.species_name
        FROM animals a
        JOIN breeds b ON a.breed_id = b.breed_id
        JOIN species s ON b.species_id = s.species_id
        WHERE a.foster_carer_id = :id
    ");
    $animal_stmt->execute([':id' => $carer_id]);
    $assigned_animals = $animal_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Carer view error: " . $e->getMessage());
    die("A database error occurred while fetching details.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foster Carer Details - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="foster_carer_index.php" class="text-decoration-none small">&larr; Back to Foster Carers</a>
            <h1 class="h3 fw-bold mt-2"><?= htmlspecialchars($carer['first_name'] . ' ' . $carer['last_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="badge <?= ($carer['status'] === 'Active') ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($carer['status'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="foster_carer_update.php?id=<?= $carer['foster_carer_id'] ?>" class="btn btn-outline-dark btn-sm">Edit Carer</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-semibold">Carer Profile Information</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <tbody>
                <tr><th style="width: 25%;">Carer ID</th><td><?= htmlspecialchars((string)$carer['foster_carer_id'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Email Address</th><td><a href="mailto:<?= htmlspecialchars($carer['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($carer['email'], ENT_QUOTES, 'UTF-8') ?></a></td></tr>
                <tr><th>Phone Number</th><td><?= htmlspecialchars($carer['phone'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Residential Suburb</th><td><?= htmlspecialchars($carer['suburb'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Preferred Animal</th><td><?= htmlspecialchars($carer['preferred_animal_type'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Maximum Capacity</th><td><?= htmlspecialchars((string)$carer['capacity'], ENT_QUOTES, 'UTF-8') ?> animal(s)</td></tr>
                <tr><th>Notes</th><td><?= nl2br(htmlspecialchars($carer['notes'] ?? 'None provided.', ENT_QUOTES, 'UTF-8')) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assigned Animals Overview -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-semibold">Currently Assigned Animals</h5>
            <span class="badge bg-primary"><?= count($assigned_animals) ?> / <?= htmlspecialchars((string)$carer['capacity'], ENT_QUOTES, 'UTF-8') ?> Placed</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Animal ID</th>
                    <th>Name</th>
                    <th>Species & Breed</th>
                    <th>Sex</th>
                    <th>Adoption Status</th>
                    <th>Date Admitted</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($assigned_animals)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No animals are currently placed with this foster carer.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assigned_animals as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$a['animal_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($a['species_name'] . ' - ' . $a['breed_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($a['sex'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($a['date_admitted'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end pe-4">
                                <a href="../animals/animal_view.php?id=<?= $a['animal_id'] ?>" class="btn btn-outline-secondary btn-sm py-0">View Profile</a>
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