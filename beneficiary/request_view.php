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
// 3. Load the details
// ---------------------------------------------------------------------
$documents = load_request_documents((int) $request['id']);
$history   = load_request_history((int) $request['id']);
$status    = REQUEST_STATUSES[$request['status']];

// ---------------------------------------------------------------------
// 4. Show the page
// ---------------------------------------------------------------------
$page_title = 'Request ' . $request['reference_no'];
require __DIR__ . '/../includes/header.php';
?>

<a href="<?= e(url('beneficiary/dashboard.php')) ?>" class="small">← My requests</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <div>
        <h1 class="h3 mb-0"><?= e($request['reference_no']) ?></h1>
        <div class="text-muted">Submitted <?= e(format_datetime($request['submitted_at'])) ?></div>
    </div>
    <div class="fs-5"><?= request_badge($request['status']) ?></div>
</div>

<!-- What this status means and what to do -->
<div class="alert alert-<?= e($status['color'] === 'secondary' || $status['color'] === 'dark' ? 'light border' : $status['color']) ?>">
    <div><?= e($status['help']) ?></div>
    <?php if ($request['status_note']): ?>
        <div class="mt-2"><strong>Note:</strong> <?= e($request['status_note']) ?></div>
    <?php endif; ?>
</div>

<?php if ($request['claim_pharmacy_name'] && in_array($request['status'], ['ready_to_claim', 'claimed'], true)): ?>
    <!-- Where to claim -->
    <div class="card border-success mb-3">
        <div class="card-body">
            <h2 class="h6 text-success">Where to claim your medicine</h2>
            <div class="fw-semibold"><?= e($request['claim_pharmacy_name']) ?><?= $request['claim_branch'] ? ' · ' . e($request['claim_branch']) : '' ?></div>
            <div class="small"><?= e($request['claim_address']) ?>, <?= e($request['claim_city']) ?></div>
            <div class="small text-muted"><?= e($request['claim_hours'] ?? '') ?></div>
            <div class="d-flex gap-2 mt-2">
                <?php if ($request['claim_phone']): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $request['claim_phone'])) ?>">Call <?= e($request['claim_phone']) ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                   href="<?= e(directions_url($request['claim_lat'], $request['claim_lng'])) ?>">Directions</a>
            </div>
            <p class="small mt-2 mb-0">Bring a valid ID and your reference number <strong><?= e($request['reference_no']) ?></strong>.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($request['status'] === 'action_needed'): ?>
    <!-- Fix and send back -->
    <div class="card border-warning mb-4">
        <div class="card-body">
            <h2 class="h5">Update your request</h2>
            <form method="post" action="<?= e(url('beneficiary/request_view.php?id=' . $request['id'])) ?>"
                  enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <p class="small text-muted">Upload only the documents you need to replace (JPG, PNG, or PDF, up to 5 MB).</p>
                <div class="row g-3">
                    <?php foreach (DOC_TYPES as $type => $info): ?>
                        <div class="col-12 col-md-4">
                            <label for="<?= e($type) ?>" class="form-label small fw-semibold"><?= e($info['label']) ?></label>
                            <input type="file" id="<?= e($type) ?>" name="<?= e($type) ?>"
                                   accept="image/jpeg,image/png,application/pdf"
                                   class="form-control<?= error_class($errors, $type) ?>">
                            <?= error_text($errors, $type) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <label for="message" class="form-label small fw-semibold">Message for the admin <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" id="message" name="message" maxlength="200"
                               class="form-control<?= error_class($errors, 'message') ?>" value="<?= e(post('message')) ?>">
                        <?= error_text($errors, 'message') ?>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input<?= error_class($errors, 'documents_consent') ?>" type="checkbox"
                                   id="documents_consent" name="documents_consent" value="1">
                            <label class="form-check-label small" for="documents_consent">
                                I agree that GabayGamot may store these documents and share them only with its administrators
                                and the matched partner program, under RA 10173.
                            </label>
                            <?= error_text($errors, 'documents_consent') ?>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Send back for review</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/request_details.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
