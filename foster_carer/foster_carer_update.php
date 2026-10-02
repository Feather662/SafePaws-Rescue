<?php
// foster_carer/foster_carer_update.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$carer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !empty($carer_id);

$errors = [];
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$suburb = '';
$preferred_animal_type = '';
$capacity = 1;
$status = 'Active';
$notes = '';

if ($is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $dbh->prepare("SELECT * FROM foster_carers WHERE foster_carer_id = :id LIMIT 1");
        $stmt->execute([':id' => $carer_id]);
        $carer = $stmt->fetch();
        if ($carer) {
            $first_name = $carer['first_name'];
            $last_name = $carer['last_name'];
            $email = $carer['email'];
            $phone = $carer['phone'];
            $suburb = $carer['suburb'];
            $preferred_animal_type = $carer['preferred_animal_type'];
            $capacity = (int)$carer['capacity'];
            $status = $carer['status'];
            $notes = $carer['notes'] ?? '';
        } else {
            header('Location: foster_carer_index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Carer fetch error: " . $e->getMessage());
        header('Location: foster_carer_index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $suburb = trim($_POST['suburb'] ?? '');
    $preferred_animal_type = trim($_POST['preferred_animal_type'] ?? '');
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? 'Active');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($suburb)) $errors[] = "Suburb is required.";
    if (empty($preferred_animal_type)) $errors[] = "Preferred animal type is required.";
    if ($capacity === false || $capacity <= 0) $errors[] = "Capacity must be a positive number greater than 0.";
    if (!in_array($status, ['Active', 'Inactive'])) $errors[] = "Invalid status selected.";

    // Prevent duplicate email registrations
    if (empty($errors)) {
        try {
            $check_sql = "SELECT foster_carer_id FROM foster_carers WHERE email = :email";
            $check_params = [':email' => $email];
            if ($is_edit) {
                $check_sql .= " AND foster_carer_id != :id";
                $check_params[':id'] = $carer_id;
            }
            $stmt = $dbh->prepare($check_sql);
            $stmt->execute($check_params);
            if ($stmt->fetch()) {
                $errors[] = "Email is already registered by another foster carer.";
            }
        } catch (PDOException $e) {
            error_log("Email check error: " . $e->getMessage());
            $errors[] = "Database check failed.";
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $dbh->prepare("
                    UPDATE foster_carers SET
                        first_name = :fn, last_name = :ln, email = :em,
                        phone = :ph, suburb = :sub, preferred_animal_type = :pref,
                        capacity = :cap, status = :st, notes = :nt
                    WHERE foster_carer_id = :id
                ");
                $stmt->execute([
                        ':fn' => $first_name, ':ln' => $last_name, ':em' => $email,
                        ':ph' => $phone, ':sub' => $suburb, ':pref' => $preferred_animal_type,
                        ':cap' => $capacity, ':st' => $status, ':nt' => !empty($notes) ? $notes : null,
                        ':id' => $carer_id
                ]);
            } else {
                $stmt = $dbh->prepare("
                    INSERT INTO foster_carers (first_name, last_name, email, phone, suburb, preferred_animal_type, capacity, status, notes)
                    VALUES (:fn, :ln, :em, :ph, :sub, :pref, :cap, :st, :nt)
                ");
                $stmt->execute([
                        ':fn' => $first_name, ':ln' => $last_name, ':em' => $email,
                        ':ph' => $phone, ':sub' => $suburb, ':pref' => $preferred_animal_type,
                        ':cap' => $capacity, ':st' => $status, ':nt' => !empty($notes) ? $notes : null
                ]);
            }
            header('Location: foster_carer_index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Carer save error: " . $e->getMessage());
            $errors[] = "An unexpected error occurred while saving the record.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Foster Carer - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 760px;">
    <div class="mb-4">
        <a href="foster_carer_index.php" class="text-decoration-none small">&larr; Back to Foster Carers</a>
        <h1 class="h3 fw-bold mt-2"><?= $is_edit ? 'Edit Foster Carer Profile' : 'Register New Foster Carer' ?></h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <!-- Fixed action target to submit to self -->
            <form method="POST" action="foster_carer_update.php<?= $is_edit ? '?id=' . $carer_id : '' ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="suburb" class="form-label small fw-semibold">Residential Suburb <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="suburb" name="suburb" value="<?= htmlspecialchars($suburb, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="preferred_animal_type" class="form-label small fw-semibold">Preferred Animal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="preferred_animal_type" name="preferred_animal_type" placeholder="e.g. Dog, Cat, Rabbit" value="<?= htmlspecialchars($preferred_animal_type, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="capacity" class="form-label small fw-semibold">Foster Capacity (Max Animals) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="20" value="<?= htmlspecialchars((string)$capacity, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status">
                            <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label small fw-semibold">Notes & Housing Conditions</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="foster_carer_index.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Save Changes' : 'Create Carer' ?></button>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>