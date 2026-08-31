<x-app-layout>
    <x-slot name="header">Editar Oportunidade</x-slot>

    <div class="mb-6">
        <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
            <a href="{{ route('opportunities.show', $opportunity) }}" class="hover:text-[var(--purple)]">{{ $opportunity->title }}</a> /
            Editar
        </p>
        <h1 class="text-2xl font-black text-[var(--text)]">Editar Oportunidade</h1>
    </div>

    <div class="py-4 max-w-2xl mx-auto">

        @if($errors->any())
            <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('opportunities.update', $opportunity) }}" class="space-y-6">
            @csrf @method('PATCH')

            <div class="card p-6 space-y-5">

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Contato</label>
                    <div class="flex items-center gap-3 px-4 py-2.5 border border-[var(--border2)] bg-[var(--s3)]">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                             style="background: var(--purple);">
                            {{ strtoupper(substr($opportunity->contact->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-[var(--text)]">{{ $opportunity->contact->name }}</div>
                            @if($opportunity->contact->company_name)
                                <div class="text-xs text-[var(--muted)]">{{ $opportunity->contact->company_name }}</div>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-[var(--muted)] mt-1">O contato de uma oportunidade não pode ser trocado depois de criada.</p>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                        Título da oportunidade <span class="text-[var(--orange)]">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $opportunity->title) }}" required
                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] @error('title') border-[var(--red)] @enderror">
                    @error('title')
                        <p class="text-xs text-[var(--red)] mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Tipo de oportunidade</label>
                    <select name="type"
                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        @foreach(\App\Models\Opportunity::$types as $key => $label)
                            <option value="{{ $key }}" {{ old('type', $opportunity->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Responsável</label>
                    <select name="assigned_to"
                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        <option value="">— sem responsável —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('assigned_to', $opportunity->assigned_to) == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">
                        Serviços de interesse
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(\App\Models\Client::$services as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="services_interest[]" value="{{ $key }}"
                                       {{ in_array($key, old('services_interest', $opportunity->services_interest ?? [])) ? 'checked' : '' }}
                                       class="accent-[var(--purple)]">
                                <span class="text-sm text-[var(--muted2)]">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Fee mensal proposto (R$)
                        </label>
                        <input type="number" name="proposed_fee" value="{{ old('proposed_fee', $opportunity->proposed_fee) }}" min="0" step="0.01"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Verba de mídia estimada (R$)
                        </label>
                        <input type="number" name="proposed_ad_budget" value="{{ old('proposed_ad_budget', $opportunity->proposed_ad_budget) }}" min="0" step="0.01"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Link do orçamento / proposta
                        </label>
                        <input type="url" name="proposal_url" value="{{ old('proposal_url', $opportunity->proposal_url) }}"
                               placeholder="https://docs.google.com/..."
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Prazo do contrato (meses)
                        </label>
                        <input type="number" name="contract_months" value="{{ old('contract_months', $opportunity->contract_months) }}"
                               min="1" max="60" placeholder="Ex: 12"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        <p class="text-xs text-[var(--muted)] mt-1">Número de parcelas mensais do contrato</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                    <textarea name="notes" rows="3"
                              placeholder="Contexto da negociação, urgências, histórico..."
                              class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-none">{{ old('notes', $opportunity->notes) }}</textarea>
                </div>

            </div>

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                        style="background: var(--purple);">
                    Salvar Alterações
                </button>
                <a href="{{ route('opportunities.show', $opportunity) }}" class="btn btn-ghost btn-sm">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</x-app-layout>
