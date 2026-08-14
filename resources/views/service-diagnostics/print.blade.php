<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico de Atendimento · {{ $integration->client->displayName() }} · v{{ $diagnostic->version }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /* Barra de ação só na tela — some na impressão/PDF */
        .print-toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between;
            padding: 12px 24px; background: var(--s1); border-bottom: 1px solid var(--border2); }
        @media print {
            .print-toolbar { display: none; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body style="background:var(--bg)">

    <div class="print-toolbar">
        <span class="text-sm font-semibold" style="color:var(--text)">
            Diagnóstico de Atendimento · {{ $integration->client->displayName() }} · Versão {{ $diagnostic->version }}
        </span>
        <button onclick="window.print()"
                class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white flex items-center gap-1.5"
                style="background:var(--purple)">
            <x-icon name="printer" size="13" />
            Imprimir / Salvar PDF
        </button>
    </div>

    <div class="p-8" style="max-width:960px; margin:0 auto">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm" style="color:var(--muted)">
                {{ $diagnostic->period_start->format('d/m/Y') }} – {{ $diagnostic->period_end->format('d/m/Y') }}
                @if($diagnostic->aiAgent) · agente {{ $diagnostic->aiAgent->name }} @endif
            </p>
        </div>

        @include('service-diagnostics._report')
    </div>

</body>
</html>
