<?php
// =====================================================================
// login.php — log in with email and password.
// =====================================================================

require_once __DIR__ . '/includes/init.php';

// ---------------------------------------------------------------------
// 1. Already logged in? Go straight to your dashboard.
// ---------------------------------------------------------------------
$user = current_user();
if ($user) {
    redirect(ROLE_HOME[$user['role']]);
}

$error = '';  // message shown above the form
$email = '';  // keep the typed email so the user doesn't retype it

// ---------------------------------------------------------------------
// 2. Handle the submitted form
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = post('email');
    $password = $_POST['password'] ?? '';  // never trim passwords
    if (!is_string($password)) {
        $password = '';
    }

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // Find the account by email (prepared statement: the "?" is filled safely)
        $stmt = db()->prepare(
            'SELECT id, role, full_name, password_hash, status, status_reason
             FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        // password_verify() hashes the typed password the same way and
        // compares it with the stored hash. We never store real passwords.
        if (!$account || !password_verify($password, $account['password_hash'])) {
            // Same message for "no such email" and "wrong password", so
            // attackers can't find out which emails are registered.
            $error = 'Wrong email or password.';
        } elseif ($account['status'] === 'pending') {
            $error = 'Your account is waiting for admin approval. '
                   . 'You can log in once it has been approved.';
        } elseif ($account['status'] === 'rejected') {
            $error = 'Your registration was not approved.'
                   . ($account['status_reason'] ? ' Reason: ' . $account['status_reason'] : '');
        } elseif ($account['status'] === 'disabled') {
            $error = 'This account has been disabled. Please contact the GabayGamot admin.';
        } else {
            // Success!
            login_user($account);
            set_flash('success', 'Welcome back, ' . $account['full_name'] . '!');
            redirect(ROLE_HOME[$account['role']]);
        }
    }
}

// ---------------------------------------------------------------------
// 3. Show the page
// ---------------------------------------------------------------------
$page_title = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm auth-card">
    <div class="card-body p-4">
        <h1 class="h3 mb-1">Log in</h1>
        <p class="text-muted mb-4">Mag-log in sa iyong account</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Log in</button>
        </form>

        <p class="text-center mt-4 mb-0">
            No account yet? <a href="<?= e(url('register.php')) ?>">Create an account</a>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
