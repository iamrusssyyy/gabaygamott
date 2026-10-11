<?php
// =====================================================================
// donor/donation_view.php — the donor's view of one donation: status,
// admin checklist result, and how much was routed to patients.
// For privacy, donors see HOW MANY units helped patients, but not who
// the patients are.
// =====================================================================

// 1. Only the donor (or pharmacy) who listed it
require_once __DIR__ . '/../includes/init.php';
require_role(['donor', 'pharmacy']);
$user = current_user();

$stmt = db()->prepare(
    'SELECT d.*, m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit
     FROM donations d JOIN medicines m ON m.id = d.medicine_id
     WHERE d.id = ? AND d.donor_id = ?'
);
$stmt->execute([to_int(get('id')) ?? 0, $user['id']]);
$donation = $stmt->fetch();

if (!$donation) {
    http_response_code(404);
    $page_title = 'Not found';
    require __DIR__ . '/../includes/header.php';
    echo '<p>Donation not found.</p>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// 2. Where the units went: counted by request status (no names)
$stmt = db()->prepare(
    'SELECT r.status, COUNT(*) AS requests, SUM(a.quantity) AS units
     FROM donation_allocations a JOIN assistance_requests r ON r.id = a.request_id
     WHERE a.donation_id = ? GROUP BY r.status'
);
$stmt->execute([$donation['id']]);
$routed = $stmt->fetchAll();
$routed_units = array_sum(array_column($routed, 'units'));
$remaining = (int) $donation['quantity'] - $routed_units;

$checklist = [
    'check_sealed'        => 'Sealed, unopened packaging',
    'check_label_legible' => 'Name, strength, and lot are readable',
    'check_expiry_valid'  => 'Expiry date matches the listing',
    'check_matches_photo' => 'Item matches the photo',
];

// 3. Show the page
$page_title = 'Donation #' . $donation['id'];
$back = current_user()['role'] === 'pharmacy' ? 'pharmacy/dashboard.php' : 'donor/dashboard.php';
require __DIR__ . '/../includes/header.php';
?>

<a href="<?= e(url($back)) ?>" class="small">← Back</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Donation #<?= (int) $donation['id'] ?></h1>
        <div class="text-muted">Listed <?= e(format_datetime($donation['created_at'])) ?></div>
    </div>
    <div class="fs-5"><?= donation_badge($donation['status']) ?></div>
</div>

<?php if ($donation['status'] === 'rejected'): ?>
    <div class="alert alert-danger"><strong>Not accepted:</strong> <?= e($donation['rejection_reason']) ?></div>
<?php elseif ($donation['status'] === 'pending'): ?>
    <div class="alert alert-warning">An admin will check your donation using the photo and details you gave.</div>
<?php else: ?>
    <div class="alert alert-success">Approved! Salamat. Your donation is being matched with patients who need it.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-md-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="fs-5 fw-semibold"><?= e(medicine_name($donation)) ?></div>
                <dl class="row small mb-0 mt-2">
                    <dt class="col-5">Quantity</dt><dd class="col-7"><?= (int) $donation['quantity'] ?> <?= e($donation['unit']) ?>s</dd>
                    <dt class="col-5">Expiry date</dt><dd class="col-7"><?= e(format_date($donation['expiry_date'])) ?></dd>
                    <dt class="col-5">Lot no.</dt><dd class="col-7"><?= e($donation['lot_number'] ?? '—') ?></dd>
                    <?php if ($donation['donor_notes']): ?>
                        <dt class="col-5">Your notes</dt><dd class="col-7"><?= e($donation['donor_notes']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($donation['status'] !== 'pending'): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted">Admin checklist</h2>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($checklist as $column => $label): ?>
                            <li><?= $donation[$column] ? '✅' : '❌' ?> <?= e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($donation['status'] === 'approved'): ?>
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 text-muted">Helping patients</h2>
                    <p class="mb-1"><strong><?= (int) $routed_units ?></strong> of <?= (int) $donation['quantity'] ?>
                        <?= e($donation['unit']) ?>s routed to patients · <strong><?= $remaining ?></strong> still available</p>
                    <?php foreach ($routed as $row): ?>
                        <div class="small"><?= request_badge($row['status']) ?> <?= (int) $row['units'] ?> units for
                            <?= (int) $row['requests'] ?> <?= (int) $row['requests'] === 1 ? 'request' : 'requests' ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-md-5">
        <?php if ($donation['photo_stored_name']): ?>
            <img src="<?= e(url('files/view.php?type=donation_photo&id=' . $donation['id'])) ?>"
                 alt="Packaging photo" class="img-fluid rounded border bg-white">
        <?php else: ?>
            <div class="border rounded p-4 text-center text-muted bg-white">No photo (sample data)</div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
