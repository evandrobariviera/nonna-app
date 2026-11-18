{{-- Relatório Analítico do Portal: números do site (Google Analytics + Search Console). --}}
@php
    $friendlyError = 'Não conseguimos buscar esses números no Google agora. Tente de novo mais tarde — se continuar, fale com o time da Nonna.';
@endphp

<x-portal-layout>
    <x-slot name="title">Relatório Analítico</x-slot>

    <div class="mb-4">
        <h1 class="text-2xl font-black" style="color: var(--text)">Relatório Analítico</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Como as pessoas chegam e o que fazem no site de {{ $client->company_name }}.</p>
    </div>

    <form method="GET" action="{{ route('portal.site-report.index') }}" class="flex flex-wrap items-center gap-3 mb-5">
        <select class="field" name="dias" onchange="this.form.submit()" style="padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
            @foreach(\App\Http\Controllers\Portal\SiteReportController::PERIODS as $value => $label)
                <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="text-xs" style="color:var(--muted)">Até ontem · dados do Google Analytics e do Google Search Console</span>
    </form>

    @include('site-report._report', ['errorText' => fn ($e) => $friendlyError])
</x-portal-layout>
