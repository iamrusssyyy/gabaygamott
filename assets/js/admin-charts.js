// =====================================================================
// assets/js/admin-charts.js — draws the "Donations received" chart on
// admin/dashboard.php with Chart.js. The numbers come from the JSON
// block the PHP page wrote.
//
// One series, so one color: light green bars, with the current month in
// dark green so it stands out. The value is written above each bar, and
// the page also has a "Show as table" view.
// =====================================================================

document.addEventListener('DOMContentLoaded', function () {
    const dataBlock = document.getElementById('chart-data');
    const canvas = document.getElementById('donations-chart');
    if (!dataBlock || !canvas || typeof Chart === 'undefined') {
        return;
    }
    const data = JSON.parse(dataBlock.textContent);

    const LIGHT = '#9fd0c0';   // past months
    const DARK  = '#137a64';   // current month (the last bar)
    const INK   = '#52514e';   // axis text

    // The last bar is dark, the others light
    const barColors = data.donations.map(function (value, index) {
        return index === data.donations.length - 1 ? DARK : LIGHT;
    });

    // Small Chart.js "plugin": after the bars are drawn, write each value
    // above its bar (like the prototype).
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw: function (chart) {
            const ctx = chart.ctx;
            ctx.save();
            ctx.font = "600 13px 'IBM Plex Sans', system-ui, sans-serif";
            ctx.fillStyle = '#17201d';
            ctx.textAlign = 'center';
            chart.getDatasetMeta(0).data.forEach(function (bar, index) {
                ctx.fillText(data.donations[index], bar.x, bar.y - 6);
            });
            ctx.restore();
        }
    };

    Chart.defaults.font.family = "'IBM Plex Sans', system-ui, sans-serif";
    Chart.defaults.color = INK;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.months,
            datasets: [{
                label: 'Donations received',
                data: data.donations,
                backgroundColor: barColors,
                borderRadius: 4,
                maxBarThickness: 64
            }]
        },
        options: {
            maintainAspectRatio: false,   // the .chart-box div sets the height
            layout: { padding: { top: 22 } },   // room for the numbers on top
            plugins: {
                legend: { display: false }       // one series: the card title names it
            },
            scales: {
                x: { grid: { display: false } },
                y: { display: false, beginAtZero: true }
            }
        },
        plugins: [valueLabels]
    });
});
