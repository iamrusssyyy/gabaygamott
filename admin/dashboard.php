<?php
// =====================================================================
// admin/dashboard.php — the admin's overview (Chapter 1, Objective 3),
// laid out like the prototype "Admin dashboard & reports":
//   * KPIs: donations received, request fulfillment rate,
//     beneficiaries served, medicines distributed
//   * Chart.js chart: donations received per month (last 6 months)
//   * Requests by status (simple bars)
//   * ALERTS: near-expiry donations, low donated supply, low pharmacy stock
//   * Work queues: requests, donations, and accounts waiting
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
$donations_total = (int) $pdo->query('SELECT COUNT(*) FROM donations')->fetchColumn();
$donations_this_month = (int) $pdo->query(
    "SELECT COUNT(*) FROM donations WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();

$requests = $pdo->query(
    "SELECT COUNT(*) AS total, SUM(status = 'claimed') AS claimed
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

// Work queues
$waiting = [
    'requests'  => (int) $pdo->query("SELECT COUNT(*) FROM assistance_requests WHERE status = 'submitted'")->fetchColumn(),
    'donations' => (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'pending'")->fetchColumn(),
    'accounts'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
];

// ---------------------------------------------------------------------
// 3. Requests by status, grouped like the prototype
// ---------------------------------------------------------------------
$status_counts = $pdo->query('SELECT status, COUNT(*) FROM assistance_requests GROUP BY status')
                     ->fetchAll(PDO::FETCH_KEY_PAIR);   // ['claimed' => 2, ...]
$status_bars = [
    // label => [count, bar color]
    'Submitted'          => [(int) ($status_counts['submitted'] ?? 0), '#2a5bd7'],
    'Verified / matched' => [(int) ($status_counts['verified'] ?? 0) + (int) ($status_counts['matched'] ?? 0), '#2a5bd7'],
    'Ready to claim'     => [(int) ($status_counts['ready_to_claim'] ?? 0), '#1a6b4f'],
    'Claimed'            => [(int) ($status_counts['claimed'] ?? 0), '#137a64'],
    'Action needed'      => [(int) ($status_counts['action_needed'] ?? 0), '#9a5b00'],
    'Rejected'           => [(int) ($status_counts['rejected'] ?? 0), '#c0392b'],
];
$largest_bar = max(1, max(array_column($status_bars, 0)));   // avoid dividing by 0

// ---------------------------------------------------------------------
// 4. Alerts
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
    "SELECT s.quantity, s.low_stock_threshold,
            p.name AS pharmacy_name, p.branch,
            m.generic_name, m.brand_name, m.strength, m.dosage_form
     FROM pharmacy_stock s
     JOIN pharmacies p ON p.id = s.pharmacy_id
     JOIN users u ON u.id = p.user_id AND u.status = 'active'
     JOIN medicines m ON m.id = s.medicine_id
     WHERE s.quantity <= s.low_stock_threshold
     ORDER BY s.quantity, p.name"
)->fetchAll();

// ---------------------------------------------------------------------
// 5. Chart data: donations received in each of the last 6 months
// ---------------------------------------------------------------------
$months = [];   // '2026-05' => 'May'
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("first day of -$i month");
    $months[date('Y-m', $time)] = date('M', $time);
}
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*)
     FROM donations WHERE created_at >= ? GROUP BY month"
);
$stmt->execute([array_key_first($months) . '-01']);
$per_month = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);   // ['2026-09' => 4, ...]

$chart_data = [
    'months'    => array_values($months),
    'donations' => array_map(fn($key) => (int) ($per_month[$key] ?? 0), array_keys($months)),
];

// ---------------------------------------------------------------------
// 6. Show the page
// ---------------------------------------------------------------------
$page_title   = 'Admin dashboard';
$use_charts   = true;
$page_scripts = ['assets/js/admin-charts.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 page-head">
    <div>
        <h1>Dashboard</h1>
        <div class="subtitle">All partners · all time · <?= e(format_date(date('Y-m-d'))) ?></div>
    </div>
    <a href="<?= e(url('admin/reports.php')) ?>" class="btn btn-primary"><i class="bi bi-printer"></i> Printable reports</a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Donations received</div>
        <div class="kpi-value"><?= number_format($donations_total) ?></div>
        <div class="kpi-note" style="color: var(--gg-green-text)"><?= $donations_this_month ?> this month</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Request fulfillment rate</div>
        <div class="kpi-value"><?= $fulfillment_rate ?>%</div>
        <div class="kpi-note">claimed ÷ submitted (<?= (int) $requests['claimed'] ?> ÷ <?= $total_requests ?>)</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Beneficiaries served</div>
        <div class="kpi-value"><?= number_format($served) ?></div>
        <div class="kpi-note">with a claimed request</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Medicines distributed</div>
        <div class="kpi-value"><?= number_format($distributed) ?></div>
        <div class="kpi-note">donated units claimed</div>
    </div></div></div>
</div>

<div class="row g-3 mb-3">
    <!-- Donations received chart (Chart.js). The numbers go into a JSON
         block that assets/js/admin-charts.js reads. -->
    <div class="col-12 col-xl-6">
        <div class="card h-100"><div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-baseline">
                <h2 class="h5 mb-3">Donations received</h2>
                <span class="small text-muted">last 6 months</span>
            </div>
            <script type="application/json" id="chart-data"><?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <div class="chart-box"><canvas id="donations-chart" aria-label="Donations received per month" role="img"></canvas></div>
            <details class="mt-2 small">
                <summary>Show as table</summary>
                <table class="table table-sm mt-2 mb-0">
                    <thead><tr><th>Month</th><th class="text-end">Donations</th></tr></thead>
                    <tbody>
                    <?php foreach ($chart_data['months'] as $i => $label): ?>
                        <tr><td><?= e($label) ?></td><td class="text-end"><?= $chart_data['donations'][$i] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div></div>
    </div>

    <!-- Requests by status: plain HTML bars (width = count ÷ largest count) -->
    <div class="col-12 col-xl-6">
        <div class="card h-100"><div class="card-body p-4">
            <h2 class="h5 mb-3">Requests by status</h2>
            <?php foreach ($status_bars as $label => [$count, $color]): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?= e($label) ?></span><strong><?= $count ?></strong>
                    </div>
                    <div class="status-bar">
                        <span style="width: <?= round($count / $largest_bar * 100) ?>%; background: <?= e($color) ?>"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <!-- Alerts -->
    <div class="col-12 col-xl-6">
        <div class="card h-100"><div class="card-body p-4">
            <h2 class="h5 mb-3">Alerts <span class="small text-muted fw-normal">· checked when you opened this page</span></h2>

            <div class="notice notice-amber mb-2 d-block">
                <strong><?= count($near_expiry) ?> donated <?= count($near_expiry) === 1 ? 'medicine expires' : 'medicines expire' ?></strong>
                within <?= NEAR_EXPIRY_DAYS ?> days.
                <?php if ($near_expiry): ?>
                    <a href="<?= e(url('admin/donations.php?status=approved')) ?>">Route them first.</a>
                    <ul class="small mb-0 mt-1 ps-3">
                        <?php foreach (array_slice($near_expiry, 0, 3) as $item): ?>
                            <?php $days = (int) floor((strtotime($item['expiry_date']) - strtotime('today')) / 86400); ?>
                            <li><a href="<?= e(url('admin/donation_view.php?id=' . $item['id'])) ?>">#<?= (int) $item['id'] ?></a>
                                <?= e(medicine_name($item)) ?> · <?= (int) $item['remaining'] ?> left · <?= $days < 0 ? 'expired' : $days . ' days' ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="notice notice-amber mb-2 d-block">
                <strong><?= count($low_supply) ?> <?= count($low_supply) === 1 ? 'medicine has' : 'medicines have' ?> low donated supply</strong>
                (open requests need more per month than donations available).
                <?php if ($low_supply): ?>
                    <ul class="small mb-0 mt-1 ps-3">
                        <?php foreach (array_slice($low_supply, 0, 3) as $item): ?>
                            <li><?= e(medicine_name($item)) ?> · need <?= (int) $item['monthly_need'] ?>/month · <?= (int) $item['available_units'] ?> available</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="notice notice-amber d-block">
                <strong><?= count($low_stock) ?> pharmacy <?= count($low_stock) === 1 ? 'item is' : 'items are' ?></strong>
                at or below their low-stock level. Pharmacies were notified.
                <?php if ($low_stock): ?>
                    <ul class="small mb-0 mt-1 ps-3">
                        <?php foreach (array_slice($low_stock, 0, 3) as $item): ?>
                            <li><?= e(medicine_name($item)) ?> at <?= e($item['pharmacy_name']) ?><?= $item['branch'] ? ' · ' . e($item['branch']) : '' ?>
                                (<?= (int) $item['quantity'] ?> left)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div></div>
    </div>

    <!-- Work queues -->
    <div class="col-12 col-xl-6">
        <div class="card h-100"><div class="card-body p-4">
            <h2 class="h5 mb-2">Work queues</h2>
            <?php
            $queues = [
                ['Requests to verify', $waiting['requests'], 'admin/requests.php?status=submitted', 'blue'],
                ['Donations to review', $waiting['donations'], 'admin/donations.php?status=pending', 'amber'],
                ['Accounts to approve', $waiting['accounts'], 'admin/users.php', 'amber'],
            ];
            ?>
            <?php foreach ($queues as $i => [$label, $count, $link, $color]): ?>
                <a href="<?= e(url($link)) ?>"
                   class="d-flex justify-content-between align-items-center py-3 text-reset text-decoration-none <?= $i > 0 ? 'border-top' : '' ?>">
                    <span class="fw-semibold"><?= e($label) ?> <?= pill((string) $count, $color) ?></span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
