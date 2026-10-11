<?php
// =====================================================================
// logout.php — logs the user out. Only accepts POST (from the Log out
// button in the header), with a CSRF token.
// =====================================================================

require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf();
logout_user();
set_flash('info', 'You have been logged out.');
redirect('login.php');
