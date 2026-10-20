<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Operacional</p>
                <h1 class="text-xl font-black" style="color:var(--text)">Central de Pendências</h1>
            </div>
            <button type="button" class="btn btn-primary" x-data @click="$dispatch('open-new-pendency')">
                + Nova pendência
            </button>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <p class="text-sm mb-5" style="color:var(--muted2); max-width:760px">
        O que está <strong>esperando alguém</strong> pra o trabalho andar: material do cliente, um acesso, uma data, uma decisão.
        Cada pendência tem quem deve entregar e quem do time cobra. Pendências criadas numa tarefa, projeto ou planejamento aparecem aqui também.
    </p>

    {{-- Nova pendência avulsa (sem tarefa/projeto/planejamento — só o cliente) --}}
    <div x-data="{ adding: {{ $errors->pendency->any() ? 'true' : 'false' }} }"
         @open-new-pendency.window="adding = true" @pendency-form-close="adding = false">
        <div x-show="adding" x-cloak class="card card-body mb-5" style="max-width:560px">
            <p class="text-sm font-bold mb-3" style="color:var(--text)">Nova pendência</p>
            @if($errors->pendency->any())
                <div class="mb-3 px-3 py-2 text-xs" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red); border-radius:6px">
                    {{ $errors->pendency->first() }}
                </div>
            @endif
            @include('pendencies._form', ['users' => $users, 'clients' => $clients, 'newFormContacts' => $newFormContacts])
        </div>
    </div>

    {{-- Editar — UM form pra página toda, preenchido pelo cartão clicado (evento
         edit-pendency, ver _item.blade.php). Um form por cartão deixava a página com 1 MB. --}}
    @php $inp = 'width:100%; background:var(--s3); border:1px solid var(--border); border-radius:6px; color:var(--text); padding:7px 10px; font-size:13px; outline:none'; @endphp
    <div x-data="{ p: null, contacts: @js($newFormContacts) }"
         @edit-pendency.window="p = $event.detail" @keydown.escape.window="p = null">
        <template x-if="p">
            <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(0,0,0,.35)" @click.self="p = null">
                <form method="POST" :action="'{{ url('/pendencias') }}/' + p.id" class="card card-body w-full mt-12 flex flex-col gap-3" style="max-width:520px">
                    @csrf @method('PATCH')
                    <p class="text-sm font-bold" style="color:var(--text)">Editar pendência</p>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">O que está pendente</label>
                        <input type="text" name="title" required maxlength="500" x-model="p.title" style="{{ $inp }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Tipo</label>
                            <select name="kind" x-model="p.kind" style="{{ $inp }}">
                                @foreach(\App\Models\Pendency::$kinds as $key => $k)
                                    <option value="{{ $key }}">{{ $k['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Prazo</label>
                            <input type="date" name="due_date" x-model="p.due_date" style="{{ $inp }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Quem deve entregar</label>
                        <div class="flex gap-1.5 mb-2">
                            @foreach(\App\Models\Pendency::$owedBy as $key => $o)
                                <label class="flex-1 text-center text-xs font-semibold py-1.5 cursor-pointer" style="border-radius:6px; border:1px solid var(--border2)"
                                       :style="p.owed_by === '{{ $key }}' ? 'background:var(--purple); color:#fff; border-color:var(--purple)' : 'color:var(--muted2)'">
                                    <input type="radio" name="owed_by" value="{{ $key }}" x-model="p.owed_by" class="sr-only">
                                    {{ $o['label'] }}
                                </label>
                            @endforeach
                        </div>
                        <select x-show="p.owed_by === 'cliente'" name="owed_contact_id" x-model="p.owed_contact_id" :disabled="p.owed_by !== 'cliente'" style="{{ $inp }}">
                            <option value="">Contato (opcional)</option>
                            <template x-for="c in (contacts[p.client_id] || [])" :key="c.id">
                                <option :value="c.id" x-text="c.name" :selected="c.id === p.owed_contact_id"></option>
                            </template>
                        </select>
                        <select x-show="p.owed_by === 'agencia'" name="owed_user_id" x-model="p.owed_user_id" :disabled="p.owed_by !== 'agencia'" style="{{ $inp }}">
                            <option value="">Quem do time (opcional)</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <input x-show="p.owed_by === 'terceiro'" type="text" name="owed_party" maxlength="255" x-model="p.owed_party" :disabled="p.owed_by !== 'terceiro'"
                               placeholder="Ex.: Gráfica, fornecedor, Meta…" style="{{ $inp }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Quem cobra</label>
                        <select name="owner_id" x-model="p.owner_id" style="{{ $inp }}">
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Detalhes (opcional)</label>
                        <textarea name="description" rows="2" maxlength="5000" x-model="p.description" style="{{ $inp }}; resize:vertical"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="p = null">Cancelar</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('pendencies.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
          data-live-filter data-results-url="{{ route('pendencies.results') }}" data-target="#pendencies-results">
        <select name="ver" class="filter-select">
            <option value="abertas" @selected($view === 'abertas')>Em aberto</option>
            <option value="resolvidas" @selected($view === 'resolvidas')>Resolvidas</option>
        </select>

        <select name="responsavel" class="filter-select">
            <option value="todos" @selected(request('responsavel', 'todos') === 'todos')>Quem cobra: todos</option>
            <option value="meus" @selected(request('responsavel') === 'meus')>Só as que eu cobro</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(request('responsavel') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>

        <select name="client_id" class="filter-select">
            <option value="">Todos os clientes</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(request('client_id') === $c->id)>{{ $c->displayName() }}</option>
            @endforeach
        </select>

        <select name="kind" class="filter-select">
            <option value="">Todos os tipos</option>
            @foreach(\App\Models\Pendency::$kinds as $key => $k)
                <option value="{{ $key }}" @selected(request('kind') === $key)>{{ $k['label'] }}</option>
            @endforeach
        </select>

        <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--red)">
            <input type="checkbox" name="vencidas" value="1" @checked(request()->boolean('vencidas')) class="w-4 h-4" style="accent-color:var(--red)">
            Só vencidas
        </label>

        <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--muted2)">
            <input type="checkbox" name="mostrar_inativos" value="1" @checked(request()->boolean('mostrar_inativos')) class="w-4 h-4" style="accent-color:var(--purple)">
            Mostrar clientes inativos
        </label>
    </form>

    <div id="pendencies-results">
        @include('pendencies._results')
    </div>
</x-app-layout>
