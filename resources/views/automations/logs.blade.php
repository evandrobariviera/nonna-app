@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('automations.index') }}" class="back-link">← Automações</a>
        <h1 class="page-title">Logs — {{ $automation->name }}</h1>
        <p class="page-subtitle">Histórico de execuções desta automação</p>
    </div>
</div>

<div style="margin-bottom:1rem; display:flex; gap:.75rem; flex-wrap:wrap">
    <div class="card" style="padding:.75rem 1.25rem; flex:1; min-width:150px; text-align:center">
        <div style="font-size:1.5rem; font-weight:700; color:var(--text)">
            {{ $logs->total() }}
        </div>
        <div style="font-size:.8rem; color:var(--muted)">Total de execuções</div>
    </div>
    <div class="card" style="padding:.75rem 1.25rem; flex:1; min-width:150px; text-align:center">
        <div style="font-size:1.5rem; font-weight:700; color:var(--green)">
            {{ $logs->getCollection()->where('status','success')->count() }}
        </div>
        <div style="font-size:.8rem; color:var(--muted)">Bem-sucedidas (esta página)</div>
    </div>
    <div class="card" style="padding:.75rem 1.25rem; flex:1; min-width:150px; text-align:center">
        <div style="font-size:1.5rem; font-weight:700; color:var(--red)">
            {{ $logs->getCollection()->where('status','failed')->count() }}
        </div>
        <div style="font-size:.8rem; color:var(--muted)">Com erro (esta página)</div>
    </div>
</div>

@if($logs->isEmpty())
    <div class="card" style="padding:2rem; text-align:center">
        <p style="color:var(--muted)">Nenhuma execução registrada ainda.</p>
    </div>
@else
    <div style="display:flex; flex-direction:column; gap:.5rem">
        @foreach($logs as $log)
            @php
                $color = match($log->status) {
                    'success' => 'var(--green)',
                    'failed'  => 'var(--red)',
                    default   => 'var(--orange)',
                };
            @endphp
            <div class="card" style="padding:1rem 1.25rem" x-data="{ open: false }">
                <div style="display:flex; align-items:center; gap:.75rem; cursor:pointer" @click="open = !open">
                    <span style="width:8px; height:8px; border-radius:50%; background:{{ $color }}; flex-shrink:0"></span>
                    <div style="flex:1; min-width:0">
                        <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap">
                            <span style="font-weight:600; font-size:.85rem; color:{{ $color }}">
                                {{ $log->statusLabel() }}
                            </span>
                            <span style="font-size:.8rem; color:var(--muted)">
                                {{ $log->entity_type }}:{{ Str::limit($log->entity_id, 8, '…') }}
                            </span>
                            @if($log->duration_ms)
                                <span style="font-size:.75rem; color:var(--muted)">{{ $log->duration_ms }}ms</span>
                            @endif
                        </div>
                        <div style="font-size:.78rem; color:var(--muted); margin-top:.15rem">
                            {{ $log->ran_at?->format('d/m/Y H:i:s') ?? $log->created_at->format('d/m/Y H:i:s') }}
                        </div>
                    </div>
                    <svg class="h-4 w-4" style="color:var(--muted); flex-shrink:0; transition:transform .2s"
                         :style="open ? 'transform:rotate(90deg)' : ''"
                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>

                <div x-show="open" x-transition style="margin-top:.75rem; display:none">
                    @if($log->output)
                        <div style="background:var(--s3); border-radius:6px; padding:.75rem; margin-bottom:.5rem">
                            <div style="font-size:.75rem; color:var(--muted); margin-bottom:.25rem">Output</div>
                            <pre style="font-size:.82rem; color:var(--text); white-space:pre-wrap; margin:0">{{ $log->output }}</pre>
                        </div>
                    @endif
                    @if($log->error_message)
                        <div style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.2); border-radius:6px; padding:.75rem">
                            <div style="font-size:.75rem; color:var(--red); margin-bottom:.25rem">Erro</div>
                            <pre style="font-size:.82rem; color:var(--red); white-space:pre-wrap; margin:0">{{ $log->error_message }}</pre>
                        </div>
                    @endif
                    @if($log->input_snapshot)
                        <details style="margin-top:.5rem">
                            <summary style="font-size:.78rem; color:var(--muted); cursor:pointer">Contexto enviado</summary>
                            <pre style="font-size:.75rem; color:var(--muted); margin-top:.4rem; background:var(--s2); padding:.5rem; border-radius:4px; overflow-x:auto">{{ json_encode($log->input_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div style="margin-top:1.5rem">
        {{ $logs->links() }}
    </div>
@endif
@endsection
