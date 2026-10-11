<?php
// =====================================================================
// partner/dashboard.php — requests matched to THIS partner's program.
// A partner never sees requests of other programs.
// =====================================================================

// 1. Only partners
require_once __DIR__ . '/../includes/init.php';
require_role(['partner']);
$user = current_user();

// 2. Load the program and its requests
$stmt = db()->prepare('SELECT name FROM partner_programs WHERE id = ?');
$stmt->execute([$user['partner_program_id']]);
$program_name = $stmt->fetchColumn() ?: 'your program';

$tabs = ['matched' => 'To prepare', 'ready_to_claim' => 'Ready to claim', 'claimed' => 'Claimed', 'action_needed' => 'Action needed'];
$tab = get('status');
if (!isset($tabs[$tab])) {
    $tab = 'matched';
}

$counts_stmt = db()->prepare('SELECT status, COUNT(*) FROM assistance_requests WHERE partner_program_id = ? GROUP BY status');
$counts_stmt->execute([$user['partner_program_id']]);
$counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = db()->prepare(
    "SELECT r.id, r.reference_no, r.patient_name, r.patient_city, r.quantity_per_month, r.supply_left,
            r.updated_at, m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM assistance_requests r JOIN medicines m ON m.id = r.medicine_id
     WHERE r.partner_program_id = ? AND r.status = ?
     ORDER BY FIELD(r.supply_left, 'none', 'lt_1_week', '1_4_weeks', 'gt_1_month'), r.updated_at"
);
$stmt->execute([$user['partner_program_id'], $tab]);
$requests = $stmt->fetchAll();

// 3. Show the page
$page_title = 'Matched requests';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-0">Matched requests</h1>
<p class="text-muted"><?= e($program_name) ?></p>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $status ? ' active' : '' ?>" href="<?= e(url('partner/dashboard.php?status=' . $status)) ?>">
                <?= e($label) ?> <span class="badge text-bg-secondary"><?= (int) ($counts[$status] ?? 0) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$requests): ?>
    <p class="text-muted">No requests here.</p>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($requests as $request): ?>
            <a class="list-group-item list-group-item-action py-3" href="<?= e(url('partner/request_view.php?id=' . $request['id'])) ?>">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold"><?= e($request['reference_no']) ?> · <?= e(medicine_name($request)) ?></div>
                        <div class="small text-muted"><?= e($request['patient_name']) ?> · <?= e($request['patient_city']) ?> ·
                            <?= (int) $request['quantity_per_month'] ?> per month</div>
                    </div>
                    <div class="text-end">
                        <?= urgency_badge($request['supply_left']) ?>
                        <div class="small text-muted">updated <?= e(time_ago($request['updated_at'])) ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
