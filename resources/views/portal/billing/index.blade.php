<x-portal-layout>
    <x-slot name="title">Boletos</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Boletos</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Boletos e códigos PIX de reposição de verba de anúncios de {{ $client->company_name }}</p>
    </div>

    @forelse($documents as $doc)
        <div class="card p-5 mb-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <p class="font-bold" style="color: var(--text)">
                        {{ $doc->typeLabel() }} — {{ $doc->adAccount->platformLabel() }}
                    </p>
                    <p class="text-xs mt-0.5" style="color: var(--muted)">
                        Enviado em {{ $doc->created_at->format('d/m/Y') }}
                        @if($doc->due_date) · vencimento {{ $doc->due_date->format('d/m/Y') }} @endif
                        @if($doc->amount) · R$ {{ number_format((float) $doc->amount, 2, ',', '.') }} @endif
                    </p>
                </div>
            </div>

            @if($doc->hasFile())
                <a href="{{ $doc->url() }}" target="_blank"
                   class="flex items-center gap-3 p-3 rounded-lg transition-colors"
                   style="background: var(--s2)">
                    <span>{{ $doc->icon() }}</span>
                    <span class="text-sm flex-1" style="color: var(--text)">{{ $doc->filename }}</span>
                    <span class="text-xs" style="color: var(--muted)">{{ $doc->sizeForHumans() }}</span>
                </a>
            @elseif($doc->pix_code)
                <div class="flex items-center gap-3 p-3 rounded-lg" style="background: var(--s2)">
                    <code class="text-xs flex-1 break-all" style="color: var(--purple); font-family:'IBM Plex Mono',monospace">
                        {{ $doc->pix_code }}
                    </code>
                    <button onclick="navigator.clipboard.writeText(`{{ $doc->pix_code }}`)"
                            class="px-3 py-2 text-xs font-bold flex-shrink-0"
                            style="border:1px solid var(--border2); color:var(--muted2)">
                        Copiar
                    </button>
                </div>
            @endif

            @if($doc->notes)
                <p class="text-xs mt-3" style="color: var(--muted)">{{ $doc->notes }}</p>
            @endif
        </div>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm font-semibold" style="color: var(--muted)">Nenhum boleto/PIX recebido ainda.</p>
        </div>
    @endforelse

</x-portal-layout>
