<?php
// =====================================================================
// partner/dashboard.php — Partner dashboard.
// TEMPORARY placeholder: Requests matched to your program will be added in a later step.
// =====================================================================

// 1. Load shared code and allow only the 'partner' role to open this page
require_once __DIR__ . '/../includes/init.php';
require_role(['partner']);

// 2. Show the page
$page_title = 'Partner dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3"><?= e($page_title) ?></h1>
<p>Hello, <?= e(current_user()['full_name']) ?>! You are logged in as <strong>partner</strong>.</p>
<p class="text-muted small">(Requests matched to your program will be added in a later step.)</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
