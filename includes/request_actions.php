<?php
// =====================================================================
// includes/request_actions.php — the action panel on the admin and
// partner request pages (prototype "Admin verify request" and
// "Partner matched request").
//
// It is ONE form with several submit buttons. Each button sends
// name="action" with its own value (verify, match, ready, ...), and
// process_request_action() in includes/requests.php does the rest.
// Only actions that are BOTH allowed for this role AND allowed from the
// current status are shown.
//
// Before including, the page must set:
//   $request          from load_request()
//   $allowed_actions  e.g. ['ready', 'claimed', 'action_needed']
//   $action_url       where the form is sent
// =====================================================================

$next_statuses = REQUEST_TRANSITIONS[$request['status']];
$can = function (string $action) use ($allowed_actions, $next_statuses): bool {
    return in_array($action, $allowed_actions, true)
        && in_array(REQUEST_ACTIONS[$action], $next_statuses, true);
};
$any_action = array_filter(array_keys(REQUEST_ACTIONS), $can);
$is_partner = !in_array('verify', $allowed_actions, true);
?>
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <span class="mono text-muted"><?= e($request['reference_no']) ?></span>
            <?= request_badge($request['status']) ?>
        </div>
        <h2 class="h5 mt-2 mb-3"><?= e(medicine_name($request)) ?> · <?= (int) $request['quantity_per_month'] ?>/month</h2>

        <?php if (!$any_action): ?>
            <p class="text-muted mb-0">
                <?= in_array($request['status'], ['claimed', 'rejected'], true)
                    ? 'This request is finished. No more actions.'
                    : ($request['status'] === 'action_needed'
                        ? 'Waiting for the beneficiary to update the request.'
                        : 'Nothing for you to do at this stage.') ?>
            </p>
        <?php else: ?>
            <form method="post" action="<?= e($action_url) ?>">
                <?= csrf_field() ?>

                <?php if ($can('match')): ?>
                    <div class="mb-3">
                        <label class="form-label" for="partner_program_id">Match to program</label>
                        <select id="partner_program_id" name="partner_program_id" class="form-select">
                            <option value="">No program — donated medicine only</option>
                            <?php foreach (active_programs() as $program): ?>
                                <option value="<?= (int) $program['id'] ?>" <?= (int) $request['partner_program_id'] === (int) $program['id'] ? 'selected' : '' ?>>
                                    <?= e($program['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($can('ready')): ?>
                    <div class="mb-3">
                        <label class="form-label" for="claim_pharmacy_id">Where will the patient claim it? <span class="req">*</span></label>
                        <select id="claim_pharmacy_id" name="claim_pharmacy_id" class="form-select">
                            <option value="">Choose a pharmacy…</option>
                            <?php foreach (claim_pharmacies($request['patient_city']) as $pharmacy): ?>
                                <option value="<?= (int) $pharmacy['id'] ?>">
                                    <?= e($pharmacy['name']) ?><?= $pharmacy['branch'] ? ' · ' . e($pharmacy['branch']) : '' ?> (<?= e($pharmacy['city']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="note">
                        <?= $can('ready') ? 'Claim instructions / note to beneficiary' : 'Note to beneficiary' ?>
                    </label>
                    <textarea id="note" name="note" maxlength="255" rows="3" class="form-control"
                              placeholder="<?= $can('ready') ? 'e.g. Claim Mon–Sat 8 AM–5 PM. Bring a valid ID.' : 'Needed for Action needed and Reject' ?>"></textarea>
                    <div class="form-text">Required for “Action needed” and “Reject”; optional otherwise.</div>
                </div>

                <!-- Main action buttons -->
                <div class="d-grid gap-2">
                    <?php if ($can('verify')): ?>
                        <button type="submit" name="action" value="verify" class="btn btn-primary btn-lg">Mark verified</button>
                    <?php endif; ?>
                    <?php if ($can('match')): ?>
                        <button type="submit" name="action" value="match" class="btn btn-primary btn-lg">Mark matched</button>
                    <?php endif; ?>
                    <?php if ($can('ready')): ?>
                        <button type="submit" name="action" value="ready" class="btn btn-primary btn-lg">Mark ready to claim</button>
                    <?php endif; ?>
                    <?php if ($can('claimed')): ?>
                        <button type="submit" name="action" value="claimed" class="btn btn-primary btn-lg">Mark claimed</button>
                    <?php endif; ?>
                </div>

                <!-- "Problem" buttons -->
                <?php if ($can('action_needed') || $can('reject')): ?>
                    <div class="d-flex gap-2 mt-2">
                        <?php if ($can('action_needed')): ?>
                            <button type="submit" name="action" value="action_needed" class="btn btn-outline-amber flex-fill">
                                <?= $is_partner ? 'Ask beneficiary to fix something' : 'Action needed' ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($can('reject')): ?>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger flex-fill"
                                    onclick="return confirm('Reject this request? The beneficiary will see your note.');">Reject</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>
