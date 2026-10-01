<?php
// 1. Activate the session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Connect to the database using the shared connection file
require_once __DIR__ . '/config/connection.php';

// Construct relative URL paths based on document root
$base_path = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . DIRECTORY_SEPARATOR;
// Normalize backslashes to forward slashes for URL compatibility
$base_path = str_replace('\\', '/', $base_path);

$login_path  = rtrim($base_path, '/') . '/login.php';
$logout_path = rtrim($base_path, '/') . '/logout.php';

// 3. If no user is logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $login_path);
    exit;
}

// 4. Verify whether the user ID recorded in session is still valid in the database
try {
    $stmt = $dbh->prepare("SELECT user_id FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. If user is invalid (e.g. deleted from DB), log them out
    if (!$user) {
        header('Location: ' . $logout_path);
        exit;
    }
} catch (PDOException $e) {
    displayPDOError($e);
    exit;
}

// 6. Valid user: script finishes without action and calling page proceeds