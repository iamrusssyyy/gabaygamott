<?php
// =====================================================================
// includes/my_requests_list.php — the beneficiary's requests as cards
// (left column of "My requests", like the prototype).
//
// Before including, the page must set:
//   $my_requests   rows from load_my_requests()
//   $selected_id   id of the request shown on the right (0 = none)
// =====================================================================
?>
<?php foreach ($my_requests as $item): ?>
    <a class="request-item<?= (int) $item['id'] === $selected_id ? ' selected' : '' ?>"
       href="<?= e(url('beneficiary/request_view.php?id=' . $item['id'])) ?>">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <span class="mono small"><?= e($item['reference_no']) ?></span>
            <?= request_badge($item['status']) ?>
        </div>
        <div class="fw-semibold"><?= e(medicine_name($item)) ?> · <?= (int) $item['quantity_per_month'] ?>/month</div>
        <?php if ($item['status'] === 'action_needed' && $item['status_note']): ?>
            <div class="small" style="color: var(--gg-amber-text)"><?= e($item['status_note']) ?></div>
        <?php else: ?>
            <div class="small text-muted">
                For <?= e($item['patient_name']) ?>
                <?= $item['program_name'] ? ' · ' . e($item['program_name']) : '' ?>
            </div>
        <?php endif; ?>
    </a>
<?php endforeach; ?>
