// =====================================================================
// assets/js/admin-charts.js — draws the two charts on admin/dashboard.php
// with Chart.js. The numbers come from the JSON block the PHP page wrote.
//
// Colors: blue and orange were checked for color-blind safety and contrast
// (a validator script, not guesswork). Each chart also has a
// "Show as table" view on the page, so color is never the only way to read it.
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {
    const dataBlock = document.getElementById('chart-data');
    if (!dataBlock || typeof Chart === 'undefined') {
        return;
    }
    const data = JSON.parse(dataBlock.textContent);

    const BLUE   = '#2a78d6';
    const ORANGE = '#eb6834';
    const GRID   = '#e1e0d9';   // light gridlines so the bars stand out
    const INK    = '#52514e';   // axis and legend text

    // Settings shared by both charts
    Chart.defaults.font.family = "'IBM Plex Sans', system-ui, sans-serif";
    Chart.defaults.color = INK;
    Chart.defaults.maintainAspectRatio = false;   // the .chart-box div sets the height

    // 1. Donation flow: two bars per month (grouped)
    new Chart(document.getElementById('donation-flow-chart'), {
        type: 'bar',
        data: {
            labels: data.months,
            datasets: [
                { label: 'Units approved', data: data.approved, backgroundColor: BLUE,
                  borderRadius: 4, maxBarThickness: 28 },
                { label: 'Units routed to patients', data: data.routed, backgroundColor: ORANGE,
                  borderRadius: 4, maxBarThickness: 28 }
            ]
        },
        options: {
            plugins: {
                legend: { position: 'top', align: 'start', labels: { boxWidth: 12, boxHeight: 12 } },
                tooltip: { mode: 'index', intersect: false }   // hover a month to see both numbers
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: GRID }, ticks: { precision: 0 } }
            }
        }
    });

    // 2. Requests by status: one horizontal bar per status (one series, one color)
    new Chart(document.getElementById('request-status-chart'), {
        type: 'bar',
        data: {
            labels: data.statuses,
            datasets: [
                { label: 'Requests', data: data.status_counts, backgroundColor: BLUE,
                  borderRadius: 4, maxBarThickness: 22 }
            ]
        },
        options: {
            indexAxis: 'y',   // makes the bars horizontal
            plugins: { legend: { display: false } },   // one series: the title names it
            scales: {
                x: { beginAtZero: true, grid: { color: GRID }, ticks: { precision: 0 } },
                y: { grid: { display: false } }
            }
        }
    });
});
