<x-portal-layout>
    <x-slot name="title">{{ $opportunity->lead->name ?: 'Lead' }}</x-slot>

    <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-xs uppercase tracking-widest mb-1" style="color: var(--muted)">
                <a href="{{ route('portal.leads.index') }}" class="hover:underline">Central de Leads</a> /
                {{ $opportunity->lead->name ?: 'Sem nome' }}
            </p>
            <h1 class="text-2xl font-black" style="color: var(--text)">{{ $opportunity->lead->name ?: 'Sem nome' }}</h1>
            <div class="mt-1">
                <span class="badge badge-{{ $opportunity->stageColor() }}">{{ $opportunity->stageLabel() }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('portal.leads.update-stage', $opportunity) }}">
            @csrf
            @method('PATCH')
            <select name="stage" onchange="this.form.submit()"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px; border-radius:6px; outline:none; cursor:pointer">
                @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
                    <option value="{{ $key }}" {{ $opportunity->stage === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 space-y-6">

            <div class="card p-5">
                <h3 class="text-xs uppercase tracking-widest mb-4" style="color: var(--muted)">Contato</h3>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-black text-white flex-shrink-0"
                         style="background: var(--purple);">
                        {{ strtoupper(substr($opportunity->lead->name ?: '?', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-semibold" style="color: var(--text)">{{ $opportunity->lead->name ?: 'Sem nome' }}</div>
                        @if($opportunity->lead->city || $opportunity->lead->state)
                            <div class="text-xs" style="color: var(--muted2)">{{ trim(($opportunity->lead->city ?? '') . ' / ' . ($opportunity->lead->state ?? ''), ' /') }}</div>
                        @endif
                    </div>
                    <div class="ml-auto text-right text-xs" style="color: var(--muted)">
                        @if($opportunity->lead->email)<div>{{ $opportunity->lead->email }}</div>@endif
                        @if($opportunity->lead->phone)<div>{{ $opportunity->lead->phone }}</div>@endif
                    </div>
                </div>
            </div>

            @include('leads._notes', ['opportunity' => $opportunity, 'notesStoreRoute' => route('portal.leads.notes.store', $opportunity)])

        </div>

        <div class="space-y-4">
            <div class="card p-4 space-y-3">
                <h3 class="text-xs uppercase tracking-widest" style="color: var(--muted)">Origem</h3>
                <div class="text-sm" style="color: var(--text)">{{ $opportunity->channel?->kindLabel() ?? '—' }}</div>
            </div>
            <div class="card p-4 space-y-3">
                <h3 class="text-xs uppercase tracking-widest" style="color: var(--muted)">Convertido em</h3>
                <div class="text-sm" style="color: var(--text)">{{ ($opportunity->received_at ?? $opportunity->created_at)->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>
</x-portal-layout>
