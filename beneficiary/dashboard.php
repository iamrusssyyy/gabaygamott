<?php
// =====================================================================
// beneficiary/dashboard.php — "My requests": the beneficiary's requests
// and their status. This is also where "Track request" leads.
// =====================================================================

// 1. Only beneficiaries
require_once __DIR__ . '/../includes/init.php';
require_role(['beneficiary']);
$user = current_user();

// 2. Load this beneficiary's requests (newest first)
$stmt = db()->prepare(
    'SELECT r.id, r.reference_no, r.patient_name, r.status, r.submitted_at, r.updated_at,
            m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM assistance_requests r
     JOIN medicines m ON m.id = r.medicine_id
     WHERE r.beneficiary_id = ?
     ORDER BY r.submitted_at DESC'
);
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

// Is there an unfinished draft in the wizard?
$has_draft = !empty($_SESSION['request_draft']);

// 3. Show the page
$page_title = 'My requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">My requests</h1>
        <p class="text-muted mb-0">Track your medicine assistance requests · Subaybayan ang iyong request</p>
    </div>
    <a href="<?= e(url('beneficiary/request_new.php')) ?>" class="btn btn-primary btn-lg">
        <?= $has_draft ? 'Continue your request' : 'Start a request' ?>
    </a>
</div>

<?php if (!$requests): ?>
    <div class="card">
        <div class="card-body">
            <h2 class="h5">You have no requests yet</h2>
            <p class="mb-2">Before you start, prepare:</p>
            <ul>
                <li>Prescription (photo or PDF)</li>
                <li>Certificate of indigency from your barangay</li>
                <li>Valid ID (optional)</li>
            </ul>
            <a href="<?= e(url('beneficiary/request_new.php')) ?>" class="btn btn-primary">Start a request</a>
        </div>
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($requests as $request): ?>
            <a class="list-group-item list-group-item-action py-3"
               href="<?= e(url('beneficiary/request_view.php?id=' . $request['id'])) ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold"><?= e(medicine_name($request)) ?></div>
                        <div class="small text-muted">
                            <?= e($request['reference_no']) ?> · for <?= e($request['patient_name']) ?> ·
                            submitted <?= e(format_date($request['submitted_at'])) ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?= request_badge($request['status']) ?>
                        <div class="small text-muted">updated <?= e(time_ago($request['updated_at'])) ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
