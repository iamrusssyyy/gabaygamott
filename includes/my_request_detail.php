<?php
// =====================================================================
// includes/my_request_detail.php — one request as the beneficiary sees
// it (right column of "My requests", like the prototype):
//   * title card · "Your medicine is ready" box (or what to do next)
//   * status history · documents · request details
//   * when "Action needed": a form to upload fixes (sent to request_view.php)
//
// Before including, the page must set:
//   $request, $documents, $history  (from load_request() etc.)
//   $errors                         (form errors; [] if none)
// =====================================================================

$status_info = REQUEST_STATUSES[$request['status']];
$has_claim_place = $request['claim_pharmacy_name'] && in_array($request['status'], ['ready_to_claim', 'claimed'], true);
?>
<!-- Title card -->
<div class="card mb-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <span class="mono text-muted"><?= e($request['reference_no']) ?></span>
            <?= request_badge($request['status']) ?>
        </div>
        <h2 class="h4 mb-1"><?= e(medicine_name($request)) ?> · <?= (int) $request['quantity_per_month'] ?>/month</h2>
        <div class="text-muted">
            For <?= e($request['patient_name']) ?>
            <?= $request['program_name'] ? ' · ' . e($request['program_name']) : '' ?>
            · submitted <?= e(format_date($request['submitted_at'])) ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <?php if ($has_claim_place): ?>
            <!-- "Your medicine is ready" -->
            <div class="ready-box h-100">
                <h2 class="h5 mb-3"><?= $request['status'] === 'claimed' ? 'Medicine claimed' : 'Your medicine is ready' ?></h2>
                <dl class="row mb-3">
                    <dt class="col-4 fw-normal text-muted">Where</dt>
                    <dd class="col-8 fw-semibold">
                        <?= e($request['claim_pharmacy_name']) ?><?= $request['claim_branch'] ? ' · ' . e($request['claim_branch']) : '' ?>,
                        <?= e($request['claim_address']) ?>, <?= e($request['claim_city']) ?>
                    </dd>
                    <?php if ($request['claim_hours']): ?>
                        <dt class="col-4 fw-normal text-muted">Hours</dt>
                        <dd class="col-8 fw-semibold"><?= e($request['claim_hours']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-4 fw-normal text-muted">Bring</dt>
                    <dd class="col-8 fw-semibold mb-0">Valid ID and this reference number</dd>
                </dl>
                <?php if ($request['status_note'] && $request['status'] === 'ready_to_claim'): ?>
                    <p class="small mb-3"><?= e($request['status_note']) ?></p>
                <?php endif; ?>
                <div class="counter-code mb-2">
                    <div class="small text-muted">Show at the counter</div>
                    <div class="mono"><?= e($request['reference_no']) ?></div>
                </div>
                <div class="d-flex gap-3 small">
                    <?php if ($request['claim_phone']): ?>
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $request['claim_phone'])) ?>"><i class="bi bi-telephone"></i> Call</a>
                    <?php endif; ?>
                    <a target="_blank" rel="noopener" href="<?= e(directions_url($request['claim_lat'], $request['claim_lng'])) ?>"><i class="bi bi-sign-turn-right"></i> Directions</a>
                </div>
            </div>

        <?php elseif ($request['status'] === 'action_needed'): ?>
            <!-- Fix and send back -->
            <div class="card h-100" style="border-color: #e7c27d">
                <div class="card-body">
                    <h2 class="h5" style="color: var(--gg-amber-text)"><i class="bi bi-exclamation-circle"></i> We need something from you</h2>
                    <?php if ($request['status_note']): ?>
                        <div class="notice notice-amber mb-3"><span><?= e($request['status_note']) ?></span></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('beneficiary/request_view.php?id=' . $request['id'])) ?>"
                          enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <p class="small text-muted">Upload only what you need to replace (JPG, PNG, or PDF, up to 5 MB).</p>
                        <?php foreach (DOC_TYPES as $type => $info): ?>
                            <div class="mb-2">
                                <label for="<?= e($type) ?>" class="form-label small mb-1"><?= e($info['label']) ?></label>
                                <input type="file" id="<?= e($type) ?>" name="<?= e($type) ?>"
                                       accept="image/jpeg,image/png,application/pdf"
                                       class="form-control<?= error_class($errors, $type) ?>">
                                <?= error_text($errors, $type) ?>
                            </div>
                        <?php endforeach; ?>
                        <label for="message" class="form-label small mb-1">Message for the admin <span class="text-muted">(optional)</span></label>
                        <input type="text" id="message" name="message" maxlength="200"
                               class="form-control mb-2<?= error_class($errors, 'message') ?>" value="<?= e(post('message')) ?>">
                        <?= error_text($errors, 'message') ?>
                        <div class="form-check my-2">
                            <input class="form-check-input<?= error_class($errors, 'documents_consent') ?>" type="checkbox"
                                   id="documents_consent" name="documents_consent" value="1">
                            <label class="form-check-label small" for="documents_consent">
                                I agree that GabayGamot may store these documents and share them only with its administrators
                                and the matched partner program, under RA 10173.
                            </label>
                            <?= error_text($errors, 'documents_consent') ?>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send back for review</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- What this status means -->
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5"><?= e($status_info['label']) ?></h2>
                    <p class="mb-2"><?= e($status_info['help']) ?></p>
                    <?php if ($request['status_note']): ?>
                        <div class="notice <?= $request['status'] === 'rejected' ? 'notice-amber' : 'notice-mint' ?>">
                            <span><strong>Note:</strong> <?= e($request['status_note']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ((int) $request['allocated_units'] > 0): ?>
                        <p class="small text-muted mt-2 mb-0"><i class="bi bi-box-seam"></i>
                            <?= (int) $request['allocated_units'] ?> donated <?= e($request['unit']) ?>s set aside for you.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Status history</h2>
                <?php require __DIR__ . '/status_history.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Documents -->
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0">Your documents</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($documents as $document): ?>
                    <a class="btn btn-outline-primary" target="_blank" rel="noopener"
                       href="<?= e(url('files/view.php?type=request_doc&id=' . $document['id'])) ?>">
                        View <?= e(strtolower(DOC_TYPES[$document['doc_type']]['label'] ?? 'document')) ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!$documents): ?><span class="small text-muted">No documents uploaded.</span><?php endif; ?>
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0">Only you, GabayGamot admins, and your matched partner program can open these files.</p>
    </div>
</div>

<!-- Request details -->
<div class="card">
    <div class="card-body">
        <h2 class="h5">Request details</h2>
        <dl class="row small mb-0">
            <dt class="col-sm-4 fw-normal text-muted">Patient</dt>
            <dd class="col-sm-8"><?= e($request['patient_name']) ?> (<?= e(RELATIONSHIPS[$request['relationship']] ?? '') ?>),
                <?= age_from_birthdate($request['patient_birthdate']) ?> years old · <?= e($request['patient_city']) ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">How long needed</dt>
            <dd class="col-sm-8"><?= e(DURATIONS[$request['duration']] ?? '') ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">Medicine left</dt>
            <dd class="col-sm-8"><?= e(SUPPLY_LEFT[$request['supply_left']]['label'] ?? '') ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">Prescribing doctor</dt>
            <dd class="col-sm-8 mb-0"><?= e($request['prescribing_doctor']) ?></dd>
        </dl>
    </div>
</div>
