<?php
// =====================================================================
// notifications.php — the logged-in user's in-app notifications.
// =====================================================================

// 1. Any logged-in user
require_once __DIR__ . '/includes/init.php';
require_login();
$user = current_user();

// 2. "Mark all as read" button
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    redirect('notifications.php');
}

// 3. Load the latest 100 notifications
$stmt = db()->prepare(
    'SELECT id, message, link, is_read, created_at
     FROM notifications WHERE user_id = ?
     ORDER BY created_at DESC, id DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// 4. Show the page
$page_title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Notifications</h1>
    <?php if ($unread_count > 0): ?>
        <form method="post" action="<?= e(url('notifications.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-secondary btn-sm">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$notifications): ?>
    <p class="text-muted">You have no notifications yet.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($notifications as $note): ?>
            <?php
            // Unread notifications are bold with a colored edge
            $classes = 'list-group-item list-group-item-action' . ($note['is_read'] ? '' : ' fw-semibold unread-note');
            $href = $note['link'] ? url($note['link']) : '#';
            ?>
            <a class="<?= $classes ?>" href="<?= e($href) ?>">
                <div><?= e($note['message']) ?></div>
                <div class="small text-muted fw-normal"><?= e(time_ago($note['created_at'])) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
