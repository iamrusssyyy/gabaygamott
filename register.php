<?php
// =====================================================================
// register.php — create a new account.
//
// Who can register here: beneficiary, donor, pharmacy, partner.
// Admin accounts can NOT be created here (only by the database seed).
//
// - Beneficiary and donor accounts are active right away (auto login).
// - Pharmacy and partner accounts start as 'pending' and must be
//   approved by an admin before they can log in.
// =====================================================================

require_once __DIR__ . '/includes/init.php';

// ---------------------------------------------------------------------
// 1. Logged-in users don't need to register
// ---------------------------------------------------------------------
$user = current_user();
if ($user) {
    redirect(ROLE_HOME[$user['role']]);
}

// The roles the public may choose, with English + Filipino helper text.
$register_roles = [
    'beneficiary' => ['I need help getting medicine', 'Kailangan ko ng tulong sa gamot'],
    'donor'       => ['I want to donate medicine',    'Gusto kong mag-donate ng gamot'],
    'pharmacy'    => ['I manage a pharmacy',          'Para sa may-ari o staff ng botika'],
    'partner'     => ['I work for a partner program', 'PCS, PCSO, DSWD, o Malasakit Center'],
];

// Partner programs for the partner's dropdown
$programs = db()->query(
    'SELECT id, name FROM partner_programs WHERE is_active = 1 ORDER BY name'
)->fetchAll();

// Every text field on the form, starting empty. After a failed submit we
// fill these with what the user typed, so they don't have to retype it.
$form = [
    'role' => '', 'full_name' => '', 'email' => '', 'phone' => '', 'city' => '',
    'donor_organization' => '', 'partner_office' => '', 'partner_program_id' => '',
    'pharmacy_name' => '', 'branch' => '', 'address' => '',
    'latitude' => '', 'longitude' => '', 'opening_hours' => '', 'fda_license_no' => '',
];
$errors = [];  // field name => error message

// ---------------------------------------------------------------------
// 2. Handle the submitted form
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($form) as $field) {
        $form[$field] = post($field);
    }
    $form['email'] = strtolower($form['email']);

    // Passwords are read separately and never put back into the form.
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $consent  = post('privacy_consent') === '1';
    $role     = $form['role'];

    // ---- 2a. Fields everyone fills in ----
    if (!array_key_exists($role, $register_roles)) {
        $errors['role'] = 'Please choose what kind of account you need.';
    }

    if (mb_strlen($form['full_name']) < 2 || mb_strlen($form['full_name']) > 120) {
        $errors['full_name'] = 'Please enter your full name.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$form['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered. Try logging in instead.';
        }
    }

    // Phone: optional, except for pharmacies (shown on the "Call" button).
    // Allowed characters: digits, spaces, + - ( )
    if ($form['phone'] === '' && $role === 'pharmacy') {
        $errors['phone'] = 'Please enter the pharmacy\'s phone number.';
    } elseif ($form['phone'] !== '' && !preg_match('/^[0-9+\-() ]{7,20}$/', $form['phone'])) {
        $errors['phone'] = 'Use numbers only, e.g. 0917 123 4567 or (02) 8123-4567.';
    }

    if (!in_array($form['city'], METRO_MANILA_CITIES, true)) {
        $errors['city'] = 'Please choose your city.';
    }

    // bcrypt (used by password_hash) only reads the first 72 characters.
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors['password'] = 'Use 8 to 72 characters.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The passwords do not match.';
    }

    if (!$consent) {
        $errors['privacy_consent'] = 'You must agree to the Privacy Notice to create an account.';
    }

    // ---- 2b. Extra fields for some roles ----
    if ($role === 'partner') {
        $program_ids = array_map('intval', array_column($programs, 'id'));
        if (!in_array((int) $form['partner_program_id'], $program_ids, true)) {
            $errors['partner_program_id'] = 'Please choose your program.';
        }
        if ($form['partner_office'] === '' || mb_strlen($form['partner_office']) > 150) {
            $errors['partner_office'] = 'Please enter your office or branch, e.g. "Malasakit Center – PGH".';
        }
    }

    if ($role === 'donor' && mb_strlen($form['donor_organization']) > 150) {
        $errors['donor_organization'] = 'Organization name is too long.';
    }

    if ($role === 'pharmacy') {
        if ($form['pharmacy_name'] === '' || mb_strlen($form['pharmacy_name']) > 150) {
            $errors['pharmacy_name'] = 'Please enter the pharmacy name.';
        }
        if (mb_strlen($form['branch']) > 100) {
            $errors['branch'] = 'Branch name is too long.';
        }
        if ($form['address'] === '' || mb_strlen($form['address']) > 255) {
            $errors['address'] = 'Please enter the street address.';
        }
        // The map pin must be inside Metro Manila (a simple box around NCR).
        $lat = (float) $form['latitude'];
        $lng = (float) $form['longitude'];
        if (!is_numeric($form['latitude']) || !is_numeric($form['longitude'])
            || $lat < 14.35 || $lat > 14.80 || $lng < 120.90 || $lng > 121.15) {
            $errors['location'] = 'Please click the map to pin your pharmacy\'s location in Metro Manila.';
        }
        if (mb_strlen($form['opening_hours']) > 100) {
            $errors['opening_hours'] = 'Opening hours text is too long.';
        }
        if ($form['fda_license_no'] === '' || mb_strlen($form['fda_license_no']) > 50) {
            $errors['fda_license_no'] = 'Please enter your FDA License to Operate (LTO) number.';
        }
    }

    // ---- 2c. Save if there are no errors ----
    if (!$errors) {
        $needs_approval = in_array($role, ['pharmacy', 'partner'], true);

        // users.organization_name holds the donor's organization OR the
        // partner's office (each has its own box on the form).
        $organization_name = null;
        if ($role === 'donor' && $form['donor_organization'] !== '') {
            $organization_name = $form['donor_organization'];
        } elseif ($role === 'partner') {
            $organization_name = $form['partner_office'];
        }

        // A TRANSACTION groups several queries: either ALL are saved, or
        // (if anything fails) NONE are. So we never get a pharmacy user
        // without its pharmacy row.
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (role, full_name, email, password_hash, phone, city,
                                    organization_name, partner_program_id, status, privacy_consent_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $role,
                $form['full_name'],
                $form['email'],
                password_hash($password, PASSWORD_DEFAULT),  // store only the hash
                $form['phone'] !== '' ? $form['phone'] : null,
                $form['city'],
                $organization_name,
                $role === 'partner' ? (int) $form['partner_program_id'] : null,
                $needs_approval ? 'pending' : 'active',
            ]);
            $new_user_id = (int) $pdo->lastInsertId();

            if ($role === 'pharmacy') {
                $stmt = $pdo->prepare(
                    'INSERT INTO pharmacies (user_id, name, branch, address, city, latitude, longitude,
                                             phone, opening_hours, fda_license_no)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $new_user_id,
                    $form['pharmacy_name'],
                    $form['branch'] !== '' ? $form['branch'] : null,
                    $form['address'],
                    $form['city'],
                    $lat,
                    $lng,
                    $form['phone'],
                    $form['opening_hours'] !== '' ? $form['opening_hours'] : null,
                    $form['fda_license_no'],
                ]);
            }

            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            // 23000 = duplicate value (someone registered this email a moment ago)
            if ($ex->getCode() === '23000') {
                $errors['email'] = 'This email is already registered. Try logging in instead.';
            } else {
                throw $ex;
            }
        }

        if (!$errors) {
            if ($needs_approval) {
                set_flash('success', 'Account created! An admin will review your details. '
                    . 'You can log in once your account is approved.');
                redirect('login.php');
            }
            // Beneficiaries and donors are logged in right away.
            login_user(['id' => $new_user_id]);
            set_flash('success', 'Welcome to GabayGamot, ' . $form['full_name'] . '! Your account is ready.');
            redirect(ROLE_HOME[$role]);
        }
    }
}

// ---------------------------------------------------------------------
// 3. Show the page
// ---------------------------------------------------------------------
$page_title   = 'Create an account';
$use_map      = true;
$page_scripts = ['assets/js/register.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm register-card">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Create an account</h1>
        <p class="text-muted mb-4">Gumawa ng account</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                Please fix the fields marked in red below.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <!-- ===== Step A: what kind of account ===== -->
            <fieldset class="mb-4">
                <legend class="h6">What do you need GabayGamot for?</legend>
                <div class="row g-2">
                    <?php foreach ($register_roles as $value => $labels): ?>
                        <div class="col-12 col-md-6">
                            <label class="role-option d-flex gap-2 border rounded p-3">
                                <input class="form-check-input mt-1" type="radio" name="role"
                                       value="<?= e($value) ?>" <?= $form['role'] === $value ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($labels[0]) ?></strong><br>
                                    <span class="small text-muted"><?= e($labels[1]) ?></span>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['role'])): ?>
                    <div class="text-danger small mt-1"><?= e($errors['role']) ?></div>
                <?php endif; ?>
                <p class="small text-muted mt-2 mb-0" data-show-for="pharmacy partner">
                    Pharmacy and partner accounts are checked by an admin before you can log in.
                </p>
            </fieldset>

            <!-- ===== Step B: about you (everyone) ===== -->
            <h2 class="h6">About you</h2>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <label for="full_name" class="form-label">Full name</label>
                    <input type="text" id="full_name" name="full_name" maxlength="120"
                           class="form-control<?= error_class($errors, 'full_name') ?>"
                           value="<?= e($form['full_name']) ?>" autocomplete="name">
                    <?= error_text($errors, 'full_name') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" maxlength="190"
                           class="form-control<?= error_class($errors, 'email') ?>"
                           value="<?= e($form['email']) ?>" autocomplete="email">
                    <?= error_text($errors, 'email') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="phone" class="form-label">
                        Mobile or phone number
                        <span class="text-muted small" data-show-for="beneficiary donor partner">(optional)</span>
                    </label>
                    <input type="tel" id="phone" name="phone" maxlength="20"
                           class="form-control<?= error_class($errors, 'phone') ?>"
                           value="<?= e($form['phone']) ?>" autocomplete="tel"
                           placeholder="0917 123 4567">
                    <?= error_text($errors, 'phone') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="city" class="form-label">City</label>
                    <select id="city" name="city" class="form-select<?= error_class($errors, 'city') ?>">
                        <option value="">Choose your city…</option>
                        <?php foreach (METRO_MANILA_CITIES as $city): ?>
                            <option value="<?= e($city) ?>" <?= $form['city'] === $city ? 'selected' : '' ?>>
                                <?= e($city) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_text($errors, 'city') ?>
                </div>
            </div>

            <!-- ===== Donor only ===== -->
            <div class="mb-4" data-show-for="donor">
                <h2 class="h6">Donating for an organization?</h2>
                <label for="donor_organization" class="form-label">
                    Organization name <span class="text-muted small">(optional)</span>
                </label>
                <input type="text" id="donor_organization" name="donor_organization" maxlength="150"
                       class="form-control<?= error_class($errors, 'donor_organization') ?>"
                       value="<?= e($form['donor_organization']) ?>">
                <?= error_text($errors, 'donor_organization') ?>
            </div>

            <!-- ===== Partner only ===== -->
            <div class="row g-3 mb-4" data-show-for="partner">
                <h2 class="h6 mb-0">Your partner program</h2>
                <div class="col-12 col-md-6">
                    <label for="partner_program_id" class="form-label">Program</label>
                    <select id="partner_program_id" name="partner_program_id"
                            class="form-select<?= error_class($errors, 'partner_program_id') ?>">
                        <option value="">Choose a program…</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?= e((string) $program['id']) ?>"
                                <?= $form['partner_program_id'] === (string) $program['id'] ? 'selected' : '' ?>>
                                <?= e($program['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_text($errors, 'partner_program_id') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="partner_office" class="form-label">Office or branch</label>
                    <input type="text" id="partner_office" name="partner_office" maxlength="150"
                           class="form-control<?= error_class($errors, 'partner_office') ?>"
                           value="<?= e($form['partner_office']) ?>"
                           placeholder="e.g. Malasakit Center – PGH">
                    <?= error_text($errors, 'partner_office') ?>
                </div>
            </div>

            <!-- ===== Pharmacy only ===== -->
            <div class="mb-4" data-show-for="pharmacy">
                <h2 class="h6">Your pharmacy</h2>
                <div class="row g-3">
                    <div class="col-12 col-md-7">
                        <label for="pharmacy_name" class="form-label">Pharmacy name</label>
                        <input type="text" id="pharmacy_name" name="pharmacy_name" maxlength="150"
                               class="form-control<?= error_class($errors, 'pharmacy_name') ?>"
                               value="<?= e($form['pharmacy_name']) ?>" placeholder="e.g. Sagip Generics">
                        <?= error_text($errors, 'pharmacy_name') ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="branch" class="form-label">
                            Branch <span class="text-muted small">(optional)</span>
                        </label>
                        <input type="text" id="branch" name="branch" maxlength="100"
                               class="form-control<?= error_class($errors, 'branch') ?>"
                               value="<?= e($form['branch']) ?>" placeholder="e.g. Cubao">
                        <?= error_text($errors, 'branch') ?>
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Street address</label>
                        <input type="text" id="address" name="address" maxlength="255"
                               class="form-control<?= error_class($errors, 'address') ?>"
                               value="<?= e($form['address']) ?>" placeholder="e.g. P. Tuazon Blvd., Cubao">
                        <div class="form-text">The city is the one you chose above.</div>
                        <?= error_text($errors, 'address') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="opening_hours" class="form-label">
                            Opening hours <span class="text-muted small">(optional)</span>
                        </label>
                        <input type="text" id="opening_hours" name="opening_hours" maxlength="100"
                               class="form-control<?= error_class($errors, 'opening_hours') ?>"
                               value="<?= e($form['opening_hours']) ?>" placeholder="e.g. Mon–Sat 8 AM–9 PM">
                        <?= error_text($errors, 'opening_hours') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="fda_license_no" class="form-label">FDA License to Operate (LTO) no.</label>
                        <input type="text" id="fda_license_no" name="fda_license_no" maxlength="50"
                               class="form-control<?= error_class($errors, 'fda_license_no') ?>"
                               value="<?= e($form['fda_license_no']) ?>">
                        <div class="form-text">The admin uses this to check that your pharmacy is licensed.</div>
                        <?= error_text($errors, 'fda_license_no') ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label mb-1">Pharmacy location on the map</label>
                        <p class="form-text mt-0">Click the map where your pharmacy is. You can drag the pin to adjust it.</p>
                        <div id="pharmacy-map" class="border<?= isset($errors['location']) ? ' border-danger' : '' ?>"></div>
                        <!-- The map fills these hidden inputs when you click -->
                        <input type="hidden" id="latitude" name="latitude" value="<?= e($form['latitude']) ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= e($form['longitude']) ?>">
                        <div id="location-text" class="small text-muted mt-1"></div>
                        <?php if (isset($errors['location'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['location']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===== Password (everyone) ===== -->
            <h2 class="h6">Password</h2>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" maxlength="72"
                           class="form-control<?= error_class($errors, 'password') ?>"
                           autocomplete="new-password">
                    <div class="form-text">At least 8 characters.</div>
                    <?= error_text($errors, 'password') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="password_confirm" class="form-label">Type the password again</label>
                    <input type="password" id="password_confirm" name="password_confirm" maxlength="72"
                           class="form-control<?= error_class($errors, 'password_confirm') ?>"
                           autocomplete="new-password">
                    <?= error_text($errors, 'password_confirm') ?>
                </div>
            </div>

            <!-- ===== Data Privacy Act consent (RA 10173) ===== -->
            <div class="form-check mb-4">
                <input class="form-check-input<?= error_class($errors, 'privacy_consent') ?>" type="checkbox"
                       id="privacy_consent" name="privacy_consent" value="1">
                <label class="form-check-label" for="privacy_consent">
                    I have read the <a href="<?= e(url('privacy.php')) ?>" target="_blank">Privacy Notice</a>
                    and I agree that GabayGamot may collect and use my personal information to run my
                    account, as allowed by the Data Privacy Act of 2012 (RA 10173).
                </label>
                <?= error_text($errors, 'privacy_consent') ?>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create account</button>
        </form>

        <p class="text-center mt-4 mb-0">
            Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
