<?php
// =====================================================================
// includes/upload.php — saving uploaded files SAFELY.
//
// Rules (from CLAUDE.md):
//   * Files are saved OUTSIDE the website folder (UPLOAD_DIR), so nobody
//     can open them by typing a URL. They are shown only through
//     files/view.php, which checks who is asking.
//   * We check the REAL file type with finfo (it reads the file's first
//     bytes). We never trust the file name or what the browser says,
//     because those are easy to fake (e.g. "virus.php" renamed "photo.jpg").
//   * Max size: MAX_UPLOAD_BYTES (5 MB).
//   * Files get a random name, e.g. 9f3c0a...e1.pdf.
// =====================================================================

// Allowed real file types => the extension we save them with
const DOCUMENT_FILE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
const PHOTO_FILE_TYPES    = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

/**
 * Saves one uploaded file.
 *   $file    one entry of $_FILES, e.g. $_FILES['prescription']
 *   $folder  sub-folder inside UPLOAD_DIR: 'requests' or 'donations'
 *   $allowed DOCUMENT_FILE_TYPES or PHOTO_FILE_TYPES
 *
 * Returns ['error' => 'message'] if something is wrong, otherwise
 * ['error' => null, 'stored_name' => ..., 'original_name' => ...,
 *  'mime_type' => ..., 'file_size' => ...].
 */
function save_upload(array $file, string $folder, array $allowed): array
{
    // PHP fills 'error' with a code that tells us what happened
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['error' => 'Please upload one file.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Please choose a file.'];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        || $file['size'] > MAX_UPLOAD_BYTES) {
        return ['error' => 'The file is too big. The limit is 5 MB.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
        return ['error' => 'The upload did not finish. Please try again.'];
    }

    // Check the REAL type by looking inside the file
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        $names = strtoupper(implode(', ', array_unique($allowed)));
        return ['error' => 'This file type is not allowed. Please upload ' . $names . ' only.'];
    }
    // For pictures, also make sure PHP can read it as a real image
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        return ['error' => 'This picture looks damaged. Please take the photo again.'];
    }

    // Make sure the folder exists (0700 = only the server can read it)
    $directory = UPLOAD_DIR . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        return ['error' => 'The server cannot save files right now. Please tell the admin.'];
    }

    // Random file name: 32 random hex characters + the extension we chose
    $stored_name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    // move_uploaded_file() only moves files that really came from an upload
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $stored_name)) {
        return ['error' => 'The file could not be saved. Please try again.'];
    }

    return [
        'error'         => null,
        'stored_name'   => $stored_name,
        // Keep the user's file name only for display (always shown with e())
        'original_name' => mb_substr(basename((string) $file['name']), 0, 255),
        'mime_type'     => $mime,
        'file_size'     => (int) $file['size'],
    ];
}

/** Full path of a saved file on the server's disk. */
function upload_path(string $folder, string $stored_name): string
{
    return UPLOAD_DIR . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . basename($stored_name);
}

/** Deletes a saved file (used when a document is replaced). */
function delete_upload(string $folder, ?string $stored_name): void
{
    if ($stored_name) {
        $path = upload_path($folder, $stored_name);
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/** True if the user actually chose a file for this input. */
function file_was_chosen(string $input_name): bool
{
    return isset($_FILES[$input_name]['error']) && $_FILES[$input_name]['error'] !== UPLOAD_ERR_NO_FILE;
}
