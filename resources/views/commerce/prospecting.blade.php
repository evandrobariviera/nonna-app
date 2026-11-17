{{-- Central de Comércio → Prospecção (CommerceProspectingController).
     Etapa 2: varredura no Google Places por cidade + segmento/nicho. Termos do Google:
     no banco só o place_id; nome/endereço vêm ao vivo ($live) e só para a página atual,
     sempre com a atribuição "Google Maps". Config (segmentos/cidades): só admin/dono. --}}
@php
    $canEdit = $isOrgAdmin ?? false;
    $tabUrl = fn ($aba, $params = []) => route('commerce.prospecting', array_merge(['aba' => $aba], $params));
@endphp
<x-app-layout>
    <x-slot name="header">Prospecção</x-slot>

    <div x-data="{ newScan: {{ $errors->hasAny(['city_id', 'segment_id', 'niche']) ? 'true' : 'false' }} }"
         @if($running) x-init="setTimeout(() => window.location.reload(), 6000)" @endif>

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <p class="text-sm max-w-2xl" style="color:var(--muted2); line-height:1.6">
                Encontre empresas de um segmento numa cidade, veja quem são e prepare a abordagem.
                A varredura busca no Google Maps e não tem custo. Nome e endereço aparecem na hora e não ficam guardados no App.
            </p>
            <button type="button" class="btn btn-primary btn-sm" @click="newScan = !newScan" @disabled(!$configured)>
                <x-icon name="search" size="14" /> Nova varredura
            </button>
        </div>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">{{ session('error') }}</div>
        @endif
        @if(!$configured)
            <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); border-radius:var(--radius)">
                <b>Falta a chave do Google Maps.</b> Peça para o administrador cadastrar em
                <a href="{{ route('settings.index') }}?tab=integracoes" class="underline">Configurações → Integrações → Nova integração → Google Maps (Places)</a>.
                Enquanto isso, dá para configurar segmentos e cidades.
            </div>
        @endif

        {{-- Nova varredura --}}
        <div x-show="newScan" x-transition style="display:none" class="card p-5 mb-5">
            <h2 class="card-title mb-3">Nova varredura</h2>
            <form method="POST" action="{{ route('commerce.scans.store') }}" class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
                @csrf
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Cidade *
                    <select name="city_id" class="field text-sm px-2.5 py-1.5" required>
                        @foreach($cities as $c)
                            <option value="{{ $c->id }}" @selected(old('city_id') === $c->id)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                    @error('city_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Segmento
                    <select name="segment_id" class="field text-sm px-2.5 py-1.5">
                        <option value="">— só o nicho —</option>
                        @foreach($segments->where('is_active', true) as $s)
                            <option value="{{ $s->id }}" @selected(old('segment_id') === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @error('segment_id')<span style="color:var(--red)">{{ $message }}</span>@enderror
                </label>
                <label class="text-xs flex flex-col gap-1" style="color:var(--muted2)">Nicho (opcional)
                    <input type="text" name="niche" value="{{ old('niche') }}" maxlength="120" class="field text-sm px-2.5 py-1.5" placeholder="ex.: harmonização facial">
                    <span style="color:var(--muted)">Com nicho, a busca usa só ele (recorte que o Google não separa).</span>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">Varrer</button>
                </div>
            </form>
        </div>

        {{-- Abas --}}
        <div class="flex gap-1 mb-4 flex-wrap" style="border-bottom:1px solid var(--border)">
            @foreach(['empresas' => 'Empresas', 'varreduras' => 'Varreduras', 'configurar' => 'Segmentos e cidades'] as $key => $label)
                <a href="{{ $tabUrl($key) }}" class="px-3 py-2 text-sm font-semibold"
                   style="{{ $tab === $key ? 'color:var(--purple); border-bottom:2px solid var(--purple)' : 'color:var(--muted)' }}">{{ $label }}
                    @if($key === 'varreduras' && $running)<span class="badge badge-orange ml-1">varrendo</span>@endif
                </a>
            @endforeach
        </div>

        @if($tab === 'empresas')
            {{-- Filtros --}}
            <form method="GET" class="flex items-center gap-2 flex-wrap mb-4">
                <input type="hidden" name="aba" value="empresas">
                <select name="cidade" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todas as cidades</option>
                    @foreach($cities as $c)<option value="{{ $c->id }}" @selected(($filters['cidade'] ?? '') === $c->id)>{{ $c->label() }}</option>@endforeach
                </select>
                <select name="segmento" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todos os segmentos</option>
                    @foreach($segments as $s)<option value="{{ $s->id }}" @selected(($filters['segmento'] ?? '') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <select name="situacao" class="field text-sm px-2.5 py-1.5" onchange="this.form.submit()">
                    <option value="">Todas as situações</option>
                    @foreach(\App\Models\CommercePlace::$statuses as $key => $st)<option value="{{ $key }}" @selected(($filters['situacao'] ?? '') === $key)>{{ $st['label'] }}</option>@endforeach
                </select>
                @if(!empty($filters['varredura']))
                    @php $sc = $scans->firstWhere('id', $filters['varredura']); @endphp
                    <input type="hidden" name="varredura" value="{{ $filters['varredura'] }}">
                    <a href="{{ route('commerce.prospecting', array_merge(['aba' => 'empresas'], \Illuminate\Support\Arr::except($filters, 'varredura'))) }}" class="badge badge-purple" style="padding:4px 10px">Varredura: {{ $sc?->label() ?? '—' }} ✕</a>
                @endif
                <span class="text-xs ml-auto" style="color:var(--muted)">{{ $places->total() }} empresa(s)</span>
            </form>

            <div class="card" style="padding:0; overflow-x:auto">
                <table class="nonna-table w-full">
                    <thead>
                        <tr><th>Empresa</th><th>Segmento · Cidade</th><th>Nota Nonna</th><th>Situação</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($places as $p)
                            @php $g = $live[$p->place_id] ?? null; $st = \App\Models\CommercePlace::$statuses[$p->status] ?? null; @endphp
                            <tr>
                                <td class="min-w-0">
                                    @if($g)
                                        <b style="color:var(--text)">{{ $g['name'] }}</b>
                                        @if($g['closed'])<span class="badge badge-red ml-1">fechada</span>@endif
                                        <span class="block text-xs" style="color:var(--muted2)">{{ $g['type'] }}{{ $g['type'] && $g['address'] ? ' · ' : '' }}{{ $g['address'] }}</span>
                                    @else
                                        <span class="text-xs" style="color:var(--muted)">{{ $configured ? 'Não foi possível ler agora' : 'Sem chave do Google' }}</span>
                                    @endif
                                </td>
                                <td class="text-xs" style="color:var(--muted2)">{{ $p->segment?->name ?? '—' }} · {{ $p->city?->name ?? '—' }}</td>
                                <td>{{ $p->score !== null ? $p->score : '—' }}</td>
                                <td>@if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif</td>
                                <td class="text-right whitespace-nowrap">
                                    @if($g && $g['maps_url'])
                                        <a href="{{ $g['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-xs">Ver no Maps</a>
                                    @endif
                                    <form method="POST" action="{{ route('commerce.places.discard', $p) }}" class="inline">@csrf
                                        <button type="submit" class="btn btn-xs">{{ $p->status === 'discarded' ? 'Voltar' : 'Descartar' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-sm" style="color:var(--muted)">Nenhuma empresa ainda. Faça uma varredura.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
                <span class="text-xs" style="color:var(--muted)">Dados de empresas: <b>Google Maps</b>. Buscados agora, não ficam guardados.</span>
                {{ $places->links() }}
            </div>
            <p class="text-xs mt-2" style="color:var(--muted)">A Nota Nonna (perfil, reputação, site e presença nas IAs) chega na próxima etapa.</p>

        @elseif($tab === 'varreduras')
            <div class="card" style="padding:0; overflow-x:auto">
                <table class="nonna-table w-full">
                    <thead><tr><th>Varredura</th><th>Quando</th><th>Empresas</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                        @forelse($scans as $s)
                            @php $st = \App\Models\CommerceScan::$statuses[$s->status] ?? null; @endphp
                            <tr>
                                <td>
                                    <b style="color:var(--text)">{{ $s->label() }}</b>
                                    @if($s->terms)
                                        <span class="block text-xs" style="color:var(--muted2)">{{ collect($s->terms)->map(fn ($t) => \Illuminate\Support\Str::before($t['term'], ' em ') . ' (' . $t['found'] . ($t['saturated'] ? ', cheia' : '') . ')')->implode(' · ') }}</span>
                                    @endif
                                    @if($s->status === 'error')<span class="block text-xs" style="color:var(--red)">{{ $s->error }}</span>@endif
                                    @if($s->saturated)
                                        <span class="block text-xs mt-1" style="color:var(--orange)">Alguma busca voltou cheia (60, o máximo do Google): pode haver mais empresas. Rode nichos mais específicos para completar.</span>
                                    @endif
                                </td>
                                <td class="text-xs whitespace-nowrap" style="color:var(--muted2)">{{ $s->created_at->format('d/m H:i') }}<span class="block">{{ $s->creator?->name }}</span></td>
                                <td class="text-sm">{{ $s->status === 'done' ? $s->places_found : '—' }}@if($s->status === 'done' && $s->places_new)<span class="block text-xs" style="color:var(--green)">{{ $s->places_new }} nova(s)</span>@endif</td>
                                <td>@if($st)<span class="badge badge-{{ $st['color'] }}">{{ $st['label'] }}</span>@endif</td>
                                <td class="text-right">@if($s->status === 'done')<a href="{{ $tabUrl('empresas', ['varredura' => $s->id]) }}" class="btn btn-xs">Ver empresas</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-sm" style="color:var(--muted)">Nenhuma varredura ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <div class="grid gap-5" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr))">
                <section class="card p-5 min-w-0">
                    <h2 class="card-title">Segmentos</h2>
                    <p class="text-xs mt-1 mb-3" style="color:var(--muted2)">Cada termo vira uma busca no Google ("dentista em Videira SC"). O tipo do Google filtra a busca (opcional, em inglês, ex.: <code>dentist</code>).</p>
                    <div class="flex flex-col gap-2">
                        @foreach($segments as $s)
                            <details class="rounded px-3 py-2" style="background:var(--s2)">
                                <summary class="text-sm cursor-pointer"><b style="color:var(--text)">{{ $s->name }}</b>
                                    @unless($s->is_active)<span class="badge badge-muted ml-1">desativado</span>@endunless
                                    <span class="block text-xs" style="color:var(--muted2)">{{ implode(', ', $s->search_terms ?? []) }}{{ $s->google_type ? ' · tipo ' . $s->google_type : '' }}</span>
                                </summary>
                                @if($canEdit)
                                    <form method="POST" action="{{ route('commerce.segments.update', $s) }}" class="grid gap-2 mt-3">@csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $s->name }}" class="field text-sm px-2.5 py-1.5" required maxlength="120">
                                        <textarea name="search_terms" rows="2" class="field text-sm px-2.5 py-1.5" required>{{ implode("\n", $s->search_terms ?? []) }}</textarea>
                                        <input type="text" name="google_type" value="{{ $s->google_type }}" class="field text-sm px-2.5 py-1.5" placeholder="tipo do Google (opcional)">
                                        <label class="text-xs flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> Ativo</label>
                                        <div><button type="submit" class="btn btn-xs btn-primary">Salvar</button></div>
                                    </form>
                                @endif
                            </details>
                        @endforeach
                    </div>
                    @if($canEdit)
                        <form method="POST" action="{{ route('commerce.segments.store') }}" class="grid gap-2 mt-4 pt-4" style="border-top:1px solid var(--border)">@csrf
                            <p class="text-xs font-semibold" style="color:var(--text)">Novo segmento</p>
                            <input type="text" name="name" value="{{ old('name') }}" class="field text-sm px-2.5 py-1.5" placeholder="ex.: Saúde · Fisioterapia" required maxlength="120">
                            <textarea name="search_terms" rows="2" class="field text-sm px-2.5 py-1.5" placeholder="um termo por linha: fisioterapia, clínica de fisioterapia" required>{{ old('search_terms') }}</textarea>
                            <input type="text" name="google_type" value="{{ old('google_type') }}" class="field text-sm px-2.5 py-1.5" placeholder="tipo do Google (opcional), ex.: physiotherapist">
                            @foreach(['name', 'search_terms', 'google_type'] as $f)@error($f)<span class="text-xs" style="color:var(--red)">{{ $message }}</span>@enderror @endforeach
                            <div><button type="submit" class="btn btn-sm">Incluir segmento</button></div>
                        </form>
                    @endif
                </section>

                <section class="card p-5 min-w-0">
                    <h2 class="card-title">Cidades</h2>
                    <div class="flex flex-col gap-2 mt-3">
                        @foreach($cities as $c)
                            <div class="flex items-center justify-between rounded px-3 py-2 text-sm" style="background:var(--s2)">
                                <span>{{ $c->label() }}</span>
                                @if($canEdit)
                                    <form method="POST" action="{{ route('commerce.cities.destroy', $c) }}" @submit.prevent="if (await $store.confirmDialog.ask('Tirar {{ addslashes($c->name) }} da lista?')) $el.submit()">@csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs">Remover</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canEdit)
                        <form method="POST" action="{{ route('commerce.cities.store') }}" class="flex gap-2 mt-4 pt-4 flex-wrap" style="border-top:1px solid var(--border)">@csrf
                            <input type="text" name="name" class="field text-sm px-2.5 py-1.5 flex-1 min-w-0" placeholder="Cidade" required maxlength="120">
                            <input type="text" name="state" class="field text-sm px-2.5 py-1.5" style="width:64px" placeholder="UF" required maxlength="2">
                            <button type="submit" class="btn btn-sm">Incluir</button>
                        </form>
                    @endif
                    @unless($canEdit)<p class="text-xs mt-3" style="color:var(--muted)">Só administradores mudam segmentos e cidades.</p>@endunless
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
