@php
    $visionLabels = [
        'direcao_geral'    => ['label' => 'Direção Geral',         'icon' => 'compass'],
        'direcao_criativa' => ['label' => 'Direção Criativa',      'icon' => 'palette'],
        'coo'              => ['label' => 'COO & Operação',         'icon' => 'settings'],
        'gestor_campanhas' => ['label' => 'Gestor de Campanhas',   'icon' => 'megaphone'],
        'head_criativa'    => ['label' => 'Head Criativa & Copy',  'icon' => 'pencil'],
        'head_tech'        => ['label' => 'Head de Tecnologia',    'icon' => 'code'],
        'designer'         => ['label' => 'Design',                'icon' => 'palette'],
        'trafego'          => ['label' => 'Tráfego',               'icon' => 'trending-up'],
        'dev'              => ['label' => 'Dev',                   'icon' => 'code'],
    ];
    $info = $visionLabels[$role] ?? ['label' => $role, 'icon' => 'layout-grid'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <x-icon name="{{ $info['icon'] }}" size="20" style="color:var(--purple)" />
            <h1 class="text-base font-bold" style="color:var(--text)">{{ $info['label'] }}</h1>
        </div>
    </x-slot>

    <div style="padding: 32px 32px 0">
        <div class="tab-placeholder" style="margin-top: 0; min-height: 420px">
            <div style="width:56px; height:56px; border-radius:14px; background:rgba(100, 59, 142,.08); border:1px solid rgba(100, 59, 142,.15); display:flex; align-items:center; justify-content:center; margin-bottom:20px">
                <x-icon name="{{ $info['icon'] }}" size="28" style="color:var(--purple); opacity:.6" />
            </div>
            <p class="tab-placeholder-title" style="font-size:15px">Dashboard — {{ $info['label'] }}</p>
            <p class="tab-placeholder-desc" style="margin-top:8px; max-width:360px">
                Este painel personalizado será construído com os indicadores e ações mais relevantes para o papel de <strong>{{ $info['label'] }}</strong>. Em breve.
            </p>
        </div>
    </div>
</x-app-layout>
