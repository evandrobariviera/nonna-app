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

    {{-- Resumo do processo — até o time pegar o jeito (pedido do Evandro, 2026-10-20).
         Fechar esconde só pra quem fechou (localStorage); o artigo completo fica na Ajuda. --}}
    <div x-data="{ hide: (() => { try { return localStorage.getItem('pendencias_resumo_oculto') === '1' } catch (e) { return false } })() }" class="mb-5">
        <div x-show="!hide" class="card px-5 py-4" style="border-left:4px solid var(--orange)">
            <div class="flex items-start justify-between gap-3 mb-3">
                <p class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text)">
                    <x-icon name="hourglass" size="14" /> Como funciona
                </p>
                <button type="button" class="text-xs flex-shrink-0" style="color:var(--muted2)"
                        @click="hide = true; try { localStorage.setItem('pendencias_resumo_oculto', '1') } catch (e) {}">Ocultar ✕</button>
            </div>
            <p class="text-sm mb-3" style="color:var(--muted2)">
                Pendência é <strong style="color:var(--text)">algo que alguém precisa entregar pra o trabalho andar</strong>: fotos do cliente, um acesso, uma data, uma decisão.
                Se é trabalho nosso, é tarefa. Se estamos <em>esperando chegar</em>, é pendência.
            </p>
            <div class="grid gap-3 md:grid-cols-4 text-xs" style="color:var(--muted2)">
                <div>
                    <p class="font-bold mb-0.5" style="color:var(--text)">1. Registre</p>
                    Dentro da tarefa, projeto ou planejamento (bloco <strong>Pendências → + Nova</strong>), ou aqui em <strong>+ Nova pendência</strong>. Coloque prazo.
                </div>
                <div>
                    <p class="font-bold mb-0.5" style="color:var(--text)">2. Três pessoas</p>
                    <strong>Quem precisa</strong> (está esperando), <strong>quem deve</strong> (cliente, terceiro ou alguém do time) e <strong>quem cobra</strong>.
                    Ex.: Marlon precisa, cliente deve, Alisson cobra.
                </div>
                <div>
                    <p class="font-bold mb-0.5" style="color:var(--text)">3. Cobre e registre</p>
                    Só quem cobra vê o botão: <strong>Cobrar no WhatsApp</strong>, <strong>Registrar cobrança</strong> ou <strong>Lembrar</strong> alguém do time. Quem precisa pode <strong>Lembrar</strong> quem cobra.
                </div>
                <div>
                    <p class="font-bold mb-0.5" style="color:var(--text)">4. Chegou? Resolva</p>
                    <strong>✓ Resolver</strong> e conte o que chegou. Quem precisava é avisado na hora.
                </div>
            </div>
            <p class="text-xs mt-3" style="color:var(--muted)">
                Acompanhe pelo número no menu, pelo sino e pelo card <strong>Suas pendências</strong> no Dashboard.
                <a href="{{ url('/ajuda/central-de-pendencias-como-funciona') }}" class="font-semibold" style="color:var(--purple)">Ler o passo a passo completo →</a>
            </p>
        </div>
        <button x-show="hide" x-cloak type="button" class="text-xs font-semibold" style="color:var(--purple)"
                @click="hide = false; try { localStorage.removeItem('pendencias_resumo_oculto') } catch (e) {}">Como funciona a Central de Pendências?</button>
    </div>

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
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Quem precisa</label>
                            <select name="requested_by" x-model="p.requested_by" style="{{ $inp }}">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--muted)">Quem cobra</label>
                            <select name="owner_id" x-model="p.owner_id" style="{{ $inp }}">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
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

        <select name="status" class="filter-select" title="Vale pra Em aberto">
            <option value="">Abertas e cobradas</option>
            <option value="aberta" @selected(request('status') === 'aberta')>Só ainda não cobradas</option>
            <option value="cobrada" @selected(request('status') === 'cobrada')>Só já cobradas</option>
        </select>

        <select name="responsavel" class="filter-select">
            <option value="todos" @selected(request('responsavel', 'todos') === 'todos')>Pessoas: todas</option>
            <option value="meus" @selected(request('responsavel') === 'meus')>Só as minhas (cobro ou entrego)</option>
            <option value="pedi" @selected(request('responsavel') === 'pedi')>Só as que eu pedi</option>
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
