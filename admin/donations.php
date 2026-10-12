<?php
// =====================================================================
// admin/donations.php — all donations, grouped by status.
// Pending donations need the admin's checklist (admin/donation_view.php).
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

// 2. Which tab? (whitelist)
$tab = get('status');
if (!isset(DONATION_STATUSES[$tab])) {
    $tab = 'pending';
}

// Count per status for the tab badges
$counts = db()->query('SELECT status, COUNT(*) AS n FROM donations GROUP BY status')
              ->fetchAll(PDO::FETCH_KEY_PAIR);   // ['pending' => 2, ...]

// Donations in this tab, with how many units are still unallocated.
// Pending ones are oldest first (first come, first checked).
$order = $tab === 'pending' ? 'd.created_at ASC' : 'd.expiry_date ASC';
$stmt = db()->prepare(
    "SELECT d.id, d.quantity, d.expiry_date, d.created_at, d.status,
            m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
            u.full_name AS donor_name, u.organization_name, u.role AS donor_role,
            d.quantity - (SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a WHERE a.donation_id = d.id)
                AS remaining
     FROM donations d
     JOIN medicines m ON m.id = d.medicine_id
     JOIN users u ON u.id = d.donor_id
     WHERE d.status = ?
     ORDER BY $order
     LIMIT 200"
);
// ($order is one of our two fixed strings above, never user input)
$stmt->execute([$tab]);
$donations = $stmt->fetchAll();

// 3. Show the page
$page_title = 'Donations';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head"><h1>Donations</h1><div class="subtitle">Verify listings with the checklist, then route approved units to requests.</div></div>

<ul class="nav nav-tabs mb-3">
    <?php foreach (DONATION_STATUSES as $status => $info): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $status ? ' active' : '' ?>"
               href="<?= e(url('admin/donations.php?status=' . $status)) ?>">
                <?= e($info['label']) ?>
                · <?= (int) ($counts[$status] ?? 0) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="table-card table-responsive">
    <table class="table align-middle mb-0">
        <thead>
            <tr><th>#</th><th>Medicine</th><th>Qty</th><?= $tab === 'approved' ? '<th>Left</th>' : '' ?><th>Expires</th><th>Donor</th><th>Listed</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($donations as $donation): ?>
            <?php $days_left = (int) floor((strtotime($donation['expiry_date']) - strtotime('today')) / 86400); ?>
            <tr>
                <td><?= (int) $donation['id'] ?></td>
                <td><?= e(medicine_name($donation)) ?></td>
                <td><?= (int) $donation['quantity'] ?></td>
                <?php if ($tab === 'approved'): ?><td><?= (int) $donation['remaining'] ?></td><?php endif; ?>
                <td class="<?= $days_left <= NEAR_EXPIRY_DAYS ? 'text-danger fw-semibold' : '' ?>">
                    <?= e(format_date($donation['expiry_date'])) ?>
                    <div class="small"><?= $days_left < 0 ? 'expired' : $days_left . ' days left' ?></div>
                </td>
                <td>
                    <?= e($donation['donor_name']) ?>
                    <div class="small text-muted"><?= e($donation['organization_name'] ?? $donation['donor_role']) ?></div>
                </td>
                <td class="small"><?= e(time_ago($donation['created_at'])) ?></td>
                <td class="text-end">
                    <a class="btn btn-sm <?= $tab === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                       href="<?= e(url('admin/donation_view.php?id=' . $donation['id'])) ?>">
                        <?= $tab === 'pending' ? 'Verify' : 'Open' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$donations): ?>
            <tr><td colspan="8" class="text-muted">No donations here.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
