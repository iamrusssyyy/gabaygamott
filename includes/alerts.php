<?php
// =====================================================================
// includes/alerts.php — AUTOMATED ALERTS (Chapter 1, Objective 3).
//
// run_alert_checks() looks for:
//   1. NEAR-EXPIRY donations: approved, still have units left, and expire
//      within NEAR_EXPIRY_DAYS days -> alert all admins and the donor.
//   2. LOW-STOCK items: a pharmacy's medicine at or below its low-stock
//      threshold -> alert that pharmacy.
//
// Each alert is sent only ONCE: we save the time it was sent
// (expiry_alert_sent_at / low_stock_alert_sent_at). When a pharmacy
// restocks above the threshold, pharmacy/stock.php clears the time, so a
// new alert is sent if the item runs low again.
//
// We don't need a scheduled task (cron job): the check runs every time
// someone logs in and every time the admin opens the dashboard.
// =====================================================================

function run_alert_checks(): void
{
    $pdo = db();

    // ---- 1. Near-expiry donations ----
    // LEFT JOIN + SUM gives how many units were already routed to requests.
    $stmt = $pdo->prepare(
        "SELECT d.id, d.donor_id, d.expiry_date, d.quantity,
                m.generic_name, m.brand_name, m.strength, m.dosage_form,
                d.quantity - COALESCE(SUM(a.quantity), 0) AS remaining
         FROM donations d
         JOIN medicines m ON m.id = d.medicine_id
         LEFT JOIN donation_allocations a ON a.donation_id = d.id
         WHERE d.status = 'approved'
           AND d.expiry_alert_sent_at IS NULL
           AND d.expiry_date <= CURDATE() + INTERVAL ? DAY
         GROUP BY d.id
         HAVING remaining > 0"
    );
    $stmt->execute([NEAR_EXPIRY_DAYS]);

    foreach ($stmt->fetchAll() as $donation) {
        $days_left = (int) floor((strtotime($donation['expiry_date']) - strtotime('today')) / 86400);
        $when = $days_left > 0 ? "expires in $days_left days" : 'has expired';
        $message = 'Near expiry: Donation #' . $donation['id'] . ' (' . medicine_name($donation) . ', '
                 . $donation['remaining'] . ' units left) ' . $when . '.';

        notify_admins($message, 'admin/donation_view.php?id=' . $donation['id']);
        notify((int) $donation['donor_id'], $message, 'donor/donation_view.php?id=' . $donation['id']);

        $pdo->prepare('UPDATE donations SET expiry_alert_sent_at = NOW() WHERE id = ?')
            ->execute([$donation['id']]);
    }

    // ---- 2. Low-stock pharmacy items ----
    $stmt = $pdo->query(
        "SELECT s.id, s.quantity, p.user_id,
                m.generic_name, m.brand_name, m.strength, m.dosage_form
         FROM pharmacy_stock s
         JOIN pharmacies p ON p.id = s.pharmacy_id
         JOIN users u ON u.id = p.user_id AND u.status = 'active'
         JOIN medicines m ON m.id = s.medicine_id
         WHERE s.quantity <= s.low_stock_threshold
           AND s.low_stock_alert_sent_at IS NULL"
    );

    foreach ($stmt->fetchAll() as $item) {
        $what = (int) $item['quantity'] === 0 ? 'Out of stock' : 'Low stock';
        notify(
            (int) $item['user_id'],
            $what . ': ' . medicine_name($item) . ' (' . $item['quantity'] . ' left). Please restock or update your stock.',
            'pharmacy/stock.php'
        );
        $pdo->prepare('UPDATE pharmacy_stock SET low_stock_alert_sent_at = NOW() WHERE id = ?')
            ->execute([$item['id']]);
    }
}
