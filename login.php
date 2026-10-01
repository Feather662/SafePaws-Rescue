<?php
// login.php

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/connection.php';

// Redirect authenticated users directly to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

// Handle credentials submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // Prepared statement prevents SQL injection
            $stmt = $dbh->prepare("SELECT user_id, first_name, last_name, username, password, status FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // Verify password against stored bcrypt hash
            if ($user && password_verify($password, $user['password'])) {
                // Ensure account has active operational status
                if ($user['status'] === 'Active') {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                    header('Location: index.php');
                    exit;
                } else {
                    $error = "This account is inactive. Please contact the administrator.";
                }
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            // Log raw technical error and display generic message
            error_log("Login Query Error: " . $e->getMessage());
            $error = "A system error occurred. Please try again later.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SafePaws Rescue - Staff Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h2 class="h4 fw-bold text-primary mb-1">SafePaws Rescue</h2>
                <p class="text-muted small mb-0">Staff Administrative Portal</p>
            </div>

            <!-- Display sanitized error notification -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary fw-semibold">Log In</button>
                </div>
            </form>
        </div>
    </div>
    <div class="text-center mt-3 text-muted small">
        &copy; <?= date('Y') ?> SafePaws Animal Rescue
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>