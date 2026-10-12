<?php
// =====================================================================
// includes/request_details.php — the details of one request, as a column
// of cards: patient, medicine, documents, status history.
// Shared by the partner and admin request pages (the action panel sits
// in the column next to it).
//
// Before including, the page must set:
//   $request    from load_request()
//   $documents  from load_request_documents()
//   $history    from load_request_history()
// =====================================================================
?>
<!-- Patient and medicine -->
<div class="card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Patient and medicine</h2>
        <dl class="row mb-0">
            <dt class="col-sm-4 fw-normal text-muted">Patient</dt>
            <dd class="col-sm-8 fw-semibold">
                <?= e($request['patient_name']) ?>, <?= age_from_birthdate($request['patient_birthdate']) ?>
                <span class="fw-normal text-muted">(<?= e(ucfirst($request['patient_sex'])) ?>, born <?= e(format_date($request['patient_birthdate'])) ?>)</span>
            </dd>
            <dt class="col-sm-4 fw-normal text-muted">Submitted by</dt>
            <dd class="col-sm-8">
                <?= e($request['beneficiary_name']) ?>
                <?php if ($request['relationship'] === 'self'): ?>
                    (the patient)
                <?php else: ?>
                    <!-- e.g. "My parent" -> "the patient is their parent" -->
                    (the patient is their <?= e(strtolower(str_replace('My ', '', RELATIONSHIPS[$request['relationship']] ?? ''))) ?>)
                <?php endif; ?>
            </dd>
            <dt class="col-sm-4 fw-normal text-muted">Address</dt>
            <dd class="col-sm-8"><?= e($request['patient_address']) ?>, <?= e($request['patient_city']) ?></dd>
            <?php if ($request['patient_phone']): ?>
                <dt class="col-sm-4 fw-normal text-muted">Contact</dt>
                <dd class="col-sm-8"><?= e($request['patient_phone']) ?></dd>
            <?php endif; ?>
            <?php if ($request['diagnosis']): ?>
                <dt class="col-sm-4 fw-normal text-muted">Condition</dt>
                <dd class="col-sm-8"><?= e($request['diagnosis']) ?></dd>
            <?php endif; ?>
            <dt class="col-sm-4 fw-normal text-muted">Medicine</dt>
            <dd class="col-sm-8 fw-semibold"><?= e(medicine_name($request)) ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">Quantity</dt>
            <dd class="col-sm-8"><?= (int) $request['quantity_per_month'] ?> <?= e($request['unit']) ?>s per month ·
                <?= e(DURATIONS[$request['duration']] ?? $request['duration']) ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">Medicine left</dt>
            <dd class="col-sm-8"><?= urgency_badge($request['supply_left']) ?></dd>
            <dt class="col-sm-4 fw-normal text-muted">Prescribing doctor</dt>
            <dd class="col-sm-8"><?= e($request['prescribing_doctor']) ?></dd>
            <?php if ($request['program_name']): ?>
                <dt class="col-sm-4 fw-normal text-muted">Program</dt>
                <dd class="col-sm-8"><?= e($request['program_name']) ?></dd>
            <?php endif; ?>
            <?php if ((int) $request['allocated_units'] > 0): ?>
                <dt class="col-sm-4 fw-normal text-muted">Donated units</dt>
                <dd class="col-sm-8"><?= (int) $request['allocated_units'] ?> <?= e($request['unit']) ?>s routed</dd>
            <?php endif; ?>
            <?php if ($request['claim_pharmacy_name']): ?>
                <dt class="col-sm-4 fw-normal text-muted">Claim at</dt>
                <dd class="col-sm-8"><?= e($request['claim_pharmacy_name']) ?><?= $request['claim_branch'] ? ' · ' . e($request['claim_branch']) : '' ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<!-- Documents (opened through files/view.php, which checks permission) -->
<div class="card mb-3">
    <div class="card-body p-4">
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

<!-- Status history (shared timeline; also shows who made each change) -->
<div class="card mb-3">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Status history</h2>
        <?php $show_changed_by = true; require __DIR__ . '/status_history.php'; ?>
    </div>
</div>
