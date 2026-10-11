<?php
// =====================================================================
// admin/request_view.php — the admin processes one request:
//   * status actions: verify, match, ready to claim, claimed,
//     action needed, reject (see includes/request_actions.php)
//   * ROUTING: give units of approved donations to this request
//     (rows in donation_allocations)
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

$request = load_request(to_int(get('id')) ?? 0);
if (!$request) {
    http_response_code(404);
    exit('Request not found.');
}
$this_page = 'admin/request_view.php?id=' . $request['id'];

// Routing is allowed while the request is being matched
$can_route = in_array($request['status'], ['verified', 'matched'], true);

// ---------------------------------------------------------------------
// 2. Handle the forms
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'allocate' && $can_route) {
        // ---- Route donated units to this request ----
        $donation_id = to_int(post('donation_id')) ?? 0;
        $quantity    = to_int(post('quantity'));
        $pdo = db();
        $pdo->beginTransaction();

        // "FOR UPDATE" locks this donation row until we commit, so two
        // admins can't give away the same units at the same moment.
        $stmt = $pdo->prepare('SELECT id, medicine_id, quantity, status, expiry_date FROM donations WHERE id = ? FOR UPDATE');
        $stmt->execute([$donation_id]);
        $donation = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM donation_allocations WHERE donation_id = ?');
        $stmt->execute([$donation_id]);
        $remaining = $donation ? (int) $donation['quantity'] - (int) $stmt->fetchColumn() : 0;

        $error = '';
        if (!$donation || $donation['status'] !== 'approved') {
            $error = 'Only approved donations can be routed.';
        } elseif ((int) $donation['medicine_id'] !== (int) $request['medicine_id']) {
            $error = 'That donation is a different medicine.';
        } elseif ($donation['expiry_date'] <= date('Y-m-d')) {
            $error = 'That donation has expired.';
        } elseif ($quantity === null || $quantity < 1 || $quantity > $remaining) {
            $error = 'Enter a quantity from 1 to ' . $remaining . '.';
        }

        if ($error !== '') {
            $pdo->rollBack();
            set_flash('danger', $error);
        } else {
            $pdo->prepare('INSERT INTO donation_allocations (donation_id, request_id, quantity, allocated_by) VALUES (?, ?, ?, ?)')
                ->execute([$donation_id, $request['id'], $quantity, current_user()['id']]);
            $pdo->commit();
            set_flash('success', $quantity . ' units from donation #' . $donation_id . ' were routed to this request.');
        }

    } elseif ($action === 'unallocate' && $can_route) {
        // ---- Undo a routing mistake ----
        $stmt = db()->prepare('DELETE FROM donation_allocations WHERE id = ? AND request_id = ?');
        $stmt->execute([to_int(post('allocation_id')) ?? 0, $request['id']]);
        set_flash('info', 'Routing removed. The units are available again.');

    } else {
        // ---- Status buttons (shared with the partner page) ----
        $error = process_request_action($request, array_keys(REQUEST_ACTIONS));
        set_flash($error === '' ? 'success' : 'danger', $error === '' ? 'Request updated.' : $error);
    }
    redirect($this_page);
}

// ---------------------------------------------------------------------
// 3. Load data
// ---------------------------------------------------------------------
$documents = load_request_documents((int) $request['id']);
$history   = load_request_history((int) $request['id']);

// Units already routed to this request
$stmt = db()->prepare(
    'SELECT a.id, a.quantity, a.allocated_at, d.id AS donation_id, d.expiry_date
     FROM donation_allocations a JOIN donations d ON d.id = a.donation_id
     WHERE a.request_id = ? ORDER BY a.allocated_at'
);
$stmt->execute([$request['id']]);
$allocations = $stmt->fetchAll();

// Approved donations of the SAME medicine that still have units left.
// Soonest-expiring first ("first expiry, first out"), so less medicine
// goes to waste.
$available = [];
if ($can_route) {
    $stmt = db()->prepare(
        "SELECT d.id, d.expiry_date, d.quantity, u.full_name AS donor_name, u.organization_name,
                d.quantity - COALESCE(SUM(a.quantity), 0) AS remaining
         FROM donations d
         JOIN users u ON u.id = d.donor_id
         LEFT JOIN donation_allocations a ON a.donation_id = d.id
         WHERE d.status = 'approved' AND d.medicine_id = ? AND d.expiry_date > CURDATE()
         GROUP BY d.id
         HAVING remaining > 0
         ORDER BY d.expiry_date"
    );
    $stmt->execute([$request['medicine_id']]);
    $available = $stmt->fetchAll();
}

// Settings for includes/request_actions.php (admins may do every action)
$allowed_actions = array_keys(REQUEST_ACTIONS);
$action_url = url($this_page);

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'Request ' . $request['reference_no'];
require __DIR__ . '/../includes/header.php';
?>

<a href="<?= e(url('admin/requests.php?status=' . $request['status'])) ?>" class="small">← Requests</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <div>
        <h1 class="h3 mb-0"><?= e($request['reference_no']) ?></h1>
        <div class="text-muted small">
            Submitted <?= e(format_datetime($request['submitted_at'])) ?> by
            <?= e($request['beneficiary_name']) ?> · <?= e($request['beneficiary_email']) ?>
            <?= $request['beneficiary_phone'] ? ' · ' . e($request['beneficiary_phone']) : '' ?>
        </div>
    </div>
    <div class="fs-5"><?= request_badge($request['status']) ?> <?= urgency_badge($request['supply_left']) ?></div>
</div>

<?php if ($request['status_note']): ?>
    <div class="alert alert-light border small"><strong>Latest note:</strong> <?= e($request['status_note']) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <?php require __DIR__ . '/../includes/request_actions.php'; ?>
    </div>

    <!-- Routing donated medicine -->
    <div class="col-12 col-lg-6">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Donated medicine for this request</h2>
                <p class="small text-muted">Needs <?= (int) $request['quantity_per_month'] ?> <?= e($request['unit']) ?>s per month
                    (<?= e(DURATIONS[$request['duration']]) ?>). Routed so far: <strong><?= (int) $request['allocated_units'] ?></strong>.</p>

                <?php foreach ($allocations as $allocation): ?>
                    <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                        <span>Donation #<?= (int) $allocation['donation_id'] ?> · <?= (int) $allocation['quantity'] ?> units ·
                            expires <?= e(format_date($allocation['expiry_date'])) ?></span>
                        <?php if ($can_route): ?>
                            <form method="post" action="<?= e($action_url) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unallocate">
                                <input type="hidden" name="allocation_id" value="<?= (int) $allocation['id'] ?>">
                                <button type="submit" class="btn btn-link btn-sm text-danger p-0">Undo</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (!$can_route): ?>
                    <p class="small text-muted mt-2 mb-0">Routing is possible when the request is Verified or Matched.</p>
                <?php elseif (!$available): ?>
                    <p class="small text-muted mt-2 mb-0">No approved donations of this medicine are available right now.</p>
                <?php else: ?>
                    <h3 class="h6 mt-3">Available donations (soonest expiry first)</h3>
                    <?php foreach ($available as $donation): ?>
                        <form method="post" action="<?= e($action_url) ?>" class="d-flex flex-wrap align-items-center gap-2 border rounded p-2 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="allocate">
                            <input type="hidden" name="donation_id" value="<?= (int) $donation['id'] ?>">
                            <div class="small flex-grow-1">
                                <a href="<?= e(url('admin/donation_view.php?id=' . $donation['id'])) ?>">Donation #<?= (int) $donation['id'] ?></a>
                                · <?= (int) $donation['remaining'] ?> left · expires <?= e(format_date($donation['expiry_date'])) ?><br>
                                <span class="text-muted"><?= e($donation['organization_name'] ?? $donation['donor_name']) ?></span>
                            </div>
                            <input type="number" name="quantity" min="1" max="<?= (int) $donation['remaining'] ?>"
                                   value="<?= min((int) $donation['remaining'], (int) $request['quantity_per_month']) ?>"
                                   class="form-control form-control-sm" style="width: 6rem" aria-label="Units to route">
                            <button type="submit" class="btn btn-sm btn-primary">Route</button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/request_details.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
