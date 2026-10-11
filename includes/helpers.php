<?php
// =====================================================================
// includes/helpers.php — small functions used on many pages.
// =====================================================================

/**
 * e() = "escape". Use it EVERY time you print data that came from a user
 * or the database:   <?= e($user['full_name']) ?>
 *
 * htmlspecialchars() turns characters like < > " ' & into safe codes
 * (&lt; &gt; ...), so if someone types <script> as their name, the browser
 * shows the text instead of running it. This blocks "XSS" attacks.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Builds a link inside our app.  url('login.php') -> '/gabaygamot/login.php'
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Sends the browser to another page of our app and stops this script.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Reads a text field from a submitted form ($_POST) safely.
 * Returns '' if the field is missing or is not plain text.
 */
function post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * Reads a value from the URL query string ($_GET) safely.
 *   page.php?q=losartan  ->  get('q') returns 'losartan'
 */
function get(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * Builds a link to the CURRENT page with some URL parameters changed,
 * keeping the others. Used for filters, sorting, and List/Map buttons.
 *   current_url_with(['view' => 'map'])
 */
function current_url_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    // Remove empty values so the link stays short
    $params = array_filter($params, fn($value) => $value !== '' && $value !== null);
    return strtok($_SERVER['REQUEST_URI'], '?') . ($params ? '?' . http_build_query($params) : '');
}

/** Money in pesos:  peso(6.5) -> "₱6.50" */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** A date like "Oct 12, 2026" */
function format_date(?string $date): string
{
    return $date ? date('M j, Y', strtotime($date)) : '';
}

/** A date and time like "Oct 12, 2026, 2:30 PM" */
function format_datetime(?string $datetime): string
{
    return $datetime ? date('M j, Y, g:i A', strtotime($datetime)) : '';
}

/**
 * "Last updated" style text: "25 min ago", "2 hrs ago", "3 days ago".
 * Older than a week shows the date instead.
 */
function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60) {
        return 'just now';
    }
    $minutes = intdiv($seconds, 60);   // intdiv = division without decimals
    if ($minutes < 60) {
        return $minutes . ' min ago';
    }
    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        return $hours . ($hours === 1 ? ' hr ago' : ' hrs ago');
    }
    $days = intdiv($hours, 24);
    if ($days < 7) {
        return $days . ($days === 1 ? ' day ago' : ' days ago');
    }
    return format_date($datetime);
}

/**
 * Full medicine name from a row that has generic_name, brand_name,
 * strength, and dosage_form:
 *   "Losartan Potassium 50 mg tablet"            (generic)
 *   "Losartan Potassium 50 mg tablet (Losarvin)" (branded)
 */
function medicine_name(array $row): string
{
    $name = $row['generic_name'] . ' ' . $row['strength'] . ' ' . $row['dosage_form'];
    if ($row['brand_name'] !== '') {
        $name .= ' (' . $row['brand_name'] . ')';
    }
    return $name;
}

/**
 * Turns a text box into a whole number, or null if it isn't one.
 *   to_int('60') -> 60,  to_int('abc') -> null,  to_int('2.5') -> null
 */
function to_int(string $value): ?int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number === false ? null : $number;
}

/**
 * Sends an in-app notification (shown under the bell in the header).
 * $link is a page inside the app, e.g. 'beneficiary/request_view.php?id=3'.
 */
function notify(int $user_id, string $message, ?string $link = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, mb_substr($message, 0, 255), $link]);
}

/** Sends the same notification to every active admin. */
function notify_admins(string $message, ?string $link = null): void
{
    $admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        notify((int) $admin['id'], $message, $link);
    }
}

/**
 * Inline form errors (shown right under the field, as in the wireframes).
 * $errors is an array like ['email' => 'Please enter a valid email.'].
 *
 *   <input class="form-control<?= error_class($errors, 'email') ?>" ...>
 *   <?= error_text($errors, 'email') ?>
 *
 * error_class() adds Bootstrap's red border; error_text() prints the message.
 */
function error_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function error_text(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<div class="invalid-feedback">' . e($errors[$field]) . '</div>';
}

/**
 * Flash message = a one-time message shown on the NEXT page,
 * e.g. "Welcome back!" after logging in. It is stored in the session
 * and deleted as soon as it is shown (see includes/header.php).
 * $type is a Bootstrap color: 'success', 'danger', 'warning', 'info'.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
