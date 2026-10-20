{{-- Formulário de pendência — criar (sem $pendency) ou editar. Usado no bloco
     <x-pendencies-panel> e na Central de Pendências (avulsa: escolhe o cliente).

     Variáveis: $pendency (opcional), $users, $contacts (contatos do cliente da origem),
     $pendableAlias + $pendableId (origem), ou $clients + $newFormContacts (avulsa). --}}
@php
    $pendency      = $pendency ?? null;
    $isEdit        = (bool) $pendency;
    $pendableAlias = $pendableAlias ?? null;
    $avulsa        = !$isEdit && !$pendableAlias;
    $inp = 'width:100%; background:var(--s3); border:1px solid var(--border); border-radius:6px; color:var(--text); padding:7px 10px; font-size:13px; outline:none';
    $lbl = 'display:block; font-size:11px; font-weight:600; color:var(--muted); margin-bottom:4px';
@endphp

<form method="POST"
      action="{{ $isEdit ? route('pendencies.update', $pendency) : route('pendencies.store') }}"
      x-data="{
          owedBy: '{{ $pendency->owed_by ?? 'cliente' }}',
          clientId: '{{ $pendency->client_id ?? '' }}',
          contactsByClient: @js($avulsa ? ($newFormContacts ?? (object) []) : (object) []),
      }"
      class="flex flex-col gap-3">
    @csrf
    @if(!empty($returnTo))<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
    @if($isEdit) @method('PATCH') @endif
    @if($pendableAlias)
        <input type="hidden" name="pendable_type" value="{{ $pendableAlias }}">
        <input type="hidden" name="pendable_id" value="{{ $pendableId }}">
    @endif

    @if($avulsa)
        <div>
            <label style="{{ $lbl }}">Cliente</label>
            <select name="client_id" x-model="clientId" required style="{{ $inp }}">
                <option value="">Escolha o cliente…</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label style="{{ $lbl }}">O que está pendente</label>
        <input type="text" name="title" required maxlength="500" value="{{ $pendency->title ?? '' }}"
               placeholder="Ex.: Fotos dos produtos novos" style="{{ $inp }}">
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label style="{{ $lbl }}">Tipo</label>
            <select name="kind" style="{{ $inp }}">
                @foreach(\App\Models\Pendency::$kinds as $key => $k)
                    <option value="{{ $key }}" @selected(($pendency->kind ?? 'insumo') === $key)>{{ $k['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="{{ $lbl }}">Prazo</label>
            <input type="date" name="due_date" value="{{ $pendency?->due_date?->format('Y-m-d') }}" style="{{ $inp }}">
        </div>
    </div>

    <div>
        <label style="{{ $lbl }}">Quem deve entregar</label>
        <div class="flex gap-1.5 mb-2">
            @foreach(\App\Models\Pendency::$owedBy as $key => $o)
                <label class="flex-1 text-center text-xs font-semibold py-1.5 cursor-pointer"
                       style="border-radius:6px; border:1px solid var(--border2)"
                       :style="owedBy === '{{ $key }}' ? 'background:var(--purple); color:#fff; border-color:var(--purple)' : 'color:var(--muted2)'">
                    <input type="radio" name="owed_by" value="{{ $key }}" x-model="owedBy" class="sr-only">
                    {{ $o['label'] }}
                </label>
            @endforeach
        </div>

        <div x-show="owedBy === 'cliente'">
            @if($avulsa)
                <select name="owed_contact_id" style="{{ $inp }}" :disabled="owedBy !== 'cliente'">
                    <option value="">Contato (opcional)</option>
                    <template x-for="c in (contactsByClient[clientId] || [])" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            @else
                <select name="owed_contact_id" style="{{ $inp }}" :disabled="owedBy !== 'cliente'">
                    <option value="">Contato (opcional)</option>
                    @foreach($contacts ?? [] as $ct)
                        <option value="{{ $ct->id }}" @selected(($pendency->owed_contact_id ?? null) === $ct->id)>{{ $ct->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div x-show="owedBy === 'agencia'" x-cloak>
            <select name="owed_user_id" style="{{ $inp }}" :disabled="owedBy !== 'agencia'">
                <option value="">Quem do time (opcional)</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(($pendency->owed_user_id ?? null) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="owedBy === 'terceiro'" x-cloak>
            <input type="text" name="owed_party" maxlength="255" value="{{ $pendency->owed_party ?? '' }}"
                   placeholder="Ex.: Gráfica, fornecedor, Meta…" style="{{ $inp }}" :disabled="owedBy !== 'terceiro'">
        </div>
    </div>

    <div>
        <label style="{{ $lbl }}">Quem cobra</label>
        <select name="owner_id" style="{{ $inp }}">
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(($pendency->owner_id ?? auth()->id()) == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label style="{{ $lbl }}">Detalhes (opcional)</label>
        <textarea name="description" rows="2" maxlength="5000" style="{{ $inp }}; resize:vertical">{{ $pendency->description ?? '' }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Salvar' : 'Registrar pendência' }}</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="$dispatch('pendency-form-close')">Cancelar</button>
    </div>
</form>
