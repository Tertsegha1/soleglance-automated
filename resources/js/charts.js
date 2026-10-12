import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Legend, Tooltip, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip, Filler);

const PALETTE = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#f43f5e'];

/**
 * Renders every <canvas data-chart="bar|line|stacked" data-labels='[...]' data-series='[{label,data,color?}]'>.
 */
export function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const kind = canvas.dataset.chart;
        const labels = JSON.parse(canvas.dataset.labels || '[]');
        const series = JSON.parse(canvas.dataset.series || '[]');
        const stacked = kind === 'stacked';

        new Chart(canvas, {
            type: kind === 'line' ? 'line' : 'bar',
            data: {
                labels,
                datasets: series.map((s, i) => ({
                    label: s.label,
                    data: s.data,
                    backgroundColor: s.color ?? PALETTE[i % PALETTE.length],
                    borderColor: s.color ?? PALETTE[i % PALETTE.length],
                    borderRadius: kind === 'line' ? 0 : 4,
                    tension: 0.3,
                })),
            },
            options: {
                responsive: true,
                animation: false,
                plugins: { legend: { display: series.length > 1, position: 'bottom' } },
                scales: {
                    x: { stacked, grid: { display: false } },
                    y: { stacked, beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    });
}
