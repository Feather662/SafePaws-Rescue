<?php
// partner/partner_update.php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../authentication.php';

$org_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit = !empty($org_id);

$errors = [];
$name = '';
$organisation_type = '';
$contact_person = '';
$email = '';
$phone = '';
$address = '';
$website = '';
$notes = '';

// Prefill form values in edit mode
if ($is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $dbh->prepare("SELECT * FROM partner_organisations WHERE organisation_id = :id LIMIT 1");
        $stmt->execute([':id' => $org_id]);
        $org = $stmt->fetch();
        if ($org) {
            $name = $org['name'];
            $organisation_type = $org['organisation_type'];
            $contact_person = $org['contact_person'];
            $email = $org['email'];
            $phone = $org['phone'];
            $address = $org['address'];
            $website = $org['website'] ?? '';
            $notes = $org['notes'] ?? '';
        } else {
            header('Location: partner_index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Partner fetch error: " . $e->getMessage());
        header('Location: partner_index.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $organisation_type = trim($_POST['organisation_type'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Server-side validations
    if (empty($name)) $errors[] = "Organisation name is required.";
    if (empty($organisation_type)) $errors[] = "Organisation type is required.";
    if (empty($contact_person)) $errors[] = "Contact person is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email address is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "Website must be a valid URL (including http:// or https://).";
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $dbh->prepare("
                    UPDATE partner_organisations SET
                        name = :nm, organisation_type = :ot, contact_person = :cp,
                        email = :em, phone = :ph, address = :addr,
                        website = :ws, notes = :nt
                    WHERE organisation_id = :id
                ");
                $stmt->execute([
                        ':nm' => $name, ':ot' => $organisation_type, ':cp' => $contact_person,
                        ':em' => $email, ':ph' => $phone, ':addr' => $address,
                        ':ws' => !empty($website) ? $website : null,
                        ':nt' => !empty($notes) ? $notes : null,
                        ':id' => $org_id
                ]);
            } else {
                $stmt = $dbh->prepare("
                    INSERT INTO partner_organisations (name, organisation_type, contact_person, email, phone, address, website, notes)
                    VALUES (:nm, :ot, :cp, :em, :ph, :addr, :ws, :nt)
                ");
                $stmt->execute([
                        ':nm' => $name, ':ot' => $organisation_type, ':cp' => $contact_person,
                        ':em' => $email, ':ph' => $phone, ':addr' => $address,
                        ':ws' => !empty($website) ? $website : null,
                        ':nt' => !empty($notes) ? $notes : null
                ]);
            }
            header('Location: partner_index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Partner save error: " . $e->getMessage());
            $errors[] = "An unexpected error occurred while saving the organisation.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Partner Organisation - SafePaws Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="container pb-5" style="max-width: 760px;">
    <div class="mb-4">
        <a href="partner_index.php" class="text-decoration-none small">&larr; Back to Partner Organisations</a>
        <h1 class="h3 fw-bold mt-2"><?= $is_edit ? 'Edit Organisation Profile' : 'Register New Partner Organisation' ?></h1>
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
            <form method="POST" action="partner_update.php<?= $is_edit ? '?id=' . $org_id : '' ?>">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label small fw-semibold">Organisation Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="organisation_type" class="form-label small fw-semibold">Type <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="organisation_type" name="organisation_type" placeholder="e.g. Veterinary Clinic" value="<?= htmlspecialchars($organisation_type, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="contact_person" class="form-label small fw-semibold">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= htmlspecialchars($contact_person, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label small fw-semibold">Physical Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-12">
                        <label for="website" class="form-label small fw-semibold">Website (Optional, URL with http/https)</label>
                        <input type="url" class="form-control" id="website" name="website" placeholder="https://example.com" value="<?= htmlspecialchars($website, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label small fw-semibold">Operational Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="partner_index.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Save Changes' : 'Create Partner' ?></button>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>