@php
    $visionLabels = [
        'direcao_geral'    => ['label' => 'Direção Geral',         'icon' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z'],
        'direcao_criativa' => ['label' => 'Direção Criativa',      'icon' => 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42'],
        'coo'              => ['label' => 'COO & Operação',         'icon' => 'M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z'],
        'gestor_campanhas' => ['label' => 'Gestor de Campanhas',   'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25A1.125 1.125 0 013 18.75v-5.625zM16.5 13.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 12 21 12.504 21 13.125v5.625c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125v-5.625zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v10.125c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625z'],
        'head_criativa'    => ['label' => 'Head Criativa & Copy',  'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10'],
        'head_tech'        => ['label' => 'Head de Tecnologia',    'icon' => 'M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z'],
        'designer'         => ['label' => 'Design',                'icon' => 'M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42'],
        'trafego'          => ['label' => 'Tráfego',               'icon' => 'M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z'],
        'dev'              => ['label' => 'Dev',                   'icon' => 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5'],
    ];
    $info = $visionLabels[$role] ?? ['label' => $role, 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:var(--purple)">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $info['icon'] }}" />
            </svg>
            <h1 class="text-base font-bold" style="color:var(--text)">{{ $info['label'] }}</h1>
        </div>
    </x-slot>

    <div style="padding: 32px 32px 0">
        <div class="tab-placeholder" style="margin-top: 0; min-height: 420px">
            <div style="width:56px; height:56px; border-radius:14px; background:rgba(100, 59, 142,.08); border:1px solid rgba(100, 59, 142,.15); display:flex; align-items:center; justify-content:center; margin-bottom:20px">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="color:var(--purple); opacity:.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $info['icon'] }}" />
                </svg>
            </div>
            <p class="tab-placeholder-title" style="font-size:15px">Dashboard — {{ $info['label'] }}</p>
            <p class="tab-placeholder-desc" style="margin-top:8px; max-width:360px">
                Este painel personalizado será construído com os indicadores e ações mais relevantes para o papel de <strong>{{ $info['label'] }}</strong>. Em breve.
            </p>
        </div>
    </div>
</x-app-layout>
