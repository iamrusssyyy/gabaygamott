<?php
// =====================================================================
// admin/medicines.php — the shared medicine catalog.
// Pharmacies, beneficiaries, and donors all choose medicines from this
// list, so names stay consistent and search works.
//   * Add a medicine        (form at the top)
//   * Edit a medicine       (?edit=ID fills the form)
//   * Hide / show a medicine (is_active). We hide instead of delete,
//     because old requests, donations, and stock still point to it.
// =====================================================================

// 1. Only admins
require_once __DIR__ . '/../includes/init.php';
require_role(['admin']);

$errors = [];
$form = ['id' => '', 'generic_name' => '', 'brand_name' => '', 'strength' => '',
         'dosage_form' => 'tablet', 'unit' => 'tablet'];

// ---------------------------------------------------------------------
// 2. Handle the forms
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    // ---- 2a. Hide or show a medicine ----
    if ($action === 'toggle') {
        $stmt = db()->prepare('UPDATE medicines SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([to_int(post('id')) ?? 0]);
        set_flash('success', 'Medicine updated.');
        redirect('admin/medicines.php');
    }

    // ---- 2b. Add or edit a medicine ----
    foreach (array_keys($form) as $field) {
        $form[$field] = post($field);
    }
    $editing_id = to_int($form['id']);   // null when adding a new one

    if ($form['generic_name'] === '' || mb_strlen($form['generic_name']) > 120) {
        $errors['generic_name'] = 'Please enter the generic name, e.g. Losartan Potassium.';
    }
    if (mb_strlen($form['brand_name']) > 120) {
        $errors['brand_name'] = 'Brand name is too long.';
    }
    if ($form['strength'] === '' || mb_strlen($form['strength']) > 40) {
        $errors['strength'] = 'Please enter the strength, e.g. 50 mg.';
    }
    if (!in_array($form['dosage_form'], DOSAGE_FORMS, true)) {
        $errors['dosage_form'] = 'Please choose a form.';
    }
    if (!in_array($form['unit'], UNITS, true)) {
        $errors['unit'] = 'Please choose a unit.';
    }

    if (!$errors) {
        try {
            if ($editing_id) {
                $stmt = db()->prepare(
                    'UPDATE medicines SET generic_name = ?, brand_name = ?, strength = ?, dosage_form = ?, unit = ?
                     WHERE id = ?'
                );
                $stmt->execute([$form['generic_name'], $form['brand_name'], $form['strength'],
                                $form['dosage_form'], $form['unit'], $editing_id]);
                set_flash('success', 'Medicine saved.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO medicines (generic_name, brand_name, strength, dosage_form, unit)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$form['generic_name'], $form['brand_name'], $form['strength'],
                                $form['dosage_form'], $form['unit']]);
                set_flash('success', 'Medicine added to the catalog.');
            }
            redirect('admin/medicines.php');
        } catch (PDOException $ex) {
            // 23000 = the UNIQUE rule in schema.sql: same generic + brand + strength + form
            if ($ex->getCode() !== '23000') {
                throw $ex;
            }
            $errors['generic_name'] = 'This medicine (same name, brand, strength, and form) is already in the catalog.';
        }
    }
}

// ---------------------------------------------------------------------
// 3. Load data
// ---------------------------------------------------------------------

// ?edit=ID -> fill the form with that medicine
$edit_id = to_int(get('edit'));
if ($edit_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT id, generic_name, brand_name, strength, dosage_form, unit FROM medicines WHERE id = ?');
    $stmt->execute([$edit_id]);
    $row = $stmt->fetch();
    if ($row) {
        $form = array_map('strval', $row);
    }
}

// The catalog, with how many pharmacies list each medicine
$search = get('q');
$stmt = db()->prepare(
    'SELECT m.*, COUNT(s.id) AS pharmacy_count
     FROM medicines m
     LEFT JOIN pharmacy_stock s ON s.medicine_id = m.id
     WHERE m.generic_name LIKE ? OR m.brand_name LIKE ?
     GROUP BY m.id
     ORDER BY m.generic_name, m.strength, m.brand_name'
);
$stmt->execute(['%' . $search . '%', '%' . $search . '%']);
$medicines = $stmt->fetchAll();

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'Medicine catalog';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-3">Medicine catalog</h1>

<div class="row g-4">
    <!-- Add / edit form -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="h5"><?= $form['id'] !== '' ? 'Edit medicine' : 'Add a medicine' ?></h2>
                <form method="post" action="<?= e(url('admin/medicines.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($form['id']) ?>">

                    <div class="mb-3">
                        <label for="generic_name" class="form-label">Generic name</label>
                        <input type="text" id="generic_name" name="generic_name" maxlength="120"
                               class="form-control<?= error_class($errors, 'generic_name') ?>"
                               value="<?= e($form['generic_name']) ?>" placeholder="e.g. Losartan Potassium">
                        <?= error_text($errors, 'generic_name') ?>
                    </div>
                    <div class="mb-3">
                        <label for="brand_name" class="form-label">Brand name <span class="small text-muted">(blank = generic)</span></label>
                        <input type="text" id="brand_name" name="brand_name" maxlength="120"
                               class="form-control<?= error_class($errors, 'brand_name') ?>"
                               value="<?= e($form['brand_name']) ?>" placeholder="e.g. Cozaar">
                        <?= error_text($errors, 'brand_name') ?>
                    </div>
                    <div class="mb-3">
                        <label for="strength" class="form-label">Strength</label>
                        <input type="text" id="strength" name="strength" maxlength="40"
                               class="form-control<?= error_class($errors, 'strength') ?>"
                               value="<?= e($form['strength']) ?>" placeholder="e.g. 50 mg">
                        <?= error_text($errors, 'strength') ?>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="dosage_form" class="form-label">Form</label>
                            <select id="dosage_form" name="dosage_form" class="form-select<?= error_class($errors, 'dosage_form') ?>">
                                <?php foreach (DOSAGE_FORMS as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $form['dosage_form'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= error_text($errors, 'dosage_form') ?>
                        </div>
                        <div class="col-6">
                            <label for="unit" class="form-label">Priced per</label>
                            <select id="unit" name="unit" class="form-select<?= error_class($errors, 'unit') ?>">
                                <?php foreach (UNITS as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $form['unit'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= error_text($errors, 'unit') ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <?= $form['id'] !== '' ? 'Save changes' : 'Add medicine' ?>
                    </button>
                    <?php if ($form['id'] !== ''): ?>
                        <a href="<?= e(url('admin/medicines.php')) ?>" class="btn btn-link w-100">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Catalog list -->
    <div class="col-12 col-lg-8">
        <form method="get" action="<?= e(url('admin/medicines.php')) ?>" class="d-flex gap-2 mb-3">
            <input type="search" name="q" class="form-control" value="<?= e($search) ?>"
                   placeholder="Search generic or brand name" aria-label="Search medicines">
            <button type="submit" class="btn btn-outline-secondary">Search</button>
        </form>

        <div class="table-responsive bg-white border rounded">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Medicine</th><th>Per</th><th>Pharmacies</th><th>Shown</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($medicines as $medicine): ?>
                    <tr class="<?= $medicine['is_active'] ? '' : 'text-muted' ?>">
                        <td><?= e(medicine_name($medicine)) ?></td>
                        <td><?= e($medicine['unit']) ?></td>
                        <td><?= (int) $medicine['pharmacy_count'] ?></td>
                        <td><?= $medicine['is_active'] ? 'Yes' : 'Hidden' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('admin/medicines.php?edit=' . $medicine['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="post" action="<?= e(url('admin/medicines.php')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $medicine['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <?= $medicine['is_active'] ? 'Hide' : 'Show' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$medicines): ?>
                    <tr><td colspan="5" class="text-muted">No medicines found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-2">Hidden medicines no longer appear in search, requests, or donation forms,
            but old records that use them are kept.</p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
