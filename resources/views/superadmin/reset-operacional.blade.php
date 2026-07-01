<x-superadmin-layout>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Reset Operacional</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Remove todos os dados operacionais mantendo clientes, contatos e usuários.</p>
    </div>

    {{-- Contagens atuais --}}
    <div class="card card-body mb-6">
        <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color: var(--muted)">O que será apagado</p>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach([
                'Tarefas'        => $counts['tasks'],
                'Projetos'       => $counts['projects'],
                'Planejamentos'  => $counts['macro_plans'],
                'Sprints'        => $counts['sprints'],
                'Oportunidades'  => $counts['opportunities'],
                'Onboardings'    => $counts['client_onboardings'],
            ] as $label => $count)
                <div class="px-4 py-3" style="background: var(--s3); border: 1px solid var(--border2)">
                    <p class="text-xs mb-1" style="color: var(--muted)">{{ $label }}</p>
                    <p class="text-2xl font-black" style="color: {{ $count > 0 ? 'var(--red)' : 'var(--muted)' }}">
                        {{ number_format($count) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Aviso --}}
    <div class="px-5 py-4 mb-6" style="background: rgba(220,38,38,.06); border: 1px solid rgba(220,38,38,.25)">
        <p class="text-sm font-semibold mb-1" style="color: #dc2626">⚠ Operação irreversível</p>
        <p class="text-sm" style="color: var(--muted2)">
            Esta ação executa um <code>TRUNCATE CASCADE</code> em produção. Não há desfazer.
            Clientes, contatos e usuários <strong>não serão afetados</strong>.
        </p>
    </div>

    {{-- Botão de confirmação --}}
    <form method="POST" action="{{ route('superadmin.reset-operacional.execute') }}"
          onsubmit="return confirm('Tem certeza? Esta ação apagará todos os dados operacionais de produção.')">
        @csrf
        <button type="submit" class="btn btn-danger">
            Executar Reset Operacional
        </button>
        <a href="{{ route('superadmin.dashboard') }}" class="btn btn-ghost ml-3">Cancelar</a>
    </form>

</x-superadmin-layout>
