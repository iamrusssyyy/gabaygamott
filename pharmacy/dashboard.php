<?php
// =====================================================================
// pharmacy/dashboard.php — a pharmacy's home page:
// store details, stock summary, items that need attention, donations.
// =====================================================================

// 1. Only pharmacy accounts
require_once __DIR__ . '/../includes/init.php';
require_role(['pharmacy']);
$pharmacy = current_pharmacy();
if (!$pharmacy) {
    exit('No pharmacy profile is linked to this account. Please contact the admin.');
}

// ---------------------------------------------------------------------
// 2. Load data
// ---------------------------------------------------------------------

// Stock summary: SUM(condition) counts the rows where the condition is true
$stmt = db()->prepare(
    'SELECT COUNT(*) AS total,
            SUM(quantity > low_stock_threshold)               AS in_stock,
            SUM(quantity > 0 AND quantity <= low_stock_threshold) AS low,
            SUM(quantity = 0)                                 AS out_of_stock,
            MIN(last_updated_at)                              AS oldest_update
     FROM pharmacy_stock WHERE pharmacy_id = ?'
);
$stmt->execute([$pharmacy['id']]);
$summary = $stmt->fetch();

// Items that need attention: low or out of stock, or not updated for 3+ days
$stmt = db()->prepare(
    'SELECT s.quantity, s.low_stock_threshold, s.last_updated_at,
            m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM pharmacy_stock s JOIN medicines m ON m.id = s.medicine_id
     WHERE s.pharmacy_id = ?
       AND (s.quantity <= s.low_stock_threshold OR s.last_updated_at < NOW() - INTERVAL 3 DAY)
     ORDER BY s.quantity, s.last_updated_at'
);
$stmt->execute([$pharmacy['id']]);
$attention = $stmt->fetchAll();

// This pharmacy's donations (pharmacies can donate too)
$stmt = db()->prepare(
    'SELECT d.id, d.quantity, d.expiry_date, d.status, d.created_at,
            m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM donations d JOIN medicines m ON m.id = d.medicine_id
     WHERE d.donor_id = ? ORDER BY d.created_at DESC LIMIT 5'
);
$stmt->execute([current_user()['id']]);
$donations = $stmt->fetchAll();

// ---------------------------------------------------------------------
// 3. Show the page
// ---------------------------------------------------------------------
$page_title = 'Pharmacy dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1"><?= e($pharmacy['name']) ?><?= $pharmacy['branch'] ? ' · ' . e($pharmacy['branch']) : '' ?></h1>
<p class="text-muted">
    <?= e($pharmacy['address']) ?>, <?= e($pharmacy['city']) ?> ·
    <?= e($pharmacy['phone'] ?? '') ?> · <?= e($pharmacy['opening_hours'] ?? 'Hours not set') ?>
</p>

<!-- Summary numbers -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted">Medicines listed</div>
            <div class="fs-3 fw-semibold"><?= (int) $summary['total'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted"><span class="text-success">●</span> In stock</div>
            <div class="fs-3 fw-semibold"><?= (int) $summary['in_stock'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted"><span class="text-warning">●</span> Low stock</div>
            <div class="fs-3 fw-semibold"><?= (int) $summary['low'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-muted"><span class="text-danger">●</span> Out of stock</div>
            <div class="fs-3 fw-semibold"><?= (int) $summary['out_of_stock'] ?></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Needs your attention</h2>
            <a href="<?= e(url('pharmacy/stock.php')) ?>" class="btn btn-primary btn-sm">Update stock</a>
        </div>
        <?php if (!$attention): ?>
            <p class="text-muted">All items are in stock and were updated in the last 3 days. Great!</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($attention as $item): ?>
                    <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                        <span><?= e(medicine_name($item)) ?></span>
                        <span class="small">
                            <?= stock_pill((int) $item['quantity'], (int) $item['low_stock_threshold']) ?>
                            · <span class="text-muted">Updated <?= e(time_ago($item['last_updated_at'])) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Your donations</h2>
            <a href="<?= e(url('donor/donation_new.php')) ?>" class="btn btn-outline-primary btn-sm">Donate medicine</a>
        </div>
        <?php if (!$donations): ?>
            <p class="text-muted">Have overstock or near-expiry boxes? Donate them to patients who need them.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($donations as $donation): ?>
                    <a class="list-group-item list-group-item-action" href="<?= e(url('donor/donation_view.php?id=' . $donation['id'])) ?>">
                        <div class="d-flex justify-content-between">
                            <span><?= e(medicine_name($donation)) ?> × <?= (int) $donation['quantity'] ?></span>
                            <?= donation_badge($donation['status']) ?>
                        </div>
                        <div class="small text-muted">Expires <?= e(format_date($donation['expiry_date'])) ?></div>
                    </a>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
