<?php
// =====================================================================
// admin/requests.php — all assistance requests, one tab per status.
// Most urgent first: people with no medicine left are at the top.
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

// 2. Filters (status tab is checked against our list = whitelist)
$tab = get('status');
if (!isset(REQUEST_STATUSES[$tab])) {
    $tab = 'submitted';
}
$search = mb_substr(get('q'), 0, 60);

$counts = db()->query('SELECT status, COUNT(*) FROM assistance_requests GROUP BY status')
              ->fetchAll(PDO::FETCH_KEY_PAIR);

$sql = "SELECT r.id, r.reference_no, r.patient_name, r.patient_birthdate, r.patient_city, r.status,
               r.quantity_per_month, r.supply_left, r.submitted_at, r.updated_at,
               m.generic_name, m.brand_name, m.strength, m.dosage_form,
               pp.name AS program_name,
               (SELECT COUNT(*) FROM request_documents d WHERE d.request_id = r.id) AS document_count
        FROM assistance_requests r
        JOIN medicines m ON m.id = r.medicine_id
        LEFT JOIN partner_programs pp ON pp.id = r.partner_program_id
        WHERE r.status = ?";
$params = [$tab];
if ($search !== '') {
    $sql .= ' AND (r.reference_no LIKE ? OR r.patient_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
// FIELD() returns the position in our list, so 'none' (1) sorts before 'gt_1_month' (4)
$sql .= " ORDER BY FIELD(r.supply_left, 'none', 'lt_1_week', '1_4_weeks', 'gt_1_month'), r.submitted_at
          LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// 3. Show the page
$page_title = 'Requests';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Assistance requests</h1>
    <form method="get" action="<?= e(url('admin/requests.php')) ?>" class="d-flex gap-2">
        <input type="hidden" name="status" value="<?= e($tab) ?>">
        <input type="search" name="q" class="form-control" value="<?= e($search) ?>"
               placeholder="Reference no. or patient" aria-label="Search requests">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </form>
</div>

<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach (REQUEST_STATUSES as $status => $info): ?>
        <li class="nav-item">
            <a class="nav-link text-nowrap<?= $tab === $status ? ' active' : '' ?>"
               href="<?= e(url('admin/requests.php?status=' . $status)) ?>">
                <?= e($info['label']) ?>
                <span class="badge text-bg-<?= e($info['color']) ?>"><?= (int) ($counts[$status] ?? 0) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<p class="small text-muted"><?= e(REQUEST_STATUSES[$tab]['help']) ?></p>

<div class="table-responsive bg-white border rounded">
    <table class="table align-middle mb-0">
        <thead>
            <tr><th>Reference</th><th>Patient</th><th>Medicine</th><th>Left</th><th>Program</th><th>Docs</th><th>Submitted</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
            <tr>
                <td class="text-nowrap"><?= e($request['reference_no']) ?></td>
                <td>
                    <?= e($request['patient_name']) ?>
                    <div class="small text-muted"><?= age_from_birthdate($request['patient_birthdate']) ?> yrs · <?= e($request['patient_city']) ?></div>
                </td>
                <td>
                    <?= e(medicine_name($request)) ?>
                    <div class="small text-muted"><?= (int) $request['quantity_per_month'] ?> per month</div>
                </td>
                <td><?= urgency_badge($request['supply_left']) ?></td>
                <td class="small"><?= e($request['program_name'] ?? '—') ?></td>
                <td><?= (int) $request['document_count'] ?></td>
                <td class="small text-nowrap"><?= e(time_ago($request['submitted_at'])) ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-primary" href="<?= e(url('admin/request_view.php?id=' . $request['id'])) ?>">Open</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?>
            <tr><td colspan="8" class="text-muted">No requests here.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
