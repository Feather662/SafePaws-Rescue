<?php
// animals/animal_index.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$error = '';
$success = '';

// Handle animal profile deletion along with associated uploaded photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $animal_id = filter_input(INPUT_POST, 'animal_id', FILTER_VALIDATE_INT);
    if ($animal_id) {
        try {
            // Retrieve file name to delete physical image from disk
            $img_stmt = $dbh->prepare("SELECT profile_image FROM animals WHERE animal_id = :id");
            $img_stmt->execute([':id' => $animal_id]);
            $image_file = $img_stmt->fetchColumn();

            if ($image_file && file_exists(__DIR__ . '/../uploads/' . $image_file)) {
                unlink(__DIR__ . '/../uploads/' . $image_file);
            }

            // Cascading foreign keys handle adoption application cleanup
            $del_stmt = $dbh->prepare("DELETE FROM animals WHERE animal_id = :id");
            $del_stmt->execute([':id' => $animal_id]);
            $success = "Animal profile and related records deleted successfully.";
        } catch (PDOException $e) {
            error_log("Animal delete error: " . $e->getMessage());
            $error = "Failed to delete animal record.";
        }
    }
}

// Retrieve multi-criteria filter values
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$species_filter = filter_input(INPUT_GET, 'species_id', FILTER_VALIDATE_INT);
$desexed_filter = isset($_GET['desexed']) && $_GET['desexed'] !== '' ? (int)$_GET['desexed'] : '';

// Build parameterized query with table joins
$sql = "SELECT a.*, b.breed_name, s.species_name, fc.first_name AS carer_first, fc.last_name AS carer_last
        FROM animals a
        JOIN breeds b ON a.breed_id = b.breed_id
        JOIN species s ON b.species_id = s.species_id
        LEFT JOIN foster_carers fc ON a.foster_carer_id = fc.foster_carer_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (a.name LIKE :s OR a.description LIKE :s OR b.breed_name LIKE :s)";
    $params[':s'] = "%" . $search . "%";
}

if (!empty($status_filter) && in_array($status_filter, ['In care', 'Available for adoption', 'Adoption pending', 'Adopted'], true)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($species_filter)) {
    $sql .= " AND s.species_id = :species_id";
    $params[':species_id'] = $species_filter;
}

if ($desexed_filter === 0 || $desexed_filter === 1) {
    $sql .= " AND a.desexed = :desexed";
    $params[':desexed'] = $desexed_filter;
}

$sql .= " ORDER BY a.animal_id DESC";

try {
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    $animals = $stmt->fetchAll();

    // Load available species options for dropdown
    $species_list = $dbh->query("SELECT species_id, species_name FROM species ORDER BY species_name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Animal fetch error: " . $e->getMessage());
    $animals = [];
    $species_list = [];
    $error = "Failed to load animal records.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Profiles - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Animal Profiles</h1>
            <p class="text-muted small mb-0">Manage rescue intake, medical records, adoption status, and placements.</p>
        </div>
        <a href="animal_update.php" class="btn btn-primary btn-sm">+ Add New Animal</a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Multi-criteria Filter Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="animal_index.php" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" name="search"
                           placeholder="Keyword (name, breed, etc.)..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Adoption Statuses</option>
                        <option value="In care" <?= ($status_filter === 'In care') ? 'selected' : '' ?>>In care</option>
                        <option value="Available for adoption" <?= ($status_filter === 'Available for adoption') ? 'selected' : '' ?>>Available for adoption</option>
                        <option value="Adoption pending" <?= ($status_filter === 'Adoption pending') ? 'selected' : '' ?>>Adoption pending</option>
                        <option value="Adopted" <?= ($status_filter === 'Adopted') ? 'selected' : '' ?>>Adopted</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="species_id">
                        <option value="">All Species</option>
                        <?php foreach ($species_list as $sp): ?>
                            <option value="<?= $sp['species_id'] ?>" <?= ($species_filter == $sp['species_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sp['species_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="desexed">
                        <option value="">Desexed (Any)</option>
                        <option value="1" <?= ($desexed_filter === 1) ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= ($desexed_filter === 0) ? 'selected' : '' ?>>No</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm flex-grow-1">Filter</button>
                    <a href="animal_index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Animal Records Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Photo</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Species & Breed</th>
                    <th>Sex</th>
                    <th>Desexed</th>
                    <th>Admitted</th>
                    <th>Status</th>
                    <th>Placement</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($animals)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">No animal records found matching current criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($animals as $a): ?>
                        <tr>
                            <td>
                                <?php if (!empty($a['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $a['profile_image'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($a['profile_image'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?>"
                                         class="rounded object-fit-cover" width="48" height="48">
                                <?php else: ?>
                                    <div class="bg-light border rounded text-center text-muted small py-2" style="width: 48px; height: 48px; font-size: 10px;">No Pic</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$a['animal_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($a['species_name'] . ' (' . $a['breed_name'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($a['sex'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $a['desexed'] ? '<span class="text-success">Yes</span>' : '<span class="text-muted">No</span>' ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($a['date_admitted'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $statusBadge = match($a['status']) {
                                    'In care' => 'bg-secondary',
                                    'Available for adoption' => 'bg-success',
                                    'Adoption pending' => 'bg-warning text-dark',
                                    'Adopted' => 'bg-dark',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <?php if (!empty($a['foster_carer_id'])): ?>
                                    <a href="../foster_carer/foster_carer_view.php?id=<?= $a['foster_carer_id'] ?>" class="text-decoration-none small">
                                        <?= htmlspecialchars($a['carer_first'] . ' ' . $a['carer_last'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Shelter</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="animal_view.php?id=<?= $a['animal_id'] ?>" class="btn btn-outline-secondary btn-sm py-0">Inspect</a>
                                <a href="animal_update.php?id=<?= $a['animal_id'] ?>" class="btn btn-outline-dark btn-sm py-0">Edit</a>
                                <form method="POST" action="animal_index.php" class="d-inline" onsubmit="return confirm('Permanently delete this animal profile?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="animal_id" value="<?= $a['animal_id'] ?>">
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