<?php
// =====================================================================
// includes/header.php — top part of every page (HTML head + navigation).
// Before including it, a page may set:
//   $page_title    text for the browser tab
//   $use_map       true to load the Leaflet map library
//   $use_charts    true to load the Chart.js library
//   $page_scripts  list of our own JS files, e.g. ['assets/js/register.js']
//                  (loaded by includes/footer.php)
// =====================================================================

$page_title = $page_title ?? APP_NAME;
$use_map    = $use_map ?? false;
$use_charts = $use_charts ?? false;
$nav_user   = current_user();
$flash      = get_flash();

// Menu links. Visitors who are not logged in see the public links
// (the protected ones send them to the login page first).
$nav_links_by_role = [
    'guest' => [
        'locator/search.php'          => 'Find medicine',
        'beneficiary/request_new.php' => 'Get assistance',
        'beneficiary/dashboard.php'   => 'Track request',
        'donor/donation_new.php'      => 'Donate',
    ],
    'beneficiary' => [
        'locator/search.php'          => 'Find medicine',
        'beneficiary/request_new.php' => 'Get assistance',
        'beneficiary/dashboard.php'   => 'My requests',
    ],
    'donor' => [
        'locator/search.php'     => 'Find medicine',
        'donor/donation_new.php' => 'Donate',
        'donor/dashboard.php'    => 'My donations',
    ],
    'pharmacy' => [
        'pharmacy/dashboard.php' => 'Dashboard',
        'pharmacy/stock.php'     => 'My stock',
        'donor/donation_new.php' => 'Donate',
        'locator/search.php'     => 'Find medicine',
    ],
    'partner' => [
        'partner/dashboard.php' => 'Matched requests',
        'locator/search.php'    => 'Find medicine',
    ],
    'admin' => [
        'admin/dashboard.php' => 'Dashboard',
        'admin/requests.php'  => 'Requests',
        'admin/donations.php' => 'Donations',
        'admin/users.php'     => 'Accounts',
        'admin/medicines.php' => 'Medicines',
        'admin/reports.php'   => 'Reports',
    ],
];
$nav_links = $nav_links_by_role[$nav_user['role'] ?? 'guest'];

// Number of unread notifications, shown next to the bell
$unread_count = 0;
if ($nav_user) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$nav_user['id']]);
    $unread_count = (int) $stmt->fetchColumn();
}

// Path of the page being shown, to highlight the current menu link
$current_path = $_SERVER['SCRIPT_NAME'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>

    <!-- IBM Plex Sans font from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS from a CDN. "integrity" makes the browser refuse
         the file if it was changed by anyone. -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <?php if ($use_map): ?>
    <!-- Leaflet map library CSS (only on pages that show a map) -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="anonymous">
    <?php endif; ?>

    <!-- Our own styles (loaded last so they can override Bootstrap) -->
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>"><?= e(APP_NAME) ?></a>

        <!-- Hamburger button for small screens -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav_links as $path => $label): ?>
                    <?php $is_current = ($current_path === url($path)); ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $is_current ? ' active fw-semibold' : '' ?>"
                           href="<?= e(url($path)) ?>"
                           <?= $is_current ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <?php if ($nav_user): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('notifications.php')) ?>">
                            Notifications
                            <?php if ($unread_count > 0): ?>
                                <span class="badge rounded-pill text-bg-danger"><?= $unread_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text small">
                            <?= e($nav_user['full_name']) ?>
                            <span class="badge text-bg-light border"><?= e($nav_user['role']) ?></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <!-- Logout is a POST form (not a link) so other sites
                             can't log you out with a hidden link. -->
                        <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('register.php')) ?>">Create account</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('login.php')) ?>">Log in</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>
