<?php
// =====================================================================
// files/view.php — the ONLY way to open an uploaded file.
//
// Uploaded files live outside the website folder, so the browser can't
// reach them directly. This script:
//   1. finds the file in the database,
//   2. checks that the viewer is allowed to see it,
//   3. reads the file from disk and sends it to the browser.
//
// Who may see what (CLAUDE.md, security rule 6):
//   request documents -> the beneficiary who owns the request, admins,
//                        and partners of the program the request is matched to
//   donation photos   -> the donor who listed it, and admins
//   draft documents   -> only the beneficiary filling in the wizard
//                        (they are stored in that person's own session)
//
// Usage: files/view.php?type=request_doc&id=12
//        files/view.php?type=donation_photo&id=4
//        files/view.php?type=draft&doc=prescription
// =====================================================================

require_once __DIR__ . '/../includes/init.php';
require_login();
$user = current_user();

$type    = get('type');
$id      = to_int(get('id')) ?? 0;
$file    = null;   // ['stored_name' => ..., 'mime_type' => ...]
$folder  = '';
$allowed = false;

if ($type === 'request_doc') {
    $stmt = db()->prepare(
        'SELECT d.stored_name, d.mime_type, r.beneficiary_id, r.partner_program_id
         FROM request_documents d
         JOIN assistance_requests r ON r.id = d.request_id
         WHERE d.id = ?'
    );
    $stmt->execute([$id]);
    $file   = $stmt->fetch();
    $folder = 'requests';
    if ($file) {
        $is_owner   = (int) $file['beneficiary_id'] === (int) $user['id'];
        $is_partner = $user['role'] === 'partner'
                      && $file['partner_program_id'] !== null
                      && (int) $file['partner_program_id'] === (int) $user['partner_program_id'];
        $allowed = $user['role'] === 'admin' || $is_owner || $is_partner;
    }

} elseif ($type === 'donation_photo') {
    $stmt = db()->prepare(
        'SELECT photo_stored_name AS stored_name, photo_mime_type AS mime_type, donor_id
         FROM donations WHERE id = ? AND photo_stored_name IS NOT NULL'
    );
    $stmt->execute([$id]);
    $file   = $stmt->fetch();
    $folder = 'donations';
    if ($file) {
        $allowed = $user['role'] === 'admin' || (int) $file['donor_id'] === (int) $user['id'];
    }

} elseif ($type === 'draft') {
    // A document uploaded in the wizard but not submitted yet
    $file    = $_SESSION['request_draft']['documents'][get('doc')] ?? null;
    $folder  = 'requests';
    $allowed = $file !== null;   // it's in this visitor's own session
}

// Not found / not allowed -> stop. We say "not found" in both cases so
// nobody can learn which file numbers exist.
if (!$file || !$allowed) {
    http_response_code(404);
    exit('File not found, or you are not allowed to see it.');
}

// Extra safety: the stored name must look exactly like the names we create
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $file['stored_name'])
    || !in_array($file['mime_type'], ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(404);
    exit('File not found.');
}

$path = upload_path($folder, $file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('The file is missing on the server.');
}

// Send the file
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $file['stored_name'] . '"'); // show in the browser
header('X-Content-Type-Options: nosniff');      // browser must trust our Content-Type
header('Cache-Control: private, no-store');      // don't keep personal documents in shared caches
readfile($path);
exit;
