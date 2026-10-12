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
// 3. Load the donor's 3 latest donations for the side panel
// ---------------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT d.id, d.quantity, d.status, m.generic_name
     FROM donations d JOIN medicines m ON m.id = d.medicine_id
     WHERE d.donor_id = ? ORDER BY d.created_at DESC LIMIT 3'
);
$stmt->execute([$user['id']]);
$recent = $stmt->fetchAll();
$my_donations_page = $user['role'] === 'pharmacy' ? 'pharmacy/dashboard.php' : 'donor/dashboard.php';

// ---------------------------------------------------------------------
// 4. Show the page (prototype "Donate")
// ---------------------------------------------------------------------
$page_title = 'Donate medicine';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Donate medicine</h1>
    <div class="subtitle">Mag-donate ng gamot</div>
</div>

<div class="row g-4">
    <!-- ===== Form ===== -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2">Please fix the fields marked in red.</div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('donor/donation_new.php')) ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="medicine_id" class="form-label">Medicine <span class="req">*</span></label>
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
                            <label for="quantity" class="form-label">Quantity <span class="req">*</span></label>
                            <input type="number" id="quantity" name="quantity" min="1" max="<?= MAX_DONATION_QUANTITY ?>"
                                   class="form-control<?= error_class($errors, 'quantity') ?>" value="<?= e($form['quantity']) ?>">
                            <div class="form-text">Count single units, e.g. 2 boxes of 100 tablets = 200.</div>
                            <?= error_text($errors, 'quantity') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="expiry_date" class="form-label">Expiry date <span class="req">*</span></label>
                            <input type="date" id="expiry_date" name="expiry_date" min="<?= e($earliest_expiry) ?>"
                                   class="form-control<?= error_class($errors, 'expiry_date') ?>" value="<?= e($form['expiry_date']) ?>">
                            <?= error_text($errors, 'expiry_date') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="lot_number" class="form-label">Batch or lot number <span class="small text-muted">(optional)</span></label>
                            <input type="text" id="lot_number" name="lot_number" maxlength="50"
                                   class="form-control<?= error_class($errors, 'lot_number') ?>" value="<?= e($form['lot_number']) ?>">
                            <?= error_text($errors, 'lot_number') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="donor_notes" class="form-label">Notes for the admin <span class="small text-muted">(optional)</span></label>
                            <input type="text" id="donor_notes" name="donor_notes" maxlength="255"
                                   class="form-control<?= error_class($errors, 'donor_notes') ?>" value="<?= e($form['donor_notes']) ?>"
                                   placeholder="e.g. Two unopened boxes of 100 tablets">
                            <?= error_text($errors, 'donor_notes') ?>
                        </div>
                        <div class="col-12">
                            <label for="photo" class="form-label">Packaging photo <span class="req">*</span></label>
                            <div class="upload-box d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                                <span class="icon-tile"><i class="bi bi-camera"></i></span>
                                <div class="flex-fill">
                                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png"
                                           class="form-control<?= error_class($errors, 'photo') ?>">
                                    <div class="form-text">Show the label, seal, and expiry date. JPG or PNG, up to 5 MB.</div>
                                    <?= error_text($errors, 'photo') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input<?= error_class($errors, 'declaration') ?>" type="checkbox"
                                       id="declaration" name="declaration" value="1">
                                <label class="form-check-label" for="declaration">
                                    I confirm these medicines are genuine, sealed and stored properly, and I consent to
                                    GabayGamot processing my donation details (RA 10173). <span class="req">*</span>
                                </label>
                                <?= error_text($errors, 'declaration') ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= e(url($my_donations_page)) ?>" class="btn btn-outline-primary btn-lg px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg px-4">Submit donation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== Side panels ===== -->
    <div class="col-12 col-lg-4">
        <div class="card-soft p-4 mb-3">
            <h2 class="h5">What we accept</h2>
            <ul class="mb-0">
                <li>Sealed, unopened packaging</li>
                <li>Readable label with name and strength</li>
                <li>Expiry on or after <strong><?= e(format_date($earliest_expiry)) ?></strong>
                    (<?= MIN_DONATION_SHELF_DAYS ?> days from today)</li>
                <li>The GabayGamot team contacts you about drop-off after review</li>
            </ul>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Recent donations</h2>
                    <a href="<?= e(url($my_donations_page)) ?>" class="small fw-semibold">See all</a>
                </div>
                <?php if (!$recent): ?>
                    <p class="small text-muted mb-0">Your donations will appear here.</p>
                <?php endif; ?>
                <?php foreach ($recent as $i => $item): ?>
                    <a href="<?= e(url('donor/donation_view.php?id=' . $item['id'])) ?>"
                       class="d-flex justify-content-between align-items-center py-2 text-decoration-none text-reset <?= $i > 0 ? 'border-top' : '' ?>">
                        <span>
                            <span class="mono d-block">#<?= (int) $item['id'] ?></span>
                            <span class="small text-muted"><?= e($item['generic_name']) ?> · <?= (int) $item['quantity'] ?></span>
                        </span>
                        <?= donation_badge($item['status']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
