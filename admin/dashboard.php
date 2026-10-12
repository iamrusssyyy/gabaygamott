<?php
// =====================================================================
// admin/dashboard.php — the admin's overview (Chapter 1, Objective 3):
//   * KPIs: requests, fulfillment rate, beneficiaries served,
//     donated units distributed
//   * Work waiting: requests, donations, and accounts to check
//   * ALERTS: near-expiry donations, low donated supply, low pharmacy stock
//   * Charts (Chart.js): donation flow per month, requests by status
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

// Opening the dashboard also sends any new automatic alerts
run_alert_checks();
$pdo = db();

// ---------------------------------------------------------------------
// 2. KPIs (all time). SUM(condition) counts the rows where it is true.
// ---------------------------------------------------------------------
$requests = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'claimed')  AS claimed,
            SUM(status = 'rejected') AS rejected,
            SUM(status NOT IN ('claimed', 'rejected')) AS open_requests
     FROM assistance_requests"
)->fetch();
$total_requests = (int) $requests['total'];

// Fulfillment rate = claimed requests ÷ all requests received
$fulfillment_rate = $total_requests > 0 ? round($requests['claimed'] / $total_requests * 100) : 0;

// Beneficiaries served = different patients whose request was claimed
$served = (int) $pdo->query(
    "SELECT COUNT(DISTINCT beneficiary_id, patient_name) FROM assistance_requests WHERE status = 'claimed'"
)->fetchColumn();

// Donated units that reached patients (routed to requests that were claimed)
$distributed = (int) $pdo->query(
    "SELECT COALESCE(SUM(a.quantity), 0)
     FROM donation_allocations a JOIN assistance_requests r ON r.id = a.request_id
     WHERE r.status = 'claimed'"
)->fetchColumn();

// Work waiting for the admin
$waiting = [
    'requests'  => (int) $pdo->query("SELECT COUNT(*) FROM assistance_requests WHERE status = 'submitted'")->fetchColumn(),
    'donations' => (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'pending'")->fetchColumn(),
    'accounts'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
];

// ---------------------------------------------------------------------
// 3. Alerts
// ---------------------------------------------------------------------

// a) Approved donations with units left that expire within NEAR_EXPIRY_DAYS
$stmt = $pdo->prepare(
    "SELECT d.id, d.expiry_date, m.generic_name, m.brand_name, m.strength, m.dosage_form,
            d.quantity - COALESCE(SUM(a.quantity), 0) AS remaining
     FROM donations d
     JOIN medicines m ON m.id = d.medicine_id
     LEFT JOIN donation_allocations a ON a.donation_id = d.id
     WHERE d.status = 'approved' AND d.expiry_date <= CURDATE() + INTERVAL ? DAY
     GROUP BY d.id
     HAVING remaining > 0
     ORDER BY d.expiry_date"
);
$stmt->execute([NEAR_EXPIRY_DAYS]);
$near_expiry = $stmt->fetchAll();

// b) LOW DONATED SUPPLY: medicines where open requests need more per month
//    than the donated units still available. The two "(SELECT ...) AS name"
//    parts are small tables we build first and then join (derived tables).
$low_supply = $pdo->query(
    "SELECT m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
            need.open_requests, need.monthly_need, COALESCE(available.units, 0) AS available_units
     FROM (SELECT medicine_id, COUNT(*) AS open_requests, SUM(quantity_per_month) AS monthly_need
           FROM assistance_requests
           WHERE status IN ('verified', 'matched')
           GROUP BY medicine_id) AS need
     JOIN medicines m ON m.id = need.medicine_id
     LEFT JOIN (SELECT d.medicine_id, SUM(d.quantity - COALESCE(used.units, 0)) AS units
                FROM donations d
                LEFT JOIN (SELECT donation_id, SUM(quantity) AS units FROM donation_allocations GROUP BY donation_id) AS used
                       ON used.donation_id = d.id
                WHERE d.status = 'approved' AND d.expiry_date > CURDATE()
                GROUP BY d.medicine_id) AS available
           ON available.medicine_id = need.medicine_id
     WHERE COALESCE(available.units, 0) < need.monthly_need
     ORDER BY need.open_requests DESC"
)->fetchAll();

// c) Pharmacy items that are low or out of stock
$low_stock = $pdo->query(
    "SELECT s.quantity, s.low_stock_threshold, s.last_updated_at,
            p.name AS pharmacy_name, p.branch,
            m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM pharmacy_stock s
     JOIN pharmacies p ON p.id = s.pharmacy_id
     JOIN users u ON u.id = p.user_id AND u.status = 'active'
     JOIN medicines m ON m.id = s.medicine_id
     WHERE s.quantity <= s.low_stock_threshold
     ORDER BY s.quantity, p.name
     LIMIT 10"
)->fetchAll();

// ---------------------------------------------------------------------
// 4. Chart data
// ---------------------------------------------------------------------

// The last 6 months as keys like '2026-05' and labels like 'May 2026'
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("first day of -$i month");
    $months[date('Y-m', $time)] = date('M Y', $time);
}
$since = array_key_first($months) . '-01';

// Units approved per month (by review date) and routed per month.
// FETCH_KEY_PAIR gives ['2026-09' => 500, ...]
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(reviewed_at, '%Y-%m') AS month, SUM(quantity)
     FROM donations WHERE status = 'approved' AND reviewed_at >= ? GROUP BY month"
);
$stmt->execute([$since]);
$approved_by_month = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(allocated_at, '%Y-%m') AS month, SUM(quantity)
     FROM donation_allocations WHERE allocated_at >= ? GROUP BY month"
);
$stmt->execute([$since]);
$routed_by_month = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$status_counts = $pdo->query('SELECT status, COUNT(*) FROM assistance_requests GROUP BY status')
                     ->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_data = [
    'months'   => array_values($months),
    'approved' => array_map(fn($key) => (int) ($approved_by_month[$key] ?? 0), array_keys($months)),
    'routed'   => array_map(fn($key) => (int) ($routed_by_month[$key] ?? 0), array_keys($months)),
    'statuses' => array_values(array_map(fn($info) => $info['label'], REQUEST_STATUSES)),
    'status_counts' => array_map(fn($key) => (int) ($status_counts[$key] ?? 0), array_keys(REQUEST_STATUSES)),
];

// ---------------------------------------------------------------------
// 5. Show the page
// ---------------------------------------------------------------------
$page_title   = 'Admin dashboard';
$use_charts   = true;
$page_scripts = ['assets/js/admin-charts.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Dashboard</h1>
    <a href="<?= e(url('admin/reports.php')) ?>" class="btn btn-outline-primary">Printable reports</a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Requests received</div>
        <div class="kpi-value"><?= number_format($total_requests) ?></div>
        <div class="small text-muted"><?= (int) $requests['open_requests'] ?> still open</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Fulfillment rate</div>
        <div class="kpi-value"><?= $fulfillment_rate ?>%</div>
        <div class="small text-muted"><?= (int) $requests['claimed'] ?> claimed ÷ <?= $total_requests ?> received</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Beneficiaries served</div>
        <div class="kpi-value"><?= number_format($served) ?></div>
        <div class="small text-muted">patients who claimed medicine</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-muted">Donated units distributed</div>
        <div class="kpi-value"><?= number_format($distributed) ?></div>
        <div class="small text-muted">tablets, capsules, etc. claimed</div>
    </div></div></div>
</div>

<!-- Work waiting -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn <?= $waiting['requests'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/requests.php?status=submitted')) ?>">
        Requests to verify <span class="badge text-bg-light"><?= $waiting['requests'] ?></span></a>
    <a class="btn <?= $waiting['donations'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/donations.php?status=pending')) ?>">
        Donations to verify <span class="badge text-bg-light"><?= $waiting['donations'] ?></span></a>
    <a class="btn <?= $waiting['accounts'] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e(url('admin/users.php')) ?>">
        Accounts to approve <span class="badge text-bg-light"><?= $waiting['accounts'] ?></span></a>
</div>

<!-- Alerts -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100 <?= $near_expiry ? 'border-danger' : '' ?>">
            <div class="card-body">
                <h2 class="h6">⚠ Near-expiry donations <span class="small text-muted fw-normal">(within <?= NEAR_EXPIRY_DAYS ?> days)</span></h2>
                <?php if (!$near_expiry): ?><p class="small text-muted mb-0">None. 👍</p><?php endif; ?>
                <?php foreach ($near_expiry as $item): ?>
                    <?php $days = (int) floor((strtotime($item['expiry_date']) - strtotime('today')) / 86400); ?>
                    <div class="small mb-1">
                        <a href="<?= e(url('admin/donation_view.php?id=' . $item['id'])) ?>">#<?= (int) $item['id'] ?></a>
                        <?= e(medicine_name($item)) ?> · <?= (int) $item['remaining'] ?> left ·
                        <span class="text-danger fw-semibold"><?= $days < 0 ? 'expired' : $days . ' days' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100 <?= $low_supply ? 'border-warning' : '' ?>">
            <div class="card-body">
                <h2 class="h6">⚠ Low donated supply</h2>
                <p class="small text-muted mb-2">Open requests need more per month than the donations available.</p>
                <?php if (!$low_supply): ?><p class="small text-muted mb-0">None. 👍</p><?php endif; ?>
                <?php foreach ($low_supply as $item): ?>
                    <div class="small mb-1">
                        <?= e(medicine_name($item)) ?> · need <?= (int) $item['monthly_need'] ?>/month
                        (<?= (int) $item['open_requests'] ?> <?= (int) $item['open_requests'] === 1 ? 'request' : 'requests' ?>) ·
                        <span class="fw-semibold"><?= (int) $item['available_units'] ?> available</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6">⚠ Low pharmacy stock</h2>
                <?php if (!$low_stock): ?><p class="small text-muted mb-0">None. 👍</p><?php endif; ?>
                <?php foreach ($low_stock as $item): ?>
                    <div class="small mb-1">
                        <?= stock_pill((int) $item['quantity'], (int) $item['low_stock_threshold']) ?> ·
                        <?= e(medicine_name($item)) ?> at <?= e($item['pharmacy_name']) ?><?= $item['branch'] ? ' · ' . e($item['branch']) : '' ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts. The numbers go into a JSON block that admin-charts.js reads. -->
<script type="application/json" id="chart-data"><?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 mb-0">Donation flow</h2>
            <p class="small text-muted">Units approved vs. units routed to patients, last 6 months</p>
            <div class="chart-box"><canvas id="donation-flow-chart" aria-label="Donation flow chart" role="img"></canvas></div>
            <details class="mt-2 small">
                <summary>Show as table</summary>
                <table class="table table-sm mt-2 mb-0">
                    <thead><tr><th>Month</th><th class="text-end">Units approved</th><th class="text-end">Units routed</th></tr></thead>
                    <tbody>
                    <?php foreach (array_values($months) as $i => $label): ?>
                        <tr><td><?= e($label) ?></td>
                            <td class="text-end"><?= number_format($chart_data['approved'][$i]) ?></td>
                            <td class="text-end"><?= number_format($chart_data['routed'][$i]) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div></div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6 mb-0">Requests by status</h2>
            <p class="small text-muted">All requests received</p>
            <div class="chart-box"><canvas id="request-status-chart" aria-label="Requests by status chart" role="img"></canvas></div>
            <details class="mt-2 small">
                <summary>Show as table</summary>
                <table class="table table-sm mt-2 mb-0">
                    <thead><tr><th>Status</th><th class="text-end">Requests</th></tr></thead>
                    <tbody>
                    <?php foreach ($chart_data['statuses'] as $i => $label): ?>
                        <tr><td><?= e($label) ?></td><td class="text-end"><?= $chart_data['status_counts'][$i] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
