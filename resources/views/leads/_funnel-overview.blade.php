{{-- Aba Resultados sem cliente escolhido: funil somado e uma linha por
     cliente (LeadFunnel::overview). Só no app interno. --}}
<p class="text-sm mb-4" style="color:var(--muted)">
    De {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}. Escolha um cliente para ver campanha por campanha.
</p>

@include('leads._funnel-chart', ['t' => \App\Services\Leads\LeadFunnel::overviewTotals($overview)])

@include('leads._overview-table', [
    'overview'  => $overview,
    'clientUrl' => fn ($client) => route('leads.index', array_merge(request()->only(['date_from', 'date_to']), ['view' => 'resultados', 'client_id' => $client->id])),
])
