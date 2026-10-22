// Gráficos no padrão Mirbal (redesign etapa 4) — ApexCharts com os padrões do template:
// sem grade, eixos sem borda/tracinho, sem barra de ferramentas, fonte Outfit, área com
// degradê, barras finas com cantos arredondados. Usado pelo componente <x-chart>.
//
// A biblioteca (~500 kB) só é baixada nas telas que têm gráfico (import dinâmico).
// Cores aceitam "var(--green)" etc. — resolvidas na hora de desenhar, então o tema
// (claro/escuro) e a cor da marca da organização valem. Trocar o tema redesenha.

const formatters = {
    number: (v) => Number(v ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 }),
    decimal: (v) => Number(v ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 }),
    currency: (v) => 'R$ ' + Number(v ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    currencyShort: (v) => {
        const n = Number(v ?? 0);
        if (Math.abs(n) >= 1000) return 'R$ ' + (n / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil';
        return 'R$ ' + n.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    },
    percent: (v) => Number(v ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%',
};

function cssValue(value) {
    if (typeof value !== 'string') return value;
    const match = value.match(/^var\((--[\w-]+)\)$/);
    return match ? getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() || value : value;
}

function buildOptions(config) {
    const isDark = document.documentElement.classList.contains('dark');
    const muted = cssValue('var(--muted)');
    const border = cssValue('var(--border2)');
    const colors = (config.colors ?? ['var(--purple)', 'var(--orange)']).map(cssValue);
    const format = formatters[config.format] ?? formatters.number;
    const axisFormat = formatters[config.axisFormat ?? config.format] ?? format;
    const type = config.type ?? 'area';
    const sparkline = !!config.sparkline;
    const circular = ['donut', 'pie', 'radialBar'].includes(type);

    const options = {
        chart: {
            type,
            height: config.height ?? 260,
            fontFamily: 'Outfit, ui-sans-serif, system-ui, sans-serif',
            foreColor: muted,
            toolbar: { show: false },
            zoom: { enabled: false },
            sparkline: { enabled: sparkline },
            animations: { speed: 400 },
            background: 'transparent',
            stacked: !!config.stacked,
        },
        theme: { mode: isDark ? 'dark' : 'light' },
        colors,
        series: config.series ?? [],
        dataLabels: { enabled: false },
        legend: {
            show: config.legend ?? (circular || (config.series ?? []).length > 1),
            position: circular ? 'bottom' : 'top',
            horizontalAlign: circular ? 'center' : 'left',
            fontSize: '13px',
            markers: { size: 5, shape: 'circle' },
            itemMargin: { horizontal: 10, vertical: 4 },
        },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: format } },
        stroke: circular
            ? { width: 3, colors: [cssValue('var(--s1)')] }
            : { curve: 'smooth', width: type === 'bar' ? 0 : 2 },
        grid: { show: false, padding: { left: 4, right: 4 } },
    };

    if (circular) {
        options.labels = config.labels ?? [];
        options.plotOptions = { pie: { donut: { size: '62%' } } };
        return { ...options, ...(config.options ?? {}) };
    }

    options.xaxis = {
        categories: config.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { fontSize: '12px', fontWeight: 500 }, rotate: 0, hideOverlappingLabels: true },
        tooltip: { enabled: false },
    };
    options.yaxis = {
        labels: { style: { fontSize: '12px', fontWeight: 500 }, formatter: axisFormat },
        tickAmount: 4,
    };

    if (type === 'area') {
        options.fill = { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95, 100] } };
    }
    if (type === 'bar') {
        options.plotOptions = { bar: { columnWidth: config.columnWidth ?? '45%', borderRadius: 4, borderRadiusApplication: 'end' } };
        options.fill = { opacity: 1 };
        options.states = { hover: { filter: { type: 'darken', value: 0.9 } } };
    }
    if (border) options.grid.borderColor = border;

    return { ...options, ...(config.options ?? {}) };
}

export function registerCharts(Alpine) {
    Alpine.data('apexChart', (config) => ({
        chart: null,
        observer: null,

        async init() {
            const { default: ApexCharts } = await import('apexcharts');
            this.chart = new ApexCharts(this.$refs.canvas, buildOptions(config));
            this.chart.render();

            // Tema claro/escuro mudou (classe "dark" no <html>) → redesenha com as cores novas
            this.observer = new MutationObserver(() => {
                this.chart?.updateOptions(buildOptions(config), false, false);
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },

        destroy() {
            this.observer?.disconnect();
            this.chart?.destroy();
        },
    }));
}
