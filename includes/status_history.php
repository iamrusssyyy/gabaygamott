<?php
// =====================================================================
// includes/status_history.php — the "Status history" timeline: circles
// joined by a line, like the prototype.
//   * steps that already happened: green circle with a check
//     (amber "!" for Action needed, red "x" for Not approved)
//   * steps still to come: empty gray circle, "Waiting"
//
// Before including, the page must set $request and $history
// (from load_request() and load_request_history()).
// =====================================================================

// The normal path of a request, in order
$normal_path = ['submitted', 'verified', 'matched', 'ready_to_claim', 'claimed'];

// How far along the normal path this request has reached
$furthest = -1;
foreach ($history as $entry) {
    $position = array_search($entry['status'], $normal_path, true);
    if ($position !== false && $position > $furthest) {
        $furthest = $position;
    }
}

// Steps still to come (none if the request is finished)
$upcoming = [];
if (!in_array($request['status'], ['claimed', 'rejected'], true)) {
    $upcoming = array_slice($normal_path, $furthest + 1);
}
$last_index = count($history) - 1;
?>
<ol class="stepper">
    <?php foreach ($history as $i => $entry): ?>
        <?php
        $mark_class = '';
        $icon = 'bi-check-lg';
        if ($entry['status'] === 'action_needed') {
            $mark_class = 'mark-warning';
            $icon = 'bi-exclamation-lg';
        } elseif ($entry['status'] === 'rejected') {
            $mark_class = 'mark-danger';
            $icon = 'bi-x-lg';
        }
        // The line after the last done step is gray if more steps are coming
        $li_class = $i === $last_index ? 'current' . ($upcoming ? ' next-upcoming' : '') : '';
        ?>
        <li class="<?= $li_class ?>">
            <span class="mark <?= $mark_class ?>"><i class="bi <?= $icon ?>"></i></span>
            <div>
                <div class="step-name"><?= e(REQUEST_STATUSES[$entry['status']]['label'] ?? $entry['status']) ?></div>
                <div class="small text-muted">
                    <?= e(date('M j, g:i A', strtotime($entry['changed_at']))) ?>
                    <?php if ($entry['note']): ?> · <?= e($entry['note']) ?><?php endif; ?>
                </div>
                <?php if (!empty($show_changed_by) && $entry['changed_by_role'] && $entry['changed_by_role'] !== 'beneficiary'): ?>
                    <div class="small text-muted">by <?= e($entry['changed_by_role'] === 'admin' ? 'GabayGamot admin' : 'partner program') ?></div>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    <?php foreach ($upcoming as $status): ?>
        <li class="upcoming">
            <span class="mark"><i class="bi bi-circle-fill" style="font-size: 0.5rem"></i></span>
            <div>
                <div class="step-name"><?= e(REQUEST_STATUSES[$status]['label']) ?></div>
                <div class="small text-muted">Waiting</div>
            </div>
        </li>
    <?php endforeach; ?>
</ol>
