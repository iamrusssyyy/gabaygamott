<?php
// =====================================================================
// beneficiary/request_new.php — the 4-step request wizard (wireframe 2h):
//   Step 1 Patient details -> Step 2 Medicine needed ->
//   Step 3 Documents       -> Step 4 Review & submit
//
// While the beneficiary fills in the steps, the answers are kept in the
// SESSION as a "draft" ($_SESSION['request_draft']). Nothing is saved in
// the requests table until they press "Submit request" in step 4.
// Uploaded documents are saved to disk right away (outside the website
// folder) and remembered in the draft.
// =====================================================================

// 1. Only beneficiaries
require_once __DIR__ . '/../includes/init.php';
require_role(['beneficiary']);
$user = current_user();

// title = left step list · sub = small line under it ·
// question = heading of the form card · filipino = helper line
$steps = [
    1 => ['title' => 'Patient details', 'sub' => 'Who needs the medicine',  'question' => 'Who needs the medicine?',   'filipino' => 'Sino ang nangangailangan ng gamot?'],
    2 => ['title' => 'Medicine needed', 'sub' => 'Name and quantity',       'question' => 'What medicine do you need?', 'filipino' => 'Anong gamot ang kailangan?'],
    3 => ['title' => 'Documents',       'sub' => 'Prescription, indigency', 'question' => 'Upload your documents',      'filipino' => 'I-upload ang mga dokumento'],
    4 => ['title' => 'Review & submit', 'sub' => 'Check and send',          'question' => 'Review and submit',          'filipino' => 'Suriin at ipadala'],
];

/** True when all required documents are uploaded and consent was given. */
function documents_complete(array $draft): bool
{
    foreach (DOC_TYPES as $type => $info) {
        if ($info['required'] && empty($draft['documents'][$type])) {
            return false;
        }
    }
    return !empty($draft['documents_consent_at']);
}

/** True when a step's answers are saved in the draft. */
function step_done(array $draft, int $step): bool
{
    return match ($step) {
        1 => isset($draft['step1']),
        2 => isset($draft['step2']),
        3 => documents_complete($draft),
        default => false,
    };
}

$draft = $_SESSION['request_draft'] ?? [];

// The furthest step allowed = the first step that isn't finished yet.
// This stops people from jumping to "Submit" without filling in step 1.
$max_step = 1;
while ($max_step < 4 && step_done($draft, $max_step)) {
    $max_step++;
}
$step = to_int(get('step')) ?? $max_step;
$step = max(1, min($step, $max_step));

// Medicines to choose from in step 2
$medicines = db()->query(
    'SELECT id, generic_name, brand_name, strength, dosage_form, unit
     FROM medicines WHERE is_active = 1 ORDER BY generic_name, strength, brand_name'
)->fetchAll();
$medicine_ids = array_map('intval', array_column($medicines, 'id'));

$errors = [];

// ---------------------------------------------------------------------
// 2. Handle the submitted step
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    // ---- "Start over": throw away the draft and its uploaded files ----
    if ($action === 'start_over') {
        foreach ($draft['documents'] ?? [] as $document) {
            delete_upload('requests', $document['stored_name']);
        }
        unset($_SESSION['request_draft']);
        set_flash('info', 'Your draft was cleared. You can start a new request.');
        redirect('beneficiary/request_new.php');
    }

    if ($step === 1) {
        // ---- Step 1: patient details ----
        $values = [];
        foreach (['patient_name', 'relationship', 'patient_birthdate', 'patient_sex', 'patient_phone',
                  'patient_address', 'patient_city', 'diagnosis'] as $field) {
            $values[$field] = post($field);
        }

        // "This request is for": Myself, or A family member (then we ask
        // which relative in the relationship dropdown).
        $values['request_for'] = post('request_for') === 'family' ? 'family' : 'self';
        if ($values['request_for'] === 'self') {
            $values['relationship'] = 'self';
        } elseif (!isset(RELATIONSHIPS[$values['relationship']]) || $values['relationship'] === 'self') {
            $errors['relationship'] = 'Please choose who the patient is to you.';
        }

        if (mb_strlen($values['patient_name']) < 2 || mb_strlen($values['patient_name']) > 120) {
            $errors['patient_name'] = "Please enter the patient's full name.";
        }
        // createFromFormat('!Y-m-d') reads a date like 1961-03-14 (the
        // format a date input sends). We also reject future dates.
        $birthdate = DateTime::createFromFormat('!Y-m-d', $values['patient_birthdate']);
        if (!$birthdate || $birthdate->format('Y-m-d') !== $values['patient_birthdate']
            || $birthdate > new DateTime('today') || $birthdate < new DateTime('-120 years')) {
            $errors['patient_birthdate'] = 'Please enter a valid birthdate.';
        }
        if (!in_array($values['patient_sex'], ['female', 'male'], true)) {
            $errors['patient_sex'] = 'Please choose one.';
        }
        if ($values['patient_phone'] !== '' && !preg_match('/^[0-9+\-() ]{7,20}$/', $values['patient_phone'])) {
            $errors['patient_phone'] = 'Use numbers only, e.g. 0917 123 4567.';
        }
        if ($values['patient_address'] === '' || mb_strlen($values['patient_address']) > 255) {
            $errors['patient_address'] = 'Please enter the house number, street, and barangay.';
        }
        if (!in_array($values['patient_city'], METRO_MANILA_CITIES, true)) {
            $errors['patient_city'] = 'Please choose the city.';
        }
        if (mb_strlen($values['diagnosis']) > 255) {
            $errors['diagnosis'] = 'Please keep this short.';
        }

        if (!$errors) {
            $_SESSION['request_draft']['step1'] = $values;
            redirect('beneficiary/request_new.php?step=2');
        }

    } elseif ($step === 2) {
        // ---- Step 2: medicine needed ----
        $values = [];
        foreach (['medicine_id', 'quantity_per_month', 'duration', 'supply_left', 'prescribing_doctor'] as $field) {
            $values[$field] = post($field);
        }

        if (!in_array(to_int($values['medicine_id']), $medicine_ids, true)) {
            $errors['medicine_id'] = 'Please choose the medicine.';
        }
        $quantity = to_int($values['quantity_per_month']);
        if ($quantity === null || $quantity < 1 || $quantity > 1000) {
            $errors['quantity_per_month'] = 'Enter a number from 1 to 1000. Example: 1 tablet a day = 30.';
        }
        if (!isset(DURATIONS[$values['duration']])) {
            $errors['duration'] = 'Please choose how long it is needed.';
        }
        if (!isset(SUPPLY_LEFT[$values['supply_left']])) {
            $errors['supply_left'] = 'Please choose how much is left.';
        }
        if (mb_strlen($values['prescribing_doctor']) < 2 || mb_strlen($values['prescribing_doctor']) > 120) {
            $errors['prescribing_doctor'] = "Please enter the doctor's name.";
        }

        if (!$errors) {
            $_SESSION['request_draft']['step2'] = $values;
            redirect('beneficiary/request_new.php?step=3');
        }

    } elseif ($step === 3) {
        // ---- Step 3: documents ----

        // "Remove" button next to an uploaded document
        $remove = post('remove_doc');
        if (isset(DOC_TYPES[$remove])) {
            delete_upload('requests', $draft['documents'][$remove]['stored_name'] ?? null);
            unset($_SESSION['request_draft']['documents'][$remove]);
            redirect('beneficiary/request_new.php?step=3');
        }

        // Save each file that was chosen (a new file replaces the old one)
        foreach (DOC_TYPES as $type => $info) {
            if (!file_was_chosen($type)) {
                continue;
            }
            $saved = save_upload($_FILES[$type], 'requests', DOCUMENT_FILE_TYPES);
            if ($saved['error'] !== null) {
                $errors[$type] = $saved['error'];
                continue;
            }
            delete_upload('requests', $draft['documents'][$type]['stored_name'] ?? null);
            unset($saved['error']);
            $_SESSION['request_draft']['documents'][$type] = $saved;
        }
        $draft = $_SESSION['request_draft'];

        foreach (DOC_TYPES as $type => $info) {
            if ($info['required'] && empty($draft['documents'][$type]) && !isset($errors[$type])) {
                $errors[$type] = 'Please upload your ' . strtolower($info['label']) . '.';
            }
        }
        if (post('documents_consent') !== '1') {
            $errors['documents_consent'] = 'Please tick the box to agree before continuing.';
        }

        if (!$errors) {
            // RA 10173: remember WHEN consent was given for these documents
            $_SESSION['request_draft']['documents_consent_at'] = date('Y-m-d H:i:s');
            redirect('beneficiary/request_new.php?step=4');
        }

    } elseif ($step === 4 && $action === 'submit') {
        // ---- Step 4: save everything as a real request ----
        $patient  = $draft['step1'];
        $medicine = $draft['step2'];
        if (!in_array((int) $medicine['medicine_id'], $medicine_ids, true)) {
            set_flash('danger', 'That medicine is no longer in our list. Please choose it again.');
            redirect('beneficiary/request_new.php?step=2');
        }
        $pdo = db();
        $pdo->beginTransaction();   // all-or-nothing (see register.php)

        $stmt = $pdo->prepare(
            "INSERT INTO assistance_requests
                (beneficiary_id, patient_name, patient_birthdate, patient_sex, relationship, patient_phone,
                 patient_address, patient_city, diagnosis, medicine_id, quantity_per_month, duration,
                 supply_left, prescribing_doctor, status, documents_consent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?)"
        );
        $stmt->execute([
            $user['id'],
            $patient['patient_name'], $patient['patient_birthdate'], $patient['patient_sex'],
            $patient['relationship'],
            $patient['patient_phone'] !== '' ? $patient['patient_phone'] : null,
            $patient['patient_address'], $patient['patient_city'],
            $patient['diagnosis'] !== '' ? $patient['diagnosis'] : null,
            (int) $medicine['medicine_id'], (int) $medicine['quantity_per_month'], $medicine['duration'],
            $medicine['supply_left'], $medicine['prescribing_doctor'],
            $draft['documents_consent_at'],
        ]);
        $request_id = (int) $pdo->lastInsertId();

        // Reference number people can quote, e.g. GG-2026-000008
        $reference_no = sprintf('GG-%s-%06d', date('Y'), $request_id);
        $pdo->prepare('UPDATE assistance_requests SET reference_no = ? WHERE id = ?')
            ->execute([$reference_no, $request_id]);

        $stmt = $pdo->prepare(
            'INSERT INTO request_documents (request_id, doc_type, original_name, stored_name, mime_type, file_size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($draft['documents'] as $type => $document) {
            $stmt->execute([$request_id, $type, $document['original_name'], $document['stored_name'],
                            $document['mime_type'], $document['file_size'], $user['id']]);
        }

        $pdo->prepare('INSERT INTO request_status_history (request_id, status, changed_by) VALUES (?, ?, ?)')
            ->execute([$request_id, 'submitted', $user['id']]);
        $pdo->commit();

        notify_admins('New assistance request ' . $reference_no . ' needs verification.',
                      'admin/request_view.php?id=' . $request_id);
        unset($_SESSION['request_draft']);
        set_flash('success', 'Request ' . $reference_no . ' was submitted! We will check your documents '
                           . 'in 1–2 working days. You will see updates here.');
        redirect('beneficiary/request_view.php?id=' . $request_id);
    }
}

// ---------------------------------------------------------------------
// 3. Values to show in the form: what was just typed (if there were
//    errors), otherwise what's in the draft, otherwise sensible defaults.
// ---------------------------------------------------------------------
$draft = $_SESSION['request_draft'] ?? [];
if ($step === 1) {
    $values = $values ?? $draft['step1'] ?? [
        'patient_name' => $user['full_name'], 'relationship' => 'self', 'patient_birthdate' => '',
        'patient_sex' => '', 'patient_phone' => $user['phone'] ?? '', 'patient_address' => '',
        'patient_city' => $user['city'] ?? '', 'diagnosis' => '',
    ];
    // "Myself" or "A family member" (worked out from the relationship)
    $values['request_for'] = $values['request_for'] ?? ($values['relationship'] === 'self' ? 'self' : 'family');
} elseif ($step === 2) {
    $values = $values ?? $draft['step2'] ?? [
        'medicine_id' => '', 'quantity_per_month' => '', 'duration' => '', 'supply_left' => '',
        'prescribing_doctor' => '',
    ];
}

// For the review step: the chosen medicine's details
$chosen_medicine = null;
if (isset($draft['step2'])) {
    foreach ($medicines as $medicine) {
        if ((int) $medicine['id'] === (int) $draft['step2']['medicine_id']) {
            $chosen_medicine = $medicine;
        }
    }
}

// ---------------------------------------------------------------------
// 4. Show the page (layout from the prototype "Request wizard")
// ---------------------------------------------------------------------
$page_title = 'New medicine request';
require __DIR__ . '/../includes/header.php';
$form_action = url('beneficiary/request_new.php?step=' . $step);
$next_title  = $steps[$step + 1]['title'] ?? '';
?>

<div class="d-flex justify-content-between align-items-start gap-3 page-head">
    <div>
        <h1>New medicine request</h1>
        <div class="subtitle">Humingi ng tulong · Step <?= $step ?> of 4</div>
    </div>
    <a href="<?= e(url('beneficiary/dashboard.php')) ?>" class="fw-semibold text-decoration-none mt-2">Cancel</a>
</div>

<!-- Phones: 4 progress bars instead of the step list -->
<div class="step-bars d-lg-none mb-4">
    <?php foreach ($steps as $number => $info): ?>
        <div class="<?= $number <= $step ? 'on' : '' ?> <?= $number === $step ? 'current' : '' ?>">
            <span></span><?= e(explode(' ', $info['title'])[0]) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">

    <!-- ===== Left: step list (desktop) and privacy note ===== -->
    <div class="col-12 col-lg-4">
        <div class="card mb-3 d-none d-lg-block">
            <ol class="step-rail">
                <?php foreach ($steps as $number => $info): ?>
                    <?php
                    $done  = step_done($draft, $number) && $number !== $step;
                    $class = $number === $step ? 'current' : ($done ? 'done' : '');
                    ?>
                    <li class="<?= $class ?>">
                        <span class="step-circle"><?= $done ? '<i class="bi bi-check-lg"></i>' : $number ?></span>
                        <div>
                            <?php if ($number <= $max_step && $number !== $step): ?>
                                <a href="<?= e(url('beneficiary/request_new.php?step=' . $number)) ?>" class="step-title d-block"><?= e($info['title']) ?></a>
                            <?php else: ?>
                                <span class="step-title d-block"><?= e($info['title']) ?></span>
                            <?php endif; ?>
                            <span class="step-sub"><?= e($info['sub']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="card">
            <div class="card-body d-flex gap-2">
                <i class="bi bi-shield-check text-muted fs-5"></i>
                <div class="small text-muted">
                    Nothing is sent until you press Submit. Documents are stored privately (RA 10173).
                    <div class="mt-2">Need help? Call <strong><?= e(HELP_PHONE) ?></strong></div>
                    <?php if ($draft): ?>
                        <form method="post" action="<?= e($form_action) ?>" class="mt-2"
                              onsubmit="return confirm('Clear everything you entered and start over?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start_over">
                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">Start over</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Right: the current step in a white card ===== -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="h4 mb-0"><?= e($steps[$step]['question']) ?></h2>
                <p class="text-muted"><?= e($steps[$step]['filipino']) ?></p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2">Please fix the fields marked in red.</div>
                <?php endif; ?>

<?php if ($step === 1): ?>
                <!-- ================= STEP 1 ================= -->
                <form method="post" action="<?= e($form_action) ?>" novalidate>
                    <?= csrf_field() ?>
                    <span class="form-label d-block">This request is for <span class="req">*</span></span>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="choice-card">
                                <input class="form-check-input" type="radio" name="request_for" value="self" id="for-self"
                                       <?= $values['request_for'] === 'self' ? 'checked' : '' ?>>
                                <span class="fw-semibold">Myself</span>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="choice-card">
                                <input class="form-check-input" type="radio" name="request_for" value="family" id="for-family"
                                       <?= $values['request_for'] === 'family' ? 'checked' : '' ?>>
                                <span class="fw-semibold">A family member</span>
                            </label>
                        </div>
                    </div>

                    <!-- Shown only when "A family member" is picked (see .relationship-field in style.css) -->
                    <div class="mb-3 relationship-field">
                        <label for="relationship" class="form-label">The patient is my… <span class="req">*</span></label>
                        <select id="relationship" name="relationship" class="form-select<?= error_class($errors, 'relationship') ?>">
                            <option value="">Choose…</option>
                            <?php foreach (RELATIONSHIPS as $value => $label): ?>
                                <?php if ($value === 'self') { continue; } ?>
                                <option value="<?= e($value) ?>" <?= $values['relationship'] === $value ? 'selected' : '' ?>><?= e(str_replace('My ', '', $label)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= error_text($errors, 'relationship') ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="patient_name" class="form-label">Patient's full name <span class="req">*</span></label>
                            <input type="text" id="patient_name" name="patient_name" maxlength="120"
                                   class="form-control<?= error_class($errors, 'patient_name') ?>" value="<?= e($values['patient_name']) ?>">
                            <?= error_text($errors, 'patient_name') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="patient_birthdate" class="form-label">Date of birth <span class="req">*</span></label>
                            <input type="date" id="patient_birthdate" name="patient_birthdate" max="<?= date('Y-m-d') ?>"
                                   class="form-control<?= error_class($errors, 'patient_birthdate') ?>" value="<?= e($values['patient_birthdate']) ?>">
                            <?= error_text($errors, 'patient_birthdate') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="patient_sex" class="form-label">Sex <span class="req">*</span></label>
                            <select id="patient_sex" name="patient_sex" class="form-select<?= error_class($errors, 'patient_sex') ?>">
                                <option value="">Choose…</option>
                                <option value="female" <?= $values['patient_sex'] === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="male" <?= $values['patient_sex'] === 'male' ? 'selected' : '' ?>>Male</option>
                            </select>
                            <?= error_text($errors, 'patient_sex') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="patient_phone" class="form-label">Contact number <span class="small text-muted">(optional)</span></label>
                            <input type="tel" id="patient_phone" name="patient_phone" maxlength="20" placeholder="0917 123 4567"
                                   class="form-control<?= error_class($errors, 'patient_phone') ?>" value="<?= e($values['patient_phone']) ?>">
                            <?= error_text($errors, 'patient_phone') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="patient_address" class="form-label">House no., street, barangay <span class="req">*</span></label>
                            <input type="text" id="patient_address" name="patient_address" maxlength="255"
                                   class="form-control<?= error_class($errors, 'patient_address') ?>" value="<?= e($values['patient_address']) ?>">
                            <?= error_text($errors, 'patient_address') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="patient_city" class="form-label">City <span class="req">*</span></label>
                            <select id="patient_city" name="patient_city" class="form-select<?= error_class($errors, 'patient_city') ?>">
                                <option value="">Choose…</option>
                                <?php foreach (METRO_MANILA_CITIES as $city): ?>
                                    <option value="<?= e($city) ?>" <?= $values['patient_city'] === $city ? 'selected' : '' ?>><?= e($city) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= error_text($errors, 'patient_city') ?>
                        </div>
                        <div class="col-12">
                            <label for="diagnosis" class="form-label">Diagnosis or condition <span class="small text-muted">(optional)</span></label>
                            <textarea id="diagnosis" name="diagnosis" maxlength="255" rows="3"
                                      class="form-control<?= error_class($errors, 'diagnosis') ?>"
                                      placeholder="e.g. Hypertension"><?= e($values['diagnosis']) ?></textarea>
                            <div class="form-text">Helps us match you with the right program.</div>
                            <?= error_text($errors, 'diagnosis') ?>
                        </div>
                    </div>
                    <div class="form-card-footer d-flex justify-content-between">
                        <a href="<?= e(url('beneficiary/dashboard.php')) ?>" class="btn btn-soft btn-lg px-4">Back</a>
                        <button type="submit" class="btn btn-primary btn-lg px-4">Next: <?= e($next_title) ?></button>
                    </div>
                </form>

<?php elseif ($step === 2): ?>
                <!-- ================= STEP 2 ================= -->
                <form method="post" action="<?= e($form_action) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="medicine_id" class="form-label">Medicine (brand or generic) <span class="req">*</span></label>
                            <select id="medicine_id" name="medicine_id" class="form-select<?= error_class($errors, 'medicine_id') ?>">
                                <option value="">Choose the medicine on your prescription…</option>
                                <?php foreach ($medicines as $medicine): ?>
                                    <option value="<?= (int) $medicine['id'] ?>" <?= (string) $values['medicine_id'] === (string) $medicine['id'] ? 'selected' : '' ?>>
                                        <?= e(medicine_name($medicine)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= error_text($errors, 'medicine_id') ?>
                            <div class="form-text">Not in the list? Call <?= e(HELP_PHONE) ?> and we'll add it.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="quantity_per_month" class="form-label">Quantity per month <span class="req">*</span></label>
                            <input type="number" id="quantity_per_month" name="quantity_per_month" min="1" max="1000"
                                   class="form-control<?= error_class($errors, 'quantity_per_month') ?>" value="<?= e($values['quantity_per_month']) ?>">
                            <div class="form-text">Use the quantity on your prescription, e.g. 1 tablet a day = 30.</div>
                            <?= error_text($errors, 'quantity_per_month') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="duration" class="form-label">How long needed <span class="req">*</span></label>
                            <select id="duration" name="duration" class="form-select<?= error_class($errors, 'duration') ?>">
                                <option value="">Choose…</option>
                                <?php foreach (DURATIONS as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $values['duration'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= error_text($errors, 'duration') ?>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block">How much is left? <span class="req">*</span></span>
                            <!-- Radio buttons that look like buttons (Bootstrap "btn-check") -->
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (SUPPLY_LEFT as $value => $info): ?>
                                    <input type="radio" class="btn-check" name="supply_left" id="supply-<?= e($value) ?>"
                                           value="<?= e($value) ?>" <?= $values['supply_left'] === $value ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="supply-<?= e($value) ?>"><?= e($info['label']) ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Why we ask: people running out of medicine get help first.</div>
                            <?php if (isset($errors['supply_left'])): ?>
                                <div class="text-danger small"><i class="bi bi-exclamation-circle"></i> <?= e($errors['supply_left']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label for="prescribing_doctor" class="form-label">Prescribing doctor <span class="req">*</span></label>
                            <input type="text" id="prescribing_doctor" name="prescribing_doctor" maxlength="120"
                                   class="form-control<?= error_class($errors, 'prescribing_doctor') ?>"
                                   value="<?= e($values['prescribing_doctor']) ?>" placeholder="e.g. Dr. Ramon Villanueva">
                            <?= error_text($errors, 'prescribing_doctor') ?>
                        </div>
                    </div>
                    <div class="form-card-footer d-flex justify-content-between">
                        <a href="<?= e(url('beneficiary/request_new.php?step=1')) ?>" class="btn btn-soft btn-lg px-4">Back</a>
                        <button type="submit" class="btn btn-primary btn-lg px-4">Next: <?= e($next_title) ?></button>
                    </div>
                </form>

<?php elseif ($step === 3): ?>
                <!-- ================= STEP 3 ================= -->
                <!-- enctype="multipart/form-data" is required for file uploads -->
                <form method="post" action="<?= e($form_action) ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <p class="small text-muted">Take a clear photo or upload a PDF. JPG, PNG, or PDF, up to 5 MB each.</p>

                    <?php foreach (DOC_TYPES as $type => $info): ?>
                        <?php $uploaded = $draft['documents'][$type] ?? null; ?>
                        <div class="upload-box mb-3 <?= $uploaded ? 'done' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <label for="<?= e($type) ?>" class="fw-semibold">
                                    <i class="bi bi-file-earmark-medical"></i> <?= e($info['label']) ?>
                                    <?= $info['required'] ? '<span class="req">*</span>' : '<span class="small text-muted fw-normal">(optional)</span>' ?>
                                </label>
                                <?php if ($uploaded): ?><span class="pill pill-green"><i class="bi bi-check-lg"></i> Uploaded</span><?php endif; ?>
                            </div>
                            <?php if ($uploaded): ?>
                                <div class="small mb-2">
                                    <a href="<?= e(url('files/view.php?type=draft&doc=' . $type)) ?>" target="_blank" rel="noopener"><?= e($uploaded['original_name']) ?></a>
                                    · <?= e(number_format($uploaded['file_size'] / 1024, 0)) ?> KB
                                    <button type="submit" name="remove_doc" value="<?= e($type) ?>" class="btn btn-link btn-sm text-danger p-0 ms-2">Remove</button>
                                </div>
                                <div class="form-text mt-0 mb-1">Choose a new file only if you want to replace it.</div>
                            <?php endif; ?>
                            <input type="file" id="<?= e($type) ?>" name="<?= e($type) ?>"
                                   accept="image/jpeg,image/png,application/pdf"
                                   class="form-control<?= error_class($errors, $type) ?>">
                            <?= error_text($errors, $type) ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- RA 10173 consent for the documents -->
                    <div class="form-check mt-4">
                        <input class="form-check-input<?= error_class($errors, 'documents_consent') ?>" type="checkbox"
                               id="documents_consent" name="documents_consent" value="1"
                               <?= !empty($draft['documents_consent_at']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="documents_consent">
                            I agree that GabayGamot may store these documents and share them only with its
                            administrators and the partner program my request is matched with, to process my
                            request, under the Data Privacy Act of 2012 (RA 10173).
                            <a href="<?= e(url('privacy.php')) ?>" target="_blank">Privacy notice</a> <span class="req">*</span>
                        </label>
                        <?= error_text($errors, 'documents_consent') ?>
                    </div>

                    <div class="form-card-footer d-flex justify-content-between">
                        <a href="<?= e(url('beneficiary/request_new.php?step=2')) ?>" class="btn btn-soft btn-lg px-4">Back</a>
                        <button type="submit" class="btn btn-primary btn-lg px-4">Next: Review</button>
                    </div>
                </form>

<?php else: ?>
                <!-- ================= STEP 4 ================= -->
                <?php $patient = $draft['step1']; $need = $draft['step2']; ?>
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between"><h3 class="h6">Patient</h3>
                        <a href="<?= e(url('beneficiary/request_new.php?step=1')) ?>" class="small fw-semibold">Edit</a></div>
                    <p class="mb-0 small">
                        <?= e($patient['patient_name']) ?> (<?= e(RELATIONSHIPS[$patient['relationship']]) ?>) ·
                        <?= e(format_date($patient['patient_birthdate'])) ?> · <?= e(ucfirst($patient['patient_sex'])) ?><br>
                        <?= e($patient['patient_address']) ?>, <?= e($patient['patient_city']) ?>
                        <?= $patient['patient_phone'] !== '' ? ' · ' . e($patient['patient_phone']) : '' ?>
                        <?= $patient['diagnosis'] !== '' ? '<br>Condition: ' . e($patient['diagnosis']) : '' ?>
                    </p>
                </div>
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between"><h3 class="h6">Medicine</h3>
                        <a href="<?= e(url('beneficiary/request_new.php?step=2')) ?>" class="small fw-semibold">Edit</a></div>
                    <p class="mb-0 small">
                        <strong><?= e($chosen_medicine ? medicine_name($chosen_medicine) : 'Medicine no longer available — please edit') ?></strong><br>
                        <?= (int) $need['quantity_per_month'] ?> per month · <?= e(DURATIONS[$need['duration']]) ?> ·
                        Left: <?= e(SUPPLY_LEFT[$need['supply_left']]['label']) ?><br>
                        Doctor: <?= e($need['prescribing_doctor']) ?>
                    </p>
                </div>
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between"><h3 class="h6">Documents</h3>
                        <a href="<?= e(url('beneficiary/request_new.php?step=3')) ?>" class="small fw-semibold">Edit</a></div>
                    <ul class="small mb-0 list-unstyled">
                        <?php foreach ($draft['documents'] as $type => $document): ?>
                            <li><i class="bi bi-check-circle-fill" style="color: var(--gg-green)"></i> <?= e(DOC_TYPES[$type]['label']) ?>:
                                <a href="<?= e(url('files/view.php?type=draft&doc=' . $type)) ?>" target="_blank" rel="noopener"><?= e($document['original_name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="post" action="<?= e($form_action) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submit">
                    <p class="small text-muted">By submitting, you confirm the information is true. GabayGamot does not
                        replace your doctor; all requests are checked by our admins and partner programs.</p>
                    <div class="form-card-footer d-flex justify-content-between">
                        <a href="<?= e(url('beneficiary/request_new.php?step=3')) ?>" class="btn btn-soft btn-lg px-4">Back</a>
                        <button type="submit" class="btn btn-primary btn-lg px-4" <?= $chosen_medicine ? '' : 'disabled' ?>>Submit request</button>
                    </div>
                </form>
<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
