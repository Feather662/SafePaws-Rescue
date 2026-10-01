<?php
// includes/navbar.php

// Determine relative base path based on directory depth
$project_root = dirname(__DIR__);
$current_dir = dirname($_SERVER['SCRIPT_FILENAME']);
$base_path = (realpath($current_dir) === realpath($project_root)) ? '' : '../';

$active_folder = basename($current_dir);
$active_file = basename($_SERVER['SCRIPT_FILENAME']);
?>
<!-- Responsive Bootstrap Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="<?= $base_path ?>dashboard.php">SafePaws Admin</a>

        <!-- Mobile hamburger toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar"
                aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <!-- Core management module links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_file === 'index.php') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_folder === 'animals') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>animals/animal_index.php">Animals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_folder === 'foster_carer') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>foster_carer/foster_carer_index.php">Foster Carers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_folder === 'partner') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>partner/partner_index.php">Partners</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_folder === 'application') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>application/application_index.php">Applications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_file === 'messages.php') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>contacts/administration.php">Contact Messages</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_file === 'users.php' || $active_folder === 'users') ? 'active fw-semibold' : '' ?>"
                       href="<?= $base_path ?>users/userManagement.php">System Users</a>
                </li>
            </ul>

            <!-- Staff session details and termination -->
            <div class="d-flex align-items-center text-light">
                <span class="small me-3">
                    Logged in as: <strong class="text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?></strong>
                </span>
                <a href="<?= $base_path ?>logout.php" class="btn btn-outline-light btn-sm">Log Out</a>
            </div>
        </div>
    </div>
</nav>