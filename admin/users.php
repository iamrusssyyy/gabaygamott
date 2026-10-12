<?php
// =====================================================================
// admin/users.php — manage accounts.
//   * "Waiting for approval" tab: approve or reject pharmacy and partner
//     sign-ups (with a reason when rejecting).
//   * "All accounts" tab: search accounts; disable or re-enable them.
// Admin accounts cannot be changed here (they are created in the database).
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

// ---------------------------------------------------------------------
// 2. Handle the action buttons (approve / reject / disable / enable)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action  = post('action');
    $user_id = to_int(post('user_id')) ?? 0;
    $reason  = mb_substr(post('reason'), 0, 255);

    // Load the account being changed (never an admin)
    $stmt = db()->prepare("SELECT id, full_name, role, status FROM users WHERE id = ? AND role <> 'admin'");
    $stmt->execute([$user_id]);
    $account = $stmt->fetch();

    // Which status each action is allowed FROM, and what it changes TO
    $actions = [
        'approve' => ['from' => ['pending', 'rejected'], 'to' => 'active'],
        'reject'  => ['from' => ['pending'],             'to' => 'rejected'],
        'disable' => ['from' => ['active'],              'to' => 'disabled'],
        'enable'  => ['from' => ['disabled'],            'to' => 'active'],
    ];

    if (!$account || !isset($actions[$action])) {
        set_flash('danger', 'That account or action was not found.');
    } elseif (!in_array($account['status'], $actions[$action]['from'], true)) {
        set_flash('warning', 'This account is already ' . $account['status'] . '.');
    } elseif ($action === 'reject' && $reason === '') {
        set_flash('danger', 'Please type a reason for rejecting ' . $account['full_name'] . '.');
    } else {
        $new_status = $actions[$action]['to'];
        $stmt = db()->prepare('UPDATE users SET status = ?, status_reason = ? WHERE id = ?');
        $stmt->execute([$new_status, $reason !== '' ? $reason : null, $account['id']]);

        if ($action === 'approve') {
            notify($account['id'], 'Your account has been approved. Welcome to GabayGamot!',
                   ROLE_HOME[$account['role']]);
        }
        set_flash('success', $account['full_name'] . ' is now ' . ACCOUNT_STATUSES[$new_status]['label'] . '.');
    }

    // Go back to the same tab/filter the admin was on. parse_str() reads
    // "tab=all&q=maria" into an array; we keep only the 3 keys we expect.
    parse_str(post('return'), $back);
    $back = array_intersect_key($back, ['tab' => 1, 'q' => 1, 'role' => 1]);
    redirect('admin/users.php' . ($back ? '?' . http_build_query($back) : ''));
}

// ---------------------------------------------------------------------
// 3. Load data
// ---------------------------------------------------------------------
$tab = get('tab') === 'all' ? 'all' : 'pending';

// Pending sign-ups, with pharmacy and program details for checking
$pending = db()->query(
    "SELECT u.id, u.role, u.full_name, u.email, u.phone, u.city, u.organization_name, u.created_at,
            pp.name AS program_name,
            p.name AS pharmacy_name, p.branch, p.address, p.latitude, p.longitude,
            p.opening_hours, p.fda_license_no
     FROM users u
     LEFT JOIN partner_programs pp ON pp.id = u.partner_program_id
     LEFT JOIN pharmacies p ON p.user_id = u.id
     WHERE u.status = 'pending'
     ORDER BY u.created_at"
)->fetchAll();

// All accounts, with optional search and role filter
$search      = get('q');
$role_filter = get('role');
$accounts    = [];
if ($tab === 'all') {
    // Build the WHERE part from fixed pieces; user input only goes in "?" values.
    $where  = ['1 = 1'];
    $params = [];
    if ($search !== '') {
        $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if (array_key_exists($role_filter, ROLE_HOME)) {
        $where[]  = 'u.role = ?';
        $params[] = $role_filter;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.role, u.full_name, u.email, u.city, u.status, u.status_reason, u.last_login_at
         FROM users u WHERE ' . implode(' AND ', $where) . '
         ORDER BY u.created_at DESC LIMIT 200'
    );
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
}

// Query string to come back to after an action
$return_to = http_build_query(array_filter(['tab' => $tab, 'q' => $search, 'role' => $role_filter]));

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'Accounts';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Accounts</h1><div class="subtitle">Pharmacy and partner accounts need approval before they can log in.</div></div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link<?= $tab === 'pending' ? ' active' : '' ?>" href="<?= e(url('admin/users.php')) ?>">
            Waiting for approval
            <?php if ($pending): ?><span class="pill pill-amber"><?= count($pending) ?> pending</span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $tab === 'all' ? ' active' : '' ?>" href="<?= e(url('admin/users.php?tab=all')) ?>">All accounts</a>
    </li>
</ul>

<?php if ($tab === 'pending'): ?>

    <?php if (!$pending): ?>
        <p class="text-muted">No accounts are waiting for approval.</p>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($pending as $account): ?>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h2 class="h5 mb-1"><?= e($account['full_name']) ?></h2>
                            <span class="role-tag"><?= e(ucfirst($account['role'])) ?></span>
                        </div>
                        <p class="small text-muted mb-2">
                            Signed up <?= e(time_ago($account['created_at'])) ?> ·
                            <?= e($account['email']) ?> · <?= e($account['phone'] ?? 'no phone') ?> · <?= e($account['city']) ?>
                        </p>

                        <?php if ($account['role'] === 'pharmacy'): ?>
                            <dl class="row small mb-2">
                                <dt class="col-4">Pharmacy</dt>
                                <dd class="col-8"><?= e($account['pharmacy_name']) ?><?= $account['branch'] ? ' · ' . e($account['branch']) : '' ?></dd>
                                <dt class="col-4">Address</dt>
                                <dd class="col-8"><?= e($account['address']) ?></dd>
                                <dt class="col-4">Hours</dt>
                                <dd class="col-8"><?= e($account['opening_hours'] ?? '—') ?></dd>
                                <dt class="col-4">FDA LTO no.</dt>
                                <dd class="col-8"><?= e($account['fda_license_no']) ?></dd>
                                <dt class="col-4">Map pin</dt>
                                <dd class="col-8">
                                    <a href="https://www.openstreetmap.org/?mlat=<?= e($account['latitude']) ?>&mlon=<?= e($account['longitude']) ?>#map=18/<?= e($account['latitude']) ?>/<?= e($account['longitude']) ?>"
                                       target="_blank" rel="noopener">Check location on OpenStreetMap</a>
                                </dd>
                            </dl>
                            <p class="small text-muted">Check the License to Operate number on the FDA website before approving.</p>
                        <?php else: ?>
                            <dl class="row small mb-2">
                                <dt class="col-4">Program</dt>
                                <dd class="col-8"><?= e($account['program_name'] ?? '—') ?></dd>
                                <dt class="col-4">Office</dt>
                                <dd class="col-8"><?= e($account['organization_name'] ?? '—') ?></dd>
                            </dl>
                            <p class="small text-muted">Confirm with the program office that this person is a coordinator.</p>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" action="<?= e(url('admin/users.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                            </form>
                            <form method="post" action="<?= e(url('admin/users.php')) ?>" class="d-flex gap-2 flex-grow-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                <input type="text" name="reason" class="form-control" maxlength="255"
                                       placeholder="Reason for rejecting" aria-label="Reason for rejecting">
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <!-- Search form (GET, so the filters appear in the URL) -->
    <form method="get" action="<?= e(url('admin/users.php')) ?>" class="row g-2 mb-3">
        <input type="hidden" name="tab" value="all">
        <div class="col-12 col-md-6">
            <input type="search" name="q" class="form-control" value="<?= e($search) ?>"
                   placeholder="Search name or email" aria-label="Search name or email">
        </div>
        <div class="col-8 col-md-4">
            <select name="role" class="form-select" aria-label="Role">
                <option value="">All roles</option>
                <?php foreach (array_keys(ROLE_HOME) as $role): ?>
                    <option value="<?= e($role) ?>" <?= $role_filter === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
    </form>

    <div class="table-card table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Role</th><th>City</th><th>Status</th><th>Last login</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <tr>
                    <td>
                        <?= e($account['full_name']) ?><br>
                        <span class="small text-muted"><?= e($account['email']) ?></span>
                    </td>
                    <td><?= e($account['role']) ?></td>
                    <td><?= e($account['city'] ?? '') ?></td>
                    <td>
                        <?= account_badge($account['status']) ?>
                        <?php if ($account['status_reason']): ?>
                            <div class="small text-muted"><?= e($account['status_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= $account['last_login_at'] ? e(time_ago($account['last_login_at'])) : 'never' ?></td>
                    <td class="text-end">
                        <?php
                        // Which button to show for this account.
                        // match (PHP 8) is like a short switch that returns a value.
                        $button = null;
                        if ($account['role'] !== 'admin') {
                            $button = match ($account['status']) {
                                'active'   => ['disable', 'Disable', 'btn-outline-danger'],
                                'disabled' => ['enable', 'Enable', 'btn-outline-primary'],
                                'rejected' => ['approve', 'Approve', 'btn-outline-primary'],
                                default    => null, // pending: use the first tab
                            };
                        }
                        ?>
                        <?php if ($button): ?>
                            <form method="post" action="<?= e(url('admin/users.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($return_to) ?>">
                                <button type="submit" name="action" value="<?= e($button[0]) ?>"
                                        class="btn btn-sm <?= e($button[2]) ?>"><?= e($button[1]) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$accounts): ?>
                <tr><td colspan="6" class="text-muted">No accounts found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
