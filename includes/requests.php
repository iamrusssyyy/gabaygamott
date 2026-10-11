<?php
// =====================================================================
// includes/requests.php — shared code for assistance requests, used by
// the beneficiary, partner, and admin pages.
// =====================================================================

// Which status a request may move to next. Pages decide WHO may do it
// (admin, partner, or beneficiary); this list decides WHAT is allowed.
const REQUEST_TRANSITIONS = [
    'submitted'      => ['verified', 'action_needed', 'rejected'],
    'action_needed'  => ['submitted', 'verified', 'rejected'],  // 'submitted' = beneficiary sent fixes
    'verified'       => ['matched', 'action_needed', 'rejected'],
    'matched'        => ['ready_to_claim', 'action_needed', 'rejected'],
    'ready_to_claim' => ['claimed', 'action_needed'],
    'claimed'        => [],   // finished
    'rejected'       => [],   // finished
];

/**
 * Loads one request with its medicine, program, claim pharmacy,
 * beneficiary, and how many donated units were routed to it.
 */
function load_request(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*,
                m.generic_name, m.brand_name, m.strength, m.dosage_form, m.unit,
                pp.name AS program_name,
                cp.name AS claim_pharmacy_name, cp.branch AS claim_branch, cp.address AS claim_address,
                cp.city AS claim_city, cp.phone AS claim_phone, cp.opening_hours AS claim_hours,
                cp.latitude AS claim_lat, cp.longitude AS claim_lng,
                u.full_name AS beneficiary_name, u.email AS beneficiary_email, u.phone AS beneficiary_phone,
                (SELECT COALESCE(SUM(a.quantity), 0) FROM donation_allocations a WHERE a.request_id = r.id)
                    AS allocated_units
         FROM assistance_requests r
         JOIN medicines m             ON m.id = r.medicine_id
         JOIN users u                 ON u.id = r.beneficiary_id
         LEFT JOIN partner_programs pp ON pp.id = r.partner_program_id
         LEFT JOIN pharmacies cp       ON cp.id = r.claim_pharmacy_id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** The uploaded documents of a request. */
function load_request_documents(int $request_id): array
{
    $stmt = db()->prepare(
        'SELECT id, doc_type, original_name, mime_type, file_size, uploaded_at
         FROM request_documents WHERE request_id = ? ORDER BY doc_type'
    );
    $stmt->execute([$request_id]);
    return $stmt->fetchAll();
}

/** The status timeline of a request, oldest first. */
function load_request_history(int $request_id): array
{
    $stmt = db()->prepare(
        'SELECT h.status, h.note, h.changed_at, u.full_name AS changed_by_name, u.role AS changed_by_role
         FROM request_status_history h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.request_id = ?
         ORDER BY h.changed_at, h.id'
    );
    $stmt->execute([$request_id]);
    return $stmt->fetchAll();
}

/**
 * Moves a request to a new status, records it in the timeline, and
 * notifies the beneficiary. Returns '' on success or an error message.
 *
 *   $note   shown to the beneficiary (reason, claim instructions, ...)
 *   $extra  optional: ['partner_program_id' => 2] or ['claim_pharmacy_id' => 5]
 */
function change_request_status(array $request, string $new_status, ?string $note, array $extra = []): string
{
    $old_status = $request['status'];
    if (!in_array($new_status, REQUEST_TRANSITIONS[$old_status] ?? [], true)) {
        return 'A request that is "' . REQUEST_STATUSES[$old_status]['label'] . '" cannot be changed to "'
             . (REQUEST_STATUSES[$new_status]['label'] ?? $new_status) . '".';
    }

    $note = ($note !== null && $note !== '') ? mb_substr($note, 0, 255) : null;
    $pdo  = db();
    $pdo->beginTransaction();

    // "AND status = ?" makes sure nobody changed it a moment ago
    // (for example, two admins clicking at the same time).
    // COALESCE(?, column) keeps the old value when we pass null.
    $stmt = $pdo->prepare(
        'UPDATE assistance_requests
         SET status = ?, status_note = ?,
             partner_program_id = COALESCE(?, partner_program_id),
             claim_pharmacy_id  = COALESCE(?, claim_pharmacy_id)
         WHERE id = ? AND status = ?'
    );
    $stmt->execute([
        $new_status,
        $note,
        $extra['partner_program_id'] ?? null,
        $extra['claim_pharmacy_id'] ?? null,
        $request['id'],
        $old_status,
    ]);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        return 'This request was just changed by someone else. Please check it again.';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO request_status_history (request_id, status, note, changed_by) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$request['id'], $new_status, $note, current_user()['id']]);
    $pdo->commit();

    // Tell the right people
    $link = 'beneficiary/request_view.php?id=' . $request['id'];
    if ($new_status === 'submitted') {
        notify_admins('Request ' . $request['reference_no'] . ' was updated by the beneficiary and needs review.',
                      'admin/request_view.php?id=' . $request['id']);
    } else {
        notify((int) $request['beneficiary_id'],
               'Your request ' . $request['reference_no'] . ' is now "' . REQUEST_STATUSES[$new_status]['label'] . '".'
               . ($note ? ' ' . $note : ''),
               $link);
    }
    return '';
}

/** Short urgency label from how much medicine is left, e.g. "None left". */
function urgency_badge(string $supply_left): string
{
    $info  = SUPPLY_LEFT[$supply_left] ?? ['label' => $supply_left, 'rank' => 4];
    $color = $info['rank'] <= 2 ? 'danger' : ($info['rank'] === 3 ? 'warning' : 'secondary');
    return '<span class="badge text-bg-' . $color . '">' . e($info['label']) . '</span>';
}

/** Age in years from a birthdate. */
function age_from_birthdate(string $birthdate): int
{
    return (new DateTime($birthdate))->diff(new DateTime('today'))->y;
}
