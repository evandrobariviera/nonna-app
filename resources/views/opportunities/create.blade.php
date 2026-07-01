<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
                <a href="{{ route('opportunities.index') }}" class="hover:text-[var(--purple)]">Oportunidades</a> /
                Nova Oportunidade
            </p>
            <h1 class="text-2xl font-black text-[var(--text)]">Criar Oportunidade</h1>
        </div>
    </x-slot>

    <div class="py-4 max-w-2xl mx-auto">

        <form method="POST" action="{{ route('opportunities.store') }}" class="space-y-6">
            @csrf

            <div class="card p-6 space-y-5">

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                        Contato <span class="text-[var(--orange)]">*</span>
                    </label>
                    @if($contact)
                        <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                        <div class="flex items-center gap-3 px-4 py-2.5 border border-[var(--border2)] bg-[var(--s3)]">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                                 style="background: var(--purple);">
                                {{ strtoupper(substr($contact->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-[var(--text)]">{{ $contact->name }}</div>
                                @if($contact->company_name)
                                    <div class="text-xs text-[var(--muted)]">{{ $contact->company_name }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <select name="contact_id" required
                                class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] @error('contact_id') border-[var(--red)] @enderror">
                            <option value="">Selecione um contato...</option>
                            @foreach($contacts as $c)
                                <option value="{{ $c->id }}" {{ old('contact_id') === $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}{{ $c->company_name ? ' — ' . $c->company_name : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <p class="text-xs text-[var(--red)] mt-1">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                        Título da oportunidade <span class="text-[var(--orange)]">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $contact ? $contact->company_name . ' — ' : '') }}" required
                           placeholder="Ex: Studio Aura — Tráfego + Social"
                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] @error('title') border-[var(--red)] @enderror">
                    @error('title')
                        <p class="text-xs text-[var(--red)] mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">
                        Serviços de interesse
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(\App\Models\Client::$services as $key => $label)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="services_interest[]" value="{{ $key }}"
                                       {{ in_array($key, old('services_interest', [])) ? 'checked' : '' }}
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
                        <input type="number" name="proposed_fee" value="{{ old('proposed_fee') }}" min="0" step="100"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Verba de mídia estimada (R$)
                        </label>
                        <input type="number" name="proposed_ad_budget" value="{{ old('proposed_ad_budget') }}" min="0" step="100"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Link do orçamento / proposta
                        </label>
                        <input type="url" name="proposal_url" value="{{ old('proposal_url') }}"
                               placeholder="https://docs.google.com/..."
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Prazo do contrato (meses)
                        </label>
                        <input type="number" name="contract_months" value="{{ old('contract_months') }}"
                               min="1" max="60" placeholder="Ex: 12"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        <p class="text-xs text-[var(--muted)] mt-1">Número de parcelas mensais do contrato</p>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                    <textarea name="notes" rows="3"
                              placeholder="Contexto da negociação, urgências, histórico..."
                              class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-none">{{ old('notes') }}</textarea>
                </div>

            </div>

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                        style="background: var(--purple);">
                    Criar Oportunidade
                </button>
                <a href="{{ route('opportunities.index') }}"
                   class="text-xs font-mono text-[var(--muted)] hover:text-[var(--text)] transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</x-app-layout>
