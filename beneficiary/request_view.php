<?php
// =====================================================================
// beneficiary/request_view.php — track one request: status, what to do
// next, where to claim, timeline, and documents.
// If the status is "Action needed", the beneficiary can upload new
// documents and send the request back for review.
// =====================================================================

// 1. Only the beneficiary who owns this request
require_once __DIR__ . '/../includes/init.php';
require_role(['beneficiary']);
$user = current_user();

$request = load_request(to_int(get('id')) ?? 0);
if (!$request || (int) $request['beneficiary_id'] !== (int) $user['id']) {
    http_response_code(404);
    $page_title = 'Not found';
    require __DIR__ . '/../includes/header.php';
    echo '<p>Request not found.</p>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];

// ---------------------------------------------------------------------
// 2. "Action needed" -> upload fixes and send back for review
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request['status'] === 'action_needed') {
    verify_csrf();
    $message = mb_substr(post('message'), 0, 200);

    // Save any new files first
    $new_files = [];
    foreach (DOC_TYPES as $type => $info) {
        if (file_was_chosen($type)) {
            $saved = save_upload($_FILES[$type], 'requests', DOCUMENT_FILE_TYPES);
            if ($saved['error'] !== null) {
                $errors[$type] = $saved['error'];
            } else {
                $new_files[$type] = $saved;
            }
        }
    }
    if ($new_files && post('documents_consent') !== '1') {
        $errors['documents_consent'] = 'Please tick the box to agree before uploading.';
    }
    if (!$new_files && $message === '') {
        $errors['message'] = 'Upload a new document or write a short message for the admin.';
    }

    if ($errors) {
        // Don't keep files from a failed attempt
        foreach ($new_files as $saved) {
            delete_upload('requests', $saved['stored_name']);
        }
    } else {
        // Replace old documents of the same type with the new ones
        foreach ($new_files as $type => $saved) {
            $stmt = db()->prepare('SELECT id, stored_name FROM request_documents WHERE request_id = ? AND doc_type = ?');
            $stmt->execute([$request['id'], $type]);
            foreach ($stmt->fetchAll() as $old) {
                delete_upload('requests', $old['stored_name']);
                db()->prepare('DELETE FROM request_documents WHERE id = ?')->execute([$old['id']]);
            }
            db()->prepare(
                'INSERT INTO request_documents (request_id, doc_type, original_name, stored_name, mime_type, file_size, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$request['id'], $type, $saved['original_name'], $saved['stored_name'],
                        $saved['mime_type'], $saved['file_size'], $user['id']]);
        }
        if ($new_files) {
            db()->prepare('UPDATE assistance_requests SET documents_consent_at = NOW() WHERE id = ?')
                ->execute([$request['id']]);
        }

        $uploaded = array_map(fn($type) => DOC_TYPES[$type]['label'], array_keys($new_files));
        $note = 'Updated by beneficiary' . ($uploaded ? ' (new: ' . implode(', ', $uploaded) . ')' : '')
              . ($message !== '' ? ': ' . $message : '.');
        $error = change_request_status($request, 'submitted', $note);

        set_flash($error === '' ? 'success' : 'danger',
                  $error === '' ? 'Thank you! Your request was sent back for review.' : $error);
        redirect('beneficiary/request_view.php?id=' . $request['id']);
    }
}

// ---------------------------------------------------------------------
// 3. Load the details (and the list of all my requests for the left side)
// ---------------------------------------------------------------------
$documents   = load_request_documents((int) $request['id']);
$history     = load_request_history((int) $request['id']);
$my_requests = load_my_requests((int) $user['id']);
$selected_id = (int) $request['id'];

// ---------------------------------------------------------------------
// 4. Show the page (prototype "My requests track")
// ---------------------------------------------------------------------
$page_title = 'Request ' . $request['reference_no'];
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 page-head">
    <div>
        <a href="<?= e(url('beneficiary/dashboard.php')) ?>" class="small d-lg-none">← My requests</a>
        <h1>My requests</h1>
        <div class="subtitle">Subaybayan ang request</div>
    </div>
    <a href="<?= e(url('beneficiary/request_new.php')) ?>" class="btn btn-primary btn-lg d-none d-md-inline-flex">+ New medicine request</a>
</div>

<div class="row g-4">
    <!-- Desktop: all my requests, this one highlighted -->
    <div class="col-12 col-lg-4 d-none d-lg-block">
        <?php require __DIR__ . '/../includes/my_requests_list.php'; ?>
    </div>
    <div class="col-12 col-lg-8">
        <?php require __DIR__ . '/../includes/my_request_detail.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
