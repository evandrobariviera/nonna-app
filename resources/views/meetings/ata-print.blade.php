<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ATA · {{ $meeting->client?->displayName() ?? $meeting->title }} · {{ $meeting->scheduled_at?->format('d/m/Y') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .print-toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between;
            padding: 12px 24px; background: var(--s1); border-bottom: 1px solid var(--border2); }
        @media print {
            .print-toolbar { display: none; }
            body { background: #fff !important; }
        }
        .ata-hero { background: linear-gradient(135deg, var(--purple-soft, rgba(100,59,142,.08)), var(--orange-soft, rgba(238,121,25,.06)));
            border: 1px solid var(--border2); border-radius: 16px; padding: 32px; margin-bottom: 24px; }
        .ata-card { background: var(--s1); border: 1px solid var(--border2); border-radius: 12px; padding: 22px 24px; margin-bottom: 14px; }
        .ata-card-title { font-family: 'IBM Plex Mono', monospace; font-size: 11px; letter-spacing: .1em; text-transform: uppercase;
            color: var(--purple); margin-bottom: 14px; }
        .ata-card.alert { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.3); }
        .ata-card.alert .ata-card-title { color: #B45309; }
        .ata-h3 { font-size: 13px; font-weight: 600; color: var(--text); margin: 14px 0 8px; }
        .ata-h3:first-child { margin-top: 0; }
        .ata-p { font-size: 14px; line-height: 1.65; color: var(--text-2, var(--muted)); margin-bottom: 10px; }
        .ata-ul { margin: 6px 0 12px 20px; }
        .ata-ul li, .ata-ol li { font-size: 14px; line-height: 1.6; color: var(--text-2, var(--muted)); margin-bottom: 6px; }
        .ata-ol { margin: 6px 0 12px 20px; }
        .ata-checklist { display: flex; flex-direction: column; gap: 8px; margin: 6px 0 12px; }
        .ata-check-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; background: var(--s2, var(--s3));
            border: 1px solid var(--border2); border-radius: 8px; }
        .ata-check-box { width: 16px; height: 16px; border: 2px solid var(--border-strong, var(--border)); border-radius: 4px;
            flex: none; margin-top: 2px; }
        .ata-check-item.done .ata-check-box { background: #10B981; border-color: #10B981; }
        .ata-check-label { font-size: 13.5px; color: var(--text); line-height: 1.5; flex: 1 }
        .ata-check-resp { font-family: 'IBM Plex Mono', monospace; font-size: 10.5px; color: var(--muted); margin-left: 8px; white-space: nowrap; }
    </style>
</head>
<body style="background:var(--bg)">

    <div class="print-toolbar">
        <span class="text-sm font-semibold" style="color:var(--text)">
            ATA · {{ $meeting->client?->displayName() ?? $meeting->title }}
        </span>
        <button onclick="window.print()"
                class="px-4 py-1.5 text-xs font-bold font-mono uppercase tracking-widest text-white flex items-center gap-1.5"
                style="background:var(--purple)">
            <x-icon name="printer" size="13" />
            Imprimir / Salvar PDF
        </button>
    </div>

    <div class="p-8" style="max-width:860px; margin:0 auto">

        <div class="ata-hero">
            <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--orange); letter-spacing:.12em">
                ATA de Reunião · Nonna Agência Digital
            </p>
            <h1 class="text-2xl font-semibold mb-1" style="color:var(--text)">{{ $meeting->client?->displayName() ?? $meeting->title }}</h1>
            <p class="text-sm" style="color:var(--muted)">{{ $meeting->typeLabel() }} · {{ $meeting->scheduled_at?->format('d/m/Y') }}</p>

            <div class="grid grid-cols-2 gap-3 mt-5" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
                <div class="px-3 py-2.5" style="background:var(--s1); border:1px solid var(--border2); border-radius:10px">
                    <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Organizador</p>
                    <p class="text-sm font-medium" style="color:var(--text)">{{ $meeting->organizer?->name ?? '—' }}</p>
                </div>
                @if($meeting->ata_recorded_at)
                <div class="px-3 py-2.5" style="background:var(--s1); border:1px solid var(--border2); border-radius:10px">
                    <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">ATA atualizada em</p>
                    <p class="text-sm font-medium" style="color:var(--text)">{{ $meeting->ata_recorded_at->format('d/m/Y H:i') }}</p>
                </div>
                @endif
                @if($meeting->participants->isNotEmpty())
                <div class="px-3 py-2.5" style="background:var(--s1); border:1px solid var(--border2); border-radius:10px">
                    <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Participantes</p>
                    <p class="text-sm font-medium" style="color:var(--text)">{{ $meeting->participants->pluck('name')->join(', ') }}</p>
                </div>
                @endif
            </div>
        </div>

        @forelse($cards as $card)
            @php
                $isAlert = $card['title'] && str_contains(mb_strtolower($card['title']), 'alerta');
            @endphp
            <div class="ata-card @if($isAlert) alert @endif">
                @if($card['title'])
                    <p class="ata-card-title">{{ $card['title'] }}</p>
                @endif

                @foreach($card['blocks'] as $block)
                    @if($block['type'] === 'h3')
                        <p class="ata-h3">{{ $block['text'] }}</p>
                    @elseif($block['type'] === 'p')
                        <p class="ata-p">{!! $block['html'] !!}</p>
                    @elseif($block['type'] === 'ul')
                        <ul class="ata-ul">
                            @foreach($block['items'] as $item)
                                <li>{!! $item !!}</li>
                            @endforeach
                        </ul>
                    @elseif($block['type'] === 'ol')
                        <ol class="ata-ol">
                            @foreach($block['items'] as $item)
                                <li>{!! $item !!}</li>
                            @endforeach
                        </ol>
                    @elseif($block['type'] === 'checklist')
                        <div class="ata-checklist">
                            @foreach($block['items'] as $item)
                                <div class="ata-check-item @if($item['done']) done @endif">
                                    <span class="ata-check-box"></span>
                                    <span class="ata-check-label">
                                        {!! $item['html'] !!}
                                        @if($item['resp'])
                                            <span class="ata-check-resp">{{ $item['resp'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @empty
            <p class="text-sm" style="color:var(--muted)">Nenhuma ATA registrada ainda.</p>
        @endforelse

    </div>

</body>
</html>
