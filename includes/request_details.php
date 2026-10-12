<?php
// =====================================================================
// includes/request_details.php — the details of one request: patient,
// medicine, documents, and status timeline.
// Shared by the beneficiary, partner, and admin request pages.
//
// Before including, the page must set:
//   $request    from load_request()
//   $documents  from load_request_documents()
//   $history    from load_request_history()
// =====================================================================
?>
<div class="row g-4">
    <div class="col-12 col-lg-7">

        <!-- Medicine -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Medicine needed</h2>
                <div class="fs-5 fw-semibold"><?= e(medicine_name($request)) ?></div>
                <dl class="row small mb-0 mt-2">
                    <dt class="col-5">Quantity per month</dt>
                    <dd class="col-7"><?= (int) $request['quantity_per_month'] ?> <?= e($request['unit']) ?>s</dd>
                    <dt class="col-5">How long</dt>
                    <dd class="col-7"><?= e(DURATIONS[$request['duration']] ?? $request['duration']) ?></dd>
                    <dt class="col-5">Medicine left</dt>
                    <dd class="col-7"><?= urgency_badge($request['supply_left']) ?></dd>
                    <dt class="col-5">Prescribing doctor</dt>
                    <dd class="col-7"><?= e($request['prescribing_doctor']) ?></dd>
                    <?php if ($request['program_name']): ?>
                        <dt class="col-5">Assistance program</dt>
                        <dd class="col-7"><?= e($request['program_name']) ?></dd>
                    <?php endif; ?>
                    <?php if ((int) $request['allocated_units'] > 0): ?>
                        <dt class="col-5">Donated medicine set aside</dt>
                        <dd class="col-7"><?= (int) $request['allocated_units'] ?> <?= e($request['unit']) ?>s</dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Patient -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Patient</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Name</dt>
                    <dd class="col-7"><?= e($request['patient_name']) ?></dd>
                    <dt class="col-5">Relationship</dt>
                    <dd class="col-7"><?= e(RELATIONSHIPS[$request['relationship']] ?? $request['relationship']) ?></dd>
                    <dt class="col-5">Age / sex</dt>
                    <dd class="col-7">
                        <?= age_from_birthdate($request['patient_birthdate']) ?> years old
                        (<?= e(format_date($request['patient_birthdate'])) ?>) · <?= e(ucfirst($request['patient_sex'])) ?>
                    </dd>
                    <dt class="col-5">Address</dt>
                    <dd class="col-7"><?= e($request['patient_address']) ?>, <?= e($request['patient_city']) ?></dd>
                    <?php if ($request['patient_phone']): ?>
                        <dt class="col-5">Phone</dt>
                        <dd class="col-7"><?= e($request['patient_phone']) ?></dd>
                    <?php endif; ?>
                    <?php if ($request['diagnosis']): ?>
                        <dt class="col-5">Condition</dt>
                        <dd class="col-7"><?= e($request['diagnosis']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Documents (opened through files/view.php, which checks permission) -->
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Documents</h2>
                <?php if (!$documents): ?>
                    <p class="small text-muted mb-0">No documents uploaded.</p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($documents as $document): ?>
                        <a class="btn btn-outline-primary" target="_blank" rel="noopener"
                           href="<?= e(url('files/view.php?type=request_doc&id=' . $document['id'])) ?>"
                           title="<?= e($document['original_name']) ?> · <?= e(number_format($document['file_size'] / 1024, 0)) ?> KB · uploaded <?= e(time_ago($document['uploaded_at'])) ?>">
                            View <?= e(strtolower(DOC_TYPES[$document['doc_type']]['label'] ?? 'document')) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($documents): ?>
                    <p class="small text-muted mt-2 mb-0">Private files · opened only after checking who you are.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status history (shared timeline; also shows who made each change) -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-3">Status history</h2>
                <?php $show_changed_by = true; require __DIR__ . '/status_history.php'; ?>
            </div>
        </div>
    </div>
</div>
