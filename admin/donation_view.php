<?php
// =====================================================================
// admin/donation_view.php — verify one donation with a checklist, then
// approve it or reject it with a reason. For approved donations, shows
// where the units were routed and which requests still need this medicine.
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

$donation_id = to_int(get('id')) ?? 0;

// The 4 checklist items (column name => what the admin checks)
$checklist = [
    'check_sealed'        => 'Packaging is sealed and unopened',
    'check_label_legible' => 'Name, strength, and lot number are readable',
    'check_expiry_valid'  => 'Expiry date on the box matches the listing',
    'check_matches_photo' => 'The item matches the photo',
];

function load_donation(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT d.*, m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
                u.full_name AS donor_name, u.email AS donor_email, u.phone AS donor_phone,
                u.organization_name, u.role AS donor_role,
                r.full_name AS reviewer_name,
                d.quantity - (SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a WHERE a.donation_id = d.id)
                    AS remaining
         FROM donations d
         JOIN medicines m ON m.id = d.medicine_id
         JOIN users u ON u.id = d.donor_id
         LEFT JOIN users r ON r.id = d.reviewed_by
         WHERE d.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$donation = load_donation($donation_id);
if (!$donation) {
    http_response_code(404);
    exit('Donation not found.');
}
$errors = [];

// ---------------------------------------------------------------------
// 2. Approve or reject
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $donation['status'] === 'pending') {
    verify_csrf();
    $action = post('action');
    $reason = mb_substr(post('rejection_reason'), 0, 255);

    // Which boxes were ticked (1 or 0 for each)
    $checks = [];
    foreach (array_keys($checklist) as $column) {
        $checks[$column] = post($column) === '1' ? 1 : 0;
    }

    if ($action === 'approve') {
        if (in_array(0, $checks, true)) {
            $errors['checklist'] = 'Tick all 4 checklist items to approve. If something fails, reject with a reason.';
        } elseif ($donation['expiry_date'] <= date('Y-m-d')) {
            $errors['checklist'] = 'This medicine has already expired and cannot be approved.';
        }
    } elseif ($action === 'reject') {
        if ($reason === '') {
            $errors['rejection_reason'] = 'Please tell the donor why, e.g. "Packaging is opened."';
        }
    } else {
        $errors['checklist'] = 'Unknown action.';
    }

    if (!$errors) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        // "AND status = 'pending'" stops two admins from reviewing it twice
        $stmt = db()->prepare(
            "UPDATE donations
             SET status = ?, check_sealed = ?, check_label_legible = ?, check_expiry_valid = ?,
                 check_matches_photo = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([
            $new_status,
            $checks['check_sealed'], $checks['check_label_legible'],
            $checks['check_expiry_valid'], $checks['check_matches_photo'],
            $new_status === 'rejected' ? $reason : null,
            current_user()['id'],
            $donation['id'],
        ]);

        if ($new_status === 'approved') {
            notify((int) $donation['donor_id'],
                   'Your donation #' . $donation['id'] . ' (' . medicine_name($donation) . ') was approved. '
                   . 'The GabayGamot team will contact you about drop-off. Salamat!',
                   'donor/donation_view.php?id=' . $donation['id']);
            run_alert_checks(); // in case it is already near expiry
            set_flash('success', 'Donation #' . $donation['id'] . ' approved. You can now route it to requests.');
        } else {
            notify((int) $donation['donor_id'],
                   'Your donation #' . $donation['id'] . ' was not accepted: ' . $reason,
                   'donor/donation_view.php?id=' . $donation['id']);
            set_flash('info', 'Donation #' . $donation['id'] . ' rejected. The donor was told the reason.');
        }
        redirect('admin/donation_view.php?id=' . $donation['id']);
    }
}

// ---------------------------------------------------------------------
// 3. Load routing information (approved donations)
// ---------------------------------------------------------------------
$allocations = [];
$waiting_requests = [];
if ($donation['status'] === 'approved') {
    $stmt = db()->prepare(
        'SELECT a.quantity, a.allocated_at, r.id AS request_id, r.reference_no, r.status
         FROM donation_allocations a JOIN assistance_requests r ON r.id = a.request_id
         WHERE a.donation_id = ? ORDER BY a.allocated_at'
    );
    $stmt->execute([$donation['id']]);
    $allocations = $stmt->fetchAll();

    // Requests for the SAME medicine that can still receive donations,
    // most urgent first (FIELD() orders by our supply_left list)
    $stmt = db()->prepare(
        "SELECT r.id, r.reference_no, r.status, r.supply_left, r.quantity_per_month, r.patient_city
         FROM assistance_requests r
         WHERE r.medicine_id = ? AND r.status IN ('verified', 'matched')
         ORDER BY FIELD(r.supply_left, 'none', 'lt_1_week', '1_4_weeks', 'gt_1_month'), r.submitted_at"
    );
    $stmt->execute([$donation['medicine_id']]);
    $waiting_requests = $stmt->fetchAll();
}

$days_left = (int) floor((strtotime($donation['expiry_date']) - strtotime('today')) / 86400);

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'Donation #' . $donation['id'];
require __DIR__ . '/../includes/header.php';
?>

<a href="<?= e(url('admin/donations.php?status=' . $donation['status'])) ?>" class="small">← Donations</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <h1 class="h3 mb-0">Donation #<?= (int) $donation['id'] ?></h1>
    <div class="fs-5"><?= donation_badge($donation['status']) ?></div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="fs-5 fw-semibold"><?= e(medicine_name($donation)) ?></div>
                <dl class="row small mb-0 mt-2">
                    <dt class="col-4">Quantity</dt>
                    <dd class="col-8"><?= (int) $donation['quantity'] ?> <?= e($donation['unit']) ?>s
                        <?php if ($donation['status'] === 'approved'): ?> · <strong><?= (int) $donation['remaining'] ?></strong> not yet routed<?php endif; ?></dd>
                    <dt class="col-4">Expiry date</dt>
                    <dd class="col-8 <?= $days_left <= NEAR_EXPIRY_DAYS ? 'text-danger fw-semibold' : '' ?>">
                        <?= e(format_date($donation['expiry_date'])) ?> (<?= $days_left < 0 ? 'expired' : $days_left . ' days left' ?>)</dd>
                    <dt class="col-4">Lot no.</dt><dd class="col-8"><?= e($donation['lot_number'] ?? '—') ?></dd>
                    <dt class="col-4">Donor notes</dt><dd class="col-8"><?= e($donation['donor_notes'] ?? '—') ?></dd>
                    <dt class="col-4">Donor</dt>
                    <dd class="col-8">
                        <?= e($donation['donor_name']) ?> (<?= e($donation['donor_role']) ?>)<?= $donation['organization_name'] ? ' · ' . e($donation['organization_name']) : '' ?><br>
                        <?= e($donation['donor_email']) ?> · <?= e($donation['donor_phone'] ?? 'no phone') ?>
                    </dd>
                    <dt class="col-4">Listed</dt><dd class="col-8"><?= e(format_datetime($donation['created_at'])) ?></dd>
                    <?php if ($donation['reviewed_at']): ?>
                        <dt class="col-4">Reviewed</dt>
                        <dd class="col-8"><?= e(format_datetime($donation['reviewed_at'])) ?> by <?= e($donation['reviewer_name'] ?? 'admin') ?></dd>
                    <?php endif; ?>
                    <?php if ($donation['rejection_reason']): ?>
                        <dt class="col-4">Reason</dt><dd class="col-8 text-danger"><?= e($donation['rejection_reason']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($donation['status'] === 'pending'): ?>
            <!-- Verification checklist -->
            <div class="card border-primary mb-3">
                <div class="card-body">
                    <h2 class="h5">Verification checklist</h2>
                    <p class="small text-muted">Check the photo (and the actual box when it is dropped off).</p>
                    <form method="post" action="<?= e(url('admin/donation_view.php?id=' . $donation['id'])) ?>" novalidate>
                        <?= csrf_field() ?>
                        <?php foreach ($checklist as $column => $label): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="<?= e($column) ?>" name="<?= e($column) ?>" value="1"
                                       <?= post($column) === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($column) ?>"><?= e($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?php if (isset($errors['checklist'])): ?>
                            <div class="text-danger small mb-2"><?= e($errors['checklist']) ?></div>
                        <?php endif; ?>
                        <button type="submit" name="action" value="approve" class="btn btn-primary mt-2">Approve donation</button>

                        <hr>
                        <label for="rejection_reason" class="form-label">Or reject with a reason</label>
                        <div class="d-flex gap-2">
                            <input type="text" id="rejection_reason" name="rejection_reason" maxlength="255"
                                   class="form-control<?= error_class($errors, 'rejection_reason') ?>"
                                   value="<?= e(post('rejection_reason')) ?>" placeholder="e.g. Packaging is already opened.">
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reject</button>
                        </div>
                        <?php if (isset($errors['rejection_reason'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted">Checklist result</h2>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($checklist as $column => $label): ?>
                            <li><?= $donation[$column] ? '✅' : '❌' ?> <?= e($label) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($donation['status'] === 'approved'): ?>
            <!-- Routing -->
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted">Routed to requests</h2>
                    <?php if (!$allocations): ?>
                        <p class="small text-muted mb-0">Not routed yet.</p>
                    <?php endif; ?>
                    <?php foreach ($allocations as $allocation): ?>
                        <div class="small mb-1">
                            <a href="<?= e(url('admin/request_view.php?id=' . $allocation['request_id'])) ?>"><?= e($allocation['reference_no']) ?></a>
                            · <?= (int) $allocation['quantity'] ?> units · <?= request_badge($allocation['status']) ?>
                            <span class="text-muted">(<?= e(format_date($allocation['allocated_at'])) ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ((int) $donation['remaining'] > 0): ?>
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 text-muted">Requests that need this medicine</h2>
                        <?php if (!$waiting_requests): ?>
                            <p class="small text-muted mb-0">No verified or matched requests for this medicine right now.</p>
                        <?php endif; ?>
                        <?php foreach ($waiting_requests as $waiting): ?>
                            <div class="small mb-1">
                                <a href="<?= e(url('admin/request_view.php?id=' . $waiting['id'])) ?>"><?= e($waiting['reference_no']) ?></a>
                                · <?= (int) $waiting['quantity_per_month'] ?>/month · <?= e($waiting['patient_city']) ?>
                                · <?= request_badge($waiting['status']) ?> <?= urgency_badge($waiting['supply_left']) ?>
                            </div>
                        <?php endforeach; ?>
                        <p class="small text-muted mt-2 mb-0">Open a request to route units from this donation.</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-5">
        <h2 class="h6 text-muted">Packaging photo</h2>
        <?php if ($donation['photo_stored_name']): ?>
            <a href="<?= e(url('files/view.php?type=donation_photo&id=' . $donation['id'])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(url('files/view.php?type=donation_photo&id=' . $donation['id'])) ?>"
                     alt="Packaging photo" class="img-fluid rounded border bg-white">
            </a>
            <div class="small text-muted mt-1">Click to open full size.</div>
        <?php else: ?>
            <div class="border rounded p-4 text-center text-muted bg-white">No photo (sample data)</div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
