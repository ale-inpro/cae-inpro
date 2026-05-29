(function () {
    function readChartData(el) {
        if (!el) return null;
        try {
            return {
                series: JSON.parse(el.dataset.series || '[]'),
                labels: JSON.parse(el.dataset.labels || '[]')
            };
        } catch (e) {
            return null;
        }
    }

    function emptyState(el, text, icon) {
        el.innerHTML =
            '<div class="rgpd-dash-chart-empty">'
            + '<i class="bi ' + icon + '"></i>'
            + '<p class="mb-0">' + text + '</p>'
            + '</div>';
    }

    function renderDonut(el, colors, emptyText) {
        var data = readChartData(el);
        if (!data) return;

        var total = data.series.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) {
            emptyState(el, emptyText, 'bi-pie-chart');
            return;
        }

        new ApexCharts(el, {
            chart: { type: 'donut', height: 300, fontFamily: 'inherit', toolbar: { show: false } },
            series: data.series,
            labels: data.labels,
            colors: colors,
            legend: { position: 'bottom', fontSize: '13px', fontWeight: 600 },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 700 } },
            stroke: { width: 2 },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: { show: true, label: 'Total', fontWeight: 700 }
                        }
                    }
                }
            }
        }).render();
    }

    function renderArea(el) {
        var data = readChartData(el);
        if (!data) return;

        var total = data.series.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) {
            emptyState(el, 'Sin solicitudes en los últimos 14 días.', 'bi-graph-up');
            return;
        }

        new ApexCharts(el, {
            chart: { type: 'area', height: 280, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
            series: [{ name: 'Solicitudes', data: data.series }],
            xaxis: { categories: data.labels, labels: { style: { fontSize: '12px', fontWeight: 600 } } },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            tooltip: { y: { formatter: function (v) { return v + ' solicitud' + (v === 1 ? '' : 'es'); } } }
        }).render();
    }

    function initRgpdDashboardCharts() {
        if (typeof ApexCharts === 'undefined') {
            window.setTimeout(initRgpdDashboardCharts, 60);
            return;
        }

        renderDonut(
            document.getElementById('rgpdDashSignaturesChart'),
            ['#f59e0b', '#10b981', '#38bdf8', '#94a3b8'],
            'Aún no hay solicitudes de firma a vecinos.'
        );
        renderDonut(
            document.getElementById('rgpdDashContractsChart'),
            ['#10b981', '#ef4444'],
            'No hay comunidades en cartera.'
        );
        renderArea(document.getElementById('rgpdDashActivityChart'));
    }

    if (document.getElementById('rgpdDashSignaturesChart') || document.getElementById('rgpdDashActivityChart')) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRgpdDashboardCharts);
        } else {
            initRgpdDashboardCharts();
        }
    }
})();