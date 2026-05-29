(function () {
    function readData(el) {
        if (!el) {
            return null;
        }
        try {
            return {
                series: JSON.parse(el.dataset.series || '[]'),
                labels: JSON.parse(el.dataset.labels || '[]'),
            };
        } catch (e) {
            return null;
        }
    }

    function emptyState(el, text, icon) {
        el.innerHTML =
            '<div class="cae-dash-chart-empty">'
            + '<i class="bi ' + icon + '"></i>'
            + '<p class="mb-0">' + text + '</p>'
            + '</div>';
    }

    function renderDonut(el, colors) {
        var data = readData(el);
        if (!data) {
            return;
        }

        var total = data.series.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) {
            emptyState(el, 'No hay registros CAE vigentes todavía.', 'bi-pie-chart');
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
                            total: { show: true, label: 'Total CAE', fontWeight: 700 },
                        },
                    },
                },
            },
        }).render();
    }

    function renderHorizontalBar(el, colors) {
        var data = readData(el);
        if (!data) {
            return;
        }

        var pairs = data.labels.map(function (label, i) {
            return { label: label, value: data.series[i] || 0 };
        }).filter(function (p) { return p.value > 0; });

        if (pairs.length === 0) {
            emptyState(el, 'Sin CAE en estados activos para mostrar.', 'bi-bar-chart');
            return;
        }

        new ApexCharts(el, {
            chart: { type: 'bar', height: 300, fontFamily: 'inherit', toolbar: { show: false } },
            series: [{ name: 'CAE', data: pairs.map(function (p) { return p.value; }) }],
            xaxis: {
                categories: pairs.map(function (p) { return p.label; }),
                labels: { style: { fontSize: '11px', fontWeight: 600 } },
            },
            colors: colors.slice(0, pairs.length),
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 8,
                    barHeight: '58%',
                    distributed: true,
                },
            },
            dataLabels: { enabled: true },
            legend: { show: false },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        }).render();
    }

    function renderArea(el) {
        var data = readData(el);
        if (!data) {
            return;
        }

        var total = data.series.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) {
            emptyState(el, 'Sin actividad CAE en los últimos 14 días.', 'bi-graph-up');
            return;
        }

        new ApexCharts(el, {
            chart: { type: 'area', height: 280, fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false } },
            series: [{ name: 'Registros CAE', data: data.series }],
            xaxis: { categories: data.labels, labels: { style: { fontSize: '12px', fontWeight: 600 } } },
            colors: ['#2563eb'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 100] } },
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
            tooltip: { y: { formatter: function (v) { return v + ' registro' + (v === 1 ? '' : 's'); } } },
        }).render();
    }

    function initDashboardCharts() {
        if (typeof ApexCharts === 'undefined') {
            window.setTimeout(initDashboardCharts, 60);
            return;
        }

        renderDonut(
            document.getElementById('dash-cae-chart'),
            ['#10b981', '#38bdf8', '#6b7280', '#f59e0b', '#ef4444']
        );
        renderHorizontalBar(
            document.getElementById('dash-cae-bar-chart'),
            ['#10b981', '#38bdf8', '#6b7280', '#f59e0b', '#ef4444']
        );
        renderArea(document.getElementById('dash-activity-chart'));
    }

    if (document.getElementById('dash-cae-chart') || document.getElementById('dash-activity-chart')) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardCharts);
        } else {
            initDashboardCharts();
        }
    }
})();
