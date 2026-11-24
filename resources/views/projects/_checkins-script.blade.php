{{-- Componente Alpine do formulário de conferência (projects/_checkins). Fica na página, não no
     pedaço da aba, porque a aba pode chegar por fetch (script em x-html não roda). --}}
<script>
function checkinForm(initial, ritmo) {
    const lights = { verde: ['#16a34a', 'No ritmo'], amarelo: ['#EE7919', 'Abaixo do ritmo'], vermelho: ['#dc2626', 'Bem abaixo do ritmo'] };
    const meta = initial.ref || {};
    const num = (t) => {
        if (t === null || t === undefined) return null;
        const m = String(t).match(/-?\d[\d.,]*/); if (!m) return null;
        let n = m[0].replace(/[.,]$/, '');
        if (n.includes(',')) n = n.replace(/\./g, '').replace(',', '.');
        else if (/^\d{1,3}(\.\d{3})+$/.test(n)) n = n.replace(/\./g, '');
        const v = parseFloat(n); return isNaN(v) ? null : v;
    };
    return {
        metas: initial.metas, cuidados: initial.cuidados, situacao: initial.situacao, fechar: !!initial.fechar,
        light(k) {
            const [p0, m0] = meta[k] || []; const p = num(p0), m = num(m0), a = num(this.metas[k]?.atual);
            if (p === null || m === null || a === null || m === p || ritmo === null) return null;
            const feito = (a - p) / (m - p); if (feito >= 1) return 'verde';
            const r = feito / Math.max(ritmo / 100, 0.1);
            return r >= 0.9 ? 'verde' : (r >= 0.6 ? 'amarelo' : 'vermelho');
        },
        lightColor(k) { return (lights[this.light(k)] || [])[0]; },
        lightLabel(k) { return (lights[this.light(k)] || [])[1]; },
    };
}
</script>
