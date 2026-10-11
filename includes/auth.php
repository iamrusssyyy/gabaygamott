<?php
// =====================================================================
// includes/auth.php — login, logout, and role checks.
//
// After login we store ONLY the user's id in the session. On every page
// we load the user again from the database, so if an admin disables an
// account, that person is logged out on their very next click.
// =====================================================================

// Where each role lands after logging in.
const ROLE_HOME = [
    'beneficiary' => 'beneficiary/dashboard.php',
    'donor'       => 'donor/dashboard.php',
    'pharmacy'    => 'pharmacy/dashboard.php',
    'partner'     => 'partner/dashboard.php',
    'admin'       => 'admin/dashboard.php',
];

/**
 * Returns the logged-in user as an array (id, role, full_name, email),
 * or null if nobody is logged in.
 */
function current_user(): ?array
{
    // "static" = remember the answer, so we query the database only once per page.
    static $user = false; // false means "not looked up yet"

    if ($user === false) {
        $user = null;

        if (isset($_SESSION['user_id'])) {
            $stmt = db()->prepare(
                'SELECT id, role, full_name, email, status FROM users WHERE id = ?'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();

            if ($row && $row['status'] === 'active') {
                $user = $row;
            } else {
                // Account was deleted or disabled after logging in.
                logout_user();
            }
        }
    }

    return $user;
}

/**
 * Put at the top of pages that need ANY logged-in user.
 */
function require_login(): void
{
    if (current_user() === null) {
        set_flash('warning', 'Please log in first.');
        redirect('login.php');
    }
}

/**
 * Put at the top of every protected page, listing who may open it:
 *     require_role(['admin']);
 *     require_role(['admin', 'partner']);
 * Not logged in -> sent to the login page.
 * Wrong role    -> "Access denied" page (HTTP 403 = Forbidden).
 */
function require_role(array $allowed_roles): void
{
    require_login();

    if (!in_array(current_user()['role'], $allowed_roles, true)) {
        http_response_code(403);
        $page_title = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger">'
           . '<h1 class="h4">Access denied</h1>'
           . '<p class="mb-0">Your account is not allowed to open this page.</p>'
           . '</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

/**
 * Logs a user in after their password has been checked.
 */
function login_user(array $user): void
{
    // Give the visitor a brand-new session ID. This blocks "session
    // fixation", where an attacker plants a known ID before login.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);
}

/**
 * Logs the current user out: erase the session data and get a new ID.
 */
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}
