<?php
// =====================================================================
// donor/donation_new.php — list a medicine donation.
// Donors (individuals and organizations) and pharmacies can donate.
//
// Validation (Chapter 1, Objective 3):
//   * quantity: whole number from 1 to MAX_DONATION_QUANTITY
//   * expiry:   at least MIN_DONATION_SHELF_DAYS (90) days from today,
//               so there is time to match it with a patient
//   * a clear photo of the packaging (JPG or PNG) is required
// The donation starts as 'pending' until an admin checks it.
// =====================================================================

// 1. Donors and pharmacies
require_once __DIR__ . '/../includes/init.php';
require_role(['donor', 'pharmacy']);
$user = current_user();

$medicines = db()->query(
    'SELECT id, generic_name, brand_name, strength, dosage_form, unit
     FROM medicines WHERE is_active = 1 ORDER BY generic_name, strength, brand_name'
)->fetchAll();
$medicine_ids = array_map('intval', array_column($medicines, 'id'));

$earliest_expiry = date('Y-m-d', strtotime('+' . MIN_DONATION_SHELF_DAYS . ' days'));
$form   = ['medicine_id' => '', 'quantity' => '', 'expiry_date' => '', 'lot_number' => '', 'donor_notes' => ''];
$errors = [];

// ---------------------------------------------------------------------
// 2. Handle the form
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($form) as $field) {
        $form[$field] = post($field);
    }

    if (!in_array(to_int($form['medicine_id']), $medicine_ids, true)) {
        $errors['medicine_id'] = 'Please choose the medicine.';
    }

    $quantity = to_int($form['quantity']);
    if ($quantity === null || $quantity < 1 || $quantity > MAX_DONATION_QUANTITY) {
        $errors['quantity'] = 'Enter a whole number from 1 to ' . number_format(MAX_DONATION_QUANTITY) . '.';
    }

    $expiry = DateTime::createFromFormat('!Y-m-d', $form['expiry_date']);
    if (!$expiry || $expiry->format('Y-m-d') !== $form['expiry_date']) {
        $errors['expiry_date'] = 'Please enter the expiry date printed on the box.';
    } elseif ($form['expiry_date'] < $earliest_expiry) {
        // 'Y-m-d' dates can be compared as text because the year comes first
        $errors['expiry_date'] = 'We can only accept medicine that expires on or after '
                               . format_date($earliest_expiry) . ' (' . MIN_DONATION_SHELF_DAYS . ' days from today).';
    } elseif ($expiry > new DateTime('+10 years')) {
        $errors['expiry_date'] = 'Please check the expiry date.';
    }

    if (mb_strlen($form['lot_number']) > 50) {
        $errors['lot_number'] = 'Lot number is too long.';
    }
    if (mb_strlen($form['donor_notes']) > 255) {
        $errors['donor_notes'] = 'Please keep notes under 255 characters.';
    }
    if (post('declaration') !== '1') {
        $errors['declaration'] = 'Please confirm the medicine is sealed and stored properly.';
    }
    if (!file_was_chosen('photo')) {
        $errors['photo'] = 'Please add a photo of the packaging.';
    }

    // Save the photo only when everything else is valid (no leftover files)
    $photo = null;
    if (!$errors) {
        $photo = save_upload($_FILES['photo'], 'donations', PHOTO_FILE_TYPES);
        if ($photo['error'] !== null) {
            $errors['photo'] = $photo['error'];
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "INSERT INTO donations (donor_id, medicine_id, quantity, expiry_date, lot_number, donor_notes,
                                    photo_original_name, photo_stored_name, photo_mime_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $user['id'], (int) $form['medicine_id'], $quantity, $form['expiry_date'],
            $form['lot_number'] !== '' ? $form['lot_number'] : null,
            $form['donor_notes'] !== '' ? $form['donor_notes'] : null,
            $photo['original_name'], $photo['stored_name'], $photo['mime_type'],
        ]);
        $donation_id = (int) db()->lastInsertId();

        notify_admins('New donation #' . $donation_id . ' from ' . $user['full_name'] . ' needs verification.',
                      'admin/donation_view.php?id=' . $donation_id);
        set_flash('success', 'Thank you! Your donation was listed. An admin will check it soon.');
        redirect('donor/donation_view.php?id=' . $donation_id);
    }
}

// ---------------------------------------------------------------------
// 3. Show the page
// ---------------------------------------------------------------------
$page_title = 'Donate medicine';
require __DIR__ . '/../includes/header.php';
?>

<div class="card register-card">
    <div class="card-body p-4">
        <h1 class="h3 mb-0">Donate medicine</h1>
        <p class="text-muted">Mag-donate ng gamot na hindi nagamit</p>

        <div class="alert alert-light border small">
            We accept <strong>sealed, unopened</strong> medicine that expires on or after
            <strong><?= e(format_date($earliest_expiry)) ?></strong>.
            After an admin checks your listing, the GabayGamot team will contact you about drop-off.
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">Please fix the fields marked in red.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('donor/donation_new.php')) ?>" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label for="medicine_id" class="form-label">Medicine</label>
                    <select id="medicine_id" name="medicine_id" class="form-select<?= error_class($errors, 'medicine_id') ?>">
                        <option value="">Choose the medicine…</option>
                        <?php foreach ($medicines as $medicine): ?>
                            <option value="<?= (int) $medicine['id'] ?>" <?= $form['medicine_id'] === (string) $medicine['id'] ? 'selected' : '' ?>>
                                <?= e(medicine_name($medicine)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_text($errors, 'medicine_id') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" max="<?= MAX_DONATION_QUANTITY ?>"
                           class="form-control<?= error_class($errors, 'quantity') ?>" value="<?= e($form['quantity']) ?>">
                    <div class="form-text">Count single units, e.g. 3 boxes of 30 tablets = 90.</div>
                    <?= error_text($errors, 'quantity') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="expiry_date" class="form-label">Expiry date</label>
                    <input type="date" id="expiry_date" name="expiry_date" min="<?= e($earliest_expiry) ?>"
                           class="form-control<?= error_class($errors, 'expiry_date') ?>" value="<?= e($form['expiry_date']) ?>">
                    <?= error_text($errors, 'expiry_date') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="lot_number" class="form-label">Lot / batch no. <span class="small text-muted">(optional)</span></label>
                    <input type="text" id="lot_number" name="lot_number" maxlength="50"
                           class="form-control<?= error_class($errors, 'lot_number') ?>" value="<?= e($form['lot_number']) ?>">
                    <?= error_text($errors, 'lot_number') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="photo" class="form-label">Photo of the packaging</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png"
                           class="form-control<?= error_class($errors, 'photo') ?>">
                    <div class="form-text">Show the name, strength, and expiry date. JPG or PNG, up to 5 MB.</div>
                    <?= error_text($errors, 'photo') ?>
                </div>
                <div class="col-12">
                    <label for="donor_notes" class="form-label">Notes <span class="small text-muted">(optional)</span></label>
                    <input type="text" id="donor_notes" name="donor_notes" maxlength="255"
                           class="form-control<?= error_class($errors, 'donor_notes') ?>" value="<?= e($form['donor_notes']) ?>">
                    <?= error_text($errors, 'donor_notes') ?>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input<?= error_class($errors, 'declaration') ?>" type="checkbox"
                               id="declaration" name="declaration" value="1">
                        <label class="form-check-label" for="declaration">
                            I confirm this medicine is sealed, unused, and was stored as the label says.
                        </label>
                        <?= error_text($errors, 'declaration') ?>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">List donation</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
