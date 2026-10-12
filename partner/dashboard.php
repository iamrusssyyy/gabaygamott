<?php
// =====================================================================
// partner/dashboard.php — requests matched to THIS partner's program
// (prototype "Partner matched requests"). A partner never sees requests
// of other programs.
// =====================================================================

// 1. Only partners
require_once __DIR__ . '/../includes/init.php';
require_role(['partner']);
$user = current_user();

// 2. Load the program and its requests
$stmt = db()->prepare('SELECT name FROM partner_programs WHERE id = ?');
$stmt->execute([$user['partner_program_id']]);
$program_name = $stmt->fetchColumn() ?: 'your program';

$tabs = ['matched' => 'Matched', 'ready_to_claim' => 'Ready to claim', 'claimed' => 'Claimed', 'action_needed' => 'Action needed'];
$tab = get('status');
if (!isset($tabs[$tab])) {
    $tab = 'matched';
}

$counts_stmt = db()->prepare('SELECT status, COUNT(*) FROM assistance_requests WHERE partner_program_id = ? GROUP BY status');
$counts_stmt->execute([$user['partner_program_id']]);
$counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = db()->prepare(
    "SELECT r.id, r.reference_no, r.patient_name, r.patient_city, r.quantity_per_month, r.supply_left,
            r.status, r.updated_at, m.generic_name, m.brand_name, m.strength, m.dosage_form
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

<div class="page-head">
    <h1>Matched requests</h1>
    <div class="subtitle"><?= e($program_name) ?> · only requests matched to your program are shown.</div>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $status => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $status ? ' active' : '' ?>" href="<?= e(url('partner/dashboard.php?status=' . $status)) ?>">
                <?= e($label) ?> · <?= (int) ($counts[$status] ?? 0) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="table-card table-responsive">
    <table class="table align-middle">
        <thead>
            <tr><th>Reference</th><th>Patient</th><th>Medicine</th><th>City</th><th>Medicine left</th><th>Updated</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
            <tr>
                <td class="text-nowrap">
                    <a class="mono fw-semibold text-decoration-none" href="<?= e(url('partner/request_view.php?id=' . $request['id'])) ?>">
                        <?= e($request['reference_no']) ?></a>
                </td>
                <td><?= e($request['patient_name']) ?></td>
                <td><?= e(medicine_name($request)) ?> × <?= (int) $request['quantity_per_month'] ?></td>
                <td><?= e($request['patient_city']) ?></td>
                <td><?= urgency_badge($request['supply_left']) ?></td>
                <td class="text-muted text-nowrap"><?= e(format_date($request['updated_at'])) ?></td>
                <td><?= request_badge($request['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?>
            <tr><td colspan="7" class="text-muted">No requests here.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<p class="small text-muted mt-2">Most urgent first. Open a reference number to see documents and update the request.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
