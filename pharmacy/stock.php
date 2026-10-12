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

// Medicines this pharmacy has NOT listed yet (for the "Add" dropdown)
$stmt = db()->prepare(
    'SELECT id, generic_name, brand_name, strength, dosage_form, unit
     FROM medicines
     WHERE is_active = 1
       AND id NOT IN (SELECT medicine_id FROM pharmacy_stock WHERE pharmacy_id = ?)
     ORDER BY generic_name, strength, brand_name'
);
$stmt->execute([$pharmacy_id]);
$unlisted = $stmt->fetchAll();

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'My stock';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div>
        <h1 class="h3 mb-0">My stock</h1>
        <p class="text-muted mb-0"><?= e($pharmacy['name']) ?><?= $pharmacy['branch'] ? ' · ' . e($pharmacy['branch']) : '' ?></p>
    </div>
    <?php if ($items): ?>
        <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="confirm_all">
            <button type="submit" class="btn btn-outline-primary">Confirm all are still correct</button>
        </form>
    <?php endif; ?>
</div>
<p class="small text-muted">
    The public sees your quantity, price, and when you last updated each item.
    Update often so people don't make a wasted trip.
</p>

<!-- Add a medicine -->
<div class="card mb-4">
    <div class="card-body">
        <h2 class="h6">Add a medicine</h2>
        <?php if (!$unlisted): ?>
            <p class="small text-muted mb-0">You already list every medicine in the catalog.
                Ask the admin to add a medicine that is missing.</p>
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
                    <label for="add_price" class="form-label small">Price (₱ per unit)</label>
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
    </div>
</div>

<!-- Current stock: one small form per medicine -->
<?php if (!$items): ?>
    <p class="text-muted">You have not listed any medicines yet. Add one above.</p>
<?php endif; ?>

<div class="list-group">
    <?php foreach ($items as $item): ?>
        <div class="list-group-item py-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <strong><?= e(medicine_name($item)) ?></strong>
                <span class="small">
                    <?= stock_pill((int) $item['quantity'], (int) $item['low_stock_threshold']) ?>
                    · <span class="text-muted">Updated <?= e(time_ago($item['last_updated_at'])) ?></span>
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>" class="row g-2 align-items-end flex-grow-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="stock_id" value="<?= (int) $item['id'] ?>">
                    <div class="col-4 col-md-3">
                        <label class="form-label small" for="qty-<?= (int) $item['id'] ?>">Quantity (<?= e($item['unit']) ?>s)</label>
                        <input type="number" id="qty-<?= (int) $item['id'] ?>" name="quantity" min="0" step="1"
                               value="<?= (int) $item['quantity'] ?>" class="form-control">
                    </div>
                    <div class="col-4 col-md-3">
                        <label class="form-label small" for="price-<?= (int) $item['id'] ?>">Price (₱ per <?= e($item['unit']) ?>)</label>
                        <input type="number" id="price-<?= (int) $item['id'] ?>" name="price" min="0.01" step="0.01"
                               value="<?= e($item['price']) ?>" class="form-control">
                    </div>
                    <div class="col-4 col-md-3">
                        <label class="form-label small" for="thr-<?= (int) $item['id'] ?>">Low-stock alert at</label>
                        <input type="number" id="thr-<?= (int) $item['id'] ?>" name="low_stock_threshold" min="0" step="1"
                               value="<?= (int) $item['low_stock_threshold'] ?>" class="form-control">
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Save</button>
                    </div>
                </form>
                <!-- A separate form, because HTML forms can't be put inside each other -->
                <form method="post" action="<?= e(url('pharmacy/stock.php')) ?>"
                      onsubmit="return confirm('Remove this medicine from your list?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="stock_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger">Remove</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
