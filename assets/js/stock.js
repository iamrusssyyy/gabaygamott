// =====================================================================
// assets/js/stock.js — "Search your items" on pharmacy/stock.php.
// Hides the table rows whose medicine name doesn't contain what was
// typed. (Nothing is sent to the server; it only filters what is shown.)
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('item-search');
    const rows = document.querySelectorAll('#stock-table tbody tr');
    if (!box) {
        return;
    }

    box.addEventListener('input', function () {
        const typed = box.value.trim().toLowerCase();
        rows.forEach(function (row) {
            // Each row has data-name="losartan potassium 50 mg tablet"
            row.hidden = typed !== '' && !row.dataset.name.includes(typed);
        });
    });
});
