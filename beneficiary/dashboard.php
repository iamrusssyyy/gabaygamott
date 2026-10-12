<?php
// =====================================================================
// beneficiary/dashboard.php — "My requests" (prototype "My requests track"):
// the list of requests on the left; on desktop, the newest request's
// details on the right. This is also where "Track request" leads.
// =====================================================================

// 1. Only beneficiaries
require_once __DIR__ . '/../includes/init.php';
require_role(['beneficiary']);
$user = current_user();

// 2. Load this beneficiary's requests, and the newest one's details
$my_requests = load_my_requests((int) $user['id']);
$request = $my_requests ? load_request((int) $my_requests[0]['id']) : null;
$selected_id = $request ? (int) $request['id'] : 0;
if ($request) {
    $documents = load_request_documents($selected_id);
    $history   = load_request_history($selected_id);
    $errors    = [];
}
$has_draft = !empty($_SESSION['request_draft']);   // unfinished wizard?

// 3. Show the page
$page_title = 'My requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 page-head">
    <div>
        <h1>My requests</h1>
        <div class="subtitle">Subaybayan ang request</div>
    </div>
    <a href="<?= e(url('beneficiary/request_new.php')) ?>" class="btn btn-primary btn-lg">
        <?= $has_draft ? 'Continue your request' : '+ New medicine request' ?>
    </a>
</div>

<?php if (!$my_requests): ?>
    <div class="card">
        <div class="card-body p-4">
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
    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <?php require __DIR__ . '/../includes/my_requests_list.php'; ?>
        </div>
        <!-- Desktop: details of the newest request (phones tap a card instead) -->
        <div class="col-12 col-lg-8 d-none d-lg-block">
            <?php require __DIR__ . '/../includes/my_request_detail.php'; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
