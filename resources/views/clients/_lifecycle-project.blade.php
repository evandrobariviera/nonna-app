{{-- Cartão de um projeto de ciclo de vida do cliente (Onboarding / Offboarding): fase atual +
     lista das fases com situação. Espera $project, $timeline (ProjectTimeline) e $label. --}}
@php
    $lcUrl = $project->macro_plan_id
        ? route('macroplans.projects.show', [$project->macro_plan_id, $project])
        : route('projects.showDirect', $project);
    $lcHex = fn (?string $s) => \App\Models\Task::colorHex($s ? \App\Models\ProjectPhase::statusColor($s) : 'muted');
@endphp
<div class="card">
    <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
        <div>
            <p class="text-sm font-bold" style="color:var(--text)">{{ $label }}</p>
            <p class="text-xs mt-0.5" style="color:var(--muted)">
                @if($project->status === 'concluido')
                    <span style="color:var(--green)">Concluído</span>
                @elseif($timeline['current'])
                    Agora: <span style="color:{{ $lcHex($timeline['current']['status']) }}">{{ $timeline['current']['phase']->name }}</span>
                @endif
                · {{ $timeline['done'] }} de {{ $timeline['phases'] }} fases concluídas
                @if($timeline['late'])<span style="color:var(--red)"> · {{ $timeline['late'] }} atrasada(s)</span>@endif
            </p>
        </div>
        <a href="{{ $lcUrl }}" class="btn btn-primary btn-sm">Abrir projeto →</a>
    </div>
    @foreach($timeline['rows'] as $row)
        <div class="px-5 py-3 flex items-center gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
            @if($row['phase']->is_milestone)
                <span class="flex-shrink-0" style="width:9px; height:9px; transform:rotate(45deg); background:{{ $lcHex($row['status']) }}"></span>
            @else
                <span class="flex-shrink-0 rounded-full" style="width:9px; height:9px; background:{{ $lcHex($row['status']) }}"></span>
            @endif
            <span class="text-sm font-semibold flex-1 min-w-0 truncate" style="color:var(--text)">{{ $row['phase']->name }}</span>
            <span class="text-xs" style="color:{{ $lcHex($row['status']) }}">{{ \App\Models\ProjectPhase::statusLabel($row['status']) }}</span>
            @unless($row['phase']->is_milestone)
                <span class="text-xs font-mono" style="color:var(--muted)">{{ $row['done'] }}/{{ $row['total'] }}</span>
            @endunless
            @if($row['end'])
                <span class="text-xs font-mono" style="color:var(--muted)">até {{ $row['end']->format('d/m') }}</span>
            @endif
        </div>
    @endforeach
    @if($timeline['rows']->isEmpty())
        <p class="px-5 py-4 text-xs" style="color:var(--muted)">O projeto ainda não tem fases — abra e aplique o Playbook.</p>
    @endif
</div>
