<?php
// =====================================================================
// includes/csrf.php — protection against CSRF attacks.
//
// CSRF ("Cross-Site Request Forgery"): a bad website could contain a
// hidden form that posts to OUR site. Because the victim's browser still
// has our login cookie, our site would think the victim sent it.
//
// Defense: every one of our forms includes a secret random token that
// only our pages know. When a form is submitted, we check the token.
// A bad website cannot know the token, so its fake form is rejected.
//
// Usage:
//   Inside every POST form:  echo csrf_field()  (prints the hidden input)
//   When handling POST:      verify_csrf();     (first line, before anything else)
//
// NOTE: never write the PHP closing tag (question mark + >) inside a
// "//" comment. PHP treats it as the end of the code, even in a comment.
// =====================================================================

/** Returns this visitor's token, creating it the first time. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes() makes cryptographically secure random data
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** The hidden <input> to put inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops the page if the submitted token is missing or wrong. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    // hash_equals() compares safely (takes the same time whether or not
    // the strings match, so attackers can't guess the token by timing).
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('This form has expired. Please go back, refresh the page, and try again.');
    }
}
