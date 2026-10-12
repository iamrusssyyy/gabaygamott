<?php
// =====================================================================
// partner/request_view.php — a partner program coordinator works on a
// request matched to their program:
//   * Ready to claim (choose the claim pharmacy)
//   * Claimed
//   * Action needed (ask the beneficiary for something)
// Verifying, matching, and rejecting stay with the GabayGamot admin.
// =====================================================================

// 1. Only partners, and only requests matched to THEIR program
require_once __DIR__ . '/../includes/init.php';
require_role(['partner']);
$user = current_user();

$request = load_request(to_int(get('id')) ?? 0);
if (!$request || $request['partner_program_id'] === null
    || (int) $request['partner_program_id'] !== (int) $user['partner_program_id']) {
    http_response_code(404);
    exit('Request not found.');
}
$this_page = 'partner/request_view.php?id=' . $request['id'];
$allowed_actions = ['ready', 'claimed', 'action_needed'];

// 2. Handle the action buttons
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $error = process_request_action($request, $allowed_actions);
    set_flash($error === '' ? 'success' : 'danger', $error === '' ? 'Request updated.' : $error);
    redirect($this_page);
}

// 3. Load details
$documents  = load_request_documents((int) $request['id']);
$history    = load_request_history((int) $request['id']);
$action_url = url($this_page);

// 4. Show the page
$page_title = 'Request ' . $request['reference_no'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a href="<?= e(url('partner/dashboard.php?status=' . $request['status'])) ?>" class="small text-decoration-none">← Matched requests</a>
    <h1 class="mono mt-1"><?= e($request['reference_no']) ?></h1>
</div>

<!-- Details on the left, the action panel on the right (stays in view while scrolling) -->
<div class="row g-4">
    <div class="col-12 col-lg-7 order-2 order-lg-1">
        <?php require __DIR__ . '/../includes/request_details.php'; ?>
    </div>
    <div class="col-12 col-lg-5 order-1 order-lg-2">
        <div class="map-sticky">
            <?php require __DIR__ . '/../includes/request_actions.php'; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
