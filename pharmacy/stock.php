<?php
// =====================================================================
// pharmacy/stock.php — a pharmacy updates its stock and prices.
//
// Every save sets last_updated_at = NOW(), so the public search can show
// an honest "Last updated 25 min ago". Even if nothing changed, the
// "Confirm all are still correct" button refreshes the times.
//
// SECURITY: every query includes "pharmacy_id = ?" with THIS pharmacy's
// id, so one pharmacy can never change another pharmacy's stock, even by
// editing the form in the browser.
// =====================================================================

// 1. Only pharmacy accounts
require_once __DIR__ . '/../includes/init.php';
require_role(['pharmacy']);
$pharmacy = current_pharmacy();
if (!$pharmacy) {
    exit('No pharmacy profile is linked to this account. Please contact the admin.');
}
$pharmacy_id = (int) $pharmacy['id'];

/**
 * Checks quantity / price / threshold typed by the pharmacy.
 * Returns an error message, or '' if everything is fine.
 */
function check_stock_values(?int $quantity, string $price, ?int $threshold): string
{
    if ($quantity === null || $quantity < 0 || $quantity > 100000) {
        return 'Quantity must be a whole number from 0 to 100,000.';
    }
    // Price: up to 6 digits, optional 1–2 decimals, e.g. 6, 6.5, 6.50
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price) || (float) $price <= 0) {
        return 'Price must be a peso amount like 6.50.';
    }
    if ($threshold === null || $threshold < 0 || $threshold > 10000) {
        return 'Low-stock alert level must be a whole number from 0 to 10,000.';
    }
    return '';
}

// ---------------------------------------------------------------------
// 2. Handle the forms
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'update') {
        // ---- Save one item ----
        $quantity  = to_int(post('quantity'));
        $price     = post('price');
        $threshold = to_int(post('low_stock_threshold'));
        $error     = check_stock_values($quantity, $price, $threshold);

        if ($error !== '') {
            set_flash('danger', $error);
        } else {
            // If restocked above the alert level, clear the old alert so a
            // new one is sent next time it runs low (see includes/alerts.php).
            // MySQL runs the SET parts from left to right, so the IF() below
            // already sees the NEW quantity and threshold. (We compare the
            // number columns, not "?" values: "?" values are sent as text,
            // and as text '100' > '20' is false.)
            $stmt = db()->prepare(
                'UPDATE pharmacy_stock
                 SET quantity = ?, price = ?, low_stock_threshold = ?, last_updated_at = NOW(),
                     low_stock_alert_sent_at = IF(quantity > low_stock_threshold, NULL, low_stock_alert_sent_at)
                 WHERE id = ? AND pharmacy_id = ?'
            );
            $stmt->execute([$quantity, $price, $threshold, to_int(post('stock_id')) ?? 0, $pharmacy_id]);
            run_alert_checks();
            set_flash('success', 'Saved. "Last updated" now shows the current time.');
        }

    } elseif ($action === 'add') {
        // ---- Add a medicine this pharmacy didn't list before ----
        $medicine_id = to_int(post('medicine_id')) ?? 0;
        $quantity    = to_int(post('quantity'));
        $price       = post('price');
        $threshold   = to_int(post('low_stock_threshold'));
        $error       = check_stock_values($quantity, $price, $threshold);

        $stmt = db()->prepare('SELECT id FROM medicines WHERE id = ? AND is_active = 1');
        $stmt->execute([$medicine_id]);
        if (!$stmt->fetch()) {
            $error = 'Please choose a medicine from the list.';
        }

        if ($error !== '') {
            set_flash('danger', $error);
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO pharmacy_stock (pharmacy_id, medicine_id, quantity, price, low_stock_threshold, last_updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$pharmacy_id, $medicine_id, $quantity, $price, $threshold]);
                run_alert_checks();
                set_flash('success', 'Medicine added to your stock list.');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') {
                    throw $ex;
                }
                set_flash('warning', 'This medicine is already on your list. Update it below instead.');
            }
        }

    } elseif ($action === 'remove') {
        // ---- Stop listing a medicine ----
        $stmt = db()->prepare('DELETE FROM pharmacy_stock WHERE id = ? AND pharmacy_id = ?');
        $stmt->execute([to_int(post('stock_id')) ?? 0, $pharmacy_id]);
        set_flash('success', 'Medicine removed from your list.');

    } elseif ($action === 'confirm_all') {
        // ---- Nothing changed, but confirm the list is still correct ----
        $stmt = db()->prepare('UPDATE pharmacy_stock SET last_updated_at = NOW() WHERE pharmacy_id = ?');
        $stmt->execute([$pharmacy_id]);
        set_flash('success', 'All items confirmed as still correct.');
    }

    redirect('pharmacy/stock.php');
}

// ---------------------------------------------------------------------
// 3. Load data
// ---------------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT s.id, s.quantity, s.price, s.low_stock_threshold, s.last_updated_at,
            m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit
     FROM pharmacy_stock s
     JOIN medicines m ON m.id = s.medicine_id
     WHERE s.pharmacy_id = ?
     ORDER BY m.generic_name, m.strength, m.brand_name'
);
$stmt->execute([$pharmacy_id]);
$items = $stmt->fetchAll();

// Medicines this pharmacy has NOT listed yet (for the "Add medicine" form)
$stmt = db()->prepare(
    'SELECT id, generic_name, brand_name, strength, dosage_form, unit
     FROM medicines
     WHERE is_active = 1
       AND id NOT IN (SELECT medicine_id FROM pharmacy_stock WHERE pharmacy_id = ?)
     ORDER BY generic_name, strength, brand_name'
);
$stmt->execute([$pharmacy_id]);
$unlisted = $stmt->fetchAll();

// Numbers for the 4 tiles at the top
$low_count = 0;
$out_count = 0;
$oldest_update = null;
foreach ($items as $item) {
    $code = stock_status((int) $item['quantity'], (int) $item['low_stock_threshold'])['code'];
    if ($code === 'low') {
        $low_count++;
    } elseif ($code === 'out') {
        $out_count++;
    }
    if ($oldest_update === null || $item['last_updated_at'] < $oldest_update) {
        $oldest_update = $item['last_updated_at'];
    }
}

// ---------------------------------------------------------------------
// 4. Show the page (prototype "Pharmacy stock & prices")
// ---------------------------------------------------------------------
$page_title   = 'Stock & prices';
$page_scripts = ['assets/js/stock.js'];
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 page-head">
    <div>
        <h1>Stock &amp; prices</h1>
        <div class="subtitle">Buyers see these with the time you last saved.</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="search-field" style="min-width: 240px">
            <i class="bi bi-search"></i>
            <input type="search" id="item-search" class="form-control" placeholder="Search your items" aria-label="Search your items">
        </div>
        <!-- Opens the "Add medicine" form below (Bootstrap "collapse") -->
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#add-form"
                aria-expanded="false" aria-controls="add-form">+ Add medicine</button>
    </div>
</div>

<!-- Summary tiles -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Items listed</div><div class="kpi-value"><?= count($items) ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Low stock</div><div class="kpi-value" style="color: var(--gg-amber-text)"><?= $low_count ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Out of stock</div><div class="kpi-value" style="color: var(--gg-red-text)"><?= $out_count ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="kpi-label">Oldest update</div><div class="kpi-value"><?= $oldest_update ? e(time_ago($oldest_update)) : '—' ?></div>
    </div></div></div>
</div>

<?php if ($low_count + $out_count > 0): ?>
    <div class="notice notice-amber mb-3">
        <span><strong><?= $low_count + $out_count ?> <?= $low_count + $out_count === 1 ? 'item needs' : 'items need' ?> restocking.</strong>
            You also get a notification when an item runs low.</span>
    </div>
<?php endif; ?>

<!-- Add a medicine (hidden until "+ Add medicine" is pressed) -->
<div class="collapse mb-3" id="add-form">
    <div class="card"><div class="card-body">
        <h2 class="h5">Add a medicine</h2>
        <?php if (!$unlisted): ?>
            <p class="small text-muted mb-0">You already list every medicine in the catalog. Ask the admin to add a missing one.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-12 col-md-5">
                    <label for="medicine_id" class="form-label small">Medicine</label>
                    <select id="medicine_id" name="medicine_id" class="form-select">
                        <?php foreach ($unlisted as $medicine): ?>
                            <option value="<?= (int) $medicine['id'] ?>"><?= e(medicine_name($medicine)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <label for="add_quantity" class="form-label small">Quantity</label>
                    <input type="number" id="add_quantity" name="quantity" min="0" step="1" class="form-control" required>
                </div>
                <div class="col-4 col-md-2">
                    <label for="add_price" class="form-label small">Price / unit (₱)</label>
                    <input type="number" id="add_price" name="price" min="0.01" step="0.01" class="form-control" required>
                </div>
                <div class="col-4 col-md-1">
                    <label for="add_threshold" class="form-label small">Alert at</label>
                    <input type="number" id="add_threshold" name="low_stock_threshold" min="0" step="1" value="20" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        <?php endif; ?>
    </div></div>
</div>

<?php if (!$items): ?>
    <p class="text-muted">You have not listed any medicines yet. Press “+ Add medicine”.</p>
<?php else: ?>
    <!-- The stock table. Each row has its own small form: the inputs say
         form="row-ID", which links them to the <form id="row-ID"> in the
         last cell (an HTML form can't wrap a table row). -->
    <div class="table-card table-responsive">
        <table class="table align-middle" id="stock-table">
            <thead>
                <tr>
                    <th>Medicine</th><th style="width: 9rem">Quantity</th><th style="width: 9rem">Price / unit (₱)</th>
                    <th style="width: 7rem">Alert at</th><th>Status</th><th>Last updated</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $form_id = 'row-' . (int) $item['id']; ?>
                <tr data-name="<?= e(strtolower(medicine_name($item))) ?>">
                    <td>
                        <div class="fw-semibold"><?= e($item['brand_name'] !== '' ? $item['brand_name'] : $item['generic_name']) ?> <?= e($item['strength']) ?></div>
                        <div class="small text-muted">
                            <?= e(ucfirst($item['dosage_form'])) ?> · <?= e($item['brand_name'] !== '' ? strtolower($item['generic_name']) : 'generic') ?>
                        </div>
                    </td>
                    <td><input type="number" name="quantity" min="0" step="1" form="<?= $form_id ?>"
                               value="<?= (int) $item['quantity'] ?>" class="form-control" aria-label="Quantity"></td>
                    <td><input type="number" name="price" min="0.01" step="0.01" form="<?= $form_id ?>"
                               value="<?= e($item['price']) ?>" class="form-control" aria-label="Price per unit"></td>
                    <td><input type="number" name="low_stock_threshold" min="0" step="1" form="<?= $form_id ?>"
                               value="<?= (int) $item['low_stock_threshold'] ?>" class="form-control" aria-label="Low-stock alert level"></td>
                    <td><?= stock_pill((int) $item['quantity'], (int) $item['low_stock_threshold'], false) ?></td>
                    <td class="text-muted text-nowrap"><?= e(time_ago($item['last_updated_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>" id="<?= $form_id ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="stock_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </form>
                        <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>" class="d-inline"
                              onsubmit="return confirm('Remove this medicine from your list?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="stock_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger" aria-label="Remove"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <p class="small text-muted mb-0">Saving a row sets its “Last updated” to now. Nothing changed? Confirm everything at once:</p>
        <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="confirm_all">
            <button type="submit" class="btn btn-outline-primary">Confirm all are still correct</button>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
