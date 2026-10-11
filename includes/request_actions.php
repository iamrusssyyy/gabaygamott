<?php
// =====================================================================
// includes/request_actions.php — the action buttons on the admin and
// partner request pages. Only actions that are BOTH allowed for this
// role AND allowed from the current status are shown.
//
// Before including, the page must set:
//   $request          from load_request()
//   $allowed_actions  e.g. ['ready', 'claimed', 'action_needed']
//   $action_url       where the forms are sent
// =====================================================================

$next_statuses = REQUEST_TRANSITIONS[$request['status']];
$can = function (string $action) use ($allowed_actions, $next_statuses): bool {
    return in_array($action, $allowed_actions, true)
        && in_array(REQUEST_ACTIONS[$action], $next_statuses, true);
};
?>
<div class="card border-primary mb-4">
    <div class="card-body">
        <h2 class="h5">What to do next</h2>

        <?php if (!array_filter(array_keys(REQUEST_ACTIONS), $can)): ?>
            <p class="text-muted mb-0">
                <?= in_array($request['status'], ['claimed', 'rejected'], true)
                    ? 'This request is finished. No more actions.'
                    : ($request['status'] === 'action_needed'
                        ? 'Waiting for the beneficiary to update the request.'
                        : 'Nothing for you to do at this stage.') ?>
            </p>
        <?php endif; ?>

        <?php if ($can('verify')): ?>
            <form method="post" action="<?= e($action_url) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <label class="form-label small fw-semibold" for="note-verify">Documents are complete and valid</label>
                <div class="d-flex gap-2">
                    <input type="text" id="note-verify" name="note" maxlength="255" class="form-control" placeholder="Note (optional)">
                    <button type="submit" class="btn btn-primary text-nowrap">Mark verified</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($can('match')): ?>
            <form method="post" action="<?= e($action_url) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="match">
                <label class="form-label small fw-semibold" for="partner_program_id">Match with an assistance program</label>
                <select id="partner_program_id" name="partner_program_id" class="form-select mb-2">
                    <option value="">No program — donated medicine only</option>
                    <?php foreach (active_programs() as $program): ?>
                        <option value="<?= (int) $program['id'] ?>" <?= (int) $request['partner_program_id'] === (int) $program['id'] ? 'selected' : '' ?>>
                            <?= e($program['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2">
                    <input type="text" name="note" maxlength="255" class="form-control" placeholder="Note for the beneficiary (optional)" aria-label="Note">
                    <button type="submit" class="btn btn-primary text-nowrap">Mark matched</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($can('ready')): ?>
            <form method="post" action="<?= e($action_url) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="ready">
                <label class="form-label small fw-semibold" for="claim_pharmacy_id">Medicine is ready — where will the patient claim it?</label>
                <select id="claim_pharmacy_id" name="claim_pharmacy_id" class="form-select mb-2">
                    <option value="">Choose a pharmacy…</option>
                    <?php foreach (claim_pharmacies($request['patient_city']) as $pharmacy): ?>
                        <option value="<?= (int) $pharmacy['id'] ?>">
                            <?= e($pharmacy['name']) ?><?= $pharmacy['branch'] ? ' · ' . e($pharmacy['branch']) : '' ?> (<?= e($pharmacy['city']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2">
                    <input type="text" name="note" maxlength="255" class="form-control" placeholder="Claim instructions (optional)" aria-label="Claim instructions">
                    <button type="submit" class="btn btn-success text-nowrap">Ready to claim</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($can('claimed')): ?>
            <form method="post" action="<?= e($action_url) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="claimed">
                <label class="form-label small fw-semibold" for="note-claimed">The patient received the medicine</label>
                <div class="d-flex gap-2">
                    <input type="text" id="note-claimed" name="note" maxlength="255" class="form-control" placeholder="Note (optional)">
                    <button type="submit" class="btn btn-dark text-nowrap">Mark claimed</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($can('action_needed')): ?>
            <form method="post" action="<?= e($action_url) ?>" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="action_needed">
                <label class="form-label small fw-semibold" for="note-action">Ask the beneficiary to fix something</label>
                <div class="d-flex gap-2">
                    <input type="text" id="note-action" name="note" maxlength="255" class="form-control"
                           placeholder="e.g. Please upload a clearer certificate of indigency.">
                    <button type="submit" class="btn btn-warning text-nowrap">Action needed</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($can('reject')): ?>
            <form method="post" action="<?= e($action_url) ?>"
                  onsubmit="return confirm('Reject this request? The beneficiary will see your reason.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <label class="form-label small fw-semibold" for="note-reject">Not approved</label>
                <div class="d-flex gap-2">
                    <input type="text" id="note-reject" name="note" maxlength="255" class="form-control" placeholder="Reason (required)">
                    <button type="submit" class="btn btn-outline-danger text-nowrap">Reject</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
