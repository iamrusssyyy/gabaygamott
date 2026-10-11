<?php
// =====================================================================
// includes/header.php — top part of every page (HTML head + navigation).
// Before including it, a page may set $page_title.
// =====================================================================

$page_title = $page_title ?? APP_NAME;
$nav_user   = current_user();
$flash      = get_flash();
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

    <!-- Our own styles (loaded last so they can override Bootstrap) -->
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>

<nav class="navbar navbar-expand-md bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>"><?= e(APP_NAME) ?></a>

        <!-- Hamburger button for small screens -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <?php if ($nav_user): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url(ROLE_HOME[$nav_user['role']])) ?>">My dashboard</a>
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
