<?php
// =====================================================================
// includes/init.php — loaded at the very top of EVERY page.
// It loads the settings and shared functions, then starts the session.
//
//   Pages in the root folder:  require_once __DIR__ . '/includes/init.php';
//   Pages in a subfolder:      require_once __DIR__ . '/../includes/init.php';
// =====================================================================

// Friendly message if the student forgot to create config/db.php
if (!file_exists(__DIR__ . '/../config/db.php')) {
    exit('Setup needed: copy config/db.example.php to config/db.php');
}

require_once __DIR__ . '/../config/db.php';   // DB_HOST, DB_NAME, DB_USER, DB_PASS
require_once __DIR__ . '/../config/app.php';  // BASE_URL, cities, upload settings
require_once __DIR__ . '/db.php';             // db() — the database connection
require_once __DIR__ . '/helpers.php';        // e(), url(), redirect(), flash messages, notify()
require_once __DIR__ . '/labels.php';         // status names and badges
require_once __DIR__ . '/csrf.php';           // csrf_field(), verify_csrf()
require_once __DIR__ . '/auth.php';           // current_user(), require_role(), login/logout
require_once __DIR__ . '/alerts.php';         // run_alert_checks() — near-expiry and low-stock alerts
require_once __DIR__ . '/upload.php';         // save_upload() — safe file uploads
require_once __DIR__ . '/requests.php';       // load_request(), change_request_status()

// ---------------------------------------------------------------------
// SESSION = a small storage area on the server for each visitor.
// PHP gives the browser a cookie with a random session ID, and on every
// request it uses that ID to find the visitor's data in $_SESSION.
// That is how we remember who is logged in from page to page.
// ---------------------------------------------------------------------
session_name('GABAYGAMOT_SESSION');
session_set_cookie_params([
    'lifetime' => 0,               // cookie is deleted when the browser closes
    'path'     => BASE_URL . '/',  // cookie is only sent to our app
    'httponly' => true,            // JavaScript cannot read it (protects against XSS theft)
    'samesite' => 'Lax',           // other websites cannot send it in hidden form posts
]);
ini_set('session.use_strict_mode', '1'); // reject session IDs that PHP did not create
session_start();
