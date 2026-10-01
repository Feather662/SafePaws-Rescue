<?php
// index.php

// Enforce session authentication and secure database connection
require_once __DIR__ . '/config/connection.php';
require_once __DIR__ . '/authentication.php';

// Initialize core dashboard statistic counters
$stats = [
    'in_care'      => 0,
    'available'    => 0,
    'with_foster'  => 0,
    'pending_apps' => 0,
    'new_messages' => 0
];

$error_message = '';

try {
    // Count animals currently housed in care
    $stmt = $dbh->query("SELECT COUNT(*) FROM animals WHERE status = 'In care'");
    $stats['in_care'] = (int)$stmt->fetchColumn();

    // Count animals currently available for adoption
    $stmt = $dbh->query("SELECT COUNT(*) FROM animals WHERE status = 'Available for adoption'");
    $stats['available'] = (int)$stmt->fetchColumn();

    // Count animals actively placed with a foster carer
    $stmt = $dbh->query("SELECT COUNT(*) FROM animals WHERE foster_carer_id IS NOT NULL");
    $stats['with_foster'] = (int)$stmt->fetchColumn();

    // Count pending adoption applications awaiting review
    $stmt = $dbh->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN ('New', 'Under review')");
    $stats['pending_apps'] = (int)$stmt->fetchColumn();

    // Count incoming contact enquiries awaiting staff response
    $stmt = $dbh->query("SELECT COUNT(*) FROM contact_messages WHERE replied = 0");
    $stats['new_messages'] = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    // Log internal query failure and display sanitized error notification
    error_log("Dashboard Metrics Query Error: " . $e->getMessage());
    $error_message = "Unable to load real-time statistics at this moment.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SafePaws Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Centralized navigation bar -->
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="container pb-5">
    <!-- Header Section -->
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-1">Administrative Dashboard</h1>
        <p class="text-muted small mb-0">Overview of shelter operations, active animals, and incoming workflows.</p>
    </div>

    <!-- Error Alert Notification -->
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger py-2" role="alert">
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Structured Operational Overview Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fw-semibold">System Operational Overview</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 50%;">Metric Description</th>
                    <th scope="col" class="text-center" style="width: 25%;">Current Total</th>
                    <th scope="col" class="text-end pe-4" style="width: 25%;">Quick Action</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td class="fw-semibold">Animals Currently In Care</td>
                    <td class="text-center"><span class="badge bg-secondary px-3 py-2 fs-6"><?= $stats['in_care'] ?></span></td>
                    <td class="text-end pe-4">
                        <a href="animals/animal_index.php?status=In+care" class="btn btn-outline-secondary btn-sm">View In-Care</a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Animals Available for Adoption</td>
                    <td class="text-center"><span class="badge bg-success px-3 py-2 fs-6"><?= $stats['available'] ?></span></td>
                    <td class="text-end pe-4">
                        <a href="animals/animal_index.php?status=Available+for+adoption" class="btn btn-outline-success btn-sm">View Available</a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Animals Placed with Foster Carers</td>
                    <td class="text-center"><span class="badge bg-primary px-3 py-2 fs-6"><?= $stats['with_foster'] ?></span></td>
                    <td class="text-end pe-4">
                        <a href="foster_carer/foster_carer_index.php" class="btn btn-outline-primary btn-sm">Inspect Placements</a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Pending Adoption Applications (New / Under Review)</td>
                    <td class="text-center"><span class="badge bg-warning text-dark px-3 py-2 fs-6"><?= $stats['pending_apps'] ?></span></td>
                    <td class="text-end pe-4">
                        <a href="application/application_index.php?status=New" class="btn btn-outline-warning btn-sm text-dark">Review Applications</a>
                    </td>
                </tr>
                <tr>
                    <td class="fw-semibold">Unanswered Public Contact Enquiries</td>
                    <td class="text-center"><span class="badge bg-danger px-3 py-2 fs-6"><?= $stats['new_messages'] ?></span></td>
                    <td class="text-end pe-4">
                        <a href="contacts/administration.php" class="btn btn-outline-danger btn-sm">Respond to Messages</a>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>