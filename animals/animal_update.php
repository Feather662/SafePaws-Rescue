<?php
// animals/animal_update.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$animal_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !empty($animal_id);

$errors = [];
$name = '';
$breed_id = '';
$sex = 'Male';
$desexed = 0;
$date_of_birth = '';
$date_admitted = date('Y-m-d');
$description = '';
$medical_notes = '';
$status = 'In care';
$foster_carer_id = null;
$existing_image = null;

// Populate form in edit mode
if ($is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $dbh->prepare("SELECT * FROM animals WHERE animal_id = :id LIMIT 1");
        $stmt->execute([':id' => $animal_id]);
        $animal = $stmt->fetch();

        if ($animal) {
            $name = $animal['name'];
            $breed_id = $animal['breed_id'];
            $sex = $animal['sex'];
            $desexed = (int)$animal['desexed'];
            $date_of_birth = $animal['date_of_birth'];
            $date_admitted = $animal['date_admitted'];
            $description = $animal['description'];
            $medical_notes = $animal['medical_notes'] ?? '';
            $status = $animal['status'];
            $foster_carer_id = $animal['foster_carer_id'];
            $existing_image = $animal['profile_image'];
        } else {
            header('Location: animal_index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Animal load error: " . $e->getMessage());
        header('Location: animal_index.php');
        exit;
    }
}

// Load dropdown reference collections
try {
    $breed_stmt = $dbh->query("
        SELECT b.breed_id, b.breed_name, s.species_name
        FROM breeds b
        JOIN species s ON b.species_id = s.species_id
        ORDER BY s.species_name ASC, b.breed_name ASC
    ");
    $all_breeds = $breed_stmt->fetchAll();

    // Query active carers with remaining available capacity
    $carer_sql = "
        SELECT fc.foster_carer_id, fc.first_name, fc.last_name, fc.preferred_animal_type,
               (fc.capacity - COUNT(a.animal_id)) AS remaining_capacity
        FROM foster_carers fc
        LEFT JOIN animals a ON fc.foster_carer_id = a.foster_carer_id
        WHERE fc.status = 'Active'
        GROUP BY fc.foster_carer_id
        HAVING remaining_capacity > 0
    ";
    if ($is_edit && !empty($foster_carer_id)) {
        $carer_sql .= " OR fc.foster_carer_id = " . (int)$foster_carer_id;
    }
    $carer_sql .= " ORDER BY fc.first_name ASC";
    $eligible_carers = $dbh->query($carer_sql)->fetchAll();
} catch (PDOException $e) {
    error_log("Lookup load error: " . $e->getMessage());
    $all_breeds = [];
    $eligible_carers = [];
}

// Handle form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $breed_id = filter_input(INPUT_POST, 'breed_id', FILTER_VALIDATE_INT);
    $sex = trim($_POST['sex'] ?? 'Male');
    $desexed = isset($_POST['desexed']) ? 1 : 0;
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $date_admitted = trim($_POST['date_admitted'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $medical_notes = trim($_POST['medical_notes'] ?? '');
    $status = trim($_POST['status'] ?? 'In care');
    $posted_carer = trim($_POST['foster_carer_id'] ?? '');
    $foster_carer_id = (!empty($posted_carer) && is_numeric($posted_carer)) ? (int)$posted_carer : null;

    // Field validations
    if (empty($name)) $errors[] = "Animal name is required.";
    if (!$breed_id) $errors[] = "Please select a valid breed.";
    if (!in_array($sex, ['Male', 'Female'], true)) $errors[] = "Invalid sex selected.";
    if (empty($date_of_birth)) $errors[] = "Date of birth is required.";
    if (empty($date_admitted)) $errors[] = "Date admitted is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (!in_array($status, ['In care', 'Available for adoption', 'Adoption pending', 'Adopted'], true)) {
        $errors[] = "Invalid status selected.";
    }

    // Chronological date business logic
    $today = date('Y-m-d');
    if ($date_of_birth > $today) $errors[] = "Date of birth cannot be in the future.";
    if ($date_admitted > $today) $errors[] = "Date admitted cannot be in the future.";
    if ($date_of_birth > $date_admitted) $errors[] = "Date of birth cannot be after the admission date.";

    // Foster carer capacity re-verification
    if ($foster_carer_id !== null) {
        $cap_stmt = $dbh->prepare("
            SELECT fc.capacity, COUNT(a.animal_id) AS current_assigned
            FROM foster_carers fc
            LEFT JOIN animals a ON fc.foster_carer_id = a.foster_carer_id
            WHERE fc.foster_carer_id = :cid AND fc.status = 'Active'
            GROUP BY fc.foster_carer_id
        ");
        $cap_stmt->execute([':cid' => $foster_carer_id]);
        $cap_info = $cap_stmt->fetch();

        if (!$cap_info) {
            $errors[] = "Selected foster carer is not active or does not exist.";
        } else {
            $already_here = ($is_edit && isset($animal) && (int)$animal['foster_carer_id'] === $foster_carer_id);
            if (!$already_here && ($cap_info['current_assigned'] >= $cap_info['capacity'])) {
                $errors[] = "Selected foster carer has reached maximum capacity.";
            }
        }
    }

    // Secure file upload handling
    $uploaded_filename = $is_edit ? ($animal['profile_image'] ?? null) : null;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "An error occurred during image upload.";
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB Limit
            $errors[] = "Image file size must not exceed 2MB.";
        } else {
            // Verify MIME type using fileinfo buffer
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!array_key_exists($mime, $allowed_mimes)) {
                $errors[] = "Only JPG, PNG, and WebP images are permitted.";
            } else {
                $upload_dir = __DIR__ . '/../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generate sanitized collision-proof filename
                $ext = $allowed_mimes[$mime];
                $new_filename = 'animal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // Remove superseded previous image in edit mode
                    if ($is_edit && !empty($animal['profile_image']) && file_exists($upload_dir . $animal['profile_image'])) {
                        unlink($upload_dir . $animal['profile_image']);
                    }
                    $uploaded_filename = $new_filename;
                } else {
                    $errors[] = "Failed to store uploaded image on server.";
                }
            }
        }
    }

    // Persist to database
    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $dbh->prepare("
                    UPDATE animals SET
                        name = :nm, breed_id = :bid, sex = :sx, desexed = :ds,
                        date_of_birth = :dob, date_admitted = :dad, description = :dsc,
                        medical_notes = :med, status = :st, profile_image = :img,
                        foster_carer_id = :fcid
                    WHERE animal_id = :id
                ");
                $stmt->execute([
                    ':nm' => $name, ':bid' => $breed_id, ':sx' => $sex, ':ds' => $desexed,
                    ':dob' => $date_of_birth, ':dad' => $date_admitted, ':dsc' => $description,
                    ':med' => !empty($medical_notes) ? $medical_notes : null,
                    ':st' => $status, ':img' => $uploaded_filename,
                    ':fcid' => $foster_carer_id, ':id' => $animal_id
                ]);
            } else {
                $stmt = $dbh->prepare("
                    INSERT INTO animals (name, breed_id, sex, desexed, date_of_birth, date_admitted, description, medical_notes, status, profile_image, foster_carer_id)
                    VALUES (:nm, :bid, :sx, :ds, :dob, :dad, :dsc, :med, :st, :img, :fcid)
                ");
                $stmt->execute([
                    ':nm' => $name, ':bid' => $breed_id, ':sx' => $sex, ':ds' => $desexed,
                    ':dob' => $date_of_birth, ':dad' => $date_admitted, ':dsc' => $description,
                    ':med' => !empty($medical_notes) ? $medical_notes : null,
                    ':st' => $status, ':img' => $uploaded_filename, ':fcid' => $foster_carer_id
                ]);
            }
            header('Location: animal_index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Animal save error: " . $e->getMessage());
            $errors[] = "Database error occurred while saving animal profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Animal Profile - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 860px;">
    <div class="mb-4">
        <a href="animal_index.php" class="text-decoration-none small">&larr; Back to Animals List</a>
        <h1 class="h3 fw-bold mt-2"><?= $is_edit ? 'Edit Animal Profile' : 'Register New Animal' ?></h1>
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
            <form method="POST" action="animal_update.php<?= $is_edit ? '?id=' . $animal_id : '' ?>" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label small fw-semibold">Animal Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="breed_id" class="form-label small fw-semibold">Species & Breed <span class="text-danger">*</span></label>
                        <select class="form-select" id="breed_id" name="breed_id" required>
                            <option value="">-- Select Species & Breed --</option>
                            <?php foreach ($all_breeds as $b): ?>
                                <option value="<?= $b['breed_id'] ?>" <?= ($breed_id == $b['breed_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['species_name'] . ' - ' . $b['breed_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold d-block">Sex <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" id="sex_m" name="sex" value="Male" <?= ($sex === 'Male') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sex_m">Male</label>
                        </div>
                        <div class="form-check form-check-inline mt-1">
                            <input class="form-check-input" type="radio" id="sex_f" name="sex" value="Female" <?= ($sex === 'Female') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sex_f">Female</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold d-block">Medical Procedure</label>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="desexed" name="desexed" value="1" <?= $desexed ? 'checked' : '' ?>>
                            <label class="form-check-label" for="desexed">Desexed / Sterilised</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label small fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($date_of_birth, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="date_admitted" class="form-label small fw-semibold">Admission Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_admitted" name="date_admitted" value="<?= htmlspecialchars($date_admitted, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label small fw-semibold">Adoption Workflow Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status">
                            <option value="In care" <?= ($status === 'In care') ? 'selected' : '' ?>>In care</option>
                            <option value="Available for adoption" <?= ($status === 'Available for adoption') ? 'selected' : '' ?>>Available for adoption</option>
                            <option value="Adoption pending" <?= ($status === 'Adoption pending') ? 'selected' : '' ?>>Adoption pending</option>
                            <option value="Adopted" <?= ($status === 'Adopted') ? 'selected' : '' ?>>Adopted</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="foster_carer_id" class="form-label small fw-semibold">Foster Carer Placement</label>
                        <select class="form-select" id="foster_carer_id" name="foster_carer_id">
                            <option value="">-- Main Shelter Facility (No Carer) --</option>
                            <?php foreach ($eligible_carers as $ec): ?>
                                <option value="<?= $ec['foster_carer_id'] ?>" <?= ($foster_carer_id == $ec['foster_carer_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ec['first_name'] . ' ' . $ec['last_name'] . ' (' . $ec['preferred_animal_type'] . ' - Free: ' . $ec['remaining_capacity'] . ')', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="profile_image" class="form-label small fw-semibold">Profile Photo (JPG, PNG, WebP &le; 2MB)</label>
                        <?php if (!empty($existing_image) && file_exists(__DIR__ . '/../uploads/' . $existing_image)): ?>
                            <div class="d-flex align-items-center gap-3 mb-2 p-2 border rounded bg-light">
                                <img src="../uploads/<?= htmlspecialchars($existing_image, ENT_QUOTES, 'UTF-8') ?>" width="64" height="64" class="rounded object-fit-cover" alt="Current Photo">
                                <span class="small text-muted">Current file: <?= htmlspecialchars($existing_image, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label small fw-semibold">Description & Behaviour <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="medical_notes" class="form-label small fw-semibold">Medical History & Special Notes</label>
                        <textarea class="form-control" id="medical_notes" name="medical_notes" rows="2"><?= htmlspecialchars($medical_notes, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="animal_index.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Save Changes' : 'Create Animal Profile' ?></button>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>