<?php
// =====================================================================
// includes/header.php — top part of every page (HTML head + menu).
// Before including it, a page may set:
//   $page_title    text for the browser tab
//   $full_width    true if the page makes its own full-width sections
//                  (the home page); otherwise content sits in a container
//   $use_map       true to load the Leaflet map library
//   $use_charts    true to load the Chart.js library
//   $page_scripts  list of our own JS files, e.g. ['assets/js/register.js']
//                  (loaded by includes/footer.php)
//
// Two layouts, as in the prototype:
//   * admins:   dark green sidebar on the left
//   * everyone else: white header bar on top, dark footer at the bottom
// =====================================================================

$page_title = $page_title ?? APP_NAME;
$full_width = $full_width ?? false;
$use_map    = $use_map ?? false;
$use_charts = $use_charts ?? false;
$nav_user   = current_user();
$flash      = get_flash();
$is_admin_layout = $nav_user && $nav_user['role'] === 'admin';

// Menu links for each role. Visitors who are not logged in see the public
// links (the protected ones send them to the login page first).
$nav_links_by_role = [
    'guest' => [
        'locator/search.php'          => 'Find medicine',
        'beneficiary/request_new.php' => 'Request assistance',
        'donor/donation_new.php'      => 'Donate',
        'beneficiary/dashboard.php'   => 'My requests',
    ],
    'beneficiary' => [
        'locator/search.php'          => 'Find medicine',
        'beneficiary/request_new.php' => 'Request assistance',
        'beneficiary/dashboard.php'   => 'My requests',
    ],
    'donor' => [
        'locator/search.php'     => 'Find medicine',
        'donor/donation_new.php' => 'Donate',
        'donor/dashboard.php'    => 'My donations',
    ],
    'pharmacy' => [
        'pharmacy/stock.php'     => 'Stock & prices',
        'donor/donation_new.php' => 'Donate',
        'pharmacy/dashboard.php' => 'Overview',
    ],
    'partner' => [
        'partner/dashboard.php' => 'Matched requests',
    ],
];
$nav_links = $nav_links_by_role[$nav_user['role'] ?? 'guest'] ?? [];

// Detail pages that should highlight a menu link, e.g. a request's page
// highlights "My requests"
$related_pages = [
    'beneficiary/dashboard.php' => 'beneficiary/request_view.php',
    'donor/dashboard.php'       => 'donor/donation_view.php',
    'partner/dashboard.php'     => 'partner/request_view.php',
];

// Admin sidebar: [icon, label, how many items are waiting, detail page
// that should also highlight this menu item]
$admin_links = [];
if ($is_admin_layout) {
    $admin_links = [
        'admin/dashboard.php' => ['bi-graph-up', 'Dashboard', 0, ''],
        'admin/requests.php'  => ['bi-file-earmark-text', 'Requests',
            (int) db()->query("SELECT COUNT(*) FROM assistance_requests WHERE status = 'submitted'")->fetchColumn(),
            'admin/request_view.php'],
        'admin/donations.php' => ['bi-box-seam', 'Donations',
            (int) db()->query("SELECT COUNT(*) FROM donations WHERE status = 'pending'")->fetchColumn(),
            'admin/donation_view.php'],
        'admin/users.php'     => ['bi-person-check', 'Accounts',
            (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(), ''],
        'admin/medicines.php' => ['bi-capsule', 'Medicines', 0, ''],
        'admin/reports.php'   => ['bi-printer', 'Reports', 0, ''],
    ];
}

// Number of unread notifications
$unread_count = 0;
if ($nav_user) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$nav_user['id']]);
    $unread_count = (int) $stmt->fetchColumn();
}

// The name shown next to the avatar, like in the prototype
$user_label = '';
$role_tag   = '';
if ($nav_user) {
    $user_label = $nav_user['full_name'];
    if ($nav_user['role'] === 'donor') {
        $user_label .= ' · ' . ($nav_user['organization_name'] ?: 'Individual donor');
    } elseif ($nav_user['role'] === 'pharmacy') {
        $role_tag = 'Pharmacy';
        $store = current_pharmacy();
        if ($store) {
            $user_label = $store['name'] . ($store['branch'] ? ' · ' . $store['branch'] : '');
        }
    } elseif ($nav_user['role'] === 'partner') {
        $role_tag = 'Partner';
        $stmt = db()->prepare('SELECT name FROM partner_programs WHERE id = ?');
        $stmt->execute([$nav_user['partner_program_id']]);
        $user_label = $stmt->fetchColumn() ?: $user_label;
    }
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

    <!-- IBM Plex Sans (text) and IBM Plex Mono (reference numbers) from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS from a CDN. "integrity" makes the browser refuse
         the file if it was changed by anyone. -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <!-- Bootstrap Icons: small line icons, used like <i class="bi bi-search"></i> -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+"
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

<?php if ($is_admin_layout): ?>
<!-- ================= ADMIN LAYOUT: sidebar + content ================= -->
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="brand" href="<?= e(url('admin/dashboard.php')) ?>">
            <span class="logo-pill"></span>
            <span><?= e(APP_NAME) ?><br>Admin</span>
        </a>
        <nav class="admin-nav" aria-label="Admin menu">
            <?php foreach ($admin_links as $path => [$icon, $label, $count, $detail_page]): ?>
                <?php $is_current = $current_path === url($path) || ($detail_page !== '' && $current_path === url($detail_page)); ?>
                <a href="<?= e(url($path)) ?>" class="<?= $is_current ? 'active' : '' ?>">
                    <i class="bi <?= e($icon) ?>"></i> <?= e($label) ?>
                    <?php if ($count > 0): ?><span class="count"><?= $count ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(url('notifications.php')) ?>" class="<?= $current_path === url('notifications.php') ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($unread_count > 0): ?><span class="count"><?= $unread_count ?></span><?php endif; ?>
            </a>
        </nav>
        <div class="admin-user">
            <div>
                <div><?= e($nav_user['full_name']) ?></div>
                <div class="small opacity-75">Administrator</div>
            </div>
            <!-- Logout is a POST form (not a link) so other sites can't log you out -->
            <form method="post" action="<?= e(url('logout.php')) ?>" class="mt-lg-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-light">Log out</button>
            </form>
        </div>
    </aside>
    <main class="admin-main">

<?php else: ?>
<!-- ================= PUBLIC / USER LAYOUT: top header ================= -->
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="logo-pill"></span> <?= e(APP_NAME) ?>
            </a>
            <?php if ($role_tag !== ''): ?><span class="role-tag d-none d-sm-inline-block"><?= e($role_tag) ?></span><?php endif; ?>

            <!-- Hamburger button for small screens -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                    aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto ms-lg-4 gap-lg-1">
                    <?php foreach ($nav_links as $path => $label): ?>
                        <?php $is_current = $current_path === url($path)
                            || (isset($related_pages[$path]) && $current_path === url($related_pages[$path])); ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $is_current ? ' active' : '' ?>" href="<?= e(url($path)) ?>"
                               <?= $is_current ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex align-items-center gap-2 py-2 py-lg-0">
                    <?php if ($nav_user): ?>
                        <span class="user-label d-none d-md-inline"><?= e($user_label) ?></span>
                        <!-- Avatar menu (Bootstrap "dropdown") -->
                        <div class="dropdown">
                            <button class="btn p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="Account menu">
                                <span class="avatar">
                                    <?= e(initials($nav_user['full_name'])) ?>
                                    <?php if ($unread_count > 0): ?><span class="dot"><?= $unread_count ?></span><?php endif; ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li class="px-3 py-2 small text-muted"><?= e($nav_user['email']) ?></li>
                                <li>
                                    <a class="dropdown-item" href="<?= e(url('notifications.php')) ?>">
                                        <i class="bi bi-bell"></i> Notifications
                                        <?php if ($unread_count > 0): ?><span class="pill pill-red ms-1"><?= $unread_count ?></span><?php endif; ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <!-- Logout is a POST form (not a link) so other sites can't log you out -->
                                    <form method="post" action="<?= e(url('logout.php')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= e(url('login.php')) ?>">Log in</a>
                        <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<main>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="<?= $is_admin_layout ? '' : 'container pt-3' ?>">
        <div class="alert alert-<?= e($flash['type']) ?> mb-0" role="alert">
            <?= e($flash['message']) ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$full_width && !$is_admin_layout): ?>
<div class="container py-4">
<?php elseif ($is_admin_layout): ?>
<div class="pt-3">
<?php endif; ?>
