@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Automações</h1>
        <p class="page-subtitle">Regras que disparam ações automaticamente no sistema</p>
    </div>
    <a href="{{ route('automations.create') }}" class="btn-primary">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nova Automação
    </a>
</div>

@if(session('success'))
    <div class="alert-success mb-6">{{ session('success') }}</div>
@endif

@if($automations->isEmpty())
    <div class="card" style="text-align:center; padding:3rem;">
        <svg class="h-12 w-12 mx-auto mb-4" style="color:var(--muted)" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </svg>
        <p style="color:var(--muted); margin-bottom:1rem">Nenhuma automação criada ainda.</p>
        <a href="{{ route('automations.create') }}" class="btn-primary">Criar primeira automação</a>
    </div>
@else
    @foreach($automations as $entityType => $group)
        <div class="section-title" style="margin-bottom:.75rem; margin-top:1.5rem">
            {{ \App\Models\Automation::$entityTypes[$entityType] ?? $entityType }}
        </div>

        <div style="display:flex; flex-direction:column; gap:.75rem; margin-bottom:1rem">
            @foreach($group as $automation)
                <div class="card" style="padding:1.25rem 1.5rem">
                    <div style="display:flex; align-items:flex-start; gap:1rem">

                        {{-- Status pill --}}
                        <div style="padding-top:2px; flex-shrink:0">
                            <form action="{{ route('automations.toggle', $automation) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit"
                                    title="{{ $automation->is_active ? 'Pausar' : 'Ativar' }}"
                                    style="width:36px; height:20px; border-radius:999px; border:none; cursor:pointer; position:relative; transition:background .2s;
                                           background:{{ $automation->is_active ? 'var(--purple)' : 'var(--border2)' }}">
                                    <span style="position:absolute; top:2px; width:16px; height:16px; border-radius:50%; background:#fff;
                                                 transition:left .2s; left:{{ $automation->is_active ? '18px' : '2px' }}"></span>
                                </button>
                            </form>
                        </div>

                        {{-- Info --}}
                        <div style="flex:1; min-width:0">
                            <div style="display:flex; align-items:center; gap:.75rem; margin-bottom:.35rem">
                                <span style="font-weight:600; font-size:.95rem; color:var(--text)">{{ $automation->name }}</span>
                                @if(!$automation->is_active)
                                    <span class="badge badge-muted" style="font-size:.7rem">Pausada</span>
                                @endif
                            </div>

                            {{-- Regra visual --}}
                            <div style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; font-size:.82rem">
                                <span style="background:rgba(106,90,205,.1); color:var(--purple); padding:.2rem .6rem; border-radius:6px; border:1px solid rgba(106,90,205,.2)">
                                    Se: {{ $automation->triggerSummary() }}
                                </span>
                                <svg class="h-3.5 w-3.5" style="color:var(--muted); flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                                <span style="background:rgba(255,140,0,.1); color:var(--orange); padding:.2rem .6rem; border-radius:6px; border:1px solid rgba(255,140,0,.2)">
                                    Então: {{ $automation->actionSummary() }}
                                </span>
                            </div>

                            @if($automation->description)
                                <p style="color:var(--muted); font-size:.8rem; margin-top:.4rem">{{ $automation->description }}</p>
                            @endif
                        </div>

                        {{-- Ações --}}
                        <div style="display:flex; align-items:center; gap:.5rem; flex-shrink:0">
                            <a href="{{ route('automations.logs', $automation) }}"
                               title="Ver logs"
                               style="color:var(--muted); display:flex; align-items:center; padding:.4rem">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                                </svg>
                            </a>
                            <a href="{{ route('automations.edit', $automation) }}"
                               style="color:var(--muted); display:flex; align-items:center; padding:.4rem">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                </svg>
                            </a>
                            <form action="{{ route('automations.destroy', $automation) }}" method="POST"
                                  onsubmit="return confirm('Remover esta automação?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="color:var(--muted); background:none; border:none; cursor:pointer; display:flex; align-items:center; padding:.4rem">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif
@endsection
