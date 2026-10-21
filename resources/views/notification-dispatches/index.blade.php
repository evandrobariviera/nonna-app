<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Histórico de Envios</h1>
    </x-slot>

    <div style="max-width:1000px">

        <div class="mb-5">
            <a href="{{ route('settings.index', ['tab' => 'mensagens']) }}"
               class="text-xs transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                ← Configurações
            </a>
            <p class="text-xs mt-2 max-w-2xl" style="color:var(--muted)">
                Cada Mensagem Padrão (WhatsApp/e-mail) que o App tentou mandar. <strong>Enviado ao n8n</strong> quer dizer
                que o n8n recebeu o pedido — se o WhatsApp falhar depois disso, aparece só nas Execuções do n8n.
                <strong>Não enviado</strong> mostra por que o App nem chegou a mandar (ex: contato sem WhatsApp).
            </p>
        </div>

        {{-- Resumo dos últimos 7 dias (sem disparos de teste) --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            @foreach(\App\Models\NotificationDispatch::$statuses as $key => $meta)
                <div class="card" style="padding:.75rem 1.25rem; text-align:center">
                    <div class="text-2xl font-bold" style="color:var(--{{ $meta['color'] }})">{{ $lastWeek[$key] ?? 0 }}</div>
                    <div class="text-xs mt-1" style="color:var(--muted)">{{ $meta['label'] }} · 7 dias</div>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('settings.notification-dispatches.index') }}" class="flex flex-wrap items-center gap-3 mb-5"
              data-live-filter data-results-url="{{ route('settings.notification-dispatches.results') }}" data-target="#dispatches-results">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Buscar cliente, contato ou número..."
                   class="flex-1 min-w-[200px] bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2 focus:outline-none focus:border-[var(--purple)] placeholder:text-[var(--muted)]">

            <select name="status"
                    class="bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                <option value="">Todas as situações</option>
                @foreach(\App\Models\NotificationDispatch::$statuses as $key => $meta)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>

            <select name="channel"
                    class="bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                <option value="">Todos os canais</option>
                @foreach(\App\Models\NotificationTemplate::$channels as $key => $label)
                    <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="type"
                    class="bg-[var(--s2)] border border-[var(--border2)] text-sm text-[var(--text)] px-3 py-2 focus:outline-none focus:border-[var(--purple)]">
                <option value="">Todos os tipos</option>
                @foreach(\App\Models\NotificationTemplate::$types as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>

        <div id="dispatches-results">
            @include('notification-dispatches._results')
        </div>
    </div>
</x-app-layout>
