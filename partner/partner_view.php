<?php
// partner/partner_view.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$org_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$org_id) {
    header('Location: partner_index.php');
    exit;
}

try {
    $stmt = $dbh->prepare("SELECT * FROM partner_organisations WHERE organisation_id = :id LIMIT 1");
    $stmt->execute([':id' => $org_id]);
    $org = $stmt->fetch();

    if (!$org) {
        header('Location: partner_index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Partner View Error: " . $e->getMessage());
    die("Database error while loading partner organisation details.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect Partner - <?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="partner_index.php" class="text-decoration-none small">&larr; Back to Partners</a>
            <h1 class="h3 fw-bold mt-2"><?= htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="badge bg-secondary"><?= htmlspecialchars($org['organisation_type'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="partner_update.php?id=<?= $org['organisation_id'] ?>" class="btn btn-outline-dark btn-sm">Edit Record</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <tbody>
                <tr><th style="width: 25%;">ID</th><td><?= htmlspecialchars((string)$org['organisation_id'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Contact Person</th><td><?= htmlspecialchars($org['contact_person'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Email</th><td><a href="mailto:<?= htmlspecialchars($org['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($org['email'], ENT_QUOTES, 'UTF-8') ?></a></td></tr>
                <tr><th>Phone</th><td><?= htmlspecialchars($org['phone'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr><th>Address</th><td><?= htmlspecialchars($org['address'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                <tr>
                    <th>Website</th>
                    <td>
                        <?php if (!empty($org['website'])): ?>
                            <a href="<?= htmlspecialchars($org['website'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($org['website'], ENT_QUOTES, 'UTF-8') ?> &nearr;
                            </a>
                        <?php else: ?>
                            <span class="text-muted">None registered</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Notes</th><td><?= nl2br(htmlspecialchars($org['notes'] ?? 'None recorded.', ENT_QUOTES, 'UTF-8')) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>