<?php
// =====================================================================
// admin/reports.php — PRINTABLE REPORTS for a date range (Chapter 1,
// Objective 3): donation flow, request fulfillment rate, beneficiaries
// served, and medicines distributed.
//
// Press "Print" (or Ctrl+P) — the menu and buttons are hidden when
// printing (see @media print in style.css), and the browser can also
// "Save as PDF".
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);
$pdo = db();

// ---------------------------------------------------------------------
// 2. Date range (default: the last 90 days)
// ---------------------------------------------------------------------
function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
$from = valid_date(get('from')) ? get('from') : date('Y-m-d', strtotime('-89 days'));
$to   = valid_date(get('to'))   ? get('to')   : date('Y-m-d');
if ($from > $to) {
    [$from, $to] = [$to, $from];   // swap if typed backwards
}
// "to" includes the whole day, so we search up to (but not including) the next day
$to_next_day = date('Y-m-d', strtotime($to . ' +1 day'));
$range = [$from, $to_next_day];

// Helper: run a query that has the date range "?, ?" placeholders
function report_rows(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------
// 3. Summary numbers
// ---------------------------------------------------------------------

// Requests received in the period, and how many of THOSE are now claimed
$received = report_rows($pdo,
    "SELECT COUNT(*) AS total, SUM(status = 'claimed') AS claimed, SUM(status = 'rejected') AS rejected
     FROM assistance_requests WHERE submitted_at >= ? AND submitted_at < ?", $range)[0];
$fulfillment_rate = $received['total'] > 0 ? round($received['claimed'] / $received['total'] * 100) : 0;

// Requests CLAIMED during the period (date taken from the status timeline)
$claimed_in_period = "SELECT DISTINCT request_id FROM request_status_history
                      WHERE status = 'claimed' AND changed_at >= ? AND changed_at < ?";

$served = report_rows($pdo,
    "SELECT COUNT(DISTINCT r.beneficiary_id, r.patient_name) AS patients, COUNT(*) AS requests
     FROM assistance_requests r WHERE r.id IN ($claimed_in_period)", $range)[0];

$units_distributed = (int) report_rows($pdo,
    "SELECT COALESCE(SUM(a.quantity), 0) AS units FROM donation_allocations a
     WHERE a.request_id IN ($claimed_in_period)", $range)[0]['units'];

// ---------------------------------------------------------------------
// 4. Donation flow
// ---------------------------------------------------------------------
$donations_by_status = report_rows($pdo,
    "SELECT status, COUNT(*) AS donations, SUM(quantity) AS units
     FROM donations WHERE created_at >= ? AND created_at < ? GROUP BY status", $range);

$units_routed = (int) report_rows($pdo,
    'SELECT COALESCE(SUM(quantity), 0) AS units FROM donation_allocations WHERE allocated_at >= ? AND allocated_at < ?',
    $range)[0]['units'];

// Per medicine: listed and approved in the period, and routed in the period
$donations_by_medicine = report_rows($pdo,
    "SELECT m.generic_name, m.brand_name, m.strength, m.dosage_form,
            COUNT(*) AS donations,
            SUM(d.quantity) AS units_listed,
            SUM(CASE WHEN d.status = 'approved' THEN d.quantity ELSE 0 END) AS units_approved,
            (SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a
             JOIN donations d2 ON d2.id = a.donation_id
             WHERE d2.medicine_id = m.id AND a.allocated_at >= ? AND a.allocated_at < ?) AS units_routed
     FROM donations d JOIN medicines m ON m.id = d.medicine_id
     WHERE d.created_at >= ? AND d.created_at < ?
     GROUP BY m.id
     ORDER BY units_listed DESC",
    array_merge($range, $range));

// ---------------------------------------------------------------------
// 5. Request fulfillment
// ---------------------------------------------------------------------
$requests_by_status = report_rows($pdo,
    'SELECT status, COUNT(*) AS requests FROM assistance_requests
     WHERE submitted_at >= ? AND submitted_at < ? GROUP BY status', $range);
$requests_by_status = array_column($requests_by_status, 'requests', 'status');   // ['claimed' => 2, ...]

$requests_by_program = report_rows($pdo,
    "SELECT COALESCE(pp.name, 'Not matched to a program') AS program,
            COUNT(*) AS requests, SUM(r.status = 'claimed') AS claimed
     FROM assistance_requests r LEFT JOIN partner_programs pp ON pp.id = r.partner_program_id
     WHERE r.submitted_at >= ? AND r.submitted_at < ?
     GROUP BY program ORDER BY requests DESC", $range);

// ---------------------------------------------------------------------
// 6. Medicines distributed and beneficiaries served (claimed in period)
// ---------------------------------------------------------------------
$medicines_distributed = report_rows($pdo,
    "SELECT m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
            COUNT(*) AS requests,
            SUM((SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a WHERE a.request_id = r.id)) AS donated_units
     FROM assistance_requests r JOIN medicines m ON m.id = r.medicine_id
     WHERE r.id IN ($claimed_in_period)
     GROUP BY m.id ORDER BY requests DESC", $range);

$served_by_city = report_rows($pdo,
    "SELECT patient_city, COUNT(DISTINCT beneficiary_id, patient_name) AS patients
     FROM assistance_requests WHERE id IN ($claimed_in_period)
     GROUP BY patient_city ORDER BY patients DESC", $range);

// ---------------------------------------------------------------------
// 7. Show the page
// ---------------------------------------------------------------------
$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
$donation_totals = array_column($donations_by_status, null, 'status');
?>

<!-- Date range (hidden when printing) -->
<form method="get" action="<?= e(url('admin/reports.php')) ?>" class="row g-2 align-items-end mb-4 no-print">
    <div class="col-6 col-md-3">
        <label for="from" class="form-label small">From</label>
        <input type="date" id="from" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label for="to" class="form-label small">To</label>
        <input type="date" id="to" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div class="col-6 col-md-2">
        <button type="submit" class="btn btn-outline-primary w-100">Show</button>
    </div>
    <div class="col-6 col-md-2">
        <button type="button" class="btn btn-primary w-100" onclick="window.print()">Print</button>
    </div>
</form>

<h1 class="fw-bold mb-0">GabayGamot Report</h1>
<p class="text-muted">
    <?= e(format_date($from)) ?> to <?= e(format_date($to)) ?> ·
    generated <?= e(format_datetime(date('Y-m-d H:i:s'))) ?> by <?= e(current_user()['full_name']) ?>
</p>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Requests received</div>
        <div class="kpi-value"><?= (int) $received['total'] ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Fulfillment rate</div>
        <div class="kpi-value"><?= $fulfillment_rate ?>%</div>
        <div class="small text-muted"><?= (int) $received['claimed'] ?> of these are claimed</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Beneficiaries served</div>
        <div class="kpi-value"><?= (int) $served['patients'] ?></div>
        <div class="small text-muted"><?= (int) $served['requests'] ?> requests claimed in period</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Donated units distributed</div>
        <div class="kpi-value"><?= number_format($units_distributed) ?></div>
    </div></div></div>
</div>

<!-- 1. Donation flow -->
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">1. Donation flow</h2>
    <p class="small">
        Listed: <strong><?= array_sum(array_column($donations_by_status, 'donations')) ?></strong> donations
        (<?= number_format(array_sum(array_column($donations_by_status, 'units'))) ?> units) ·
        Approved: <strong><?= (int) ($donation_totals['approved']['donations'] ?? 0) ?></strong> ·
        Not accepted: <strong><?= (int) ($donation_totals['rejected']['donations'] ?? 0) ?></strong> ·
        Waiting for review: <strong><?= (int) ($donation_totals['pending']['donations'] ?? 0) ?></strong> ·
        Units routed to patients in this period: <strong><?= number_format($units_routed) ?></strong>
    </p>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Medicine</th><th class="text-end">Donations</th><th class="text-end">Units listed</th>
                <th class="text-end">Units approved</th><th class="text-end">Units routed</th></tr></thead>
            <tbody>
            <?php foreach ($donations_by_medicine as $row): ?>
                <tr><td><?= e(medicine_name($row)) ?></td>
                    <td class="text-end"><?= (int) $row['donations'] ?></td>
                    <td class="text-end"><?= number_format($row['units_listed']) ?></td>
                    <td class="text-end"><?= number_format($row['units_approved']) ?></td>
                    <td class="text-end"><?= number_format($row['units_routed']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$donations_by_medicine): ?><tr><td colspan="5" class="text-muted">No donations in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div></div>

<!-- 2. Request fulfillment -->
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">2. Request fulfillment</h2>
    <p class="small">Fulfillment rate = requests now claimed ÷ requests received in this period
        = <?= (int) $received['claimed'] ?> ÷ <?= (int) $received['total'] ?> = <strong><?= $fulfillment_rate ?>%</strong></p>
    <div class="row g-4">
        <div class="col-12 col-md-5">
            <table class="table table-sm mb-0">
                <thead><tr><th>Current status</th><th class="text-end">Requests</th></tr></thead>
                <tbody>
                <?php foreach (REQUEST_STATUSES as $status => $info): ?>
                    <tr><td><?= e($info['label']) ?></td><td class="text-end"><?= (int) ($requests_by_status[$status] ?? 0) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-12 col-md-7">
            <table class="table table-sm mb-0">
                <thead><tr><th>Partner program</th><th class="text-end">Requests</th><th class="text-end">Claimed</th></tr></thead>
                <tbody>
                <?php foreach ($requests_by_program as $row): ?>
                    <tr><td><?= e($row['program']) ?></td><td class="text-end"><?= (int) $row['requests'] ?></td>
                        <td class="text-end"><?= (int) $row['claimed'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$requests_by_program): ?><tr><td colspan="3" class="text-muted">No requests in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div></div>

<!-- 3. Beneficiaries served -->
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">3. Beneficiaries served</h2>
    <p class="small">Patients whose medicine was claimed in this period: <strong><?= (int) $served['patients'] ?></strong></p>
    <table class="table table-sm mb-0" style="max-width: 420px">
        <thead><tr><th>City</th><th class="text-end">Patients</th></tr></thead>
        <tbody>
        <?php foreach ($served_by_city as $row): ?>
            <tr><td><?= e($row['patient_city']) ?></td><td class="text-end"><?= (int) $row['patients'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$served_by_city): ?><tr><td colspan="2" class="text-muted">None in this period.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>

<!-- 4. Medicines distributed -->
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">4. Medicines distributed</h2>
    <p class="small text-muted">Requests claimed in this period. "Donated units" counts medicine from GabayGamot
        donations; the rest was covered by the partner program.</p>
    <table class="table table-sm mb-0">
        <thead><tr><th>Medicine</th><th class="text-end">Requests claimed</th><th class="text-end">Donated units</th></tr></thead>
        <tbody>
        <?php foreach ($medicines_distributed as $row): ?>
            <tr><td><?= e(medicine_name($row)) ?></td><td class="text-end"><?= (int) $row['requests'] ?></td>
                <td class="text-end"><?= number_format($row['donated_units']) ?> <?= e($row['unit']) ?>s</td></tr>
        <?php endforeach; ?>
        <?php if (!$medicines_distributed): ?><tr><td colspan="3" class="text-muted">None in this period.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
