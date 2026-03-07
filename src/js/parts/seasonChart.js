import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const COLORS = [
    '#f59e0b', // amber
    '#3b82f6', // blue
    '#ef4444', // red
    '#10b981', // emerald
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#f97316', // orange
    '#14b8a6', // teal
    '#6366f1', // indigo
];

export async function seasonChart(canvas) {
    const url = canvas.dataset.url;
    if (!url) return;

    const res = await fetch(url);
    const json = await res.json();

    if (!json.success || !json.labels.length) {
        canvas.closest('.js-season-chart-wrapper').style.display = 'none';
        return;
    }

    const datasets = json.datasets.map((ds, i) => ({
        label: ds.label,
        data: ds.data,
        borderColor: COLORS[i % COLORS.length],
        backgroundColor: COLORS[i % COLORS.length] + '15',
        borderWidth: 2.5,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: COLORS[i % COLORS.length],
        tension: 0.3,
        fill: false,
    }));

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: json.labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12, weight: 'bold' },
                    },
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: (ctx) => `  ${ctx.dataset.label}: ${ctx.parsed.y} pts`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 45,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 },
                        callback: (v) => v + ' pts',
                    },
                },
            },
        },
    });
}
