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
