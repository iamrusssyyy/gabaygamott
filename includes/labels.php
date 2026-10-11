<?php
// =====================================================================
// includes/labels.php — friendly names for the short codes stored in
// the database. Example: the database stores 'ready_to_claim', but the
// user sees a green "Ready to claim" badge.
//
// 'color' is a Bootstrap color name used for badges:
//   secondary = gray, info = light blue, primary = green (our theme),
//   success = green, warning = yellow, danger = red, dark = black
// =====================================================================

// Statuses of an assistance request, in the order they normally happen.
const REQUEST_STATUSES = [
    'submitted' => [
        'label' => 'Submitted',
        'color' => 'secondary',
        'help'  => 'We received your request. An admin will check your documents (usually 1–2 working days).',
    ],
    'verified' => [
        'label' => 'Verified',
        'color' => 'info',
        'help'  => 'Your documents are complete. We are now matching you with an assistance program or donated medicine.',
    ],
    'matched' => [
        'label' => 'Matched',
        'color' => 'primary',
        'help'  => 'Your request was matched with an assistance program and/or donated medicine. We will tell you where to claim it.',
    ],
    'ready_to_claim' => [
        'label' => 'Ready to claim',
        'color' => 'success',
        'help'  => 'Your medicine is ready. See where to claim it below. Bring a valid ID.',
    ],
    'claimed' => [
        'label' => 'Claimed',
        'color' => 'dark',
        'help'  => 'You have received your medicine. Salamat!',
    ],
    'action_needed' => [
        'label' => 'Action needed',
        'color' => 'warning',
        'help'  => 'We need something from you. Please read the note and update your request.',
    ],
    'rejected' => [
        'label' => 'Not approved',
        'color' => 'danger',
        'help'  => 'Your request could not be approved. Please read the reason below.',
    ],
];

// Statuses of a donation.
const DONATION_STATUSES = [
    'pending'  => ['label' => 'Waiting for review', 'color' => 'warning'],
    'approved' => ['label' => 'Approved',           'color' => 'success'],
    'rejected' => ['label' => 'Not accepted',       'color' => 'danger'],
];

// Statuses of a user account.
const ACCOUNT_STATUSES = [
    'pending'  => ['label' => 'Waiting for approval', 'color' => 'warning'],
    'active'   => ['label' => 'Active',               'color' => 'success'],
    'rejected' => ['label' => 'Rejected',             'color' => 'danger'],
    'disabled' => ['label' => 'Disabled',             'color' => 'secondary'],
];

// Wizard step 1: who the patient is to the person filling in the form.
const RELATIONSHIPS = [
    'self'    => 'Myself',
    'parent'  => 'My parent',
    'child'   => 'My child',
    'spouse'  => 'My spouse',
    'sibling' => 'My brother or sister',
    'other'   => 'Other relative or friend',
];

// Wizard step 2: how long the medicine is needed.
const DURATIONS = [
    'one_time' => 'One time only',
    '1_month'  => '1 month',
    '3_months' => '3 months',
    '6_months' => '6 months',
    'ongoing'  => 'Ongoing (maintenance)',
];

// Wizard step 2: how much medicine is left. 'rank' sets the priority:
// people who have run out are helped first (rank 1 = most urgent).
const SUPPLY_LEFT = [
    'none'       => ['label' => 'None left',         'rank' => 1],
    'lt_1_week'  => ['label' => 'Less than 1 week',  'rank' => 2],
    '1_4_weeks'  => ['label' => '1 to 4 weeks',      'rank' => 3],
    'gt_1_month' => ['label' => 'More than a month', 'rank' => 4],
];

// Wizard step 3: document types.
const DOC_TYPES = [
    'prescription'          => ['label' => 'Prescription',             'required' => true],
    'indigency_certificate' => ['label' => 'Certificate of indigency', 'required' => true],
    'valid_id'              => ['label' => 'Valid ID',                 'required' => false],
];

/** Colored badge for a request status, e.g. <span class="badge ...">Matched</span> */
function request_badge(string $status): string
{
    $info = REQUEST_STATUSES[$status] ?? ['label' => $status, 'color' => 'secondary'];
    return '<span class="badge text-bg-' . e($info['color']) . '">' . e($info['label']) . '</span>';
}

/** Colored badge for a donation status. */
function donation_badge(string $status): string
{
    $info = DONATION_STATUSES[$status] ?? ['label' => $status, 'color' => 'secondary'];
    return '<span class="badge text-bg-' . e($info['color']) . '">' . e($info['label']) . '</span>';
}

/** Colored badge for an account status. */
function account_badge(string $status): string
{
    $info = ACCOUNT_STATUSES[$status] ?? ['label' => $status, 'color' => 'secondary'];
    return '<span class="badge text-bg-' . e($info['color']) . '">' . e($info['label']) . '</span>';
}

/**
 * Stock status from quantity, as described in database/schema.sql:
 *   0 = out of stock, at or below the threshold = low stock, else in stock.
 * Returns ['code' => 'low', 'label' => 'Low stock', 'color' => 'warning'].
 */
function stock_status(int $quantity, int $threshold): array
{
    if ($quantity === 0) {
        return ['code' => 'out', 'label' => 'Out of stock', 'color' => 'danger'];
    }
    if ($quantity <= $threshold) {
        return ['code' => 'low', 'label' => 'Low stock', 'color' => 'warning'];
    }
    return ['code' => 'in', 'label' => 'In stock', 'color' => 'success'];
}

/** The ● dot + label used in the wireframes, e.g. "● Low stock · 18". */
function stock_dot(int $quantity, int $threshold, bool $show_quantity = true): string
{
    $status = stock_status($quantity, $threshold);
    $text = $status['label'] . ($show_quantity && $quantity > 0 ? ' · ' . $quantity : '');
    return '<span class="stock-dot text-' . e($status['color']) . '">●</span> ' . e($text);
}
