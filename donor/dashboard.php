<?php
// =====================================================================
// donor/dashboard.php — "My donations": totals and the donor's list.
// =====================================================================

// 1. Only donors
require_once __DIR__ . '/../includes/init.php';
require_role(['donor']);
$user = current_user();

// 2. Load the donations with how many units were routed to patients.
// The sub-query adds up allocations for each donation.
$stmt = db()->prepare(
    'SELECT d.id, d.quantity, d.expiry_date, d.status, d.created_at,
            m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
            (SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a WHERE a.donation_id = d.id) AS routed
     FROM donations d JOIN medicines m ON m.id = d.medicine_id
     WHERE d.donor_id = ?
     ORDER BY d.created_at DESC'
);
$stmt->execute([$user['id']]);
$donations = $stmt->fetchAll();

// Totals for the summary cards
$total_listed   = count($donations);
$units_approved = 0;
$units_routed   = 0;
foreach ($donations as $donation) {
    if ($donation['status'] === 'approved') {
        $units_approved += (int) $donation['quantity'];
    }
    $units_routed += (int) $donation['routed'];
}

// 3. Show the page
$page_title = 'My donations';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">My donations</h1>
        <p class="text-muted mb-0">Salamat sa iyong tulong!</p>
    </div>
    <a href="<?= e(url('donor/donation_new.php')) ?>" class="btn btn-primary btn-lg">Donate medicine</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-4"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Donations listed</div><div class="kpi-value"><?= $total_listed ?></div>
    </div></div></div>
    <div class="col-4"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Units approved</div><div class="kpi-value"><?= number_format($units_approved) ?></div>
    </div></div></div>
    <div class="col-4"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Units routed to patients</div><div class="kpi-value"><?= number_format($units_routed) ?></div>
    </div></div></div>
</div>

<?php if (!$donations): ?>
    <p class="text-muted">You haven't listed any donations yet.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($donations as $donation): ?>
            <a class="list-group-item list-group-item-action py-3" href="<?= e(url('donor/donation_view.php?id=' . $donation['id'])) ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">#<?= (int) $donation['id'] ?> · <?= e(medicine_name($donation)) ?></div>
                        <div class="small text-muted">
                            <?= (int) $donation['quantity'] ?> <?= e($donation['unit']) ?>s ·
                            expires <?= e(format_date($donation['expiry_date'])) ?> ·
                            listed <?= e(format_date($donation['created_at'])) ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?= donation_badge($donation['status']) ?>
                        <?php if ((int) $donation['routed'] > 0): ?>
                            <div class="small text-muted"><?= (int) $donation['routed'] ?> routed to patients</div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
