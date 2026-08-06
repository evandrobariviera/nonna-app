<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">Notificações</h1>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if($notifications->isEmpty())
        <div class="card px-6 py-10 text-center" style="color:var(--muted)">
            <p class="text-sm">Nenhuma notificação ainda.</p>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach($notifications as $n)
                <div class="card px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="badge badge-{{ $n->statusColor() }}">{{ $n->statusLabel() }}</span>
                                <a href="{{ $n->link ?? '#' }}" class="font-bold text-sm" style="color:var(--text)">{{ $n->title }}</a>
                            </div>
                            <p class="text-xs font-mono mb-1" style="color:var(--muted)">
                                {{ $n->generated_at->format('d/m/Y H:i') }}
                            </p>
                            @if($n->body)
                                <p class="text-sm mt-2" style="color:var(--muted2)">{{ $n->body }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($n->status === 'novo')
                                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="lido">
                                    <button type="submit" class="btn btn-ghost btn-xs">
                                        Marcar como lido
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="novo">
                                    <button type="submit" class="btn btn-ghost btn-xs">
                                        Marcar como não lido
                                    </button>
                                </form>
                            @endif
                            @if($n->status !== 'resolvido')
                                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="resolvido">
                                    <button type="submit" class="btn btn-success btn-xs">
                                        Resolver
                                    </button>
                                </form>
                            @endif
                            @if($n->status !== 'descartado')
                                <form method="POST" action="{{ route('notifications.update-status', $n) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="descartado">
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        Descartar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-app-layout>
