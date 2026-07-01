<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
                <a href="{{ route('contacts.index') }}" class="hover:text-[var(--purple)]">Contatos</a> /
                Novo Contato
            </p>
            <h1 class="text-2xl font-black text-[var(--text)]">Cadastrar Contato</h1>
        </div>
    </x-slot>

    <div class="py-4 max-w-2xl mx-auto">

        <form method="POST" action="{{ route('contacts.store') }}" class="space-y-6">
            @csrf

            <div class="card p-6 space-y-5">

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Nome completo <span class="text-[var(--orange)]">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] @error('name') border-[var(--red)] @enderror">
                        @error('name')
                            <p class="text-xs text-[var(--red)] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Cargo</label>
                        <input type="text" name="job_title" value="{{ old('job_title') }}"
                               placeholder="Sócio, Gestor, Financeiro..."
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Empresa</label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">E-mail</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">WhatsApp</label>
                        <input type="text" name="whatsapp" value="{{ old('whatsapp') }}"
                               placeholder="(00) 00000-0000"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Telefone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                        Origem <span class="text-[var(--orange)]">*</span>
                    </label>
                    <select name="source" required
                            class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                        @foreach(\App\Models\Contact::$sources as $key => $label)
                            <option value="{{ $key }}" {{ old('source') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Observações</label>
                    <textarea name="notes" rows="3"
                              placeholder="Contexto do lead, primeiro contato, urgência..."
                              class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-none">{{ old('notes') }}</textarea>
                </div>

            </div>

            <div class="flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                        style="background: var(--purple);">
                    Cadastrar Contato
                </button>

                <button type="submit" name="create_opportunity" value="1"
                        class="px-6 py-2.5 text-xs font-bold font-mono uppercase tracking-widest border transition-colors hover:border-[var(--orange)] hover:text-[var(--orange)]"
                        style="border-color: var(--border2); color: var(--muted2);">
                    Cadastrar + Criar Oportunidade →
                </button>

                <a href="{{ route('contacts.index') }}"
                   class="text-xs font-mono text-[var(--muted)] hover:text-[var(--text)] transition-colors">
                    Cancelar
                </a>
            </div>

        </form>
    </div>
</x-app-layout>
